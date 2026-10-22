<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Agendamento da barbearia' }}</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-100 text-stone-900">
    <header class="border-b border-stone-200 bg-white">
        <nav aria-label="Navegação principal" class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-4 px-4 py-5">
            <a href="{{ route('home') }}" class="text-lg font-bold">Barbearia</a>
            <div class="flex gap-5 text-sm font-medium">
                <a href="{{ route('home') }}" class="hover:underline">Agendar</a>
                @if (app()->environment(['local', 'testing']) && Route::has('agenda'))
                    <a href="{{ route('agenda') }}" class="hover:underline">Agenda do barbeiro</a>
                @endif
            </div>
        </nav>
    </header>
    <main class="mx-auto max-w-4xl px-4 py-10">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
