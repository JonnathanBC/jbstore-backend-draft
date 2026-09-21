<?php

namespace App\Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Http\Requests\AddToCartRequest;
use App\Modules\Products\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    private const INSTANCE = 'shopping';

    public function index(Request $request): JsonResponse
    {
        $this->restore($request);
        return $this->cartResponse();
    }

    public function store(AddToCartRequest $request): JsonResponse
    {
        $product = Product::with('variants.features')->findOrFail($request->integer('product_id'));
        $selectedFeatures = $request->input('selected_features');

        // Buscar la variante que coincide
        $variant = $product->variants->filter(function($variant) use ($selectedFeatures) {
            return !array_diff($variant->features->pluck('id')->toArray(), $selectedFeatures);
        })->first();

        $this->restore($request);

        Cart::instance(self::INSTANCE)->add([
        'id'    => $product->id,
        'name'  => $product->name,
        'qty'   => $request->integer('quantity'),
        'price' => $product->price,
        'options' => $variant ? [
            'variant_id' => $variant->id,
            'image'      => $variant->image,
            'sku'        => $variant->sku,
            // Extrae [id => description] directo de la relación en memoria
            'features'   => $variant->features->pluck('description', 'id')->toArray(),
            ] : [],
        ]);

        $this->persist($request);

        return $this->cartResponse(201);
    }

    public function destroy(Request $request, string $rowId): JsonResponse
    {
        $this->restore($request);

        $cart = Cart::instance(self::INSTANCE);

        if (! $cart->content()->has($rowId)) {
            return response()->json(['message' => 'Item no encontrado'], 404);
        }

        $cart->remove($rowId);

        $this->persist($request);

        return $this->cartResponse();
    }

    /**
     * Carga el carrito guardado del usuario en la sesión del request.
     */
    private function restore(Request $request): void
    {
        $userId = $request->user()->id;
        $stored = DB::table(config('cart.database.table'))
            ->where('identifier', $userId)
            ->where('instance', self::INSTANCE)
            ->first();

        if (! $stored) {
            return;
        }

        $serialized = base64_decode($stored->content, true);
        $storedContent = $serialized === false ? false : @unserialize($serialized);

        if (! $storedContent instanceof \Illuminate\Support\Collection) {
            DB::table(config('cart.database.table'))
                ->where('identifier', $userId)
                ->where('instance', self::INSTANCE)
                ->delete();

            return;
        }

        $cart = Cart::instance(self::INSTANCE);
        $cart->destroy();

        foreach ($storedContent as $cartItem) {
            $cart->add($cartItem);
        }
    }

    /**
     * Guarda el carrito en DB (store borra el registro previo e inserta el nuevo).
     */
    private function persist(Request $request): void
    {
        $userId = $request->user()->id;
        $content = base64_encode(serialize(Cart::instance(self::INSTANCE)->content()));

        DB::table(config('cart.database.table'))->updateOrInsert(
            [
                'identifier' => $userId,
                'instance' => self::INSTANCE,
            ],
            [
                'content' => $content,
                'created_at' => now(),
            ],
        );
    }

    private function cartResponse(int $status = 200): JsonResponse
    {
        $cart = Cart::instance(self::INSTANCE);

        return response()->json([
            'items' => $cart->content()->values(),
            'count' => $cart->count(),
            'subtotal' => $cart->subtotal(2, '.', ''),
        ], $status);
    }
}
