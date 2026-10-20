<?php

namespace App\Domain\Findings\Enums;

enum FindingEventType: string
{
    case Opened = 'opened';
    case Reopened = 'reopened';
    case Escalated = 'escalated';
    case Deescalated = 'deescalated';
    case Acknowledged = 'acknowledged';
    case Snoozed = 'snoozed';
    case Unsnoozed = 'unsnoozed';
    case Resolved = 'resolved';
    case Notified = 'notified';

    public function label(): string
    {
        return match ($this) {
            self::Opened => __('Detected'),
            self::Reopened => __('Detected again'),
            self::Escalated => __('Severity raised'),
            self::Deescalated => __('Severity lowered'),
            self::Acknowledged => __('Acknowledged'),
            self::Snoozed => __('Snoozed'),
            self::Unsnoozed => __('Snooze ended'),
            self::Resolved => __('Resolved'),
            self::Notified => __('Notification sent'),
        };
    }
}
