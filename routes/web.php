<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NfcController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

// Home redireciona para itens
Route::redirect('/', '/items');

// Items (rotas fixas antes do resource, senao 'ask' vira {item})
Route::get('items/ask', [ItemController::class, 'ask'])->name('items.ask');
Route::post('items/analyze', [ItemController::class, 'analyze'])->name('items.analyze');
Route::resource('items', ItemController::class);
Route::patch('items/{item}/move', [ItemController::class, 'move'])->name('items.move');

// Locations
Route::resource('locations', LocationController::class);
Route::get('api/locations/tree', [LocationController::class, 'tree'])->name('locations.tree');

// Tags
Route::resource('tags', TagController::class);
Route::get('api/tags/search', [TagController::class, 'search'])->name('tags.search');

// NFC: a tag da caixa guarda o link /n/{codigo}
Route::get('n/{code}', [NfcController::class, 'open'])->name('nfc.open');
Route::post('items/{item}/nfc-move', [NfcController::class, 'moveItem'])->name('items.nfc-move');
