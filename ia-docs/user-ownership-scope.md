# Scope global por usuario (ownership)

## Problema

Hoy cada controller tiene que acordarse de filtrar por dueño:

```php
// AddressController::index
Address::query()->where('user_id', $request->user()->id)->latest()->get();
```

Funciona mientras nadie se olvide. El día que alguien agregue `show`, `update` o `destroy` con route model binding (`Address $address`) sin chequear el dueño, se abre un **IDOR**: cualquier usuario logueado cambia el id en la URL y lee o modifica direcciones ajenas.

La seguridad no puede depender de la memoria del desarrollador. Tiene que ser el **comportamiento por defecto** del modelo.

## Qué se scopea y qué NO

| Recurso | ¿Scope por usuario? | Motivo |
|---|---|---|
| `Address` | **Sí** | Pertenece a un usuario (`user_id`). |
| `Order` / `OrderItem` (futuro) | **Sí** (`Order`) | Pertenece a un usuario. `OrderItem` se accede siempre vía `$order->items`. |
| Carrito | **No aplica** | No es un modelo Eloquent. Usa la tabla del paquete `shoppingcart` con `identifier = user_id` (`CartController::restore/persist`). Ya está aislado. |
| `Product`, `Variant`, `Category`, `Family`, `Subcategory`, `Cover` | **NO** | Es el **catálogo**: es público y el mismo para todos. Si lo scopeás por usuario, nadie ve productos. |

> Si en el futuro esto pasa a ser un marketplace, el catálogo se scopea por **vendedor**, no por comprador. Es otro scope y otra columna.

## Diseño

Son dos piezas en `app/Concerns` y se reutilizan en cada modelo "de usuario".

### 1. El scope

```php
<?php

namespace App\Concerns\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OwnedByUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user === null) {
            return; // consola, jobs, seeders: sin usuario, sin filtro (ver "Límites")
        }

        $builder->where($model->qualifyColumn('user_id'), $user->getAuthIdentifier());
    }
}
```

### 2. El trait

El trait registra el scope y asigna el dueño automáticamente al crear.

```php
<?php

namespace App\Concerns;

use App\Concerns\Scopes\OwnedByUserScope;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new OwnedByUserScope);

        static::creating(function ($model) {
            if ($model->user_id === null && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### 3. Uso en el modelo

```php
class Address extends Model
{
    use BelongsToUser, HasFactory;

    // sacar 'user_id' de $fillable: lo asigna el trait, nunca el cliente
}
```

### 4. Controllers más simples

```php
public function index()
{
    return AddressResource::collection(Address::latest()->get()); // ya filtrado
}

public function store(StoreAddressRequest $request)
{
    $address = Address::create($request->validated()); // user_id automático
    // ...
}

