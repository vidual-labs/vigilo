<?php

namespace App\Domain\Runs\Enums;

enum RunTrigger: string
{
    case Schedule = 'schedule';
    case Manual = 'manual';
    case Cli = 'cli';

    public function label(): string
    {
        return match ($this) {
            self::Schedule => __('Scheduled'),
            self::Manual => __('Manual'),
            self::Cli => __('Command line'),
        };
    }
}
