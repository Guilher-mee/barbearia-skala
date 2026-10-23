<?php

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Agenda do dia')]
class extends Component
{
    public string $date = '';

    public function boot(): void
    {
        Gate::authorize('access-staff-area');
    }

    public function mount(): void
    {
        $this->date = CarbonImmutable::now(config('booking.timezone'))->toDateString();
    }

    public function updatedDate(): void
    {
        $this->refreshAgenda();
    }

    public function refreshAgenda(): void
    {
        $this->resetValidation();
        $this->validate(['date' => ['required', 'date_format:Y-m-d']], [
            'date.required' => 'Selecione uma data.',
            'date.date_format' => 'Informe uma data válida.',
        ]);
        unset($this->bookings);
    }

    /** @return Collection<int, Booking> */
    #[Computed]
    public function bookings(): Collection
    {
        if (Validator::make(['date' => $this->date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            return new Collection;
        }

        $day = CarbonImmutable::parse($this->date, config('booking.timezone'))->startOfDay();

        return Booking::with('service')
            ->where('start_at', '>=', $day->utc())
            ->where('start_at', '<', $day->addDay()->utc())
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();
    }
};
?>

<div>
    <h1 class="text-3xl font-bold">Agenda do dia</h1>
    <form wire:submit="refreshAgenda" class="mt-6 flex flex-wrap items-end gap-4">
        <div>
            <label for="agenda-date" class="mb-2 block font-medium">Data da agenda</label>
            <input id="agenda-date" type="date" wire:model.live="date" class="rounded-lg border border-stone-300 bg-white p-3" required>
            @error('date') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-stone-900 px-5 py-3 font-semibold text-white hover:bg-stone-700 disabled:opacity-50">Atualizar agenda</button>
        <span wire:loading role="status" class="text-sm text-stone-600">Atualizando...</span>
    </form>

    @if (! $errors->has('date'))
        <div class="mt-6 flex flex-col gap-3">
            @forelse ($this->bookings as $booking)
                <article wire:key="booking-{{ $booking->id }}" class="flex flex-col gap-3 rounded-xl border border-stone-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-lg font-semibold">{{ $booking->start_at->timezone(config('booking.timezone'))->format('H:i') }} – {{ $booking->end_at->timezone(config('booking.timezone'))->format('H:i') }}</p>
                        <p class="text-stone-600">{{ $booking->service->name }}</p>
                    </div>
                    <div class="sm:text-right">
                        <p class="font-medium">{{ $booking->customer_name }}</p>
                        <p class="text-stone-600">{{ $booking->customer_phone }}</p>
                    </div>
                </article>
            @empty
                <p class="rounded-lg bg-white p-6">Nenhum agendamento para este dia.</p>
            @endforelse
        </div>
    @endif
</div>
