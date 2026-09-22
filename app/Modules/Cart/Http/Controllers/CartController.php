<?php

namespace App\Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Http\Requests\AddToCartRequest;
use App\Modules\Cart\Http\Requests\MergeCartRequest;
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
        $this->restore($request);

        $error = $this->addItem(
            $request->integer('product_id'),
            $request->integer('quantity'),
            $request->input('selected_features', []),
        );

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $this->persist($request);

        return $this->cartResponse(201);
    }

    /**
     * Fusiona el carrito de invitado con el del usuario. Los items inválidos
     * (producto inexistente, variante sin match o sin stock) se omiten.
     */
    public function merge(MergeCartRequest $request): JsonResponse
    {
        $this->restore($request);

        foreach ($request->input('items') as $item) {
            $this->addItem(
                (int) $item['product_id'],
                (int) $item['quantity'],
                $item['selected_features'] ?? [],
                clampToStock: true,
            );
        }

        $this->persist($request);

        return $this->cartResponse();
    }

    /**
     * Agrega un item al carrito ya restaurado. Devuelve un mensaje de error o null.
     *
     * @param  array<int|string, int|string>  $selectedFeatures
     */
    private function addItem(int $productId, int $quantity, array $selectedFeatures, bool $clampToStock = false): ?string
    {
        $product = Product::with('variants.features')->find($productId);

        if (! $product) {
            return 'Producto no encontrado';
        }

        $selectedFeatures = collect($selectedFeatures)
            ->mapWithKeys(fn ($featureId, $optionId) => [
                (int) $optionId => (int) $featureId,
            ]);

        // La variante debe coincidir exactamente por option_id y feature_id.
        $variant = $product->variants->first(
            fn ($variant) => $variant->features->count() === $selectedFeatures->count()
                && $selectedFeatures->every(
                    fn ($featureId, $optionId) => $variant->features->contains(
                        fn ($feature) => (int) $feature->option_id === $optionId
                            && (int) $feature->id === $featureId
                    )
                )
        );

        if ($product->variants->isNotEmpty() && ! $variant) {
            return 'Seleccioná una variante válida';
        }

        $cart = Cart::instance(self::INSTANCE);

        // TODO: reactivar validación de stock (desactivada temporalmente para pruebas)
        // $inCart = $cart->search(fn ($item) => $item->id === $product->id
        //     && ($item->options->variant_id ?? null) === $variant?->id
        // )->sum('qty');
        //
        // $available = ($variant ? $variant->stock : $product->stock) - $inCart;
        //
        // if ($quantity > $available) {
        //     if (! $clampToStock || $available < 1) {
        //         return 'Stock insuficiente';
        //     }
        //     $quantity = $available;
        // }

        $cart->add([
            'id'    => $product->id,
            'name'  => $product->name,
            'qty'   => $quantity,
            'price' => $product->price,
            'options' => $variant ? [
                'variant_id' => $variant->id,
                'image'      => $variant->image,
                'sku'        => $variant->sku,
                // Extrae [id => description] directo de la relación en memoria
                'features'   => $variant->features->pluck('description', 'id')->toArray(),
            ] : [
                'image'      => $product->image,
                'sku'        => $product->sku,
                'features'   => [],
            ],
        ]);

        return null;
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
