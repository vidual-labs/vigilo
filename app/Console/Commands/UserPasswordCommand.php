<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Sets a user's password from the command line, e.g. when the operator is
 * locked out. The password is asked for without echo, so it never lands in
 * the shell history or in .env (where Docker Compose would mangle a "$").
 */
class UserPasswordCommand extends Command
{
    protected $signature = 'vigilo:user:password {email} {--generate : Generate a password, print it once and ask for a new one at the next sign-in}';

    protected $description = 'Set a user\'s password (asked for without echo, or generated with --generate)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No user with {$email}.");

            return self::FAILURE;
        }

        $generated = (bool) $this->option('generate');
        if ($generated) {
            $password = PasswordPolicy::generate();
        } else {
            $password = (string) $this->secret('New password');
            if ($password === '') {
                $this->error('No password given. Run it in an interactive terminal, or use --generate.');

                return self::FAILURE;
            }
            if ($problems = PasswordPolicy::problems($password, $email)) {
                $this->error('Password '.implode('; ', $problems).'.');

                return self::FAILURE;
            }
            if ($password !== (string) $this->secret('Repeat the password')) {
                $this->error('The passwords do not match. Nothing was changed.');

                return self::FAILURE;
            }
        }

        // Same as the reset-by-mail flow: "remember me" cookies stop working.
        $user->forceFill([
            'password' => $password,
            'must_change_password' => $generated,
            'remember_token' => Str::random(60),
        ])->save();

        $this->info("Password set for {$email}.".($generated ? " Password: {$password}" : ''));
        if (! $user->is_active) {
            $this->warn('This account is deactivated and cannot sign in until an operator activates it.');
        }

        return self::SUCCESS;
    }
}
