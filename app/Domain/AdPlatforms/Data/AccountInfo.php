<?php

namespace App\Domain\AdPlatforms\Data;

final readonly class AccountInfo
{
    public function __construct(
        public ?string $name,
        public ?string $currency,
        public ?string $timezone,
        /** Whether the platform will deliver ads for this account at all. */
        public bool $isActive,
        public ?string $rawStatus,
    ) {}

    /** @return array<string, mixed> */
    public function toSettings(): array
    {
        return [
            'account_name' => $this->name,
            'account_currency' => $this->currency,
            'account_timezone' => $this->timezone,
            'account_active' => $this->isActive,
            'account_status' => $this->rawStatus,
        ];
    }
}
