<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Ko\'rinish sozlamalari')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Ko\'rinish sozlamalari') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Ko\'rinish')" :subheading="__('Hisobingiz uchun ko\'rinish sozlamalarini yangilang')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Yorug\'') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Qorong\'u') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('Tizim') }}</flux:radio>
        </flux:radio.group>
    </x-pages::settings.layout>
</section>
