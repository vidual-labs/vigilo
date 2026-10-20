<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $locale = 'en';

    public bool $receivesDigest = true;

    public bool $notifyCritical = true;

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(): void
    {
        $user = $this->user();
        $this->fill([
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->preferredLocale(),
            'receivesDigest' => $user->receives_digest,
            'notifyCritical' => $user->notify_critical,
        ]);
    }

    public function save(): void
    {
        $user = $this->user();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['required', Rule::in(User::LOCALES)],
            'receivesDigest' => ['boolean'],
            'notifyCritical' => ['boolean'],
        ]);
        $user->update([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'locale' => $data['locale'],
            'receives_digest' => $this->receivesDigest,
            'notify_critical' => $this->notifyCritical,
        ]);
        session()->put('locale', $data['locale']);
        session()->flash('status', __('Profile saved.'));
        $this->redirectRoute('profile', navigate: true);
    }

    public function changePassword(): void
    {
        $user = $this->user();
        $this->validate([
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(12), 'different:currentPassword', 'same:passwordConfirmation'],
        ], attributes: ['password' => __('new password')]);

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', __('The current password is not correct.'));

            return;
        }

        $user->forceFill(['password' => $this->password, 'must_change_password' => false])->save();
        $this->reset('currentPassword', 'password', 'passwordConfirmation');
        session()->flash('status', __('Password changed.'));
        $this->redirectRoute('findings.index', navigate: true);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        return view('livewire.profile', ['mustChange' => $this->user()->must_change_password])->title(__('Profile'));
    }
}
