<?php

namespace App\Http\Controllers;

use App\Ai\Exceptions\AiException;
use App\Ai\ItemAdvisor;
use App\Ai\Search\NaturalSearch;
use App\Http\Requests\AdjustQuantityRequest;
use App\Http\Requests\AnalyzeItemRequest;
use App\Http\Requests\MoveItemRequest;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseItem;
use App\Models\Tag;
use App\Purchases\PurchaseLinker;
use App\Queries\ItemFilter;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Parts: list, form, AI analysis and natural language search, quick actions
 * (quantity, move). Rules live in the requests, ItemService and App\Ai.
 * AI failures (AiException) are rendered in bootstrap/app.php.
 */
class ItemController extends Controller
{
    private const PER_PAGE = 24;

    public function __construct(private readonly ItemService $items, private readonly PurchaseLinker $purchases) {}

    public function index(Request $request): View
    {
        $filter = ItemFilter::fromRequest($request);

        return view('items.index', [
            'items' => $filter->apply(Item::query()->with(['location:id,name,path', 'media']))
                ->latest()->paginate(self::PER_PAGE)->withQueryString(),
            'filter' => $filter,
            'locationsFlat' => Location::orderBy('path')->get(['id', 'name', 'path']),
            'withoutLocationCount' => Item::whereNull('location_id')->count(),
            'tags' => Tag::withCount('items')->orderBy('name')->get(),
            'categories' => Item::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $purchase = $request->integer('compra') ? PurchaseItem::with(['order', 'media'])->find($request->integer('compra')) : null;

        if ($purchase?->item_id !== null) {
            return redirect()->route('items.show', $purchase->item_id)->with('error', 'Essa compra já está ligada a uma peça.');
        }

        return view('items.create', [
            'locationId' => $request->integer('location_id') ?: null,
            'purchase' => $purchase,
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $item = $this->items->create($request->itemData(), $request->tagNames(), $request->photoFiles(StoreItemRequest::MAX_PHOTOS));

        if (($purchase = $request->purchaseItem()) !== null) {
            $this->purchases->linkNew($purchase, $item);
        }

        // "Salvar e próxima": back to the form in the same box (registering in sequence)
        if ($request->boolean('proxima')) {
            return redirect()
                ->route('items.create', array_filter(['location_id' => $item->location_id]))
                ->with('success', "Peça salva: {$item->name}. Pode tirar a próxima foto.");
        }

        return redirect()->route('items.show', $item)->with('success', 'Peça salva.');
    }

    public function show(Item $item): View
    {
        return view('items.show', ['item' => $item->load(['location', 'tags', 'media', 'purchaseItems.order'])]);
    }

    public function edit(Item $item): View
    {
        return view('items.edit', ['item' => $item->load(['location', 'tags', 'media'])]);
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $this->items->update(
            $item,
            $request->itemData(),
            $request->tagNames(),
            $request->photoFiles(StoreItemRequest::MAX_PHOTOS),
            $request->mediaToRemove(),
        );

        return redirect()->route('items.show', $item)->with('success', 'Alterações salvas.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $this->items->delete($item);

        return redirect()->route('items.index')->with('success', 'Peça excluída.');
    }

    /**
     * Suggestion from the photos and/or what was typed: name, description, specs,
     * category, tags, box and whether the part already exists.
     */
    public function analyze(AnalyzeItemRequest $request, ItemAdvisor $advisor): JsonResponse
    {
        $advice = $advisor->advise(
            $request->photoPaths(),
            (string) $request->validated('name'),
            (string) $request->validated('description'),
        );

        return response()->json(['success' => true, 'data' => $advice]);
    }

    /**
     * Natural language search ("tenho algo pra medir temperatura?").
     */
    public function ask(Request $request, NaturalSearch $search): View
    {
        $question = trim(mb_substr((string) $request->query('q', ''), 0, 300));

        $results = collect();
        $error = null;
        if ($question !== '') {
            try {
                $results = $search->search($question);
            } catch (AiException $e) {
                report($e);
                $error = 'A IA não respondeu: '.$e->getMessage();
            }
        }

        return view('items.ask', compact('question', 'results', 'error'));
    }

    public function quantity(AdjustQuantityRequest $request, Item $item): JsonResponse
    {
        $quantity = $this->items->adjustQuantity($item, $request->delta());

        return response()->json([
            'success' => true,
            'quantity' => $quantity,
            'message' => $quantity.' '.($quantity === 1 ? 'unidade' : 'unidades'),
        ]);
    }

    public function move(MoveItemRequest $request, Item $item): JsonResponse
    {
        $this->items->move($item, $request->locationId());

        return response()->json(['success' => true, 'message' => 'Peça guardada.']);
    }
}
