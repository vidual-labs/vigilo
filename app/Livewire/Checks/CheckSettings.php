<?php

namespace App\Livewire\Checks;

use App\Domain\Checks\CheckRegistry;
use App\Domain\Runs\Enums\RunTrigger;
use App\Domain\Runs\RunManager;
use App\Livewire\Concerns\ChecksOperator;
use App\Livewire\Concerns\EditsCheckSettings;
use App\Models\Run;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CheckSettings extends Component
{
    use ChecksOperator, EditsCheckSettings;

    public function mount(): void
    {
        $this->loadCheckForm(null);
    }

    public function save(): void
    {
        $this->authorizeOperator();
        $this->saveCheckForm(null);
        session()->flash('status', __('Check settings saved. They apply from the next run.'));
        $this->redirectRoute('checks.index', navigate: true);
    }

    public function runNow(RunManager $runs): void
    {
        $user = $this->authorizeOperator();
        $run = $runs->start(RunTrigger::Manual, user: $user);
        session()->flash('status', __('Run #:id started: fetching data and running all checks. Findings update when it finishes.', ['id' => $run->id]));
    }

    public function render(): View
    {
        return view('livewire.checks.settings', [
            'checks' => app(CheckRegistry::class)->all(),
            'inherited' => $this->inheritedCheckSettings(null),
            'readonly' => ! $this->canOperate(),
            'runs' => Run::query()->with('client')->latest('started_at')->limit(10)->get(),
            'schedule' => config('vigilo.schedule.daily_run_at').' '.config('vigilo.timezone'),
        ])->title(__('Checks'));
    }
}
