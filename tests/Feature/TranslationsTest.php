<?php

use App\Domain\Checks\CheckRegistry;

/** Every literal __('…') / trans_choice('…') string in app/ and resources/views. */
function translatableStrings(): array
{
    $strings = [];
    foreach ([app_path(), resource_path('views')] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            preg_match_all("/(?:__|trans_choice|Format::t)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $m);
            foreach ($m[1] as $s) {
                $strings[str_replace("\\'", "'", $s)] = $file->getFilename();
            }
        }
    }

    // Group keys (checks.*, findings.*, …) are covered by the parity test below.
    return array_filter($strings, fn ($key) => ! preg_match('/^(checks|findings|evidence|mock|validation|passwords|auth)\./', $key), ARRAY_FILTER_USE_KEY);
}

it('has a German translation for every UI string', function () {
    $de = json_decode((string) file_get_contents(lang_path('de.json')), true);

    $missing = array_keys(array_diff_key(translatableStrings(), $de));

    expect($missing)->toBe([]);
});

it('has the same keys in English and German group files', function (string $group) {
    $en = Arr::dot(require lang_path("en/{$group}.php"));
    $de = Arr::dot(require lang_path("de/{$group}.php"));

    expect(array_keys(array_diff_key($en, $de)))->toBe([])
        ->and(array_keys(array_diff_key($de, $en)))->toBe([]);
})->with(['checks', 'findings', 'evidence', 'mock']);

it('describes every check and every threshold in both languages', function (string $locale) {
    foreach (app(CheckRegistry::class)->all() as $key => $check) {
        foreach (['name', 'description', 'advice'] as $part) {
            expect(trans("checks.{$key}.{$part}", [], $locale))->not->toBe("checks.{$key}.{$part}");
        }
        foreach (array_keys($check->fields()) as $field) {
            expect(trans("checks.{$key}.fields.{$field}", [], $locale))->not->toBe("checks.{$key}.fields.{$field}");
        }
        expect(config("vigilo.checks.{$key}"))->toBeArray();
    }
})->with(['en', 'de']);

it('has a title for every finding a check can raise', function () {
    $titles = [];
    foreach (glob(app_path('Domain/Checks/Checks/*Check.php')) as $file) {
        $source = (string) file_get_contents($file);
        if (! preg_match("/function key\(\): string\s*\{\s*return '([a-z_]+)';/", $source, $key)) {
            continue;
        }
        // Literals in the 4th argument of every ->draft(…) call …
        $offset = 0;
        while (($pos = strpos($source, '->draft(', $offset)) !== false) {
            $depth = 0;
            $args = [''];
            for ($i = $pos + 8; $i < strlen($source); $i++) {
                $ch = $source[$i];
                if ($ch === '(' || $ch === '[') {
                    $depth++;
                } elseif ($ch === ')' || $ch === ']') {
                    if ($depth-- === 0) {
                        break;
                    }
                } elseif ($ch === ',' && $depth === 0) {
                    $args[] = '';

                    continue;
                }
                $args[count($args) - 1] .= $ch;
            }
            // (array subscripts like $worst['conversions'] are not titles)
            preg_match_all("/'([a-z_]+)'/", (string) preg_replace('/\\[[^\\]]*\\]/', '', $args[3] ?? ''), $m);
            array_push($titles, ...array_map(fn ($t) => "findings.{$key[1]}.{$t}", $m[1]));
            $offset = $pos + 1;
        }
        // … and in [Severity::X, 'title'] pairs.
        preg_match_all("/\[Severity::\w+, '([a-z_]+)'\]/", $source, $m);
        array_push($titles, ...array_map(fn ($t) => "findings.{$key[1]}.{$t}", $m[1]));
    }

    $titles = array_unique($titles);
    expect(count($titles))->toBeGreaterThan(15);
    foreach ($titles as $title) {
        expect(trans($title, [], 'en'))->not->toBe($title)
            ->and(trans($title, [], 'de'))->not->toBe($title);
    }
});
