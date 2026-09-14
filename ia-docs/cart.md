# Carrito de compras — Guía paso a paso

Este documento tiene dos partes:

- **Parte A — Implementación actual:** el carrito usa el paquete `hardevine/shoppingcart` (instalado desde un fork) dentro del módulo `Cart`, como API.
- **Parte B — Alternativa:** cómo construir un carrito propio sin paquete, con tablas `carts` y `cart_items`.

---

# Parte A — Implementación actual: `hardevine/shoppingcart`

## A.1 Instalación (fork para Laravel 13)

El paquete original soporta hasta Laravel 12. Se usa un fork con `|^13` agregado en `illuminate/support`, `illuminate/session` e `illuminate/events`.

```json
"require": {
    "hardevine/shoppingcart": "dev-master"
},
"repositories": {
    "shoppingcart": {
        "type": "vcs",
        "url": "https://github.com/JonnathanBC/LaravelShoppingcart"
    }
}
```

- `repositories` le dice a composer **de dónde** bajar el paquete. Composer lo identifica por el `"name"` dentro del `composer.json` del fork (`hardevine/shoppingcart`), no por la URL.
- `dev-master` significa "último commit de la rama `master`". Los tags del fork (`3.4`, etc.) todavía tienen `^12`. Para usar una versión estable, crear un tag nuevo (ej. `3.5`) y pedir `^3.5`.

Namespace de la facade: `Gloudemans\Shoppingcart\Facades\Cart`.

## A.2 El problema: el paquete guarda en sesión

`Cart::add()` guarda en la **sesión**. Las rutas con middleware `api` no tienen sesión persistente, así que el carrito se pierde al terminar cada request.

**Solución del propio paquete:** `Cart::store($identifier)` y `Cart::restore($identifier)` guardan y recuperan el carrito en la tabla `shoppingcart`.

```bash
php artisan vendor:publish --provider="Gloudemans\Shoppingcart\ShoppingcartServiceProvider" --tag=migrations
php artisan migrate
```

Patrón en **cada** request:

```
1. Cart::restore($user->id)   ← traer de la base a memoria
2. Cart::add / remove / ...   ← operar
3. Cart::store($user->id)     ← guardar en la base
```

Por eso las rutas usan `auth:sanctum`: se necesita el id del usuario como identificador.

## A.3 Rutas

`app/Modules/Cart/Routes/api.php`

```php
Route::prefix('api/cart')->middleware('auth:sanctum')->group(function () {
    Route::post('/items', [CartController::class, 'store']);
    Route::delete('/items/{rowId}', [CartController::class, 'destroy']);
});
```

- `POST /api/cart/items` → body `{ "product_id": 42, "quantity": 2 }`.
- `DELETE /api/cart/items/{rowId}` → `rowId` es el hash que el paquete genera por cada línea (se ve en `Cart::content()`), NO el id del producto.

## A.4 Request y controller

`AddToCartRequest`:

```php
public function rules(): array
{
    return [
        'product_id' => 'required|integer|exists:products,id',
        'quantity' => 'required|integer|min:1',
    ];
}
```

Controller:

```php
public function store(AddToCartRequest $request)
{
    $user = $request->user();
    $product = Product::findOrFail($request->integer('product_id'));

    Cart::restore($user->id);

    Cart::add([
        'id' => $product->id,
        'name' => $product->name,
        'qty' => $request->integer('quantity'),
        'price' => $product->price,
    ]);

    Cart::store($user->id);

    return response()->json([
        'items' => Cart::content(),
        'total' => Cart::total(),
    ], 201);
}
```

Reglas:

- Buscar el producto por **id**, nunca por slug (ver `ia-docs/best-practices.md`).
- El array de `Cart::add` necesita **`id`, `name`, `qty` y `price`**. Si falta alguna, falla con `Undefined array key`.
- `price` y `name` salen de la base, **nunca** del request.
- `$request->integer()` convierte a `int`. La regla `integer` solo valida, no convierte.
- Usar `$product->id`, NUNCA `$product->get('id')`: `get()` se reenvía al query builder y ejecuta `SELECT id FROM products` sobre toda la tabla.

