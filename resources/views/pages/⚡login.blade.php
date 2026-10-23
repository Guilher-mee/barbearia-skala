<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Área do barbeiro')]
class extends Component
{
    public string $email = '';

    public string $password = '';

    public function login(): RedirectResponse
    {
        $this->email = Str::lower(trim($this->email));
        $validated = $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter no máximo 255 caracteres.',
            'password.required' => 'Informe sua senha.',
        ]);
        $key = 'staff-login:'.hash('sha256', $this->email.'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->reset('password');

            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Aguarde um minuto e tente novamente.']);
        }

        if (! Auth::attempt([...$validated, 'is_staff' => true])) {
            RateLimiter::hit($key, 60);
            $this->reset('password');

            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        RateLimiter::clear($key);
        Session::regenerate();
        $this->reset('password');

        return redirect()->intended(route('agenda'));
    }
};
?>

<div class="mx-auto max-w-md">
    <h1 class="text-3xl font-bold">Área do barbeiro</h1>
    <p class="mt-2 text-stone-600">Entre para consultar a agenda e cadastrar serviços.</p>
    <form wire:submit="login" class="mt-6 flex flex-col gap-5 rounded-xl border border-stone-200 bg-white p-6">
        <div>
            <label for="login-email" class="mb-2 block font-medium">E-mail</label>
            <input id="login-email" type="email" wire:model="email" autocomplete="username" maxlength="255" class="w-full rounded-lg border border-stone-300 p-3" required>
            @error('email') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="login-password" class="mb-2 block font-medium">Senha</label>
            <input id="login-password" type="password" wire:model="password" autocomplete="current-password" class="w-full rounded-lg border border-stone-300 p-3" required>
            @error('password') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-stone-900 px-5 py-3 font-semibold text-white disabled:opacity-50">Entrar</button>
    </form>
</div>
