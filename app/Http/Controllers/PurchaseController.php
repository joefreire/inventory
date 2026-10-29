<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportPurchasesRequest;
use App\Http\Requests\JoinPurchaseItemRequest;
use App\Models\Item;
use App\Models\PurchaseItem;
use App\Purchases\PurchaseImporter;
use App\Purchases\PurchaseLinker;
use App\Queries\PurchaseItemFilter;
use Illuminate\Http\JsonResponse;
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

    /**
     * Parts matching a name, for the "Juntar a uma peça que já tenho" sheet.
     */
    public function parts(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $like = '%'.addcslashes($q, '\%_').'%';

        $data = Item::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', $like))
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'quantity'])
            ->map(fn (Item $item) => ['id' => $item->id, 'name' => $item->name, 'quantity' => $item->quantity]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function join(JoinPurchaseItemRequest $request, PurchaseItem $purchaseItem, PurchaseLinker $linker): RedirectResponse
    {
        $item = $request->item();
        $quantity = $linker->join($purchaseItem, $item, $request->addQuantity());

        return redirect()->route('items.show', $item)->with('success', $request->addQuantity()
            ? "Juntado a {$item->name}: agora são {$quantity} unidades."
            : "Juntado a {$item->name}.");
    }

    public function dismiss(PurchaseItem $purchaseItem, PurchaseLinker $linker): RedirectResponse
    {
        $linker->dismiss($purchaseItem);

        return back()->with('success', 'Fica de fora da lista de pendentes.');
    }

    public function undismiss(PurchaseItem $purchaseItem, PurchaseLinker $linker): RedirectResponse
    {
        $linker->undismiss($purchaseItem);

        return back()->with('success', 'Voltou para os pendentes.');
    }
}
