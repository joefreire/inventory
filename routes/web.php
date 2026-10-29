<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NfcController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\TagController;
use App\Locations\NfcCode;
use Illuminate\Support\Facades\Route;

// Everything requires login (the app is public at inventario.runningwithjoe.com).
// An NFC tag opened without a session goes to login and back to the box.
Route::middleware('auth')->group(function () {
    Route::redirect('/', '/items');

    // fixed paths before the resource, or "ask" would be read as {item}
    Route::middleware('throttle:ai')->group(function () {
        Route::get('items/ask', [ItemController::class, 'ask'])->name('items.ask');
        Route::post('items/analyze', [ItemController::class, 'analyze'])->name('items.analyze');
    });
    Route::resource('items', ItemController::class);
    Route::patch('items/{item}/move', [ItemController::class, 'move'])->name('items.move');
    Route::patch('items/{item}/quantity', [ItemController::class, 'quantity'])->name('items.quantity');
    Route::post('items/{item}/nfc-move', [NfcController::class, 'moveItem'])->name('items.nfc-move');

    Route::resource('locations', LocationController::class);

    Route::get('compras', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('compras/importar', [PurchaseController::class, 'import'])->name('purchases.import');
    Route::post('compras/importar', [PurchaseController::class, 'storeImport'])->name('purchases.import.store');
    Route::get('compras/pecas', [PurchaseController::class, 'parts'])->name('purchases.parts');
    Route::post('compras/propostas/gerar', [PurchaseController::class, 'generateProposals'])->middleware('throttle:ai')->name('purchases.proposals.generate');
    Route::get('compras/propostas', [PurchaseController::class, 'proposals'])->name('purchases.proposals');
    Route::post('compras/propostas', [PurchaseController::class, 'reviewProposals'])->name('purchases.proposals.review');
    Route::post('compras/itens/{purchaseItem}/juntar', [PurchaseController::class, 'join'])->name('purchases.items.join');
    Route::patch('compras/itens/{purchaseItem}/dispensar', [PurchaseController::class, 'dismiss'])->name('purchases.items.dismiss');
    Route::patch('compras/itens/{purchaseItem}/retomar', [PurchaseController::class, 'undismiss'])->name('purchases.items.undismiss');

    Route::resource('tags', TagController::class);

    // part photos from the private disk
    Route::get('media/{media}/{conversion}', [MediaController::class, 'show'])
        ->whereNumber('media')
        ->whereIn('conversion', MediaController::CONVERSIONS)
        ->name('media.show');

    // NFC: the box tag holds the link /n/{code}
    Route::get('n', [NfcController::class, 'resolve'])->name('nfc.resolve');
    Route::get('n/{code}', [NfcController::class, 'open'])->where('code', NfcCode::PATTERN)->name('nfc.open');
});

require __DIR__.'/auth.php';
