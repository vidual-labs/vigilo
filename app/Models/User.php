<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property UserRole $role
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const LOCALES = ['en', 'de'];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'locale', 'is_active',
        'receives_digest', 'notify_critical', 'must_change_password', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'receives_digest' => 'boolean',
            'notify_critical' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function isOperator(): bool
    {
        return $this->role === UserRole::Operator;
    }

    /** @param Builder<User> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<User> $query */
    public function scopeOperators(Builder $query): void
    {
        $query->where('role', UserRole::Operator->value);
    }

    public function preferredLocale(): string
    {
        return in_array($this->locale, self::LOCALES, true) ? $this->locale : config('app.locale');
    }
}
