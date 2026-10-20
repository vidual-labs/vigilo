<?php

namespace App\Livewire\Concerns;

use App\Models\User;

/** Server-side guard for every mutating Livewire action: viewers are read-only. */
trait ChecksOperator
{
    protected function authorizeOperator(): User
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user->can('operate'), 403);

        return $user;
    }

    public function canOperate(): bool
    {
        return (bool) auth()->user()?->can('operate');
    }
}
