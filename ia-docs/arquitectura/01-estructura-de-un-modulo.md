# 1. Estructura de un módulo

> Un módulo es una **mini-aplicación** con su propio modelo, reglas, rutas y migraciones. Pensalo como un departamento de un edificio: tiene sus cañerías propias, y si necesita algo del vecino, toca el timbre (Action pública). No rompe la pared.

## Estructura completa

No todo módulo necesita todas las carpetas. **Creá la carpeta cuando la necesites, no antes.**

```
app/Modules/Orders/
├── OrdersServiceProvider.php     ← registra todo lo del módulo
├── Actions/                      ← casos de uso (escriben)
│   ├── CreateOrderFromCart.php
│   └── UpdateOrderStatus.php
├── Services/                     ← lógica reutilizable sin "caso de uso" (opcional)
├── Enums/
│   └── OrderStatusEnum.php       ← estados + máquina de transiciones
├── Events/                       ← "algo pasó" (para que otros reaccionen)
├── Listeners/                    ← reacciones a eventos de OTROS módulos
├── Observers/
│   └── OrderObserver.php         ← efectos técnicos del ciclo de vida del modelo
├── Policies/                     ← autorización fina por registro
├── Http/
│   ├── Controllers/
│   ├── Requests/                 ← validación de entrada
│   └── Resources/                ← forma de salida (JSON)
├── Models/
│   └── Order.php
├── Routes/
│   ├── admin.php                 ← api/admin/*, auth:sanctum + can:admin
│   └── api.php                   ← api/*, cliente
├── Views/                        ← blades propios (ej. ticket PDF)
├── Factories/                    ← factories del módulo (para tests/seeders)
└── database/migrations/
```

## El flujo de una petición

```
HTTP ─▶ Route ─▶ FormRequest ─▶ Controller ─▶ Action ─▶ Model(s)
                 (valida)        (traduce)     (decide)   (persiste)
                                     │
                                     └─▶ Resource ─▶ JSON
```

Ejemplo real: `POST api/admin/orders/{id}/shipping`

```php
// Http/Controllers/ShippingController.php — SOLO HTTP
public function store(CreateShippingRequest $request, string $orderId, CreateShipping $createShipping): JsonResponse
{
    $shipping = $createShipping->handle(
        Order::findForAdminOrFail($orderId),
        (int) $request->validated('driver_id'),
    );

    return response()->json($shipping->refresh(), 201);
}
```

```php
// Actions/CreateShipping.php — EL CASO DE USO
public function handle(Order $order, int $driverId): Shipping
{
    return DB::transaction(function () use ($order, $driverId) {
        $this->updateOrderStatus->handle($order, OrderStatusEnum::Shipped); // le pide a Orders
        return Shipping::create(['order_id' => $order->id, 'driver_id' => $driverId]);
    });
}
```

---

## ¿Qué va en cada capa? (y cuándo usarla)

### Controller
**Responsabilidad:** traducir HTTP ↔ dominio.

- ✅ Recibir el Request, llamar a una Action, devolver respuesta/Resource, elegir el status code.
- ✅ Lecturas simples (index/show) pueden quedarse en el controller con `paginated()`.
- ❌ `if` de negocio, transacciones, llamadas a APIs externas, cambios de estado.

**Señal de alarma:** un método de controller de más de ~15 líneas. Hoy `PaymentController` tiene toda la integración con Niubiz inline → candidato a extraer (ver [05](05-pagos-e-idempotencia.md)).

### FormRequest (`Http/Requests`)
**Responsabilidad:** validar **forma** de la entrada (tipos, requeridos, existencia).

- ✅ `required`, `exists:drivers,id`, `Rule::enum(...)`, tamaños, regex.
- ✅ `authorize()` cuando la autorización depende del payload.
- ❌ Reglas de negocio que dependen del estado ("la orden tiene que estar en processing") → eso es de la **Action**, porque también se tiene que cumplir cuando la Action la llama otro módulo (que no pasa por el Request).
- Siempre `$request->validated()`, nunca `$request->all()` (mass assignment).

Reglas: array de strings (`['required', 'exists:drivers,id']`) **o** string con pipes (`'required|exists:drivers,id'`). Nunca mezclados (`['required|exists:...']` no funciona).

### Resource (`Http/Resources`)
**Responsabilidad:** la forma del JSON de salida. Es tu **contrato público**.

- ✅ Siempre que el modelo tenga campos que el cliente no debe ver (ids internos, costos, flags) o cuando querés desacoplar la API de las columnas.
- ✅ Dos resources para el mismo modelo si hay dos audiencias: `ProductResource` (admin) vs `PublicProductResource` (tienda).
- ❌ Devolver modelos crudos en endpoints públicos: un día agregás una columna `cost_price` y la estás publicando sin darte cuenta.

### Action (`Actions/`)
**Responsabilidad:** **un caso de uso** que cambia el sistema. Es la API pública del módulo.

Usá una Action cuando:
- Escribe en la base (crear, cambiar estado, borrar con reglas).
- Toca **más de una tabla** o **más de un módulo** → la transacción va acá.
- Otro módulo necesita disparar esa operación (Payments → `CreateOrderFromCart`, Shippings → `UpdateOrderStatus`).
- Querés testear la regla sin HTTP.

Convención: `VerboSustantivo`, método `handle()`, dependencias por constructor.

```php
class UpdateOrderStatus
{
    public function handle(Order $order, OrderStatusEnum $next): Order { ... }
}
```

**No hagas Action para todo.** Un `index` que pagina no necesita Action. Un CRUD trivial sin reglas tampoco.

### Service (`Services/`)
**Responsabilidad:** lógica **reutilizable** que no es un caso de uso en sí, o que encapsula algo con estado/infraestructura.