public function setDefault(Address $address) // si no es tuya => 404
{
    DB::transaction(function () use ($address) {
        Address::whereKeyNot($address->id)->update(['is_default' => false]); // solo las tuyas
        $address->update(['is_default' => true]);
    });

    return new AddressResource($address->fresh());
}
```

**Detalle clave:** el route model binding usa el query del modelo, así que **también pasa por el scope**. Si la dirección no es tuya, el resultado es un **404**, no un 403. Es lo correcto porque no le confirma al atacante que ese id existe.

## Conceptos

- **Global scope**: un `WHERE` que Laravel agrega **automáticamente a toda consulta Eloquent** del modelo. Aplica a `get()`, `find()`, `first()`, `paginate()`, el route model binding y los `update()`/`delete()` masivos.
  ```php
  Address::latest()->get();
  // SQL: select * from addresses where addresses.user_id = 7 order by created_at desc
  ```
- **`withoutGlobalScope(X::class)`**: apaga el scope **solo en esa consulta**. Se usa en casos puntuales, como un endpoint de admin. Es explícito a propósito, así el bypass se ve en el code review.
- **Local scope** (`scopeActive()` → `Address::active()`): es un filtro que **vos llamás** cuando lo querés. No es automático. Si el filtro es **opcional**, va como local scope. Si es **obligatorio siempre**, va como global scope.
- **Trait**: un bloque de código que se "pega" en una clase con `use`. Sirve para reutilizar comportamiento sin herencia. Laravel ejecuta automáticamente el método `boot{NombreDelTrait}()` al cargar el modelo.
- **`app/Concerns`**: es solo una carpeta. Es la convención de Laravel para traits reutilizables (el framework usa `Illuminate\Database\Eloquent\Concerns`). No tiene nada de mágico.

## Guía: cómo se arma un global scope (paso a paso)

### Paso 1: crear la clase Scope (la **regla**)

```bash
php artisan make:scope OwnedByUserScope
```

Esto genera `app/Models/Scopes/OwnedByUserScope.php`. En este proyecto lo movemos a `app/Concerns/Scopes/` porque los modelos viven en módulos y la regla es compartida.

Un scope implementa **un solo método**, `apply()`, que recibe el query builder y le agrega condiciones:

```php
class OwnedByUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $builder->where($model->qualifyColumn('user_id'), $user->getAuthIdentifier());
    }
}
```

Buenas prácticas en `apply()`:
- **`qualifyColumn('user_id')`** en vez de `'user_id'` a secas. Genera `addresses.user_id`, así que no se rompe con `join` ni `whereHas` (sin esto aparece el error *ambiguous column*).
- **Nada de lógica de negocio**: solo filtrar. Nada de queries extra, logs ni excepciones.
- **Una responsabilidad por scope**. Si mañana necesitás "solo activos", eso es otro scope, no un `if` más acá.

### Paso 2: decidir cómo aplicarlo al modelo

Hay tres formas. Elegí según cuánto se reutiliza:

| Forma | Cuándo usarla |
|---|---|
| `#[ScopedBy([OwnedByUserScope::class])]` sobre el modelo | Scope usado en **un solo modelo** y sin comportamiento extra. |
| `static::addGlobalScope(...)` en `booted()` del modelo | Igual que la anterior, estilo "clásico". |
| **Trait** que registra el scope (lo que usamos) | Scope usado en **varios modelos** y que además trae comportamiento asociado (asignar `user_id`, relación `user()`). |

Acá conviene el **trait**, porque "ser de un usuario" significa **tres cosas juntas**: filtrar, asignar el dueño y tener la relación. Si fueran piezas sueltas, algún modelo terminaría con dos de las tres.

### Paso 3: crear el trait (el **conector**)

