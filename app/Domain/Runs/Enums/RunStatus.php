<?php

namespace App\Domain\Runs\Enums;

enum RunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('Running'),
            self::Completed => __('Completed'),
            self::CompletedWithErrors => __('Completed with errors'),
            self::Failed => __('Failed'),
        };
    }
}
