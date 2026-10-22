<?php

use App\BookingSchedule;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
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
    }

    public function updated(string $property): void
    {
        $this->confirmation = null;

        if (in_array($property, ['service_id', 'date'], true)) {
            $this->time = '';
            $this->resetValidation();
            unset($this->availableTimes);
        }
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
        if ($this->service_id === '' || $this->date === '') {
            return [];
        }

        try {
            return $this->schedule->availableSlots($this->service_id, $this->date);
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

            unset($this->availableTimes);

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
        unset($this->availableTimes);
    }
};
?>

<div class="mx-auto max-w-xl">
    <h1 class="text-3xl font-bold">Agende seu atendimento</h1>
    <p class="mt-2 text-stone-600">Escolha um serviço e um horário. Atendemos das {{ config('booking.opens_at') }} às {{ config('booking.closes_at') }}.</p>

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
                <select id="service" wire:model.live="service_id" class="w-full rounded-lg border border-stone-300 p-3" required>
                    <option value="">Selecione um serviço</option>
                    @foreach ($this->services as $service)
                        <option wire:key="service-{{ $service->id }}" value="{{ $service->id }}">{{ $service->name }} · {{ $service->duration_minutes }} min · R$ {{ number_format((float) $service->price, 2, ',', '.') }}</option>
                    @endforeach
                </select>
                @error('service_id') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="date" class="mb-2 block font-medium">Data</label>
                <input id="date" type="date" wire:model.live="date" min="{{ \Carbon\CarbonImmutable::now(config('booking.timezone'))->toDateString() }}" class="w-full rounded-lg border border-stone-300 p-3" required>
                @error('date') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="time" class="mb-2 block font-medium">Horário disponível</label>
                <select id="time" wire:model="time" @disabled($this->availableTimes === []) class="w-full rounded-lg border border-stone-300 p-3 disabled:bg-stone-100" required>
                    <option value="">Selecione um horário</option>
                    @foreach ($this->availableTimes as $slot)
                        <option wire:key="slot-{{ $service_id }}-{{ $date }}-{{ $slot }}" value="{{ $slot }}">{{ $slot }}</option>
                    @endforeach
                </select>
                <p wire:loading wire:target="service_id,date" role="status" class="mt-1 text-sm text-stone-600">Consultando horários...</p>
                @if ($service_id !== '' && $this->availableTimes === [])
                    <p class="mt-2 text-sm text-stone-600">Nenhum horário disponível para este serviço e data.</p>
                @endif
                @error('time') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
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
