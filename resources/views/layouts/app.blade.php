<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-anefip.svg') }}">

        @php($parametres = \App\Models\ParametresApplication::courants())
        <title>{{ $parametres->nom_application }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @include('partials.variables-theme', ['parametres' => $parametres])
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-fond font-sans text-ink antialiased">
        <div class="min-h-screen lg:flex" x-data="{ menuOuvert: false }" @keydown.escape.window="menuOuvert = false">
            <div x-show="menuOuvert" x-transition.opacity class="fixed inset-0 z-30 lg:hidden" style="display: none; background-color: rgba(16, 24, 40, 0.5);" @click="menuOuvert = false" aria-hidden="true"></div>

            <aside class="fixed inset-y-0 start-0 z-40 w-72 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 lg:translate-x-0" :class="menuOuvert ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
                <livewire:layout.navigation />
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-bordure bg-white px-4 lg:hidden">
                    <button type="button" @click="menuOuvert = true" class="bouton-icone -ms-2" aria-label="Ouvrir la navigation">
                        <x-icone nom="bars-3" class="h-6 w-6" />
                    </button>
                    <x-application-logo taille="sm" />
                    <span class="truncate text-base font-semibold">{{ $parametres->nom_application }}</span>
                </header>

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mx-auto max-w-7xl">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
