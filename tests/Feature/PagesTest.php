<?php

use App\Domain\Demo\DemoSeeder;
use App\Models\Client;
use App\Models\Finding;

beforeEach(function () {
    app(DemoSeeder::class)->seed();
});

dataset('locales', ['en', 'de']);

it('renders every page for an operator', function (string $locale) {
    $user = $this->operator(['locale' => $locale]);
    $client = Client::query()->where('slug', 'alpenblick-solar-gmbh')->firstOrFail();
    $finding = Finding::query()->where('client_id', $client->id)->firstOrFail();

    foreach (['/findings', '/findings/'.$finding->id, '/clients', '/clients/create', '/clients/'.$client->id,
        '/connections', '/checks', '/notifications', '/users', '/profile'] as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
})->with('locales');

it('shows findings with translated titles', function () {
    $this->actingAs($this->operator(['locale' => 'de']))->get('/findings')
        ->assertSee('Befunde')
        ->assertSee('Alpenblick Solar GmbH')
        ->assertSee('keine Conversions', false);

    $this->actingAs($this->operator(['locale' => 'en']))->get('/findings')
        ->assertSee('no conversions yesterday', false);
});

it('lets viewers read but hides operator-only pages', function () {
    $viewer = $this->viewer();
    $client = Client::query()->firstOrFail();

    $this->actingAs($viewer)->get('/findings')->assertOk()->assertDontSee('Run all checks now');
    $this->actingAs($viewer)->get('/clients/'.$client->id)->assertOk()->assertSee('read-only access');
    $this->actingAs($viewer)->get('/clients/create')->assertForbidden();
    $this->actingAs($viewer)->get('/users')->assertForbidden();
});

it('shows the mock mode badge while simulating', function () {
    $this->actingAs($this->operator())->get('/findings')->assertSee('Mock mode');
});
