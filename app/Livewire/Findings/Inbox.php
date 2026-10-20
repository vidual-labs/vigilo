<?php

namespace App\Livewire\Findings;

use App\Domain\Checks\CheckRegistry;
use App\Domain\Findings\Enums\FindingStatus;
use App\Domain\Findings\Enums\Severity;
use App\Domain\Findings\Services\FindingActions;
use App\Domain\Runs\Enums\RunTrigger;
use App\Domain\Runs\RunManager;
use App\Enums\Platform;
use App\Livewire\Concerns\ChecksOperator;
use App\Models\Client;
use App\Models\Finding;
use App\Models\Run;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Inbox extends Component
{
    use ChecksOperator, WithPagination;

    #[Url(except: '')]
    public string $client = '';

    #[Url(except: '')]
    public string $severity = '';

    /** "active" = open + acknowledged (snoozed and resolved are out of the way). */
    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $check = '';

    #[Url(except: '')]
    public string $platform = '';

    #[Url(except: '')]
    public string $q = '';

    /** @var list<int|string> */
    public array $selected = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['client', 'severity', 'status', 'check', 'platform', 'q'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function resetFilters(): void
    {
        $this->reset('client', 'severity', 'check', 'platform', 'q', 'selected');
        $this->status = 'active';
        $this->resetPage();
    }

    public function acknowledge(int $id, FindingActions $actions): void
    {
        $actions->acknowledge($this->find($id), $this->authorizeOperator());
    }

    public function snooze(int $id, int $days, FindingActions $actions): void
    {
        $actions->snooze($this->find($id), $this->authorizeOperator(), $days);
    }

    public function resolve(int $id, FindingActions $actions): void
    {
        $actions->resolve($this->find($id), $this->authorizeOperator());
    }

    public function reopen(int $id, FindingActions $actions): void
    {
        $actions->reopen($this->find($id), $this->authorizeOperator());
    }

    public function bulk(string $action, FindingActions $actions): void
    {
        $user = $this->authorizeOperator();
        $findings = Finding::query()->visibleTo($user)->whereIn('id', array_map('intval', $this->selected))->get();
        foreach ($findings as $finding) {
            match ($action) {
                'acknowledge' => $actions->acknowledge($finding, $user),
                'snooze' => $actions->snooze($finding, $user, 7),
                'resolve' => $actions->resolve($finding, $user),
                default => null,
            };
        }
        $this->selected = [];
        session()->flash('status', __(':count findings updated.', ['count' => $findings->count()]));
    }

    public function runNow(RunManager $runs): void
    {
        $user = $this->authorizeOperator();
        $run = $runs->start(RunTrigger::Manual, user: $user);
        session()->flash('status', __('Run #:id started: fetching data and running all checks. Findings update when it finishes.', ['id' => $run->id]));
    }

    private function find(int $id): Finding
    {
        return Finding::query()->visibleTo(auth()->user())->findOrFail($id);
    }

    /** @return Builder<Finding> */
    private function query(): Builder
    {
        $user = auth()->user();

        return Finding::query()
            ->visibleTo($user)
            ->with('client')
            ->when($this->client !== '', fn (Builder $q) => $q->where('client_id', (int) $this->client))
            ->when($this->severity !== '', fn (Builder $q) => $q->where('severity', $this->severity))
            ->when($this->check !== '', fn (Builder $q) => $q->where('check_key', $this->check))
            ->when($this->platform !== '', fn (Builder $q) => $q->where('platform', $this->platform))
            ->when($this->status === 'active', fn (Builder $q) => $q->whereIn('status', [FindingStatus::Open->value, FindingStatus::Acknowledged->value]))
            ->when(! in_array($this->status, ['active', 'all'], true), fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->q !== '', function (Builder $q) {
                $term = '%'.mb_strtolower($this->q).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('lower(dedupe_key) like ?', [$term])
                    ->orWhereRaw('lower(cast(evidence as text)) like ?', [$term])
                    ->orWhereHas('client', fn (Builder $c) => $c->whereRaw('lower(name) like ?', [$term])));
            });
    }

    public function render(): View
    {
        $user = auth()->user();
        $counts = Finding::query()->visibleTo($user)->where('status', FindingStatus::Open->value)
            ->selectRaw('severity, count(*) as n')->groupBy('severity')->toBase()->pluck('n', 'severity');

        return view('livewire.findings.inbox', [
            'findings' => $this->query()->orderBySeverity()->orderByDesc('opened_at')->paginate(25),
            'clients' => Client::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'checks' => app(CheckRegistry::class)->keys(),
            'severities' => Severity::cases(),
            'platforms' => Platform::cases(),
            'statuses' => FindingStatus::cases(),
            'counts' => $counts,
            'lastRun' => Run::query()->latest('started_at')->first(),
            'snoozeDays' => FindingActions::SNOOZE_DAYS,
        ])->title(__('Findings'));
    }
}
