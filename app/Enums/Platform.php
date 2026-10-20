<?php

namespace App\Enums;

enum Platform: string
{
    case Google = 'google';
    case Meta = 'meta';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google Ads',
            self::Meta => 'Meta Ads',
        };
    }

    /** What the platform calls the id an operator pastes into vigilo. */
    public function accountIdLabel(): string
    {
        return match ($this) {
            self::Google => __('Customer ID'),
            self::Meta => __('Ad account ID'),
        };
    }
}
