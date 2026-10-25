<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller for managing item tags.
 *
 * Handles CRUD operations for tags, including JSON responses
 * for AJAX-based tag creation and search functionality.
 */
class TagController extends Controller
{
    /**
     * Display a paginated list of all tags.
     *
     * @return View
     */
    public function index(): View
    {
        $tags = Tag::withCount('items')
            ->orderBy('name')
            ->paginate(50);

        return view('tags.index', compact('tags'));
    }

    /**
     * Show the form for creating a new tag.
     *
     * @return View
     */
    public function create(): View
    {
        return view('tags.create');
    }

    /**
     * Store a newly created tag.
     *
     * Returns JSON response for AJAX requests, redirect otherwise.
     *
     * @param StoreTagRequest $request
     * @return JsonResponse|RedirectResponse
     */
    public function store(StoreTagRequest $request): JsonResponse|RedirectResponse
    {
        $tag = Tag::create($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'tag' => $tag,
            ]);
        }

        return redirect()
            ->route('tags.index')
            ->with('success', "Tag {$tag->name} criada.");
    }

    /**
     * Display the specified tag and its items.
     *
     * @param Tag $tag
     * @return View
     */
    public function show(Tag $tag): View
    {
        $items = $tag->items()
            ->with(['location', 'media', 'tags'])
            ->latest()
            ->paginate(24);

        return view('tags.show', compact('tag', 'items'));
    }

    /**
     * Show the form for editing the specified tag.
     *
     * @param Tag $tag
     * @return View
     */
    public function edit(Tag $tag): View
    {
        return view('tags.edit', compact('tag'));
    }

    /**
     * Update the specified tag.
     *
     * @param UpdateTagRequest $request
     * @param Tag $tag
     * @return RedirectResponse
     */
    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return redirect()
            ->route('tags.index')
            ->with('success', "Alterações salvas.");
    }

    /**
     * Remove the specified tag.
     *
     * @param Tag $tag
     * @return RedirectResponse
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $name = $tag->name;
        $tag->delete();

        return redirect()
            ->route('tags.index')
            ->with('success', "Tag {$name} excluída.");
    }

    /**
     * Search tags by name for autocomplete functionality.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        $tags = Tag::where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'color']);

        return response()->json($tags);
    }
}
