<x-layouts::app.header :title="$title ?? null">
    <flux:main container id="main-content">
        {{ $slot }}
    </flux:main>
</x-layouts::app.header>