## A.5 Instancias: varios carritos por usuario

### El concepto

Una **instancia** es un **carrito con nombre**. El paquete no maneja "un" carrito, sino varios contenedores independientes, cada uno identificado por un nombre. Si no indicás ninguno, usa la instancia `default`.

Pensalo como un supermercado: el mismo cliente puede tener **un carrito de compra** y **una lista de deseos**. Son dos contenedores distintos con productos distintos, pero son del mismo cliente.

Casos típicos:

| Instancia | Para qué |
|---|---|
| `default` / `shopping` | Carrito de compra |
| `wishlist` | Lista de deseos |
| `compare` | Productos para comparar |
| `saved` | "Guardar para después" |

### Cómo funciona por dentro

`Cart::instance('wishlist')` cambia la clave donde se guarda el contenido a `cart.wishlist`. Todo lo que hagas después (`add`, `remove`, `content`, `total`, `store`, `restore`) opera **solo sobre esa instancia**.

```php
Cart::instance('shopping')->add([
    'id' => 1, 'name' => 'Zapatilla', 'qty' => 1, 'price' => 100,
]);

Cart::instance('wishlist')->add([
    'id' => 2, 'name' => 'Remera', 'qty' => 1, 'price' => 50,
]);

Cart::instance('shopping')->count();   // 1
Cart::instance('wishlist')->count();   // 1
Cart::instance('shopping')->total();   // solo la zapatilla
```

### En la base de datos

La tabla `shoppingcart` tiene clave primaria compuesta por `identifier` + `instance`. Así, el mismo usuario puede tener una fila por instancia:

| identifier | instance | content |
|---|---|---|
| 7 | shopping | (items serializados) |
| 7 | wishlist | (items serializados) |

`store` y `restore` usan la **instancia activa** en ese momento. Por eso hay que elegir la instancia **antes** de llamarlos.

### Patrón en la API

```php
Cart::instance('wishlist')->restore($user->id);
Cart::instance('wishlist')->add([...]);
Cart::instance('wishlist')->store($user->id);
```

Rutas sugeridas: un recurso por instancia.

```php
Route::prefix('api/wishlist')->middleware('auth:sanctum')->group(function () {
    Route::post('/items', [WishlistController::class, 'store']);
    Route::delete('/items/{rowId}', [WishlistController::class, 'destroy']);
});
```

### ⚠️ Gotcha: la instancia queda "pegada"

La facade `Cart` es un **singleton** durante el request. Si llamás `Cart::instance('wishlist')`, TODAS las llamadas siguientes en ese request van a `wishlist`, aunque no lo repitas:

```php
Cart::instance('wishlist')->add([...]);
Cart::content();   // ← devuelve la WISHLIST, no el carrito de compra
```

Reglas para evitarlo:

- Indicar **siempre** la instancia de forma explícita: `Cart::instance('shopping')->...`.
- Usar constantes para no escribir mal el nombre: un typo como `'wishlits'` crea otra instancia vacía sin avisar.

```php
final class CartInstance
{
    public const SHOPPING = 'shopping';
    public const WISHLIST = 'wishlist';
}

Cart::instance(CartInstance::WISHLIST)->add([...]);
```

### Mover un producto entre instancias

Ejemplo: de la wishlist al carrito de compra.

```php
Cart::instance(CartInstance::WISHLIST)->restore($user->id);
Cart::instance(CartInstance::SHOPPING)->restore($user->id);

$item = Cart::instance(CartInstance::WISHLIST)->get($rowId);

Cart::instance(CartInstance::SHOPPING)->add([
    'id' => $item->id,
    'name' => $item->name,
    'qty' => 1,
    'price' => Product::findOrFail($item->id)->price,
]);
Cart::instance(CartInstance::WISHLIST)->remove($rowId);

Cart::instance(CartInstance::SHOPPING)->store($user->id);
Cart::instance(CartInstance::WISHLIST)->store($user->id);
```

