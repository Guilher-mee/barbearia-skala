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
        unset($this->bookings, $this->summary);
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

    /** @return array{booking_count: int, unpriced_count: int, total_cents: int, total_formatted: string} */
    #[Computed]
    public function summary(): array
    {
        $totalCents = 0;
        $unpricedCount = 0;
        $bookings = $this->bookings;

        foreach ($bookings as $booking) {
            if ($booking->price_at_booking === null) {
                $unpricedCount++;

                continue;
            }

            [$whole, $fraction] = explode('.', $booking->price_at_booking);
            $totalCents += ((int) $whole * 100) + (int) $fraction;
        }

        return [
            'booking_count' => $bookings->count(),
            'unpriced_count' => $unpricedCount,
            'total_cents' => $totalCents,
            'total_formatted' => number_format(intdiv($totalCents, 100), 0, ',', '.').','.str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT),
        ];
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
        <section aria-labelledby="daily-summary-heading" class="mt-6 rounded-xl border border-stone-200 bg-white p-5">
            <h2 id="daily-summary-heading" class="text-xl font-semibold">Resumo do dia</h2>
            <dl class="mt-4 flex flex-wrap gap-8">
                <div>
                    <dt class="text-stone-600">Agendamentos do dia</dt>
                    <dd class="text-2xl font-bold">{{ $this->summary['booking_count'] }}</dd>
                </div>
                <div>
                    <dt class="text-stone-600">Valor agendado (previsto)</dt>
                    <dd class="text-2xl font-bold">R$ {{ $this->summary['total_formatted'] }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-sm text-stone-600">O valor previsto não representa pagamentos recebidos.</p>
            @if ($this->summary['unpriced_count'] > 0)
                <p role="status" class="mt-3 text-sm text-amber-800">Agendamentos sem valor registrado: {{ $this->summary['unpriced_count'] }}. Esses valores não estão incluídos no total.</p>
            @endif
        </section>
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
