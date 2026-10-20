<?php

use App\Domain\Findings\Enums\FindingStatus;
use App\Livewire\Checks\CheckSettings;
use App\Livewire\Clients\ClientEdit;
use App\Livewire\Connections\ConnectionIndex;
use App\Livewire\Findings\Inbox;
use App\Livewire\Notifications\NotificationSettings;
use App\Livewire\Profile;
use App\Livewire\Users\UserIndex;
use App\Models\AdConnection;
use App\Models\CheckSetting;
use App\Models\Client;
use App\Models\Finding;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('acknowledges, snoozes and resolves findings from the inbox', function () {
    $operator = $this->operator();
    [$a, $b, $c] = Finding::factory()->count(3)->create();

    Livewire::actingAs($operator)->test(Inbox::class)
        ->call('acknowledge', $a->id)
        ->call('snooze', $b->id, 7)
        ->call('resolve', $c->id);

    expect($a->fresh()->status)->toBe(FindingStatus::Acknowledged)
        ->and($a->fresh()->acknowledged_by)->toBe($operator->id)
        ->and($b->fresh()->status)->toBe(FindingStatus::Snoozed)
        ->and($c->fresh()->status)->toBe(FindingStatus::Resolved)
        ->and($c->fresh()->events()->count())->toBe(1);
});

it('filters the inbox', function () {
    $mine = Finding::factory()->critical()->create();
    $other = Finding::factory()->create();

    Livewire::actingAs($this->viewer())->test(Inbox::class)
        ->set('severity', 'critical')
        ->assertSee(route('findings.show', $mine))
        ->assertDontSee(route('findings.show', $other));
});

it('refuses every mutating action to viewers', function () {
    $viewer = $this->viewer();
    $finding = Finding::factory()->create();
    $client = Client::factory()->create();
    $connection = AdConnection::factory()->for($client)->create();

    Livewire::actingAs($viewer)->test(Inbox::class)->call('acknowledge', $finding->id)->assertForbidden();
    Livewire::actingAs($viewer)->test(Inbox::class)->call('runNow')->assertForbidden();
    Livewire::actingAs($viewer)->test(ClientEdit::class, ['client' => $client])->call('save')->assertForbidden();
    Livewire::actingAs($viewer)->test(ClientEdit::class, ['client' => $client])->call('delete')->assertForbidden();
    Livewire::actingAs($viewer)->test(ConnectionIndex::class)->call('fetchNow', $connection->id)->assertForbidden();
    Livewire::actingAs($viewer)->test(CheckSettings::class)->call('save')->assertForbidden();
    Livewire::actingAs($viewer)->test(NotificationSettings::class)->call('sendDigestNow')->assertForbidden();

    expect($finding->fresh()->status)->toBe(FindingStatus::Open)->and($client->fresh())->not->toBeNull();
});

it('creates a client with budgets, lead source, monitored URL and a check override', function () {
    $operator = $this->operator();

    Livewire::actingAs($operator)->test(ClientEdit::class)
        ->set('name', 'Seeberger Holzbau')
        ->set('currency', 'CHF')
        ->set('timezone', 'Europe/Zurich')
        ->set('budgets.google', '3000')
        ->set('leadType', 'lodgely')
        ->set('leadKey', 'seeberger-holzbau')
        ->set('checkForm.cpl_spike.values.warning_factor', '1,8')
        ->set('checkForm.landing_page.enabled', '0')
        ->call('save')
        ->assertHasNoErrors();

    $client = Client::query()->where('name', 'Seeberger Holzbau')->firstOrFail();
    expect($client->slug)->toBe('seeberger-holzbau')
        ->and($client->budgets()->value('monthly_amount'))->toEqual('3000.00')
        ->and($client->leadSource->external_key)->toBe('seeberger-holzbau')
        ->and(CheckSetting::query()->where('client_id', $client->id)->where('check_key', 'cpl_spike')->value('thresholds'))->toBe(['warning_factor' => 1.8])
        ->and(CheckSetting::query()->where('client_id', $client->id)->where('check_key', 'landing_page')->value('enabled'))->toBeFalse();

    Livewire::actingAs($operator)->test(ClientEdit::class, ['client' => $client])
        ->set('newUrl', 'https://www.seeberger-holzbau.example/kontakt')
        ->call('addUrl')
        ->assertHasNoErrors();
    expect($client->monitoredUrls()->count())->toBe(1);
});

it('validates check thresholds', function () {
    Livewire::actingAs($this->operator())->test(CheckSettings::class)
        ->set('checkForm.budget_pacing.values.min_day_of_month', 'soon')
        ->call('save')
        ->assertHasErrors('checkForm.budget_pacing.values.min_day_of_month');
});

it('adds, tests and refuses duplicate connections', function () {
    $operator = $this->operator();
    $client = Client::factory()->create();

    Livewire::actingAs($operator)->test(ConnectionIndex::class)
        ->call('create')
        ->set('form.client_id', (string) $client->id)
        ->set('form.platform', 'google')
        ->set('form.account_id', '123-456-7890')
        ->set('form.token', '1//own-refresh-token')
        ->call('save')
        ->assertHasNoErrors();

    $connection = AdConnection::query()->firstOrFail();
    expect($connection->account_id)->toBe('1234567890')
        ->and($connection->credential('refresh_token'))->toBe('1//own-refresh-token')
        ->and($connection->getRawOriginal('credentials'))->not->toContain('own-refresh-token');

    Livewire::actingAs($operator)->test(ConnectionIndex::class)
        ->call('test', $connection->id)
        ->assertSet('testResults.'.$connection->id.'.ok', true)
        ->call('create')
        ->set('form.client_id', (string) $client->id)
        ->set('form.account_id', '1234567890')
        ->call('save')
        ->assertHasErrors('form.account_id');
});

it('manages webhooks and shows the secret only once', function () {
    $component = Livewire::actingAs($this->operator())->test(NotificationSettings::class)
        ->set('name', 'n8n')
        ->set('url', 'https://n8n.example/webhook/vigilo')
        ->call('addEndpoint')
        ->assertHasNoErrors();

    $endpoint = WebhookEndpoint::query()->firstOrFail();
    $component->assertSet('revealedSecret', $endpoint->secret);
    expect($endpoint->getRawOriginal('secret'))->not->toBe($endpoint->secret);
});

it('creates users with a one-time password and protects the acting operator', function () {
    $operator = $this->operator();

    $component = Livewire::actingAs($operator)->test(UserIndex::class)
        ->set('name', 'Jana')->set('email', 'jana@agency.example')->set('role', 'viewer')
        ->call('create')->assertHasNoErrors();

    $jana = User::query()->where('email', 'jana@agency.example')->firstOrFail();
    expect(Hash::check($component->get('revealedPassword'), $jana->password))->toBeTrue()
        ->and($jana->must_change_password)->toBeTrue();

    Livewire::actingAs($operator)->test(UserIndex::class)->call('delete', $operator->id)->assertForbidden();
});

it('changes the password and clears the forced change', function () {
    $user = $this->operator(['must_change_password' => true]);

    Livewire::actingAs($user)->test(Profile::class)
        ->set('currentPassword', 'correct-horse-battery-9')
        ->set('password', 'a-much-better-passphrase')
        ->set('passwordConfirmation', 'a-much-better-passphrase')
        ->call('changePassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('findings.index'));

    expect($user->fresh()->must_change_password)->toBeFalse();
});