El precio se vuelve a leer de la base, porque puede haber cambiado desde que se guardó en la wishlist.

---

# Parte B — Alternativa: carrito propio sin paquete

Guía para construir un carrito propio en un módulo `Cart`, siguiendo la misma estructura que `Products`, `Categories` y `Users`.

---

## 0. Concepto primero: ¿por qué NO sesión?

Los paquetes tipo `shoppingcart` guardan el carrito en la **sesión**. Esto es una **API consumida con tokens (Sanctum)**: cada request es independiente y no hay sesión confiable. Por eso el carrito va en la **base de datos**:

- Sobrevive si el usuario cambia de dispositivo.
- Se puede validar stock y precio en el servidor.
- Se testea fácil.

**Regla de oro:** el cliente NUNCA manda el precio. Solo manda `product_id`, `variant_id` y `quantity`. El precio siempre sale de la base.

---

## 1. Modelo de datos

```
users 1 ── 1 carts 1 ── N cart_items N ── 1 products
                                    N ── 1 variants (opcional)
```

- `carts`: un carrito activo por usuario.
- `cart_items`: cada línea. Guarda `unit_price` como **snapshot**, o sea el precio al momento de agregarlo, para detectar cambios de precio.
- Unicidad: la misma combinación `cart + product + variant` no se repite. Si agregás otra vez, se suma la cantidad.

---

## 2. Estructura del módulo

```
app/Modules/Cart/
├── CartServiceProvider.php
├── Models/
│   ├── Cart.php
│   └── CartItem.php
├── Services/
│   └── CartService.php
├── Http/
│   ├── Controllers/CartController.php
│   ├── Requests/AddCartItemRequest.php
│   ├── Requests/UpdateCartItemRequest.php
│   └── Resources/CartResource.php
├── Routes/api.php
└── database/migrations/
    ├── 2026_09_14_100000_create_carts_table.php
    └── 2026_09_14_100100_create_cart_items_table.php
```

---

## 3. Migraciones

`database/migrations/2026_09_14_100000_create_carts_table.php`

```php
<?php

namespace App\Modules\Cart\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
```

`database/migrations/2026_09_14_100100_create_cart_items_table.php`

```php
<?php

namespace App\Modules\Cart\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('variant_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');
            $table->integer('quantity')->unsigned();
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->unique(['cart_id', 'product_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
```

> ⚠️ **Dinero:** `unit_price` es `decimal`, NO `float`. Un `float` tiene errores de redondeo (`0.1 + 0.2 != 0.3`). Hoy `products.price` es `float`; conviene migrarlo a `decimal(10, 2)` en algún momento.

> ⚠️ **Gotcha del unique con NULL:** en MySQL y Postgres, `NULL != NULL`, así que el índice único NO evita duplicados cuando `variant_id` es null. Por eso el service busca el item existente antes de crear uno nuevo (paso 5).

---

## 4. Modelos

`Models/Cart.php`

```php
<?php

namespace App\Modules\Cart\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function total(): float
    {
        return round(
            $this->items->sum(fn (CartItem $item) => $item->subtotal()),
            2
        );
    }
}
```

`Models/CartItem.php`

```php
<?php

namespace App\Modules\Cart\Models;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function subtotal(): float
    {
        return round($this->quantity * (float) $this->unit_price, 2);
    }
}
```

Opcional, en `User.php`:

```php
public function cart()
{
    return $this->hasOne(\App\Modules\Cart\Models\Cart::class);
}
```

---

## 5. Service: acá vive la lógica

El controller NO decide nada: valida la entrada y delega. Toda la regla de negocio va en el service.

`Services/CartService.php`

