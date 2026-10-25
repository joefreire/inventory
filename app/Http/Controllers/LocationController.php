<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Item;
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
     *
     * @param LocationService $locationService
     */
    public function __construct(
        private readonly LocationService $locationService
    ) {}

    /**
     * Display a list of root locations with their children.
     *
     * @return View
     */
    public function index(): View
    {
        $locations = Location::whereNull('parent_id')
            ->with('allChildren')
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return view('locations.index', compact('locations'));
    }

    /**
     * Show the form for creating a new location.
     *
     * @param Request $request
     * @return View
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
     *
     * @param StoreLocationRequest $request
     * @return RedirectResponse
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
     *
     * @param Location $location
     * @return View
     */
    public function show(Location $location): View
    {
        $location->load(['items.media', 'items.tags', 'children.items']);

        $items = $location->items()
            ->with(['media', 'tags'])
            ->latest()
            ->paginate(24);

        // Para "Guardar item aqui" (iPhone: a tag abre esta página)
        $otherItems = Item::where(fn ($q) => $q->whereNull('location_id')->orWhere('location_id', '!=', $location->id))
            ->with('location')
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'location_id']);

        return view('locations.show', compact('location', 'items', 'otherItems'));
    }

    /**
     * Show the form for editing the specified location.
     *
     * @param Location $location
     * @return View
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
     *
     * @param UpdateLocationRequest $request
     * @param Location $location
     * @return RedirectResponse
     */
    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return redirect()
            ->route('locations.index')
            ->with('success', "Alterações salvas.");
    }

    /**
     * Remove the specified location.
     *
     * @param Location $location
     * @return RedirectResponse
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
     *
     * @return JsonResponse
     */
    public function tree(): JsonResponse
    {
        return response()->json($this->locationService->getFullTree());
    }
}
