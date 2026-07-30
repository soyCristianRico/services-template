{{-- Baseline 404. Extend it per site: add the nav shortcuts that matter there. --}}
@extends('layouts.public')

@section('content')
    <section class="bg-background">
        <div class="mx-auto flex max-w-2xl flex-col items-center px-6 py-24 text-center lg:py-32">
            <span class="font-display text-7xl font-extrabold tracking-tight text-brand sm:text-8xl">404</span>

            <flux:heading level="1" class="mt-6">Esta página no existe</flux:heading>

            <flux:text class="mt-4 text-lg text-muted-foreground">
                La página que buscas no está disponible o ha cambiado de dirección.
            </flux:text>

            <div class="mt-8">
                <flux:button href="{{ url('/') }}" variant="primary">Volver al inicio</flux:button>
            </div>
        </div>
    </section>
@endsection