```php
<?php

namespace App\Modules\Cart\Services;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Variant;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getCart(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id])
            ->load('items.product', 'items.variant');
    }

    public function addItem(User $user, int $productId, ?int $variantId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $productId, $variantId, $quantity) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
            $product = Product::findOrFail($productId);
            $variant = $this->resolveVariant($product, $variantId);

            $item = $cart->items()
                ->where('product_id', $product->id)
                ->where('variant_id', $variant?->id)
                ->lockForUpdate()
                ->first();

            $newQuantity = ($item?->quantity ?? 0) + $quantity;
            $this->ensureStock($product, $variant, $newQuantity);

            if ($item) {
                $item->update(['quantity' => $newQuantity]);
            } else {
                $cart->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                ]);
            }

            return $this->getCart($user);
        });
    }

    public function updateItem(User $user, CartItem $item, int $quantity): Cart
    {
        $this->ensureOwnership($user, $item);
        $this->ensureStock($item->product, $item->variant, $quantity);

        $item->update(['quantity' => $quantity]);

        return $this->getCart($user);
    }

    public function removeItem(User $user, CartItem $item): Cart
    {
        $this->ensureOwnership($user, $item);
        $item->delete();

        return $this->getCart($user);
    }

    public function clear(User $user): void
    {
        Cart::where('user_id', $user->id)->first()?->items()->delete();
    }

    private function resolveVariant(Product $product, ?int $variantId): ?Variant
    {
        if ($variantId === null) {
            return null;
        }

        $variant = Variant::find($variantId);

        if (! $variant || $variant->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'variant_id' => 'La variante no pertenece al producto.',
            ]);
        }

        return $variant;
    }

    private function ensureStock(Product $product, ?Variant $variant, int $quantity): void
    {
        $available = $variant ? $variant->stock : $product->stock;

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => "Stock insuficiente. Disponible: {$available}.",
            ]);
        }
    }

    private function ensureOwnership(User $user, CartItem $item): void
    {
        abort_if($item->cart->user_id !== $user->id, 403);
    }
}
```

**Por qué cada decisión:**

| Decisión | Motivo |
|---|---|
| `DB::transaction` + `lockForUpdate` | Dos requests simultáneos no pueden duplicar la línea ni pisarse la cantidad. |
| `resolveVariant` valida el `product_id` | Evita que alguien mande la variante de OTRO producto. |
| `ensureOwnership` | Evita que un usuario modifique ítems del carrito de otro usuario (IDOR). |
| El precio sale de `$product->price` | El cliente nunca decide cuánto paga. |
| El stock se valida pero NO se descuenta | El carrito no reserva stock. Se descuenta en el **checkout**. |

---

## 6. Form Requests

`Http/Requests/AddCartItemRequest.php`

```php
<?php

namespace App\Modules\Cart\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
```

`Http/Requests/UpdateCartItemRequest.php`

```php
<?php

namespace App\Modules\Cart\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
```

---

## 7. Resource

`Http/Resources/CartResource.php`

```php
<?php

namespace App\Modules\Cart\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name' => $item->product->name,
                'image' => $item->variant?->image ?? $item->product->image_path,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'current_price' => (float) $item->product->price,
                'price_changed' => (float) $item->unit_price !== (float) $item->product->price,
                'subtotal' => $item->subtotal(),
            ]),
            'items_count' => $this->items->sum('quantity'),
            'total' => $this->total(),
        ];
    }
}
```

`price_changed` le avisa al frontend si el precio cambió desde que se agregó el producto.

---

## 8. Controller

`Http/Controllers/CartController.php`

