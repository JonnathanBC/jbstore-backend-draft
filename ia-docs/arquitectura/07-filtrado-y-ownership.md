# 7. Filtrado, paginación y ownership

## Ownership: "cada uno ve lo suyo"

Detalle completo: [`../user-ownership-scope.md`](../user-ownership-scope.md). Resumen:

- `App\Concerns\BelongsToUser` agrega el global scope `OwnedByUserScope` (`where user_id = Auth::id()`) y completa `user_id` al crear.
- Lo usan `Address` y `Order`. Cualquier modelo **del cliente** (wishlist, reviews, payments) debería usarlo.
- Efecto: `Order::find($idDeOtro)` devuelve `null` → el route model binding da **404**, no 403. Correcto: no confirmás que el recurso existe.

### Admin: el bypass tiene que ser explícito y con nombre

El admin ve las órdenes de **todos**. Eso se hace con un método con nombre en el modelo, **nunca** con `withoutGlobalScope` regado por los módulos:

```php
// Orders/Models/Order.php
public static function findForAdminOrFail(string $id): self
{
    return static::withoutGlobalScope(OwnedByUserScope::class)->findOrFail($id);
}

public static function queryForAdmin(): Builder   // ← falta hoy
{
    return static::withoutGlobalScope(OwnedByUserScope::class);
}
```

⚠️ **Bug actual:** `OrderController::index` (admin) usa `Order::query()`, que **está scopeado al admin logueado** → el admin solo ve sus propias compras. Y `downloadOrderTicket(Order $order)` usa route model binding, con el mismo problema. Usar `Order::queryForAdmin()` y `findForAdminOrFail`.

Regla: **en rutas `api/admin/*`, todo acceso a un modelo con ownership pasa por un método `...ForAdmin`.** Con eso, buscar `ForAdmin` te muestra todos los lugares donde se saltea la seguridad.

---

## Paginación y orden: `Controller::paginated()`

Helper en `app/Http/Controllers/Controller.php`, usado por casi todos los `index`:

```php
return $this->paginated($query, $request, ['updated_at', 'price'], ProductResource::class);
```

| Query param | Efecto |
|---|---|
| `page`, `per_page` (default 10) | Paginación estándar de Laravel |
| `order[campo]=asc\|desc` | Ordena **solo** si `campo` está en la whitelist |
| `pagination=false` | Devuelve todo sin paginar (`{ data: [...] }`) |

✅ La **whitelist** de orden es clave: sin ella, `order[password]=asc` te permite inferir datos ordenando por columnas privadas.

⚠️ Falta un **tope** a `per_page` y a `pagination=false`: `per_page=1000000` o `pagination=false` sobre 50.000 productos tumba el servidor. Poner `min((int) $request->per_page, 100)` y permitir `pagination=false` solo en endpoints chicos (selects).

---

## Filtrado: un patrón para todos los módulos

**Hoy** cada controller filtra a mano (`category_id` en subcategorías, `search`/`features[]`/`orderBy` en productos de familia) y hay código muerto (`where('key', ...)` en órdenes, columna que no existe).

**Objetivo:** una clase de filtros por recurso, con **whitelist** de filtros permitidos. Simple, sin paquetes:

```php
// app/Http/Filters/QueryFilters.php  (compartido)
abstract class QueryFilters
{
    /** @return array<string, string> query param => método de esta clase */
    abstract protected function filters(): array;

    public function apply(Builder $query, Request $request): Builder
    {
        foreach ($this->filters() as $param => $method) {
            if ($request->filled($param)) {
                $this->{$method}($query, $request->input($param));
            }
        }

        return $query;
    }
}
```

```php
// Orders/Http/Filters/AdminOrderFilters.php
class AdminOrderFilters extends QueryFilters
{
    protected function filters(): array
    {
        return [
            'status' => 'status',
            'from'   => 'from',
            'to'     => 'to',
            'search' => 'search',
        ];
    }

    protected function status(Builder $q, string $value): void
    {
        $q->where('status', OrderStatusEnum::from($value)); // valores inválidos → 500: validarlos en el Request
    }

    protected function from(Builder $q, string $date): void { $q->whereDate('created_at', '>=', $date); }
    protected function to(Builder $q, string $date): void   { $q->whereDate('created_at', '<=', $date); }

    protected function search(Builder $q, string $term): void
    {
        // Agrupado: un orWhere suelto anularía los demás filtros (status AND ... OR ...)
        // orders.id es uuid: en Postgres comparar con un texto que no es uuid tira error
        $q->where(fn ($q) => $q
            ->when(Str::isUuid($term), fn ($q) => $q->orWhere('id', $term))
            ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', "%{$term}%")));
    }
}
```

```php
// OrderController::index
public function index(IndexOrdersRequest $request, AdminOrderFilters $filters)
{
    $query = $filters->apply(Order::queryForAdmin()->latest(), $request);

    return $this->paginated($query, $request, ['created_at', 'total']);
}
```

Reglas:

- **Los filtros viven en el módulo del recurso** (`Orders/Http/Filters`). La clase base es compartida.
- **Validar** los parámetros en un FormRequest (`status` con `Rule::enum`, fechas con `date`). El filtro asume datos válidos.
- **Nunca** filtrar por un nombre de columna que manda el cliente (`where($request->field, ...)`). Siempre un mapa cerrado.
- `ilike` es de PostgreSQL. Está bien usarlo (la base es Postgres), pero sabelo si algún día cambiás de motor.
- **Búsqueda de texto:** `ilike '%x%'` no usa índices. Mientras haya pocos miles de productos alcanza. Con más, usar un índice trigram (`pg_trgm`) o full-text search de Postgres. Recién con mucho volumen, un motor aparte (Meilisearch/Scout).

### Filtros del catálogo (tienda)

`PublicFamilyProductController` ya implementa la lógica correcta de facetas:

- `features[]=1&features[]=5`: **OR** dentro de la misma opción (Rojo **o** Azul), **AND** entre opciones (Rojo **y** talle M), y los dos tienen que estar en la **misma variante**.
- `orderBy=relevant | major_to_minor | minor_to_major`.

Al migrar al patrón, esto pasa a `Products/Http/Filters/CatalogFilters.php` y se reutiliza para familia, categoría y subcategoría (hoy cada controller repite lógica).
