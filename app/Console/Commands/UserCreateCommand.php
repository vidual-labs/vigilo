<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Console\Command;

class UserCreateCommand extends Command
{
    protected $signature = 'vigilo:user:create {email} {--name=} {--role=operator : operator or viewer} {--password= : leave out to generate one} {--locale=en}';

    protected $description = 'Create a user (prints a generated password unless --password is given)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $role = UserRole::tryFrom((string) $this->option('role'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $role === null) {
            $this->error('A valid email and --role=operator|viewer are required.');

            return self::FAILURE;
        }
        if (User::query()->where('email', $email)->exists()) {
            $this->error("A user with {$email} already exists.");

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        $generated = $password === '';
        if ($generated) {
            $password = PasswordPolicy::generate();
        } elseif ($problems = PasswordPolicy::problems($password, $email)) {
            $this->error('Password '.implode('; ', $problems).'.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => (string) ($this->option('name') ?: ucfirst(strstr($email, '@', true) ?: 'User')),
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'locale' => in_array($this->option('locale'), User::LOCALES, true) ? $this->option('locale') : 'en',
            'receives_digest' => $role === UserRole::Operator,
            'must_change_password' => $generated,
        ]);

        $this->info("Created {$role->value} {$email}.".($generated ? " Password: {$password}" : ''));

        return self::SUCCESS;
    }
}
