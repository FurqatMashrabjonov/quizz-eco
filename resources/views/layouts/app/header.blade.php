<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <a href="{{ route('dashboard') }}" class="text-base font-semibold text-zinc-900 dark:text-white" wire:navigate>
                {{ config('app.name') }}
            </a>

            <flux:spacer />

            <div
                x-data="{
                    scale: parseInt(localStorage.getItem('fontScale')) || 100,
                    apply() {
                        const el = document.getElementById('main-content')
                        if (el) el.style.zoom = this.scale / 100
                        localStorage.setItem('fontScale', this.scale)
                    },
                }"
                x-init="apply()"
                class="flex items-center gap-2"
            >
                <flux:icon.minus variant="mini" class="text-zinc-400" />
                <input
                    type="range"
                    min="85"
                    max="130"
                    step="5"
                    x-model.number="scale"
                    x-on:input="apply()"
                    class="h-1.5 w-20 cursor-pointer appearance-none rounded-full bg-zinc-200 accent-(--color-accent) dark:bg-zinc-700 sm:w-24"
                    :aria-label="__('Matn hajmi')"
                />
                <flux:icon.plus variant="mini" class="text-zinc-400" />
            </div>

            <flux:button
                x-data
                x-on:click="$flux.dark = ! $flux.dark"
                variant="subtle"
                square
                :aria-label="__('Mavzuni almashtirish')"
            >
                <flux:icon.moon x-show="$flux.dark" variant="mini" />
                <flux:icon.sun x-show="! $flux.dark" variant="mini" />
            </flux:button>

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar
                            :name="auth()->user()->name"
                            :initials="auth()->user()->initials()"
                        />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->username }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Chiqish') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
