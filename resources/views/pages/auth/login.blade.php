<x-layouts::auth :title="__('Kirish')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Hisobingizga kiring')" :description="__('Davom etish uchun login va parolingizni kiriting')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />


        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Username -->
            <flux:input
                name="username"
                :label="__('Login')"
                :value="old('username')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="login"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Parol')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Parol')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Parolni unutdingizmi?') }}
                    </flux:link>
                @endif
            </div>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Kirish') }}
                </flux:button>
            </div>
        </form>

    </div>
</x-layouts::auth>
