<?php

use App\Models\User;
use Livewire\Livewire;

test('keeps booking public and internal links private', function () {
    $this->get(route('home'))->assertOk()->assertSee('Área do barbeiro')
        ->assertDontSee(route('agenda'))->assertDontSee(route('services'));
    $this->get(route('login'))->assertOk()->assertSee('Entrar');
    $this->get(route('agenda'))->assertRedirect(route('login'));
    $this->get(route('services'))->assertRedirect(route('login'));
});

test('rejects authenticated users without staff permission including direct components', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('agenda'))->assertForbidden();
    $this->get(route('services'))->assertForbidden();
    Livewire::test('pages::daily-agenda')->assertForbidden();
    Livewire::test('pages::manage-services')->assertForbidden();
});

test('allows staff to use internal pages in production', function () {
    $this->actingAs(User::factory()->staff()->create());
    app()->detectEnvironment(fn (): string => 'production');

    $this->get(route('agenda'))->assertOk()->assertSee('Agenda do dia')->assertSee('Sair');
    $this->get(route('services'))->assertOk()->assertSee('Cadastrar serviço');
});

test('authenticates staff with normalized email and renews the session', function () {
    $staff = User::factory()->staff()->create(['email' => 'barbeiro@example.test']);
    $this->get(route('login'));
    $previousSession = session()->getId();

    Livewire::test('pages::login')->set('email', '  BARBEIRO@example.test  ')->set('password', 'password')
        ->call('login')->assertHasNoErrors()->assertRedirect(route('agenda'))->assertSet('password', '');

    $this->assertAuthenticatedAs($staff);
    expect(session()->getId())->not->toBe($previousSession);
});

test('returns staff to the internal page requested before login', function () {
    $staff = User::factory()->staff()->create();
    $this->get(route('services'))->assertRedirect(route('login'));

    Livewire::test('pages::login')->set('email', $staff->email)->set('password', 'password')
        ->call('login')->assertRedirect(route('services'));
});

test('uses a generic login failure for wrong passwords and unauthorized accounts', function (bool $staff, string $password) {
    $user = User::factory()->create(['is_staff' => $staff]);

    Livewire::test('pages::login')->set('email', $user->email)->set('password', $password)
        ->call('login')->assertHasErrors(['email'])->assertSee('E-mail ou senha inválidos.')->assertSet('password', '');

    $this->assertGuest();
})->with([[true, 'incorrect'], [false, 'password']]);

test('validates required login fields', function () {
    Livewire::test('pages::login')->call('login')->assertHasErrors(['email', 'password'])
        ->assertSee('Informe seu e-mail.')->assertSee('Informe sua senha.');
});

test('limits failed logins by normalized email and IP and releases the lock after a minute', function () {
    $staff = User::factory()->staff()->create(['email' => 'barbeiro@example.test']);

    foreach (range(1, 5) as $attempt) {
        Livewire::test('pages::login')->set('email', $staff->email)->set('password', 'incorrect')
            ->call('login')->assertSee('E-mail ou senha inválidos.');
    }

    Livewire::test('pages::login')->set('email', ' BARBEIRO@example.test ')->set('password', 'password')
        ->call('login')->assertSee('Muitas tentativas.')->assertHasErrors(['email']);
    $this->assertGuest();

    $this->travel(61)->seconds();
    Livewire::test('pages::login')->set('email', $staff->email)->set('password', 'password')
        ->call('login')->assertRedirect(route('agenda'));
    $this->assertAuthenticatedAs($staff);
});

test('does not share a login lock with another account', function () {
    $staff = User::factory()->staff()->create();

    foreach (range(1, 5) as $attempt) {
        Livewire::test('pages::login')->set('email', 'outro@example.test')->set('password', 'incorrect')->call('login');
    }

    Livewire::test('pages::login')->set('email', $staff->email)->set('password', 'password')
        ->call('login')->assertRedirect(route('agenda'));
});

test('ends the session through POST and denies later access and component updates', function () {
    $this->actingAs(User::factory()->staff()->create());
    $component = Livewire::test('pages::manage-services')->set('name', 'Corte')->set('duration_minutes', '30')->set('price', '50');
    session()->put('private-marker', 'private');
    $previousToken = session()->token();

    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
    expect(session()->has('private-marker'))->toBeFalse();
    expect(session()->token())->not->toBe($previousToken);
    $this->get(route('agenda'))->assertRedirect(route('login'));
    $component->call('save')->assertForbidden();
    $this->assertDatabaseCount('services', 0);
    $this->get('/sair')->assertMethodNotAllowed();
});

test('denies direct components to guests', function () {
    Livewire::test('pages::daily-agenda')->assertForbidden();
    Livewire::test('pages::manage-services')->assertForbidden();
});

test('does not allow staff permission through mass assignment', function () {
    $user = User::factory()->create();
    $user->fill(['is_staff' => true])->save();

    expect($user->fresh()->is_staff)->toBeFalse();
});
