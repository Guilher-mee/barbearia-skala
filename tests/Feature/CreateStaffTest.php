<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('provisions a staff account with normalized email and hashed password', function () {
    $this->artisan('app:create-staff')
        ->expectsQuestion('Nome', '  Ana Silva  ')->expectsQuestion('E-mail', ' BARBEIRO@example.test ')
        ->expectsQuestion('Senha', 'SenhaDeTeste!42')->expectsQuestion('Confirme a senha', 'SenhaDeTeste!42')
        ->expectsOutput('Funcionário criado com sucesso.')->assertSuccessful();

    $user = User::sole();
    expect($user->name)->toBe('Ana Silva');
    expect($user->email)->toBe('barbeiro@example.test');
    expect($user->is_staff)->toBeTrue();
    expect(Hash::check('SenhaDeTeste!42', $user->password))->toBeTrue();
});

test('refuses an existing email without changing the account', function () {
    $user = User::factory()->create(['email' => 'barbeiro@example.test']);

    $this->artisan('app:create-staff')->expectsQuestion('Nome', 'Ana')
        ->expectsQuestion('E-mail', ' BARBEIRO@example.test ')
        ->expectsOutput('Este e-mail já está cadastrado.')->assertFailed();

    $this->assertDatabaseCount('users', 1);
    expect($user->fresh()->is_staff)->toBeFalse();
});

test('rejects weak or unconfirmed passwords', function (string $password, string $confirmation) {
    $this->artisan('app:create-staff')->expectsQuestion('Nome', 'Ana')->expectsQuestion('E-mail', 'ana@example.test')
        ->expectsQuestion('Senha', $password)->expectsQuestion('Confirme a senha', $confirmation)
        ->expectsOutput('A senha deve ter pelo menos 12 caracteres, maiúsculas, minúsculas, números e símbolos, e a confirmação deve ser igual.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
})->with([['short', 'short'], ['SenhaDeTeste!42', 'different']]);

test('refuses unattended provisioning instead of using a default password', function () {
    $this->artisan('app:create-staff', ['--no-interaction' => true])
        ->expectsOutput('Execute este comando interativamente para informar a senha de forma oculta.')->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

test('validates staff identity before asking for a password', function () {
    $this->artisan('app:create-staff')->expectsQuestion('Nome', 'Ana')->expectsQuestion('E-mail', 'invalid')
        ->expectsOutput('Informe um e-mail válido.')->assertFailed();

    $this->assertDatabaseCount('users', 0);
});
