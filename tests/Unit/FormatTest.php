<?php

use App\Support\Format;

/** ICU separates currency and amount with (narrow) no-break spaces. */
function spaces(string $value): string
{
    return str_replace(["\u{00A0}", "\u{202F}"], ' ', $value);
}

it('formats money, numbers and percentages for the reader', function () {
    expect(spaces(Format::money(1234.5, 'EUR', 'en')))->toBe('€1,234.50')
        ->and(spaces(Format::money(1234.5, 'EUR', 'de')))->toBe('1.234,50 €')
        ->and(spaces(Format::percent(0.426, 0, 'en')))->toBe('43%')
        ->and(Format::number(4.26, 1, 'de'))->toBe('4,3');
});

it('renders typed params', function () {
    expect(spaces(Format::param(Format::m(10, 'CHF'), 'en')))->toBe('CHF 10.00')
        ->and(Format::param(Format::x(2.04), 'en'))->toBe('2.0×')
        ->and(Format::param(['type' => 'platform', 'value' => 'meta']))->toBe('Meta Ads')
        ->and(Format::param('plain'))->toBe('plain')
        ->and(Format::param(Format::t('no response'), 'de'))->toBe('keine Antwort');
});
