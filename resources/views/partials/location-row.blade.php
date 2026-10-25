<a href="{{ route('locations.show', $location) }}" class="row-item" style="padding-left: {{ 14 + $depth * 22 }}px">
    @if($depth > 0)<i class="bi bi-arrow-return-right small-muted"></i>@endif
    <span class="grow">
        <span><span class="tape">{{ $location->name }}</span></span>
        @if($location->description)<span class="sub">{{ $location->description }}</span>@endif
    </span>
    <span class="qty">{{ $location->total_item_count }}<small>pc</small></span>
</a>
@foreach($location->allChildren ?? $location->children as $child)
    @include('partials.location-row', ['location' => $child, 'depth' => $depth + 1])
@endforeach
