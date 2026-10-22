<?php

use App\BookingSchedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $argv[1],
    'database.connections.sqlite.busy_timeout' => (int) $argv[4],
]);
DB::purge('sqlite');

if ($argv[3] === 'hold') {
    Event::listen(TransactionBeginning::class, function (): void {
        echo "LOCKED\n";
        fflush(STDOUT);

        if (trim((string) fgets(STDIN)) !== 'RELEASE') {
            throw new RuntimeException('O teste não liberou a transação.');
        }
    });
}

echo "READY\n";
fflush(STDOUT);

if (trim((string) fgets(STDIN)) !== 'RUN') {
    throw new RuntimeException('O teste não iniciou a reserva.');
}

echo "ATTEMPT\n";
fflush(STDOUT);

try {
    $booking = $application->make(BookingSchedule::class)->create([
        'service_id' => (int) $argv[2],
        'date' => '2099-01-02',
        'time' => '09:00',
        'customer_name' => 'Cliente '.$argv[3],
        'customer_phone' => '11912345678',
    ]);
    echo json_encode(['status' => 'created', 'id' => $booking->id], JSON_THROW_ON_ERROR)."\n";
} catch (ValidationException $exception) {
    echo json_encode(['status' => 'rejected', 'errors' => $exception->errors()], JSON_THROW_ON_ERROR)."\n";
}

fflush(STDOUT);
