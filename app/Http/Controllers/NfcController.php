<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tags NFC das caixas.
 *
 * A tag guarda só um link /n/{codigo}. Encostar o celular (Android ou iPhone)
 * abre a página da caixa. No Android, o app também le a tag pelo navegador
 * (Web NFC) para mover um item para a caixa encostada.
 */
class NfcController extends Controller
{
    public function __construct(
        private readonly ItemService $itemService
    ) {}

    /**
     * Abre a caixa da tag.
     */
    public function open(string $code): RedirectResponse
    {
        $location = Location::where('nfc_code', $code)->firstOrFail();

        return redirect()->route('locations.show', $location);
    }

    /**
     * Move o item para a caixa cuja tag foi lida (link ou codigo).
     */
    public function moveItem(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate(['tag' => 'required|string|max:500']);

        $code = self::codeFromTag($validated['tag']);
        $location = $code ? Location::where('nfc_code', $code)->first() : null;

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Essa tag não e de nenhuma caixa do inventário.',
            ], 404);
        }

        $this->itemService->move($item, $location->id);

        return response()->json([
            'success' => true,
            'message' => "Item guardado em {$location->path}.",
            'location' => ['id' => $location->id, 'path' => $location->path],
        ]);
    }

    /**
     * Extrai o codigo de ".../n/abc123" ou do proprio codigo.
     */
    public static function codeFromTag(string $tag): ?string
    {
        $tag = trim($tag);
        if (preg_match('#/n/([a-z0-9]{4,16})/?(?:[?\#].*)?$#i', $tag, $m)) {
            return strtolower($m[1]);
        }

        return preg_match('/^[a-z0-9]{4,16}$/i', $tag) ? strtolower($tag) : null;
    }
}
