<?php

namespace App\Livewire\Clients;

use App\Domain\Findings\Enums\FindingStatus;
use App\Livewire\Concerns\ChecksOperator;
use App\Models\Client;
use App\Models\Finding;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class ClientIndex extends Component
{
    use ChecksOperator;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: false)]
    public bool $inactive = false;

    public function render(): View
    {
        $user = auth()->user();
        $clients = Client::query()->visibleTo($user)
            ->with(['connections', 'leadSource', 'budgets'])
            ->when(! $this->inactive, fn ($q) => $q->where('is_active', true))
            ->when($this->q !== '', fn ($q) => $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($this->q).'%']))
            ->orderBy('name')
            ->get();

        $open = Finding::query()->visibleTo($user)
            ->whereIn('status', [FindingStatus::Open->value, FindingStatus::Acknowledged->value])
            ->selectRaw('client_id, severity, count(*) as n')
            ->groupBy('client_id', 'severity')
            ->get()
            ->groupBy('client_id')
            ->map(fn ($rows) => $rows->pluck('n', 'severity'));

        return view('livewire.clients.index', compact('clients', 'open'))->title(__('Clients'));
    }
}
