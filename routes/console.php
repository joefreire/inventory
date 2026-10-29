<?php

use App\Jobs\EnrichPurchaseItem;
use App\Jobs\ProposePurchaseLinks;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Purchases\PurchaseImporter;
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

// Importa pedidos de compras (JSON gerado pela skill de compras) via SSH.
Artisan::command('compras:importar {file}', function (string $file) {
    $json = is_file($file) ? file_get_contents($file) : false;
    $payload = $json === false ? null : json_decode($json, true);
    if (! is_array($payload)) {
        $this->error('JSON invalido ou arquivo nao encontrado.');

        return 1;
    }

    $result = app(PurchaseImporter::class)->import($payload);
    foreach ($result->errors as $error) {
        $this->error($error);
    }
    $this->info("Pedidos novos: {$result->ordersCreated}, atualizados: {$result->ordersUpdated}, itens novos: {$result->itemsCreated}");

    return $result->errors === [] ? 0 : 1;
})->purpose('Importa pedidos de compras de um arquivo JSON');

// Lista pedidos ja importados (a skill le so o que falta).
Artisan::command('compras:pedidos {source}', function (string $source) {
    $orders = PurchaseOrder::where('source', $source)->orderBy('id')->get()
        ->map(fn (PurchaseOrder $o) => ['number' => $o->number, 'status' => $o->status->value])
        ->all();
    $this->line((string) json_encode($orders, JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Lista os pedidos ja importados de uma loja');

// Enfileira a IA nos itens de compra ainda nao enriquecidos (--falhas: so os que deram erro).
Artisan::command('compras:enriquecer {--falhas}', function () {
    $items = PurchaseItem::whereNull('enriched_at')
        ->when($this->option('falhas'), fn ($q) => $q->whereNotNull('enrichment_error'))
        ->pluck('id');
    foreach ($items as $id) {
        EnrichPurchaseItem::dispatch($id);
    }
    $this->info("Itens na fila: {$items->count()}");

    return 0;
})->purpose('Enfileira o enriquecimento por IA dos itens de compra pendentes');

// Enfileira a IA para propor de qual peca do estoque e cada compra antiga.
Artisan::command('compras:propor', function () {
    ProposePurchaseLinks::dispatch();
    $this->info('Busca de propostas na fila.');

    return 0;
})->purpose('Enfileira a ligacao por IA das compras antigas as pecas');
