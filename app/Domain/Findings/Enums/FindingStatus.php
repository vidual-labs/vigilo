<?php

namespace App\Domain\Findings\Enums;

enum FindingStatus: string
{
    /** Detected and nobody has reacted yet. */
    case Open = 'open';

    /** Someone has seen it; stays visible, no further alerts unless it escalates. */
    case Acknowledged = 'acknowledged';

    /** Hidden until snoozed_until; reopens if still detected afterwards. */
    case Snoozed = 'snoozed';

    /** Condition cleared (automatically) or closed by an operator. */
    case Resolved = 'resolved';

    /** Statuses that count as "still a problem". */
    public function isActive(): bool
    {
        return $this !== self::Resolved;
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Open->value, self::Acknowledged->value, self::Snoozed->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Acknowledged => __('Acknowledged'),
            self::Snoozed => __('Snoozed'),
            self::Resolved => __('Resolved'),
        };
    }
}
