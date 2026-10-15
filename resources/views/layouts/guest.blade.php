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
    <body class="h-full bg-white font-sans text-ink antialiased">
        <div class="flex min-h-screen">
            <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
                <div class="mx-auto w-full max-w-sm">
                    <div class="flex items-center gap-3">
                        <x-application-logo taille="lg" />
                        <div class="min-w-0">
                            <p class="truncate text-lg font-semibold text-ink">{{ $parametres->nom_application }}</p>
                            <p class="text-sm text-tertiaire">Gestion du personnel</p>
                        </div>
                    </div>

                    <div class="mt-10">
                        {{ $slot }}
                    </div>
                </div>
            </div>

            <div class="panneau-marque relative hidden flex-1 lg:flex lg:flex-col lg:justify-end" aria-hidden="true">
                <div class="p-12 text-white xl:p-16">
                    <x-application-logo taille="xl" class="drop-shadow-lg" />
                    <p class="mt-8 max-w-md text-3xl font-semibold leading-tight tracking-tight">{{ $parametres->titreConnexion() }}</p>
                    <p class="mt-4 max-w-md text-base text-white/80">{{ $parametres->texteConnexion() }}</p>
                </div>
            </div>
        </div>
    </body>
</html>
