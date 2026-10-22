<?php

use App\Models\Service;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/** @param Closure(string): void $test */
function withConcurrentBookingDatabase(Closure $test): void
{
    $database = tempnam(sys_get_temp_dir(), 'booking-');
    $originalDatabase = config('database.connections.sqlite.database');
    config(['database.connections.sqlite.database' => $database]);
    DB::purge('sqlite');

    try {
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        $test($database);
    } finally {
        DB::purge('sqlite');
        config(['database.connections.sqlite.database' => $originalDatabase]);
        unlink($database);
    }
}

/** @return array{Process, InputStream} */
function startBookingWorker(string $database, int $serviceId, string $mode, int $timeout = 5000): array
{
    $input = new InputStream;
    $process = new Process([
        PHP_BINARY, base_path('tests/Fixtures/booking-worker.php'), $database, (string) $serviceId, $mode, (string) $timeout,
    ], base_path(), ['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'stderr']);
    $process->setInput($input);
    $process->setTimeout(20);
    $process->start();
    awaitBookingWorker($process, "READY\n");

    return [$process, $input];
}

function awaitBookingWorker(Process $process, string $signal): void
{
    $output = '';
    $received = $process->waitUntil(function (string $type, string $chunk) use (&$output, $signal): bool {
        if ($type === Process::OUT) {
            $output .= $chunk;
        }

        return str_contains($output, $signal);
    });

    if (! $received) {
        throw new RuntimeException('Falha no processo: '.$process->getOutput().$process->getErrorOutput());
    }
}

/** @return array{status: string, id?: int, errors?: array<string, list<string>>} */
function bookingWorkerResult(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException('Falha no processo: '.$process->getOutput().$process->getErrorOutput());
    }

    $lines = explode("\n", trim($process->getOutput()));

    return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
}

test('commits only one of two competing bookings even when the calendar is initially empty', function () {
    withConcurrentBookingDatabase(function (string $database): void {
        $service = Service::factory()->create(['duration_minutes' => 20]);
        $otherService = Service::factory()->create(['duration_minutes' => 40]);
        [$first, $firstInput] = startBookingWorker($database, $service->id, 'hold');
        [$second, $secondInput] = startBookingWorker($database, $otherService->id, 'compete');

        try {
            $firstInput->write("RUN\n");
            awaitBookingWorker($first, "LOCKED\n");
            $secondInput->write("RUN\n");
            awaitBookingWorker($second, "ATTEMPT\n");
            $firstInput->write("RELEASE\n");
            $firstInput->close();
            $secondInput->close();

            expect(bookingWorkerResult($first)['status'])->toBe('created');
            $rejected = bookingWorkerResult($second);

            expect($rejected['status'])->toBe('rejected');
            expect($rejected['errors']['time'])->toContain('Este horário acabou de ser ocupado. Escolha outro horário.');
            $this->assertDatabaseCount('bookings', 1);
            $this->assertDatabaseHas('bookings', ['service_id' => $service->id]);
        } finally {
            $first->stop();
            $second->stop();
        }
    });
});

test('returns a temporary validation error when the SQLite write lock expires without inserting a booking', function () {
    withConcurrentBookingDatabase(function (string $database): void {
        $service = Service::factory()->create(['duration_minutes' => 20]);
        [$holder, $holderInput] = startBookingWorker($database, $service->id, 'hold');
        [$contender, $contenderInput] = startBookingWorker($database, $service->id, 'timeout', 50);

        try {
            $holderInput->write("RUN\n");
            awaitBookingWorker($holder, "LOCKED\n");
            $contenderInput->write("RUN\n");
            $contenderInput->close();
            $result = bookingWorkerResult($contender);

            expect($result['status'])->toBe('rejected');
            expect($result['errors']['time'])->toContain('A agenda está ocupada no momento. Tente novamente.');
            $this->assertDatabaseCount('bookings', 0);

            $holderInput->write("RELEASE\n");
            $holderInput->close();

            expect(bookingWorkerResult($holder)['status'])->toBe('created');
            $this->assertDatabaseCount('bookings', 1);
        } finally {
            $holder->stop();
            $contender->stop();
        }
    });
});
