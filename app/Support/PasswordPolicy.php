<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Password rules for accounts created outside the UI (first-boot admin,
 * `vigilo:user:create`). The UI uses Laravel's Password rule with the
 * same minimum length.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /** Passwords that must never pass, including our own placeholders. */
    private const DENYLIST = [
        'password', 'passwort', 'changeme', 'change-me', 'changethis', 'admin', 'administrator',
        'vigilo', 'secret', 'letmein', 'welcome', 'qwerty', 'qwertz', '123456', '12345678',
        '123456789', '1234567890', '123456789012', 'password123', 'admin123', 'example',
    ];

    /** @return list<string> human-readable problems; empty = acceptable */
    public static function problems(string $password, string $email = ''): array
    {
        $problems = [];
        $lower = strtolower($password);

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $problems[] = 'must be at least '.self::MIN_LENGTH.' characters long';
        }

        // "Password2024!" is still "password": compare the letters only, too.
        $letters = preg_replace('/[^a-z]/', '', $lower) ?? '';
        $isKnown = preg_match('/change[\W_]*me/', $lower) === 1;
        foreach (self::DENYLIST as $bad) {
            $badLetters = preg_replace('/[^a-z]/', '', $bad) ?? '';
            if ($lower === $bad || (strlen($badLetters) >= 4 && $letters === $badLetters)) {
                $isKnown = true;
                break;
            }
        }
        if ($isKnown) {
            $problems[] = 'is a default or well-known password';
        }

        $local = strtolower(Str::before($email, '@'));
        if ($local !== '' && strlen($local) >= 4 && str_contains($lower, $local)) {
            $problems[] = 'must not contain the email address';
        }

        if (count(array_unique(str_split($password))) < 6) {
            $problems[] = 'uses too few different characters';
        }

        $classes = (int) preg_match('/[a-z]/', $password) + (int) preg_match('/[A-Z]/', $password)
            + (int) preg_match('/\d/', $password) + (int) preg_match('/[^A-Za-z0-9]/', $password);
        if ($classes < 3 && mb_strlen($password) < 16) {
            $problems[] = 'needs three of: lowercase, uppercase, digits, symbols (or 16+ characters)';
        }

        return array_values(array_unique($problems));
    }

    public static function generate(): string
    {
        // Letters and digits only: survives copy/paste from logs and shells.
        return Str::password(24, symbols: false);
    }
}
