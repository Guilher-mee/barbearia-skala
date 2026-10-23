<?php

namespace App;

use App\Models\Booking;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PDOException;

class BookingSchedule
{
    /** @return list<string> */
    public function availableSlots(int|string $serviceId, string $date): array
    {
        return array_column(array_filter(
            $this->slotsForDate($serviceId, $date),
            fn (array $slot): bool => $slot['status'] === 'available',
        ), 'time');
    }

    /** @return list<array{time: string, end_time: string, status: 'available'|'booked'|'past'}> */
    public function slotsForDate(int|string $serviceId, string $date): array
    {
        $selection = $this->validateSelection($serviceId, $date);
        $service = $this->service($selection['service_id']);
        [$opening, $closing] = $this->businessHours($selection['date']);
        $now = CarbonImmutable::now(config('booking.timezone'));
        $bookings = Booking::where('start_at', '<', $closing->utc())
            ->where('end_at', '>', $opening->utc())
            ->get(['start_at', 'end_at']);
        $slots = [];

        for ($start = $opening; $start->addMinutes($service->duration_minutes)->lte($closing); $start = $start->addMinutes($service->duration_minutes)) {
            $end = $start->addMinutes($service->duration_minutes);
            $hasOverlap = $bookings->contains(fn (Booking $booking): bool => $booking->start_at !== null
                && $booking->end_at !== null
                && $booking->start_at->lt($end)
                && $booking->end_at->gt($start));

            $slots[] = [
                'time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'status' => $hasOverlap ? 'booked' : ($start->lte($now) ? 'past' : 'available'),
            ];
        }

        return $slots;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Booking
    {
        foreach (['customer_name', 'customer_phone'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $validated = Validator::make($data, [
            ...$this->selectionRules(),
            'time' => ['bail', 'required', 'date_format:H:i'],
            'customer_name' => ['bail', 'required', 'string', 'max:255'],
            'customer_phone' => [
                'bail', 'required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]+$/D',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $digits = preg_replace('/\D/', '', $value);

                    if (strlen($digits) < 10 || strlen($digits) > 15) {
                        $fail('Informe um telefone com 10 a 15 dígitos.');
                    }
                },
            ],
        ], $this->messages())->validate();

        try {
            return DB::transaction(function () use ($validated): Booking {
                $service = $this->service((int) $validated['service_id']);
                [$opening, $closing] = $this->businessHours($validated['date']);
                $start = $opening->setTimeFromTimeString($validated['time']);
                $end = $start->addMinutes($service->duration_minutes);

                if ($start->lt($opening) || $end->gt($closing) || ! $start->isSameDay($end)) {
                    throw ValidationException::withMessages(['time' => 'O atendimento deve ocorrer entre 09:00 e 18:00.']);
                }

                if ($start->lte(CarbonImmutable::now(config('booking.timezone')))) {
                    throw ValidationException::withMessages(['time' => 'Escolha um horário futuro.']);
                }

                if ((int) $opening->diffInMinutes($start) % $service->duration_minutes !== 0) {
                    throw ValidationException::withMessages(['time' => 'Escolha um horário da grade deste serviço.']);
                }

                $hasOverlap = Booking::where('start_at', '<', $end->utc())
                    ->where('end_at', '>', $start->utc())
                    ->exists();

                if ($hasOverlap) {
                    throw ValidationException::withMessages(['time' => 'Este horário acabou de ser ocupado. Escolha outro horário.']);
                }

                return Booking::create([
                    'service_id' => $service->id,
                    'customer_name' => $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'],
                    'start_at' => $start->utc(),
                    'end_at' => $end->utc(),
                ]);
            });
        } catch (PDOException $exception) {
            if (in_array((int) ($exception->errorInfo[1] ?? 0), [5, 6], true)) {
                throw ValidationException::withMessages(['time' => 'A agenda está ocupada no momento. Tente novamente.']);
            }

            throw $exception;
        }
    }

    /** @return array{service_id: int, date: string} */
    private function validateSelection(int|string $serviceId, string $date): array
    {
        $validated = Validator::make(['service_id' => $serviceId, 'date' => $date], $this->selectionRules(), $this->messages())->validate();

        return ['service_id' => (int) $validated['service_id'], 'date' => $validated['date']];
    }

    /** @return array{service_id: list<string>, date: list<string>} */
    private function selectionRules(): array
    {
        return [
            'service_id' => ['bail', 'required', 'integer', 'min:1'],
            'date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::now(config('booking.timezone'))->toDateString()],
        ];
    }

    private function service(int $serviceId): Service
    {
        $service = Service::whereKey($serviceId)->first();

        if ($service === null) {
            throw ValidationException::withMessages(['service_id' => 'Selecione um serviço disponível.']);
        }

        if ($service->duration_minutes <= 0) {
            throw ValidationException::withMessages(['service_id' => 'Este serviço está com a duração inválida.']);
        }

        return $service;
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function businessHours(string $date): array
    {
        $day = CarbonImmutable::parse($date, config('booking.timezone'))->startOfDay();

        return [
            $day->setTimeFromTimeString(config('booking.opens_at')),
            $day->setTimeFromTimeString(config('booking.closes_at')),
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'service_id.required' => 'Selecione um serviço.',
            'service_id.integer' => 'Selecione um serviço válido.',
            'service_id.min' => 'Selecione um serviço válido.',
            'date.required' => 'Selecione uma data.',
            'date.date_format' => 'Informe uma data válida.',
            'date.after_or_equal' => 'Escolha hoje ou uma data futura.',
            'time.required' => 'Selecione um horário.',
            'time.date_format' => 'Informe um horário válido.',
            'customer_name.required' => 'Informe seu nome.',
            'customer_name.string' => 'Informe um nome válido.',
            'customer_name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'customer_phone.required' => 'Informe seu telefone.',
            'customer_phone.string' => 'Informe um telefone válido.',
            'customer_phone.max' => 'O telefone deve ter no máximo 30 caracteres.',
            'customer_phone.regex' => 'Informe um telefone válido.',
        ];
    }
}
