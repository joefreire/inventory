<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportPurchasesRequest;
use App\Models\PurchaseItem;
use App\Purchases\PurchaseImporter;
use App\Queries\PurchaseItemFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Purchases area: list of bought lines and import of the store JSON.
 */
class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $filter = PurchaseItemFilter::fromRequest($request);

        $lines = $filter->apply(PurchaseItem::query())
            ->with(['order', 'media'])
            ->paginate(24)
            ->withQueryString();

        return view('purchases.index', [
            'lines' => $lines,
            'filter' => $filter,
            'pendingCount' => PurchaseItem::query()->pending()->count(),
        ]);
    }

    public function import(): View
    {
        return view('purchases.import');
    }

    public function storeImport(ImportPurchasesRequest $request, PurchaseImporter $importer): RedirectResponse
    {
        $result = $importer->import($request->payload());

        if ($result->errors !== []) {
            return back()->withErrors(['file' => implode(' ', array_slice($result->errors, 0, 3))]);
        }

        $orders = fn (int $n, string $word) => $n.' '.$word.($n === 1 ? '' : 's');

        return redirect()->route('purchases.import')->with('success', sprintf(
            '%s %s, %s %s, %s %s.',
            $orders($result->ordersCreated, 'pedido'),
            $result->ordersCreated === 1 ? 'novo' : 'novos',
            $result->ordersUpdated,
            $result->ordersUpdated === 1 ? 'atualizado' : 'atualizados',
            $result->itemsCreated,
            $result->itemsCreated === 1 ? 'item' : 'itens',
        ));
    }
}