```php
<?php

namespace App\Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Http\Requests\AddCartItemRequest;
use App\Modules\Cart\Http\Requests\UpdateCartItemRequest;
use App\Modules\Cart\Http\Resources\CartResource;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function index(Request $request)
    {
        return new CartResource($this->cartService->getCart($request->user()));
    }

    public function store(AddCartItemRequest $request)
    {
        $cart = $this->cartService->addItem(
            $request->user(),
            $request->integer('product_id'),
            $request->filled('variant_id') ? $request->integer('variant_id') : null,
            $request->integer('quantity'),
        );

        return (new CartResource($cart))->response()->setStatusCode(201);
    }

    public function update(UpdateCartItemRequest $request, CartItem $item)
    {
        $cart = $this->cartService->updateItem($request->user(), $item, $request->integer('quantity'));

        return new CartResource($cart);
    }

    public function destroy(Request $request, CartItem $item)
    {
        return new CartResource($this->cartService->removeItem($request->user(), $item));
    }

    public function clear(Request $request)
    {
        $this->cartService->clear($request->user());

        return response()->noContent();
    }
}
```

---

## 9. Rutas

`Routes/api.php`

```php
<?php

use App\Modules\Cart\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/cart')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/items', [CartController::class, 'store']);
    Route::patch('/items/{item}', [CartController::class, 'update']);
    Route::delete('/items/{item}', [CartController::class, 'destroy']);
    Route::delete('/', [CartController::class, 'clear']);
});
```

| Método | URL | Qué hace |
|---|---|---|
| GET | `/api/cart` | Ver carrito |
| POST | `/api/cart/items` | Agregar producto (suma si ya existe) |
| PATCH | `/api/cart/items/{item}` | Cambiar cantidad |
| DELETE | `/api/cart/items/{item}` | Quitar línea |
| DELETE | `/api/cart` | Vaciar carrito |

---

## 10. Service Provider y registro

`CartServiceProvider.php`

```php
<?php

namespace App\Modules\Cart;

use App\Modules\Cart\Services\CartService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CartService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Route::middleware('api')->group(__DIR__ . '/Routes/api.php');
    }
}
```

Registrarlo en `bootstrap/providers.php`:

```php
use App\Modules\Cart\CartServiceProvider;

return [
    // ...
    ProductsServiceProvider::class,
    CartServiceProvider::class,
];
```

Después:

```bash
php artisan migrate
php artisan route:list --path=api/cart
```

---

## 11. Tests (TDD: escribilos ANTES del código)

`tests/Feature/Cart/CartTest.php`. Estos son los casos mínimos:

```php
<?php

use App\Modules\Products\Models\Product;
use App\Modules\Users\Models\User;
use Laravel\Sanctum\Sanctum;

it('agrega un producto al carrito', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

    $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
        ->assertCreated()
        ->assertJsonPath('data.total', 200);
});

it('suma cantidad si el producto ya está en el carrito', function () { /* ... */ });
it('rechaza cantidad mayor al stock', function () { /* assertUnprocessable() */ });
it('rechaza una variante de otro producto', function () { /* ... */ });
it('no permite modificar ítems de otro usuario', function () { /* assertForbidden() */ });
it('ignora un price enviado por el cliente', function () { /* ... */ });
it('requiere autenticación', function () { /* assertUnauthorized() */ });
```

Si usás PHPUnit en vez de Pest, son los mismos casos escritos como métodos `test_*`.

---

## 12. Orden de implementación sugerido

1. Tests del paso 11, en rojo.
2. Migraciones y modelos (pasos 3 y 4).
3. Service (paso 5).
4. Requests, Resource, Controller y Rutas (pasos 6 a 9).
5. Provider y registro (paso 10), luego `migrate`.
6. Tests en verde. Después refactorizar.

---

## 13. Próximos pasos (fuera de este alcance)

- **Carrito de invitado:** agregar `carts.guest_token` (uuid) nullable y hacer `user_id` nullable. Al hacer login, se fusiona el carrito del invitado con el del usuario.
- **Checkout / Orders:** crea `orders` + `order_items`, descuenta stock dentro de una transacción con `lockForUpdate`, y vacía el carrito.
- **Precio en variantes:** hoy `variants` no tiene `price`. Si las variantes van a tener precios distintos, `unit_price` debería salir de la variante.
- **Migrar `products.price` a `decimal`.**
