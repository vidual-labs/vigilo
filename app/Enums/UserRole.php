<?php

namespace App\Enums;

enum UserRole: string
{
    /** Sees every client and may change anything. */
    case Operator = 'operator';

    /** Sees every client, changes nothing. */
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Operator => __('Operator'),
            self::Viewer => __('Viewer'),
        };
    }
}
