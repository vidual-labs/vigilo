<?php

use App\Domain\Demo\DemoSeeder;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\FetchLog;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('creates the first operator with a generated password and prints it once', function () {
    $this->artisan('vigilo:admin:ensure')
        ->expectsOutputToContain('password:')
        ->assertSuccessful();

    $admin = User::query()->first();
    expect($admin->email)->toBe('admin@example.com')
        ->and($admin->role)->toBe(UserRole::Operator)
        ->and($admin->must_change_password)->toBeTrue();
});

it('uses a strong ADMIN_PASSWORD as given', function () {
    config(['vigilo.admin.password' => 'Kupfer-Kessel-Wind-42']);

    $this->artisan('vigilo:admin:ensure')->assertSuccessful();

    expect(Hash::check('Kupfer-Kessel-Wind-42', User::query()->first()->password))->toBeTrue()
        ->and(User::query()->first()->must_change_password)->toBeFalse();
});

it('refuses to start with a weak or default ADMIN_PASSWORD', function (string $password) {
    config(['vigilo.admin.password' => $password]);

    $this->artisan('vigilo:admin:ensure')->expectsOutputToContain('refusing to start')->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with(['changeme', 'admin', 'Password1234', 'short1A!']);

it('does nothing once an operator exists', function () {
    $this->operator();
    config(['vigilo.admin.password' => 'changeme']);

    $this->artisan('vigilo:admin:ensure')->assertSuccessful();

    expect(User::query()->count())->toBe(1);
});

it('loads demo clients that exercise every check, once', function () {
    config(['vigilo.demo_data' => true]);
    $this->operator();

    $this->artisan('vigilo:demo:seed --first-boot')->assertSuccessful();
    $this->artisan('vigilo:demo:seed --first-boot')->assertSuccessful();

    expect(Client::query()->where('is_demo', true)->count())->toBe(7)
        ->and(Finding::query()->distinct()->pluck('check_key')->sort()->values()->all())
        ->toBe(['ad_policy', 'budget_pacing', 'connection_health', 'conversion_drop', 'cpl_spike', 'landing_page', 'spend_without_leads'])
        ->and(Finding::query()->where('status', 'acknowledged')->count())->toBe(1)
        ->and(Finding::query()->where('status', 'resolved')->count())->toBeGreaterThanOrEqual(1);
});

it('removes demo data and does not bring it back on the next boot', function () {
    config(['vigilo.demo_data' => true]);
    app(DemoSeeder::class)->seed();
    Client::factory()->create(['name' => 'Real client']);

    $this->artisan('vigilo:demo:remove')->assertSuccessful();
    $this->artisan('vigilo:demo:seed --first-boot')->assertSuccessful();

    expect(Client::query()->pluck('name')->all())->toBe(['Real client']);
});

it('creates users from the command line', function () {
    $this->artisan('vigilo:user:create', ['email' => 'Viewer@Agency.example', '--role' => 'viewer'])
        ->expectsOutputToContain('Password:')->assertSuccessful();

    expect(User::query()->where('email', 'viewer@agency.example')->value('role'))->toBe(UserRole::Viewer);
});

it('runs a full cycle from the command line', function () {
    $this->artisan('vigilo:demo:seed')->assertSuccessful();
    $this->artisan('vigilo:demo:seed')->expectsOutputToContain('already loaded')->assertSuccessful();

    $this->artisan('vigilo:run --sync --no-fetch')->expectsOutputToContain('finished: completed')->assertSuccessful();
});

it('prunes data past its retention', function () {
    $client = Client::factory()->create();
    FetchLog::query()->create(['client_id' => $client->id, 'subject_type' => 'client', 'subject_id' => $client->id, 'kind' => 'probe', 'status' => 'success', 'started_at' => now()->subDays(120)]);
    FetchLog::query()->create(['client_id' => $client->id, 'subject_type' => 'client', 'subject_id' => $client->id, 'kind' => 'probe', 'status' => 'success', 'started_at' => now()]);
    Finding::factory()->for($client)->create(['status' => 'resolved', 'resolved_at' => now()->subDays(400)]);

    $this->artisan('vigilo:prune')->assertSuccessful();

    expect(FetchLog::query()->count())->toBe(1)->and(Finding::query()->count())->toBe(0);
});

it('reports queue health', function () {
    $this->artisan('vigilo:health:queue')->assertSuccessful();

    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);

    $this->artisan('vigilo:health:queue')->assertFailed();
});
