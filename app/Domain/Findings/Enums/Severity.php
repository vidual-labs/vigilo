<?php

namespace App\Domain\Findings\Enums;

enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function rank(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Warning => 2,
            self::Critical => 3,
        };
    }

    public function isWorseThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public static function worst(self ...$severities): self
    {
        $worst = self::Info;
        foreach ($severities as $severity) {
            if ($severity->isWorseThan($worst)) {
                $worst = $severity;
            }
        }

        return $worst;
    }

    public function label(): string
    {
        return match ($this) {
            self::Info => __('Info'),
            self::Warning => __('Warning'),
            self::Critical => __('Critical'),
        };
    }

    /** Tailwind classes for the severity pill (light + dark). */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Info => 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200',
            self::Warning => 'bg-amber-100 text-amber-900 dark:bg-amber-900/50 dark:text-amber-200',
            self::Critical => 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200',
        };
    }

    /** @return list<string> values ordered critical first */
    public static function orderedValues(): array
    {
        return [self::Critical->value, self::Warning->value, self::Info->value];
    }
}
