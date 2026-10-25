<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('items.index', absolute: false), navigate: false);
    }
}; ?>

<div>
    <form wire:submit="login" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input wire:model="form.email" id="email" type="email" name="email" class="form-control @error('form.email') is-invalid @enderror"
                   required autofocus autocomplete="username" inputmode="email" autocapitalize="none">
            @error('form.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Senha</label>
            <input wire:model="form.password" id="password" type="password" name="password" class="form-control @error('form.password') is-invalid @enderror"
                   required autocomplete="current-password">
            @error('form.password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-check mb-4">
            <input wire:model="form.remember" id="remember" type="checkbox" class="form-check-input" name="remember">
            <label for="remember" class="form-check-label">Continuar conectado neste celular</label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100" wire:loading.attr="disabled">
            <span wire:loading.remove>Entrar</span>
            <span wire:loading><span class="spinner-border spinner-border-sm"></span> Entrando...</span>
        </button>
    </form>
</div>
