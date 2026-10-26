<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Item;
use App\Locations\LocationTree;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller for managing storage locations.
 *
 * Handles CRUD operations for the hierarchical location tree,
 * including parent-child relationships and location browsing.
 */
class LocationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly LocationService $locationService,
        private readonly LocationTree $tree,
    ) {}

    /**
     * Display a list of root locations with their children.
     */
    public function index(): View
    {
        return view('locations.index', ['locations' => $this->tree->roots()]);
    }

    /**
     * Show the form for creating a new location.
     */
    public function create(Request $request): View
    {
        $parentId = $request->get('parent_id');
        $parent = $parentId ? Location::find($parentId) : null;
        $locations = Location::orderBy('path')->get();

        return view('locations.create', compact('parent', 'locations'));
    }

    /**
     * Store a newly created location.
     */
    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $location = Location::create($request->validated());

        return redirect()
            ->route('locations.index')
            ->with('success', "Caixa {$location->name} criada.");
    }

    /**
     * Display the specified location and its items.
     */
    public function show(Location $location): View
    {
        $location->load(['parent', 'children' => fn ($q) => $q->withCount('items')->orderBy('name')]);

        $items = $location->items()
            ->with('media')
            ->latest()
            ->paginate(24);

        // Para "Guardar item aqui" (iPhone: a tag abre esta página)
        $otherItems = Item::where(fn ($q) => $q->whereNull('location_id')->orWhere('location_id', '!=', $location->id))
            ->with('location:id,name')
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'location_id']);

        return view('locations.show', compact('location', 'items', 'otherItems'));
    }

    /**
     * Show the form for editing the specified location.
     */
    public function edit(Location $location): View
    {
        $locations = Location::where('id', '!=', $location->id)
            ->orderBy('path')
            ->get();

        return view('locations.edit', compact('location', 'locations'));
    }

    /**
     * Update the specified location.
     */
    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return redirect()
            ->route('locations.index')
            ->with('success', 'Alterações salvas.');
    }

    /**
     * Remove the specified location.
     */
    public function destroy(Location $location): RedirectResponse
    {
        $name = $location->name;
        $location->delete();

        return redirect()
            ->route('locations.index')
            ->with('success', "Caixa {$name} excluída.");
    }

    /**
     * Get the location tree as JSON for API/AJAX requests.
     */
    public function tree(): JsonResponse
    {
        return response()->json($this->locationService->getFullTree());
    }
}
