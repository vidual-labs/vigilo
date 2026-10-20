<?php

namespace App\Domain\Checks;

use App\Domain\Checks\Checks\AdPolicyCheck;
use App\Domain\Checks\Checks\BudgetPacingCheck;
use App\Domain\Checks\Checks\ConnectionHealthCheck;
use App\Domain\Checks\Checks\ConversionDropCheck;
use App\Domain\Checks\Checks\CplSpikeCheck;
use App\Domain\Checks\Checks\LandingPageCheck;
use App\Domain\Checks\Checks\SpendWithoutLeadsCheck;
use App\Domain\Checks\Contracts\Check;
use Illuminate\Contracts\Container\Container;

/**
 * Every check vigilo runs, in display order. Adding a check = a class
 * implementing Check, a line here, defaults in config/vigilo.php and
 * strings in lang/{en,de}/checks.php + findings.php (see CLAUDE.md).
 */
class CheckRegistry
{
    /** @var list<class-string<Check>> */
    public const CHECKS = [
        ConnectionHealthCheck::class,
        ConversionDropCheck::class,
        SpendWithoutLeadsCheck::class,
        BudgetPacingCheck::class,
        AdPolicyCheck::class,
        CplSpikeCheck::class,
        LandingPageCheck::class,
    ];

    /** @var array<string, Check>|null */
    private ?array $checks = null;

    public function __construct(private readonly Container $container) {}

    /** @return array<string, Check> key => check */
    public function all(): array
    {
        if ($this->checks === null) {
            $this->checks = [];
            foreach (self::CHECKS as $class) {
                $check = $this->container->make($class);
                $this->checks[$check->key()] = $check;
            }
        }

        return $this->checks;
    }

    public function get(string $key): ?Check
    {
        return $this->all()[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
