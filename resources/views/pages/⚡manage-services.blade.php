<?php

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Serviços da barbearia')]
class extends Component
{
    public string $name = '';

    public string $duration_minutes = '';

    public string $price = '';

    #[Locked]
    public bool $saved = false;

    public function boot(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
    }

    public function updated(): void
    {
        $this->saved = false;
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function services(): Collection
    {
        return Service::orderBy('name')->orderBy('id')->get();
    }

    public function save(): void
    {
        $this->saved = false;
        $this->name = trim($this->name);
        $validated = $this->validate([
            'name' => ['bail', 'required', 'string', 'max:255'],
            'duration_minutes' => ['bail', 'required', 'integer', 'min:1', 'max:540'],
            'price' => ['bail', 'required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
        ], [
            'name.required' => 'Informe o nome do serviço.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'duration_minutes.required' => 'Informe a duração do serviço.',
            'duration_minutes.integer' => 'A duração deve ser um número inteiro de minutos.',
            'duration_minutes.min' => 'A duração deve ser de pelo menos 1 minuto.',
            'duration_minutes.max' => 'A duração deve ser de no máximo 540 minutos.',
            'price.required' => 'Informe o preço do serviço.',
            'price.numeric' => 'Informe um preço válido.',
            'price.min' => 'O preço não pode ser negativo.',
            'price.max' => 'O preço deve ser de no máximo R$ 99.999.999,99.',
            'price.decimal' => 'O preço deve ter no máximo duas casas decimais.',
        ]);

        Service::create($validated);

        $this->reset('name', 'duration_minutes', 'price');
        $this->resetValidation();
        $this->saved = true;
        unset($this->services);
    }
};
?>

<div>
    <h1 class="text-3xl font-bold">Serviços da barbearia</h1>
    <div class="mt-6 grid items-start gap-6 md:grid-cols-2">
        <form wire:submit="save" class="flex flex-col gap-5 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Cadastrar serviço</h2>
            @if ($saved)
                <p role="status" class="rounded-lg border border-green-200 bg-green-50 p-3 text-green-900">Serviço cadastrado com sucesso.</p>
            @endif
            <div>
                <label for="service-name" class="mb-2 block font-medium">Nome</label>
                <input id="service-name" type="text" wire:model="name" maxlength="255" class="w-full rounded-lg border border-stone-300 p-3" required>
                @error('name') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="service-duration" class="mb-2 block font-medium">Duração (minutos)</label>
                <input id="service-duration" type="number" wire:model="duration_minutes" min="1" max="540" step="1" class="w-full rounded-lg border border-stone-300 p-3" required>
                @error('duration_minutes') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="service-price" class="mb-2 block font-medium">Preço (R$)</label>
                <input id="service-price" type="number" inputmode="decimal" wire:model="price" min="0" max="99999999.99" step="0.01" class="w-full rounded-lg border border-stone-300 p-3" required>
                @error('price') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-stone-900 px-5 py-3 font-semibold text-white hover:bg-stone-700 disabled:cursor-wait disabled:opacity-50">
                <span wire:loading.remove wire:target="save">Cadastrar serviço</span>
                <span wire:loading wire:target="save">Cadastrando...</span>
            </button>
        </form>
        <section aria-labelledby="services-heading" class="flex flex-col gap-3">
            <h2 id="services-heading" class="text-xl font-semibold">Serviços cadastrados</h2>
            @forelse ($this->services as $service)
                <article wire:key="service-{{ $service->id }}" class="rounded-xl border border-stone-200 bg-white p-5">
                    <h3 class="font-semibold">{{ $service->name }}</h3>
                    <p class="mt-1 text-stone-600">{{ $service->duration_minutes }} min · R$ {{ number_format((float) $service->price, 2, ',', '.') }}</p>
                </article>
            @empty
                <p class="rounded-lg bg-white p-6">Nenhum serviço cadastrado.</p>
            @endforelse
        </section>
    </div>
</div>
