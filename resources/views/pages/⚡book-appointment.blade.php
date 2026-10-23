<?php

use App\BookingSchedule;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Agende seu atendimento')]
class extends Component
{
    public string $service_id = '';

    public string $date = '';

    #[Locked]
    public string $calendarMonth = '';

    public string $time = '';

    public string $customer_name = '';

    public string $customer_phone = '';

    /** @var array{service: string, date: string, start: string, end: string}|null */
    #[Locked]
    public ?array $confirmation = null;

    protected BookingSchedule $schedule;

    public function boot(BookingSchedule $schedule): void
    {
        $this->schedule = $schedule;
    }

    public function mount(): void
    {
        $this->date = CarbonImmutable::now(config('booking.timezone'))->toDateString();
        $this->calendarMonth = substr($this->date, 0, 7);
    }

    public function updated(string $property): void
    {
        $this->confirmation = null;

        if (in_array($property, ['service_id', 'date'], true)) {
            if ($property === 'date' && $this->hasValidDate()) {
                $this->calendarMonth = substr($this->date, 0, 7);
            }

            $this->clearSelection();
        }
    }

    public function selectDate(string $date): void
    {
        Validator::make(['date' => $date], [
            'date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::now(config('booking.timezone'))->toDateString()],
        ], [
            'date.required' => 'Selecione uma data.',
            'date.date_format' => 'Informe uma data válida.',
            'date.after_or_equal' => 'Escolha hoje ou uma data futura.',
        ])->validate();

        $this->date = $date;
        $this->calendarMonth = substr($date, 0, 7);
        $this->clearSelection();
    }

    public function selectTomorrow(): void
    {
        $this->selectDate(CarbonImmutable::now(config('booking.timezone'))->addDay()->toDateString());
    }

    public function nextMonth(): void
    {
        $this->changeMonth(1);
    }

    public function previousMonth(): void
    {
        if ($this->canGoPreviousMonth) {
            $this->changeMonth(-1);
        }
    }

    public function selectTime(string $time): void
    {
        $this->time = '';
        $this->confirmation = null;
        $this->resetValidation('time');
        unset($this->daySlots, $this->availableTimes);

        if (! in_array($time, $this->availableTimes, true)) {
            throw ValidationException::withMessages(['time' => 'Este horário não está disponível. Escolha um horário livre.']);
        }

        $this->time = $time;
    }

    #[Computed]
    public function monthLabel(): string
    {
        return CarbonImmutable::parse($this->calendarMonth.'-01', config('booking.timezone'))
            ->locale('pt_BR')->translatedFormat('F Y');
    }

    #[Computed]
    public function canGoPreviousMonth(): bool
    {
        return $this->calendarMonth > CarbonImmutable::now(config('booking.timezone'))->format('Y-m');
    }

    #[Computed]
    public function selectedDateLabel(): string
    {
        return $this->hasValidDate()
            ? CarbonImmutable::parse($this->date, config('booking.timezone'))->format('d/m/Y')
            : '';
    }

    /** @return list<array{date: string, label: string, day: int, in_month: bool, is_today: bool, is_past: bool}> */
    #[Computed]
    public function calendarDays(): array
    {
        $month = CarbonImmutable::parse($this->calendarMonth.'-01', config('booking.timezone'));
        $today = CarbonImmutable::now(config('booking.timezone'))->startOfDay();
        $end = $month->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);
        $days = [];

