<?php

namespace App\Domain\AdPlatforms\Enums;

/** Health of an ad connection or lead source as of its last fetch. */
enum ConnectionStatus: string
{
    case Unknown = 'unknown';
    case Ok = 'ok';
    case Failing = 'failing';
    case AuthFailed = 'auth_failed';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => __('Not fetched yet'),
            self::Ok => __('OK'),
            self::Failing => __('Failing'),
            self::AuthFailed => __('Authentication failed'),
            self::Disabled => __('Disabled'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Ok => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200',
            self::Failing => 'bg-amber-100 text-amber-900 dark:bg-amber-900/50 dark:text-amber-200',
            self::AuthFailed => 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200',
            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }
}
