<?php

namespace App\Domain\Leads\Enums;

enum LeadSourceType: string
{
    case Mock = 'mock';
    case Lodgely = 'lodgely';

    public function label(): string
    {
        return match ($this) {
            self::Mock => __('Simulated (mock)'),
            self::Lodgely => 'lodgely',
        };
    }
}
