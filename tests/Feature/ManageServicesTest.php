<?php

use App\Models\Service;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('opens the service page through the shared navigation without built assets', function () {
    $this->get(route('services'))->assertOk()
        ->assertSee('Serviços da barbearia')->assertSee('Cadastrar serviço')
        ->assertSee('Nenhum serviço cadastrado.')->assertDontSee('/build/assets/');
    $this->get(route('home'))->assertOk()->assertSee(route('services'));
    $this->get(route('agenda'))->assertOk()->assertSee(route('services'));
});

test('creates a service with trimmed name and resets the form after saving', function () {
    Livewire::test('pages::manage-services')
        ->set('name', '  Corte  ')->set('duration_minutes', '40')->set('price', '50.00')
        ->call('save')->assertHasNoErrors()->assertSet('saved', true)
        ->assertSee('Serviço cadastrado com sucesso.')->assertSee('Corte')->assertSee('40 min')->assertSee('50,00')
        ->assertSet('name', '')->assertSet('duration_minutes', '')->assertSet('price', '')
        ->assertDontSee('Nenhum serviço cadastrado.');

    $this->assertDatabaseCount('services', 1);
    $this->assertDatabaseHas('services', ['name' => 'Corte', 'duration_minutes' => 40, 'price' => 50]);
    expect(Service::firstOrFail()->price)->toBe('50.00');
});

test('lists services in alphabetical order with BRL prices', function () {
    Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 40, 'price' => '50.90']);
    Service::factory()->create(['name' => 'Barba', 'duration_minutes' => 20, 'price' => '30.00']);

    $component = Livewire::test('pages::manage-services')
        ->assertSee('Barba')->assertSee('20 min')->assertSee('30,00')->assertSee('Corte')->assertSee('50,90');

    expect($component->get('services')->pluck('name')->all())->toBe(['Barba', 'Corte']);
});

test('allows different services to share a name', function () {
    Service::factory()->create(['name' => 'Corte']);

    Livewire::test('pages::manage-services')
        ->set('name', 'Corte')->set('duration_minutes', '40')->set('price', '50')
        ->call('save')->assertHasNoErrors();

    $this->assertDatabaseCount('services', 2);
});

test('accepts valid duration and price boundaries', function (string $duration, string $price, string $expectedPrice) {
    Livewire::test('pages::manage-services')
        ->set('name', 'Serviço')->set('duration_minutes', $duration)->set('price', $price)
        ->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas('services', ['name' => 'Serviço', 'duration_minutes' => (int) $duration]);
    expect(Service::firstOrFail()->price)->toBe($expectedPrice);
})->with([
    ['1', '0', '0.00'],
    ['540', '99999999.99', '99999999.99'],
    ['20', '30.5', '30.50'],
]);

test('shows all required field errors without saving a service', function () {
    Livewire::test('pages::manage-services')->call('save')
        ->assertHasErrors(['name', 'duration_minutes', 'price'])
        ->assertSee('Informe o nome do serviço.')->assertSee('Informe a duração do serviço.')->assertSee('Informe o preço do serviço.');

    $this->assertDatabaseCount('services', 0);
});

test('rejects invalid service fields while retaining the form values', function (string $field, string $value, string $message) {
    $component = Livewire::test('pages::manage-services')
        ->set('name', 'Corte')->set('duration_minutes', '40')->set('price', '50.00')
        ->set($field, $value)->call('save')
        ->assertHasErrors([$field])->assertSee($message)->assertSet('saved', false);

    if ($field !== 'name') {
        $component->assertSet($field, $value)->assertSet('name', 'Corte');
    }

    $this->assertDatabaseCount('services', 0);
})->with([
    'blank name' => ['name', '   ', 'Informe o nome do serviço.'],
    'long name' => ['name', str_repeat('a', 256), 'O nome deve ter no máximo 255 caracteres.'],
    'missing duration' => ['duration_minutes', '', 'Informe a duração do serviço.'],
    'invalid duration' => ['duration_minutes', 'abc', 'A duração deve ser um número inteiro de minutos.'],
    'fractional duration' => ['duration_minutes', '20.5', 'A duração deve ser um número inteiro de minutos.'],
    'zero duration' => ['duration_minutes', '0', 'A duração deve ser de pelo menos 1 minuto.'],
    'negative duration' => ['duration_minutes', '-20', 'A duração deve ser de pelo menos 1 minuto.'],
    'excessive duration' => ['duration_minutes', '541', 'A duração deve ser de no máximo 540 minutos.'],
    'missing price' => ['price', '', 'Informe o preço do serviço.'],
    'invalid price' => ['price', 'abc', 'Informe um preço válido.'],
    'negative price' => ['price', '-0.01', 'O preço não pode ser negativo.'],
    'excessive price' => ['price', '100000000', 'O preço deve ser de no máximo R$ 99.999.999,99.'],
    'excessive decimal places' => ['price', '50.001', 'O preço deve ter no máximo duas casas decimais.'],
]);

test('escapes service names in the list and in the public booking form', function () {
    Service::factory()->create(['name' => '<script>alert(1)</script>']);

    Livewire::test('pages::manage-services')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
    $this->get(route('home'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('books a newly registered service and shows it in the daily agenda', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'UTC'));
    Livewire::test('pages::manage-services')
        ->set('name', 'Corte')->set('duration_minutes', '40')->set('price', '50.00')
        ->call('save')->assertHasNoErrors();
    $service = Service::firstOrFail();

    Livewire::test('pages::book-appointment')->assertSee('Corte')->assertSee('40 min')->assertSee('50,00')
        ->set('service_id', (string) $service->id)->set('date', '2026-10-23')
        ->assertSet('availableTimes', fn (array $times): bool => in_array('09:40', $times, true) && ! in_array('09:20', $times, true))
        ->set('time', '09:40')->set('customer_name', 'Ana Silva')->set('customer_phone', '11912345678')
        ->call('save')->assertHasNoErrors()->assertSee('Agendamento confirmado!');

    $this->assertDatabaseHas('bookings', [
        'service_id' => $service->id, 'customer_name' => 'Ana Silva',
        'start_at' => '2026-10-23 12:40:00', 'end_at' => '2026-10-23 13:20:00',
    ]);
    Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')
        ->assertSee('Corte')->assertSee('Ana Silva')->assertSee('09:40')->assertSee('10:20');
});

test('rejects service page requests and livewire actions outside demo environments', function () {
    $component = Livewire::test('pages::manage-services')
        ->set('name', 'Corte')->set('duration_minutes', '40')->set('price', '50.00');
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/servicos')->assertNotFound();
    $component->call('save')->assertNotFound();
    $this->assertDatabaseCount('services', 0);
});
