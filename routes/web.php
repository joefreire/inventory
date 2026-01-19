<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

// Home redireciona para itens
Route::redirect('/', '/items');

// Items
Route::resource('items', ItemController::class);
Route::post('items/analyze', [ItemController::class, 'analyze'])->name('items.analyze');
Route::patch('items/{item}/move', [ItemController::class, 'move'])->name('items.move');

// Locations
Route::resource('locations', LocationController::class);
Route::get('api/locations/tree', [LocationController::class, 'tree'])->name('locations.tree');

// Tags
Route::resource('tags', TagController::class);
Route::get('api/tags/search', [TagController::class, 'search'])->name('tags.search');
