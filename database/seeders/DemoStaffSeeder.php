<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class DemoStaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('A conta de demonstração só pode ser preparada em local ou testing.');
        }

        $email = Str::lower(trim((string) config('demo.email')));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user !== null) {
            if (! $user->is_staff) {
                throw new RuntimeException('O e-mail de demonstração já pertence a um usuário sem permissão de funcionário. Configure outro DEMO_STAFF_EMAIL.');
            }

            return;
        }

        $attributes = Validator::make([
            'name' => trim((string) config('demo.name')),
            'email' => $email,
            'password' => config('demo.password'),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();

        $user = new User($attributes);
        $user->is_staff = true;
        $user->save();
    }
}
