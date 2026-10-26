<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cria ou troca a senha do usuario do inventario (nao ha cadastro pela tela).
Artisan::command('inventario:usuario {email} {--name=Dono}', function (string $email) {
    $senha = $this->secret('Senha (min. 12 caracteres)');
    if (mb_strlen((string) $senha) < 12) {
        $this->error('Senha curta demais.');

        return 1;
    }

    $user = User::updateOrCreate(
        ['email' => $email],
        ['name' => $this->option('name'), 'password' => Hash::make($senha)]
    );
    $this->info(($user->wasRecentlyCreated ? 'Criado' : 'Senha trocada').": {$user->email}");

    return 0;
})->purpose('Cria o usuario do inventario ou troca a senha');
