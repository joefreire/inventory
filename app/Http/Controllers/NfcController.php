<?php

namespace App\Http\Controllers;

use App\Http\Requests\NfcScanRequest;
use App\Locations\NfcCode;
use App\Models\Item;
use App\Models\Location;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Box NFC tags. The tag holds only the link /n/{code}: touching the phone opens
 * the box (Android and iPhone). On Android the app also reads tags in the
 * browser (Web NFC) and sends the raw text here; NfcCode reads it, so the code
 * format lives in one place.
 */
class NfcController extends Controller
{
    public function __construct(private readonly ItemService $items) {}

    /** Link written on the tag. */
    public function open(string $code): RedirectResponse
    {
        return redirect()->route('locations.show', $this->box($code));
    }

    /** "Ler tag" tab: whatever the phone read (link or code) -> the box. */
    public function resolve(NfcScanRequest $request): RedirectResponse
    {
        $code = NfcCode::fromScanned($request->scanned());
        if ($code === null || ! Location::where('nfc_code', $code)->exists()) {
            return redirect()->route('items.index')->with('error', 'Essa tag não é de nenhuma caixa do inventário.');
        }

        return redirect()->route('nfc.open', $code);
    }

    /** "Guardar: encostar na caixa": stores the item in the box whose tag was read. */
    public function moveItem(NfcScanRequest $request, Item $item): JsonResponse
    {
        $code = NfcCode::fromScanned($request->scanned());
        $location = $code === null ? null : Location::where('nfc_code', $code)->first();

        if (! $location) {
            return response()->json(['success' => false, 'message' => 'Essa tag não é de nenhuma caixa do inventário.'], 404);
        }

        $this->items->move($item, $location->id);

        return response()->json([
            'success' => true,
            'message' => "Item guardado em {$location->path}.",
            'location' => ['id' => $location->id, 'path' => $location->path],
        ]);
    }

    private function box(string $code): Location
    {
        return Location::where('nfc_code', strtolower($code))->firstOrFail();
    }
}
