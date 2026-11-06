<?php

use App\Models\Finding;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('shows the login page and redirects guests there', function () {
    $this->get('/findings')->assertRedirect('/login');
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('signs in an active user', function () {
    $user = $this->operator(['email' => 'ops@agency.example']);

    $this->post('/login', ['email' => 'OPS@agency.example', 'password' => 'correct-horse-battery-9'])
        ->assertRedirect(route('findings.index'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects wrong passwords and deactivated users', function () {
    $this->operator(['email' => 'a@agency.example']);
    $this->operator(['email' => 'b@agency.example', 'is_active' => false]);

    $this->post('/login', ['email' => 'a@agency.example', 'password' => 'nope'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => 'b@agency.example', 'password' => 'correct-horse-battery-9'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('throttles repeated login attempts', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'x@agency.example', 'password' => 'nope']);
    }

    $this->post('/login', ['email' => 'x@agency.example', 'password' => 'nope'])->assertStatus(429);
});

it('sends users with a generated password to the profile first', function () {
    $user = $this->operator(['must_change_password' => true]);

    $this->actingAs($user)->get('/findings')->assertRedirect(route('profile'));
    $this->actingAs($user)->get('/profile')->assertOk()->assertSee('generated password');
});

it('sends reset links that point at APP_URL, not the request host', function () {
    Notification::fake();
    config(['app.url' => 'https://vigilo.agency.example']);
    $user = $this->operator(['email' => 'ops@agency.example']);

    $this->withHeader('Host', 'evil.example')->post('/forgot-password', ['email' => 'ops@agency.example'])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
        $url = $n->toMail($user)->actionUrl;

        return str_starts_with($url, 'https://vigilo.agency.example/reset-password/');
    });
});

it('switches the language for guests and users', function () {
    $this->post('/locale', ['locale' => 'de']);
    $this->get('/login')->assertSee('Anmelden');

    $user = $this->operator();
    $this->actingAs($user)->post('/locale', ['locale' => 'de']);
    expect($user->fresh()->locale)->toBe('de');
});

it('answers the health endpoint', function () {
    $this->get('/up')->assertOk();
});

/** Snapshot of the first Livewire component on a page, as the browser would send it back. */
function livewireSnapshot(string $html): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $html, $m);

    return html_entity_decode($m[1] ?? '', ENT_QUOTES);
}

it('locks a deactivated user out of pages that are already open', function () {
    $viewer = $this->viewer();
    $finding = Finding::factory()->create();
    $snapshot = livewireSnapshot($this->actingAs($viewer)->get('/findings')->assertOk()->getContent());

    $viewer->forceFill(['is_active' => false])->save();

    $response = $this->actingAs($viewer->fresh())->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => ['status' => 'all'], 'calls' => []]]]);

    $response->assertRedirect(route('login'));
    expect($response->getContent())->not->toContain(route('findings.show', $finding));
});

it('keeps a forced password change in force on pages that are already open', function () {
    $operator = $this->operator();
    $snapshot = livewireSnapshot($this->actingAs($operator)->get('/findings')->assertOk()->getContent());

    $operator->forceFill(['must_change_password' => true])->save();

    $this->actingAs($operator->fresh())->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]])
        ->assertRedirect(route('profile'));
});
