<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NfcController;
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
    Route::get('api/locations/tree', [LocationController::class, 'tree'])->name('locations.tree');

    Route::resource('tags', TagController::class);
    Route::get('api/tags/search', [TagController::class, 'search'])->name('tags.search');

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
