<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Console\Command;

/**
 * First-boot admin, run by the Docker entrypoint on every start. Creates
 * the first operator from ADMIN_EMAIL / ADMIN_PASSWORD when no operator
 * exists yet; does nothing afterwards. A weak ADMIN_PASSWORD fails the
 * command (and with it the container start). An empty one is replaced by a
 * generated password that is printed once.
 */
class AdminEnsureCommand extends Command
{
    protected $signature = 'vigilo:admin:ensure';

    protected $description = 'Create the first operator from ADMIN_EMAIL / ADMIN_PASSWORD if none exists';

    public function handle(): int
    {
        $email = strtolower(trim((string) config('vigilo.admin.email')));
        $password = (string) config('vigilo.admin.password');

        if (User::query()->operators()->exists()) {
            $this->line('vigilo: an operator account exists, nothing to do.');

            return self::SUCCESS;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("vigilo: ADMIN_EMAIL '{$email}' is not a valid email address.");

            return self::FAILURE;
        }

        $generated = false;
        if ($password === '') {
            $password = PasswordPolicy::generate();
            $generated = true;
        } elseif ($problems = PasswordPolicy::problems($password, $email)) {
            $this->error('vigilo: refusing to start — ADMIN_PASSWORD '.implode('; ', $problems).'.');
            $this->error('Set a strong ADMIN_PASSWORD in .env, or leave it empty to have one generated.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => (string) config('vigilo.admin.name', 'Admin'),
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Operator,
            'locale' => config('app.locale', 'en'),
            'receives_digest' => true,
            'notify_critical' => true,
            'must_change_password' => $generated,
        ]);

        if ($generated) {
            $this->newLine();
            $this->line('==================================================================');
            $this->line('  vigilo first operator account');
            $this->line("  email:    {$email}");
            $this->line("  password: {$password}");
            $this->line('  This password is shown once. You will be asked to change it');
            $this->line('  after the first login.');
            $this->line('==================================================================');
            $this->newLine();
        } else {
            $this->info("vigilo: created operator {$email}.");
        }

        return self::SUCCESS;
    }
}