        for ($day = $month->startOfWeek(CarbonImmutable::SUNDAY); $day->lte($end); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('d/m/Y'),
                'day' => $day->day,
                'in_month' => $day->format('Y-m') === $this->calendarMonth,
                'is_today' => $day->isSameDay($today),
                'is_past' => $day->lt($today),
            ];
        }

        return $days;
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function services(): Collection
    {
        return Service::orderBy('name')->get();
    }

    /** @return list<string> */
    #[Computed]
    public function availableTimes(): array
    {
        return array_column(array_filter(
            $this->daySlots,
            fn (array $slot): bool => $slot['status'] === 'available',
        ), 'time');
    }

    /** @return list<array{time: string, end_time: string, status: 'available'|'booked'|'past'}> */
    #[Computed]
    public function daySlots(): array
    {
        if ($this->service_id === '' || $this->date === '') {
            return [];
        }

        try {
            return $this->schedule->slotsForDate($this->service_id, $this->date);
        } catch (ValidationException) {
            return [];
        }
    }

    public function save(): void
    {
        $this->confirmation = null;

        try {
            $booking = $this->schedule->create($this->only(['service_id', 'date', 'time', 'customer_name', 'customer_phone']));
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['time'])) {
                $this->time = '';
            }

            unset($this->daySlots, $this->availableTimes);

            throw $exception;
        }

        $timezone = config('booking.timezone');
        $this->confirmation = [
            'service' => $booking->service->name,
            'date' => $booking->start_at->timezone($timezone)->format('d/m/Y'),
            'start' => $booking->start_at->timezone($timezone)->format('H:i'),
            'end' => $booking->end_at->timezone($timezone)->format('H:i'),
        ];
        $this->reset('customer_name', 'customer_phone', 'time');
        $this->resetValidation();
        unset($this->daySlots, $this->availableTimes);
    }

    private function hasValidDate(): bool
    {
        return Validator::make(['date' => $this->date], ['date' => ['required', 'date_format:Y-m-d']])->passes();
    }

    private function changeMonth(int $months): void
    {
        $this->calendarMonth = CarbonImmutable::parse($this->calendarMonth.'-01', config('booking.timezone'))
            ->addMonthsNoOverflow($months)->format('Y-m');
        $this->date = '';
        $this->clearSelection();
    }

    private function clearSelection(): void
    {
        $this->time = '';
        $this->confirmation = null;
        $this->resetValidation();
        unset($this->daySlots, $this->availableTimes, $this->calendarDays, $this->monthLabel, $this->canGoPreviousMonth, $this->selectedDateLabel);
    }
};
?>

