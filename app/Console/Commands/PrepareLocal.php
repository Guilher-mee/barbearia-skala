<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('app:prepare-local')]
#[Description('Prepara a chave ausente e os dados iniciais de desenvolvimento local')]
class PrepareLocal extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (blank(config('app.key'))) {
            $this->call('key:generate', ['--no-interaction' => true]);

            if (blank(config()->get('app.key'))) {
                $this->error('Não foi possível gerar a chave da aplicação. Verifique o campo APP_KEY e a escrita no .env.');

                return self::FAILURE;
            }
        }

        if (! app()->environment(['local', 'testing'])) {
            $this->info('Dados de demonstração ignorados fora de local ou testing.');

            return self::SUCCESS;
        }

        try {
            $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true]);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Acesso local e serviços de exemplo preparados. Use a opção Área do barbeiro para entrar.');

        return self::SUCCESS;
    }
}
