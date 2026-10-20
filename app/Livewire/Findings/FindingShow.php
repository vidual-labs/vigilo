<?php

namespace App\Livewire\Findings;

use App\Domain\Findings\Services\FindingActions;
use App\Livewire\Concerns\ChecksOperator;
use App\Models\Finding;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FindingShow extends Component
{
    use ChecksOperator;

    public Finding $finding;

    public function mount(Finding $finding): void
    {
        // Visibility seam: 404 for findings of clients the user cannot see.
        $this->finding = Finding::query()->visibleTo(auth()->user())->findOrFail($finding->id);
    }

    public function acknowledge(FindingActions $actions): void
    {
        $actions->acknowledge($this->finding, $this->authorizeOperator());
    }

    public function snooze(int $days, FindingActions $actions): void
    {
        $actions->snooze($this->finding, $this->authorizeOperator(), $days);
    }

    public function resolve(FindingActions $actions): void
    {
        $actions->resolve($this->finding, $this->authorizeOperator());
    }

    public function reopen(FindingActions $actions): void
    {
        $actions->reopen($this->finding, $this->authorizeOperator());
    }

    public function render(): View
    {
        $this->finding->refresh()->load(['client', 'events.user', 'acknowledgedBy', 'resolvedBy']);

        return view('livewire.findings.show', [
            'snoozeDays' => FindingActions::SNOOZE_DAYS,
        ])->title($this->finding->title());
    }
}