```php
trait BelongsToUser
{
    // Laravel ejecuta boot{NombreDelTrait}() automáticamente
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new OwnedByUserScope);

        static::creating(function ($model) {
            if ($model->user_id === null && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Paso 4: aplicarlo al modelo

```php
class Address extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [/* SIN user_id */];
}
```

Hay que sacar `user_id` de `$fillable`. Así el cliente **no puede** mandarlo en el body: el dueño lo decide siempre el servidor.

### Paso 5: limpiar el código viejo

Buscá los filtros manuales que ahora sobran y sacalos:

```bash
rg "where\('user_id'" app/Modules/Addresses
```

### Paso 6: tests de aislamiento

Ver la sección "Tests obligatorios". **Sin estos tests no está terminado**: el scope es seguridad, y la seguridad que no se testea no existe.

## Cómo escalar

### Agregar un modelo nuevo "de usuario" (checklist)

Por ejemplo `Order`, `Wishlist` o `Review`:

1. La migración tiene `foreignId('user_id')->constrained()->cascadeOnDelete()` y un **índice** que empiece por `user_id` (todas las consultas van a filtrar por esa columna).
2. El modelo hace `use BelongsToUser;` y **no** tiene `user_id` en `$fillable`.
3. Las rutas van bajo `auth:sanctum`.
4. Los controllers **no** filtran a mano por `user_id`.
5. Se copian los tests de aislamiento del modelo que ya existe.

Con esto, sumar un modelo nuevo es **una línea**, no una auditoría de seguridad.

### Modelos hijos: no los scopees, accedé vía el padre

`OrderItem` no necesita `user_id` ni scope. Siempre se accede a través de su padre:

```php
Route::get('/orders/{order}/items', ...);  // {order} ya pasó por el scope → 404 si no es tuya
$order->items;                             // hereda la seguridad del padre
```

Para rutas anidadas usá `->scopeBindings()`. Así `/orders/1/items/99` valida que el item 99 pertenezca a la orden 1.

### Admin: bypass explícito y centralizado

No desparrames `withoutGlobalScope` por todos lados. Centralizalo en un solo lugar del módulo admin:

```php
// en el controller o service de admin
Order::withoutGlobalScope(OwnedByUserScope::class)->with('user')->paginate();
```

Una regla fácil de revisar: **`withoutGlobalScope` solo aparece en código bajo `can:admin`**. Se puede verificar con `rg withoutGlobalScope app/`.

### Jobs y colas

En un job no hay usuario autenticado, así que el scope no filtra. El job **recibe el id y filtra explícitamente**:

```php
class SendOrderConfirmation implements ShouldQueue
{
    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::findOrFail($this->orderId); // sin auth: sin scope, es intencional
    }
}
```

### Si el negocio crece: multi-tenant

Si un día hay **tiendas** o **vendedores**, el patrón es el mismo con otra columna: `TenantScope` + trait `BelongsToTenant` con `store_id`. Los scopes se combinan: un modelo puede usar `BelongsToTenant` y `BelongsToUser` a la vez, y cada uno agrega su `WHERE`.

### Antipatrones

- ❌ Scopear el catálogo (`Product`) por usuario.
- ❌ Aplicar un global scope para algo **opcional** (por ejemplo "solo activos" en el panel admin). Eso es un local scope.
- ❌ Hacer `withoutGlobalScopes()` (plural, apaga **todos**) por comodidad.
- ❌ Confiar solo en el scope y olvidarse de las Policies para reglas de acción ("¿se puede cancelar?").
- ❌ Usar `DB::table('addresses')` en código de usuario: se saltea el scope sin avisar.

## Cómo funciona con Sanctum

El middleware `auth:sanctum` llama a `Auth::shouldUse('sanctum')`. A partir de ahí `auth()->user()` devuelve el usuario del token, así que el scope funciona en cualquier ruta protegida sin configuración extra.

**Regla:** toda ruta que toque un modelo con `BelongsToUser` **debe** estar bajo `auth:sanctum`.

## Límites (LEER)

El scope es **defensa en profundidad**, no magia. No cubre:

1. **Query Builder crudo**: `DB::table('addresses')`, `DB::select(...)`. El scope es de Eloquent. Esos queries se filtran a mano.
2. **Sin usuario autenticado**: jobs en cola, comandos, seeders y tinker. Ahí el scope **no filtra**, y es a propósito: si no, los seeders y los jobs no podrían trabajar. Un job que procese órdenes de un usuario recibe el `user_id` y filtra explícitamente.
3. **Admin**: un admin logueado también queda filtrado a *sus* datos. Los endpoints `can:admin` que necesiten ver todo lo hacen de forma **explícita**:
   ```php
   Order::withoutGlobalScope(OwnedByUserScope::class)->paginate();
   ```
   Que sea explícito es la gracia: el bypass queda visible en el code review.
4. **Autorización de acciones**: el scope responde "¿esto es tuyo?". No responde "¿podés hacer esto?", por ejemplo cancelar una orden ya enviada. Para eso siguen existiendo las Policies.

## Tests obligatorios (aislamiento)

Por cada modelo con `BelongsToUser`:

- [ ] El usuario A lista y **no** ve registros del usuario B.
- [ ] El usuario A hace `GET/PATCH/DELETE` sobre un id de B y recibe **404**.
- [ ] Al crear sin `user_id`, se asigna el usuario autenticado.
- [ ] Mandar `user_id` de otro usuario en el body **no** tiene efecto.
- [ ] Un admin con `withoutGlobalScope` ve todo.

## Hallazgos de la revisión (a resolver aparte)

- **`is_default` sin regla de unicidad**: `AddressController::store` guarda el `is_default` que manda el cliente sin desmarcar las otras direcciones. Puede haber varias predeterminadas. La lógica de `setDefault` (transacción) tiene que reutilizarse en `store`.
- **No existe endpoint `setDefault`** ni `update/destroy` de direcciones. Conviene crearlos **después** de aplicar el scope.
- **`UserPolicy`** usa `$authUser->admin`, que no existe en `User`. El rol es `role === UserRoleEnum::Admin`.
- **`CoverObserver`** parece corrupto: namespace y nombre de clase rotos (`App\Modules\Users\.s`, `class Cover.`). Revisar si carga.
- **`Route::resource('/products', PublicProductController::class)`** en `Products/Routes/api.php` registra también `store/update/destroy`, pero el controller solo tiene `index/show/byIds`. No es un agujero de escritura, pero expone rutas que terminan en error. Usar `->only(['index', 'show'])`.
- **Carrito**: el `content` se deserializa con `unserialize` (`CartController::restore`). Hoy viene de la base propia, pero `unserialize` sobre datos que alguna vez puedan venir del cliente es un riesgo. Evaluar `allowed_classes`.
- **Stock**: la validación de stock en el carrito está comentada (TODO).
