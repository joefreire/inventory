<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use App\Models\Location;
use App\Models\Tag;
use App\Services\ItemService;
use App\Services\OpenAIVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Controller for managing inventory items.
 *
 * Handles CRUD operations, filtering, search, photo analysis,
 * and item movement between locations.
 */
class ItemController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param ItemService $itemService
     */
    public function __construct(
        private readonly ItemService $itemService
    ) {}

    /**
     * Display a paginated list of items with filters.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = Item::with(['location', 'tags', 'media']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('location_id')) {
            $query->inLocation($request->location_id);
        }

        if ($request->filled('tag_id')) {
            $query->withTag($request->tag_id);
        }

        if ($request->filled('category')) {
            $query->inCategory($request->category);
        }

        $items = $query->latest()->paginate(24)->withQueryString();

        $locations = Location::whereNull('parent_id')->with('allChildren')->get();
        $tags = Tag::withCount('items')->orderBy('name')->get();
        $categories = Item::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return view('items.index', compact('items', 'locations', 'tags', 'categories'));
    }

    /**
     * Show the form for creating a new item.
     *
     * @return View
     */
    public function create(): View
    {
        $locations = Location::orderBy('path')->get();
        $tags = Tag::orderBy('name')->get();

        return view('items.create', compact('locations', 'tags'));
    }

    /**
     * Store a newly created item.
     *
     * @param StoreItemRequest $request
     * @return RedirectResponse
     */
    public function store(StoreItemRequest $request): RedirectResponse
    {
        $item = $this->itemService->create(
            $request->validatedWithProcessing(),
            $request->getTagNames(),
            $request->file('photo')
        );

        return redirect()
            ->route('items.show', $item)
            ->with('success', 'Item criado com sucesso!');
    }

    /**
     * Display the specified item.
     *
     * @param Item $item
     * @return View
     */
    public function show(Item $item): View
    {
        $item->load(['location', 'tags', 'media']);

        return view('items.show', compact('item'));
    }

    /**
     * Show the form for editing the specified item.
     *
     * @param Item $item
     * @return View
     */
    public function edit(Item $item): View
    {
        $item->load(['location', 'tags', 'media']);
        $locations = Location::orderBy('path')->get();
        $tags = Tag::orderBy('name')->get();

        return view('items.edit', compact('item', 'locations', 'tags'));
    }

    /**
     * Update the specified item.
     *
     * @param UpdateItemRequest $request
     * @param Item $item
     * @return RedirectResponse
     */
    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $this->itemService->update(
            $item,
            $request->validated(),
            $request->getTagNames(),
            $request->file('photo'),
            $request->shouldRemovePhoto()
        );

        return redirect()
            ->route('items.show', $item)
            ->with('success', 'Item atualizado com sucesso!');
    }

    /**
     * Remove the specified item.
     *
     * @param Item $item
     * @return RedirectResponse
     */
    public function destroy(Item $item): RedirectResponse
    {
        $this->itemService->delete($item);

        return redirect()
            ->route('items.index')
            ->with('success', 'Item excluido com sucesso!');
    }

    /**
     * Analyze an uploaded photo using AI vision service.
     *
     * @param Request $request
     * @param OpenAIVisionService $visionService
     * @return JsonResponse
     */
    public function analyze(Request $request, OpenAIVisionService $visionService): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:10240',
        ]);

        try {
            $result = $visionService->analyzeUploadedFile($request->file('photo'));

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error analyzing image', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao analisar imagem: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Move an item to a different location.
     *
     * @param Request $request
     * @param Item $item
     * @return JsonResponse
     */
    public function move(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $this->itemService->move($item, $validated['location_id'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Item movido com sucesso!',
        ]);
    }
}
