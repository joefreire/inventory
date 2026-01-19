@foreach($locations as $location)
    <a href="{{ route('items.index', array_merge(request()->except('location_id'), ['location_id' => $location->id])) }}"
       class="list-group-item list-group-item-action depth-{{ min($depth, 4) }} {{ request('location_id') == $location->id ? 'active' : '' }}">
        @if($location->children->count() > 0)
            <i class="bi bi-folder2 me-1"></i>
        @else
            <i class="bi bi-folder me-1"></i>
        @endif
        {{ $location->name }}
        <span class="badge bg-secondary float-end">{{ $location->total_item_count }}</span>
    </a>
    @if($location->children->count() > 0)
        @include('partials.location-tree', ['locations' => $location->children, 'depth' => $depth + 1])
    @endif
@endforeach
