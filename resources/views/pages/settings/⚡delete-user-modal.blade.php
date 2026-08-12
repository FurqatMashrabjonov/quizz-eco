<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Hisobingizni o\'chirmoqchimisiz?') }}</flux:heading>

            <flux:subheading>
                {{ __('Hisobingiz o\'chirilgandan so\'ng, unga tegishli barcha ma\'lumotlar butunlay o\'chib ketadi. Tasdiqlash uchun parolingizni kiriting.') }}
            </flux:subheading>
        </div>

        <flux:input wire:model="password" :label="__('Parol')" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Bekor qilish') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Hisobni o\'chirish') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
