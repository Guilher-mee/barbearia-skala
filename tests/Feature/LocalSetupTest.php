<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'demo.name' => 'Administrador de demonstração',
        'demo.email' => 'admin@example.test',
        'demo.password' => 'DemoBarbearia!123',
    ]);
});

test('prepares local services and a staff login without reservations', function (string $environment) {
    app()->detectEnvironment(fn (): string => $environment);

    $this->artisan('app:prepare-local', ['--no-interaction' => true])->assertSuccessful();

    $staff = User::sole();
    expect($staff->name)->toBe('Administrador de demonstração');
    expect($staff->email)->toBe('admin@example.test');
    expect($staff->is_staff)->toBeTrue();
    expect(Hash::check('DemoBarbearia!123', $staff->password))->toBeTrue();
    $this->assertDatabaseCount('services', 3);
    $this->assertDatabaseCount('bookings', 0);

    Livewire::test('pages::login')->set('email', 'admin@example.test')->set('password', 'DemoBarbearia!123')
        ->call('login')->assertHasNoErrors()->assertRedirect(route('agenda'));
    $this->assertAuthenticatedAs($staff);
    $this->get(route('agenda'))->assertOk();
    $this->get(route('services'))->assertOk();
})->with(['local', 'testing']);

test('preserves existing credentials services and bookings when preparation is repeated', function () {
    $this->artisan('app:prepare-local')->assertSuccessful();
    $staff = User::sole();
    $staff->update(['name' => 'Nome personalizado', 'password' => 'NovaSenhaPrivada!42']);
    $passwordHash = $staff->fresh()->password;
    $service = Service::where('name', 'Corte de cabelo')->firstOrFail();
    $service->update(['price' => '70.00']);
    $booking = Booking::factory()->for($service)->create();
    $originalBooking = $booking->fresh()->getAttributes();
    $key = config('app.key');

    $this->artisan('app:prepare-local')->assertSuccessful();

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('services', 3);
    $this->assertDatabaseCount('bookings', 1);
    expect($staff->fresh()->name)->toBe('Nome personalizado');
    expect($staff->fresh()->password)->toBe($passwordHash);
    expect($service->fresh()->price)->toBe('70.00');
    expect($booking->fresh()->getAttributes())->toBe($originalBooking);
    expect(config('app.key'))->toBe($key);
});

test('accepts configured identity and password for a new demonstration account', function () {
    config(['demo.name' => '  Equipe local  ', 'demo.email' => ' LOCAL@example.test ', 'demo.password' => 'OutraSenhaLocal!42']);

    $this->seed(DemoStaffSeeder::class);

    $staff = User::sole();
    expect($staff->name)->toBe('Equipe local');
    expect($staff->email)->toBe('local@example.test');
    expect(Hash::check('OutraSenhaLocal!42', $staff->password))->toBeTrue();
});

test('refuses an existing non staff account without changing it or preparing services', function () {
    $user = User::factory()->create(['email' => 'ADMIN@example.test']);
    $attributes = $user->fresh()->getAttributes();

    $this->artisan('app:prepare-local')
        ->expectsOutput('O e-mail de demonstração já pertence a um usuário sem permissão de funcionário. Configure outro DEMO_STAFF_EMAIL.')
        ->assertFailed();

    expect($user->fresh()->getAttributes())->toBe($attributes);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('services', 0);
});

test('rejects invalid demonstration credentials without creating any data', function (string $field, string $value) {
    config(['demo.'.$field => $value]);

    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('services', 0);
})->with([['name', ''], ['email', 'invalid'], ['password', 'short']]);

test('skips demonstration data outside local environments even in unattended execution', function (string $environment) {
    app()->detectEnvironment(fn (): string => $environment);

    $this->artisan('app:prepare-local', ['--no-interaction' => true])
        ->expectsOutput('Dados de demonstração ignorados fora de local ou testing.')->assertSuccessful();

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('services', 0);
})->with(['production', 'staging']);

test('refuses direct forced demonstration seeding outside local environments', function (string $environment, string $seeder) {
    app()->detectEnvironment(fn (): string => $environment);

    expect(fn () => $this->artisan('db:seed', ['--class' => $seeder, '--force' => true, '--no-interaction' => true])->run())
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('services', 0);
})->with(['production', 'staging'])->with([DatabaseSeeder::class, DemoStaffSeeder::class]);

test('generates a missing key in an isolated environment file and preserves it on the next run', function () {
    $environmentPath = app()->environmentPath();
    $environmentFile = app()->environmentFile();
    $temporaryPath = sys_get_temp_dir().'/barbearia-env-'.bin2hex(random_bytes(8));
    mkdir($temporaryPath);
    file_put_contents($temporaryPath.'/.env', "APP_KEY=\n");
    app()->useEnvironmentPath($temporaryPath)->loadEnvironmentFrom('.env');
    config(['app.key' => '']);

    try {
        $this->artisan('app:prepare-local')->assertSuccessful();
        $key = config('app.key');
        expect($key)->toStartWith('base64:');
        expect(file_get_contents($temporaryPath.'/.env'))->toBe('APP_KEY='.$key."\n");

        $this->artisan('app:prepare-local')->assertSuccessful();
        expect(config('app.key'))->toBe($key);
        expect(file_get_contents($temporaryPath.'/.env'))->toBe('APP_KEY='.$key."\n");
    } finally {
        app()->useEnvironmentPath($environmentPath)->loadEnvironmentFrom($environmentFile);
        unlink($temporaryPath.'/.env');
        rmdir($temporaryPath);
    }
});

test('reports a key generation failure without preparing demonstration data', function () {
    $environmentPath = app()->environmentPath();
    $environmentFile = app()->environmentFile();
    $temporaryPath = sys_get_temp_dir().'/barbearia-env-'.bin2hex(random_bytes(8));
    mkdir($temporaryPath);
    file_put_contents($temporaryPath.'/.env', "APP_NAME=Teste\n");
    app()->useEnvironmentPath($temporaryPath)->loadEnvironmentFrom('.env');
    config(['app.key' => '']);

    try {
        $this->artisan('app:prepare-local')
            ->expectsOutput('Não foi possível gerar a chave da aplicação. Verifique o campo APP_KEY e a escrita no .env.')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    } finally {
        app()->useEnvironmentPath($environmentPath)->loadEnvironmentFrom($environmentFile);
        unlink($temporaryPath.'/.env');
        rmdir($temporaryPath);
    }
});

test('supports a public booking followed by staff consultation after local preparation', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));
    $this->artisan('app:prepare-local')->assertSuccessful();
    $service = Service::where('name', 'Corte de cabelo')->firstOrFail();

    Livewire::test('pages::book-appointment')
        ->set('service_id', (string) $service->id)->set('date', '2026-10-24')->set('time', '09:00')
        ->set('customer_name', 'Cliente de exemplo')->set('customer_phone', '11912345678')
        ->call('save')->assertHasNoErrors()->assertSee('Agendamento confirmado!');

    $this->assertDatabaseHas('bookings', ['service_id' => $service->id, 'customer_name' => 'Cliente de exemplo', 'price_at_booking' => '35.00']);
    Livewire::test('pages::login')->set('email', 'admin@example.test')->set('password', 'DemoBarbearia!123')
        ->call('login')->assertRedirect(route('agenda'));
    Livewire::test('pages::daily-agenda')->set('date', '2026-10-24')->assertSee('Cliente de exemplo');
});