- ✅ `CartService`: envuelve el paquete del carrito (restore/persist/totals). Lo usan varias Actions y controllers.
- ✅ `VariantService::generateForProduct()`: algoritmo (producto cartesiano) usado desde varios puntos.
- ✅ Clientes de APIs externas: un futuro `NiubizClient` (token, sesión, autorización).
- ❌ "Services" que son solo `create/update/delete` delegando a Eloquent (`ProductsService`, `CategoriesService` hoy): no agregan nada; o se eliminan o se convierten en Actions con reglas reales.

**Regla corta:** Action = *"qué hace el negocio"*. Service = *"herramienta que usan las Actions"*.

### Model
**Responsabilidad:** persistencia + relaciones + reglas **que solo dependen del propio registro**.

- ✅ Relaciones, casts, scopes locales (`scopeCurrent`), accessors.
- ✅ Métodos de consulta con nombre que esconden detalles: `Order::findForAdminOrFail()`.
- ✅ Invariantes del propio registro: `Address::markAsDefault()`.
- ❌ Llamar a otros módulos, APIs externas o mandar mails.
- ❌ Relaciones hacia módulos que dependen de él (ver [02](02-comunicacion-entre-modulos.md)).

### Enum
**Responsabilidad:** valores cerrados + reglas sobre esos valores.

- ✅ Estados con su máquina de transiciones (`OrderStatusEnum::allowedTransitions()`).
- ✅ `values()` para migraciones y validación.
- Es vocabulario **público** del módulo: otros módulos pueden usar `OrderStatusEnum::Shipped`.

### Observer
**Responsabilidad:** efectos **técnicos** atados al ciclo de vida de un modelo (`creating`, `created`, `updated`...).

- ✅ `OrderObserver::created` → generar el PDF del ticket.
- ✅ `CoverObserver::creating` → calcular `order` automático.
- ❌ Reglas de negocio importantes o efectos en otros módulos: quedan **escondidas** (nadie las ve leyendo la Action) y se disparan también en seeders, tests y tinker.

**Ojo:** el observer corre dentro de la transacción si la creación está en una. Si hace I/O lento (PDF, mail), usá `ShouldHandleEventsAfterCommit` o mandalo a una cola.

### Event + Listener
**Responsabilidad:** avisar *"algo pasó"* a módulos que **no deben ser conocidos** por quien emite.

- ✅ `Categories` emite `SubcategoryDeleting`, `Products` escucha y bloquea si hay productos. Categories no sabe que Products existe.
- ✅ Efectos secundarios que pueden ir a cola: `OrderPaid` → mandar mail, notificar al admin, descontar stock en un sistema externo.
- ❌ Para el flujo principal que el lector necesita ver (ver [02](02-comunicacion-entre-modulos.md#action-o-evento)).

Convención: el **evento** vive en el módulo que lo emite; el **listener** en el módulo que reacciona; el **registro** (`Event::listen`) en el provider del que reacciona.

### Policy
**Responsabilidad:** autorización **por registro** ("¿este usuario puede ver ESTA orden?").

- ✅ Rutas de admin: alcanza con el Gate `can:admin` en la ruta.
- ✅ Cliente: el [scope de ownership](../user-ownership-scope.md) ya resuelve "solo lo mío". Usá Policy cuando haya reglas más finas (ej. "solo puede cancelar si la orden está pending").
- ❌ `if ($user->role === ...)` dentro del controller.

### Service Provider
**Responsabilidad:** **enchufar** el módulo a Laravel. Nada de lógica.

```php
class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // bindings / singletons del módulo (si hacen falta)
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/Views', 'orders');

        Route::middleware('api')->group(__DIR__.'/Routes/api.php');
        Route::middleware('api')->group(__DIR__.'/Routes/admin.php');

        Order::observe(OrderObserver::class);
        // Event::listen(EventoDeOtroModulo::class, MiListener::class);
    }
}
```

Checklist del provider:
- [ ] `loadMigrationsFrom` si el módulo tiene `database/migrations` (hoy **Cart** no lo hace → su migración no corre).
- [ ] No llamar `loadMigrationsFrom` a carpetas que no existen (hoy **Payments**).
- [ ] Registrado en `bootstrap/providers.php`.

### Rutas
- `Routes/admin.php`: `prefix('api/admin')`, `middleware(['auth:sanctum', 'can:admin'])`, `name('admin.')`.
- `Routes/api.php`: cliente/público. Lo público, explícito (`api/public/...`).
- `Route::apiResource` (sin `create`/`edit`) para APIs. `Route::resource` expone rutas de formularios HTML que una API no usa.
- Registrá **solo** los métodos implementados: `->only(['index', 'show'])`. Hoy `api/public/products` expone POST/PUT/DELETE sin auth porque usa `Route::resource` completo.

---

## Tabla de decisión rápida

| Necesito... | Usá |
|-------------|-----|
| Validar lo que manda el cliente | FormRequest |
| Dar forma al JSON | Resource |
| Ejecutar una operación de negocio | Action |
| Una operación que otro módulo dispara | Action (pública) |
| Envolver una librería/API externa | Service |
| Algo técnico al crear/actualizar un modelo | Observer |
| Avisar a módulos que no conozco | Event (+ Listener en el otro módulo) |
| Autorizar por registro | Policy (o scope de ownership) |
| Conectar el módulo a Laravel | ServiceProvider |

## Tests por módulo

```
tests/Feature/{Modulo}/{CasoDeUso}Test.php   ← HTTP de punta a punta (lo principal)
tests/Unit/...                                ← enums, algoritmos (VariantService)
```

Mínimo por endpoint de escritura: camino feliz, regla de negocio violada (422), validación (422), no-admin (403), sin auth (401). Ver `tests/Feature/Shippings/CreateShippingTest.php` como plantilla.