<div class="mx-auto max-w-xl">
    <h1 class="text-3xl font-bold">Agende seu atendimento</h1>
    <p class="mt-2 text-stone-600">Escolha o serviço, o dia e um horário livre. Atendemos das {{ config('booking.opens_at') }} às {{ config('booking.closes_at') }}.</p>

    @if ($confirmation)
        <div role="status" class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900">
            <p class="font-semibold">Agendamento confirmado!</p>
            <p>{{ $confirmation['service'] }} · {{ $confirmation['date'] }} · {{ $confirmation['start'] }} às {{ $confirmation['end'] }}</p>
        </div>
    @endif

    @if ($this->services->isEmpty())
        <p class="mt-6 rounded-lg bg-white p-6">Nenhum serviço disponível no momento.</p>
        @error('service_id') <p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
    @else
        <form wire:submit="save" class="mt-6 flex flex-col gap-5 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <div>
                <label for="service" class="mb-2 block font-medium">Serviço</label>
                <select id="service" wire:model.live="service_id" wire:loading.attr="disabled" class="w-full rounded-lg border border-stone-300 p-3" required>
                    <option value="">Selecione um serviço</option>
                    @foreach ($this->services as $service)
                        <option wire:key="service-{{ $service->id }}" value="{{ $service->id }}">{{ $service->name }} · {{ $service->duration_minutes }} min · R$ {{ number_format((float) $service->price, 2, ',', '.') }}</option>
                    @endforeach
                </select>
                @error('service_id') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <fieldset class="min-w-0">
                <legend class="mb-3 font-medium">Escolha a data</legend>
                <div class="rounded-lg border border-stone-200 p-2 sm:p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <button type="button" wire:click="previousMonth" wire:loading.attr="disabled" @disabled(! $this->canGoPreviousMonth) aria-label="Mês anterior" class="rounded-lg border border-stone-300 px-3 py-2 text-lg hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-30">‹</button>
                        <p aria-live="polite" class="font-semibold capitalize">{{ $this->monthLabel }}</p>
                        <button type="button" wire:click="nextMonth" wire:loading.attr="disabled" aria-label="Próximo mês" class="rounded-lg border border-stone-300 px-3 py-2 text-lg hover:bg-stone-100 disabled:opacity-30">›</button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center">
                        @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
                            <span wire:key="weekday-{{ $weekday }}" class="py-2 text-xs font-medium text-stone-500">{{ $weekday }}</span>
                        @endforeach
                        @foreach ($this->calendarDays as $day)
                            @if ($day['in_month'])
                                <button
                                    wire:key="day-{{ $day['date'] }}"
                                    type="button"
                                    wire:click="selectDate('{{ $day['date'] }}')"
                                    wire:loading.attr="disabled"
                                    @disabled($day['is_past'])
                                    aria-label="Selecionar {{ $day['label'] }}{{ $day['is_today'] ? ' (hoje)' : '' }}"
                                    aria-pressed="{{ $date === $day['date'] ? 'true' : 'false' }}"
                                    @if ($day['is_today']) aria-current="date" @endif
                                    @class([
                                        'flex min-h-12 flex-col items-center justify-center rounded-lg border p-1 text-sm disabled:cursor-not-allowed disabled:opacity-35 sm:min-h-14',
                                        'border-stone-900 bg-stone-900 font-bold text-white' => $date === $day['date'],
                                        'border-stone-500 bg-stone-50' => $day['is_today'] && $date !== $day['date'],
                                        'border-transparent hover:border-stone-300 hover:bg-stone-100' => ! $day['is_today'] && $date !== $day['date'],
                                    ])
                                >
                                    <span>{{ $day['day'] }}</span>
                                    @if ($day['is_today']) <span class="text-[10px]">Hoje</span> @endif
                                </button>
                            @else
                                <span wire:key="outside-{{ $day['date'] }}" aria-hidden="true"></span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @error('date') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </fieldset>
            <fieldset class="min-w-0">
                <legend class="mb-2 font-medium">Escolha um horário livre</legend>
                <p wire:loading role="status" class="mb-2 text-sm text-stone-600">Consultando horários...</p>
                @if ($service_id === '')
                    <p class="text-sm text-stone-600">Selecione um serviço para consultar os horários.</p>
                @elseif ($date === '')
                    <p class="text-sm text-stone-600">Selecione uma data.</p>
                @else
                    <p class="mb-3 text-sm text-stone-600">Data selecionada: {{ $this->selectedDateLabel }}</p>
                    @if ($this->availableTimes === [])
                        <p class="mb-2 text-sm text-stone-600">Nenhum horário disponível para este serviço e data.</p>
                        @if ($this->daySlots !== [] && collect($this->daySlots)->every(fn ($slot) => $slot['status'] === 'booked'))
                            <p role="status" class="mb-2 text-sm font-medium">Todos os horários deste dia estão reservados.</p>
                        @elseif ($date === \Carbon\CarbonImmutable::now(config('booking.timezone'))->toDateString())
                            <p role="status" class="mb-2 text-sm font-medium">Não há mais horários disponíveis hoje. Escolha outra data.</p>
                        @endif
                        @if ($date === \Carbon\CarbonImmutable::now(config('booking.timezone'))->toDateString())
                            <button type="button" wire:click="selectTomorrow" wire:loading.attr="disabled" class="mb-3 rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-100 disabled:opacity-50">Consultar amanhã</button>
                        @endif
                    @endif
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" role="group" aria-label="Horários de atendimento">
                        @foreach ($this->daySlots as $slot)
                            <button
                                wire:key="time-{{ $service_id }}-{{ $date }}-{{ $slot['time'] }}"
                                type="button"
                                wire:click="selectTime('{{ $slot['time'] }}')"
                                wire:loading.attr="disabled"
                                @disabled($slot['status'] !== 'available')
                                aria-pressed="{{ $time === $slot['time'] ? 'true' : 'false' }}"
                                aria-label="{{ $slot['time'] }} às {{ $slot['end_time'] }} — {{ ['available' => 'Livre', 'booked' => 'Reservado', 'past' => 'Já passou'][$slot['status']] }}"
                                @class([
                                    'flex flex-col items-center gap-1 rounded-lg border px-2 py-3 text-sm disabled:cursor-not-allowed',
                                    'border-stone-900 bg-stone-900 font-semibold text-white' => $time === $slot['time'] && $slot['status'] === 'available',
                                    'border-green-300 bg-green-50 text-green-900 hover:border-green-600' => $time !== $slot['time'] && $slot['status'] === 'available',
                                    'border-stone-200 bg-stone-100 text-stone-500' => $slot['status'] !== 'available',
                                ])
                            >
                                <span>{{ $slot['time'] }}–{{ $slot['end_time'] }}</span>
                                <span class="text-xs">{{ ['available' => 'Livre', 'booked' => 'Reservado', 'past' => 'Já passou'][$slot['status']] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
                @error('time') <p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </fieldset>
            <div>
                <label for="customer-name" class="mb-2 block font-medium">Seu nome</label>
                <input id="customer-name" type="text" wire:model="customer_name" autocomplete="name" maxlength="255" class="w-full rounded-lg border border-stone-300 p-3" required>
                @error('customer_name') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="customer-phone" class="mb-2 block font-medium">Telefone</label>
                <input id="customer-phone" type="tel" wire:model="customer_phone" autocomplete="tel" maxlength="30" placeholder="(11) 99999-9999" class="w-full rounded-lg border border-stone-300 p-3" required>
                @error('customer_phone') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" @disabled($this->availableTimes === []) class="rounded-lg bg-stone-900 px-5 py-3 font-semibold text-white hover:bg-stone-700 disabled:cursor-wait disabled:opacity-50">
                <span wire:loading.remove wire:target="save">Confirmar agendamento</span>
                <span wire:loading wire:target="save">Confirmando...</span>
            </button>
        </form>
    @endif
</div>
