<?php

use App\Support\PasswordPolicy;

it('accepts strong passwords', function (string $password) {
    expect(PasswordPolicy::problems($password, 'admin@agency.de'))->toBe([]);
})->with(['Tr0mbone-Lantern-77', 'correct horse battery staple', PasswordPolicy::generate()]);

it('rejects weak, default and personal passwords', function (string $password) {
    expect(PasswordPolicy::problems($password, 'maria.schulz@agency.de'))->not->toBe([]);
})->with([
    'short' => 'Ab1!',
    'default' => 'changeme',
    'placeholder' => 'change-me-please-123',
    'well known with digits' => 'Password2026!',
    'contains email' => 'maria.schulz-Secure-1',
    'one character' => 'aaaaaaaaaaaaaaaaaaaa',
    'digits only' => '123456789012',
]);
