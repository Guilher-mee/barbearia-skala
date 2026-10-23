<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-staff')]
#[Description('Cria um funcionário com acesso à área do barbeiro')]
class CreateStaff extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Execute este comando interativamente para informar a senha de forma oculta.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nome'));
        $email = Str::lower(trim((string) $this->ask('E-mail')));
        $validator = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ], [
            'name.required' => 'Informe o nome.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter no máximo 255 caracteres.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->error('Este e-mail já está cadastrado.');

            return self::FAILURE;
        }

        $password = $this->secret('Senha', false);
        $confirmation = $this->secret('Confirme a senha', false);
        $validator = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], [
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            $this->error('A senha deve ter pelo menos 12 caracteres, maiúsculas, minúsculas, números e símbolos, e a confirmação deve ser igual.');

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->is_staff = true;

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            $this->error('Este e-mail já está cadastrado.');

            return self::FAILURE;
        }

        $this->info('Funcionário criado com sucesso.');

        return self::SUCCESS;
    }
}
