<?php

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Livewire\Concerns\ChecksOperator;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UserIndex extends Component
{
    use ChecksOperator;

    public string $name = '';

    public string $email = '';

    public string $role = 'viewer';

    public string $locale = 'en';

    /** Generated password, shown once. */
    public ?string $revealedPassword = null;

    public ?string $revealedFor = null;

    public function mount(): void
    {
        $this->authorizeOperator();
        $this->locale = (string) config('app.locale', 'en');
    }

    public function create(): void
    {
        $this->authorizeOperator();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'locale' => ['required', Rule::in(User::LOCALES)],
        ]);
        $password = PasswordPolicy::generate();
        $role = UserRole::from($data['role']);
        User::query()->create([
            ...$data,
            'email' => strtolower($data['email']),
            'password' => $password,
            'receives_digest' => $role === UserRole::Operator,
            'must_change_password' => true,
        ]);
        $this->revealedPassword = $password;
        $this->revealedFor = strtolower($data['email']);
        $this->reset('name', 'email');
    }

    public function setRole(int $id, string $role): void
    {
        $actor = $this->authorizeOperator();
        $user = User::query()->findOrFail($id);
        abort_if($user->is($actor), 403);
        $user->update(['role' => UserRole::from($role)]);
    }

    public function toggleActive(int $id): void
    {
        $actor = $this->authorizeOperator();
        $user = User::query()->findOrFail($id);
        abort_if($user->is($actor), 403);
        $user->update(['is_active' => ! $user->is_active]);
    }

    public function resetPassword(int $id): void
    {
        $this->authorizeOperator();
        $user = User::query()->findOrFail($id);
        $password = PasswordPolicy::generate();
        $user->forceFill(['password' => $password, 'must_change_password' => true])->save();
        $this->revealedPassword = $password;
        $this->revealedFor = $user->email;
    }

    public function delete(int $id): void
    {
        $actor = $this->authorizeOperator();
        $user = User::query()->findOrFail($id);
        abort_if($user->is($actor), 403);
        $user->delete();
    }

    public function render(): View
    {
        return view('livewire.users.index', [
            'users' => User::query()->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ])->title(__('Users'));
    }
}
