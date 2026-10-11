# Refactor backend — plan para 2026-10-12

> Auditoría sobre `develop` @ `52ccc48`. Todo lo listado lo verifiqué leyendo código o con `route:list`; lo dudoso está marcado **(verificar)**. Los números de línea son de hoy: si tocás un archivo antes, re-ubicalos.

## Resumen

- **El global scope `OwnedByUserScope` se aplica también al admin.** Con el admin autenticado, `Order::query()`, el route model binding `{order}` y la relación `$shipping->order` filtran por `user_id = admin`. Consecuencias: el listado admin de órdenes sale vacío o solo con las del admin, el ticket da 404, y **completar o fallar un envío tira 500** (`TypeError`: le llega `null` a `UpdateOrderStatus`).
- **`GET /api/payments/token` le entrega el access token de Niubiz (credencial del comercio) a cualquier usuario logueado.**
- **No hay throttle** en `login`, `register`, `payments/session` ni `payments/capture`. Tampoco hay rate limit global: el grupo `api` de Laravel 11+ no lo trae.
- **Borrar una categoría borra en cascada sus subcategorías y TODOS sus productos.** El listener que protege la subcategoría no corre en ese camino.
- **Hay rutas públicas basura**: `Route::resource` sobre `PublicProductController` expone `POST/PUT/DELETE /api/public/products`. Hoy dan 500 porque los métodos no existen, pero no deberían existir. Lo mismo pasa con los stubs de `orders`, `options` y `features`.
- **Checkout**: la validación de stock está desactivada (TODO) y el stock nunca se descuenta. Además, sin dirección default, el pago se cobra y la orden falla.
- `register` con un teléfono repetido da 500. `OptionProductController::removeFeature` modifica el producto equivocado.
- Respuestas inconsistentes: modelos crudos (`toArray()` expone `role` y `google_id`), Resources usados a medias, mezcla de `{data:…}` y respuestas crudas.

## 1. Seguridad (CRÍTICO/ALTO)

- **[ALTO]** `app/Modules/Payments/Http/Controllers/PaymentController.php:18` + `Routes/api.php:9` — `generateToken` devuelve el security token de Niubiz al cliente. Con él se puede llamar a la API del comercio. El checkout JS solo necesita `sessionKey`, `merchantId` y `purchaseNumber`. **Fix:** borrar la ruta y el método. Si el front lo usa **(verificar)**, que pase a usar solo lo que devuelve `/session`. Ajustar `GeneratePaymentTokenTest`.
- **[ALTO]** `app/Modules/Auth/Routes/api.php:8-9`, `app/Modules/Payments/Routes/api.php:9-11` — sin throttle: brute force de login, spam de registros, y cada `POST /session` crea un `Payment` más una sesión en Niubiz. **Fix:** definir limiters en `AppServiceProvider::boot` y aplicarlos:
  ```php
  RateLimiter::for('auth', fn ($r) => Limit::perMinute(5)->by(Str::lower($r->input('email')).'|'.$r->ip()));
  RateLimiter::for('payments', fn ($r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
  // routes: ->middleware('throttle:auth') / ->middleware('throttle:payments')
  ```
  Ojo: el front llama server-to-server, así que `$r->ip()` es la IP del server del front **(verificar)**. Para login priorizá la clave por email, o configurá `TrustProxies` y reenviá `X-Forwarded-For`.
- **[ALTO]** `app/Modules/Products/Http/Controllers/ProductController.php:30` — `'image' => 'required|file'` acepta cualquier tipo de archivo, y `store()` usa la extensión que deduce del mime (un `.php` o `.html` termina en `storage/app/public`). Solo lo usa el admin, pero si roban esa cuenta es una RCE/XSS. **Fix:** `image|mimes:jpg,jpeg,png,webp|max:1024`, igual que en `update`.
- **[MEDIO]** `config/sanctum.php` (`'expiration' => null`) — los tokens no vencen nunca y cada login crea uno más. **Fix:** `expiration` de 7 a 30 días por env, agendar `sanctum:prune-expired` en `routes/console.php` y, opcional, borrar los tokens viejos en el login.
- **[MEDIO]** `app/Modules/Users/Models/User.php:33` — `'role'` está en `$fillable`. Hoy no se explota: `RegisterUser`, `CompleteProfile` y `FindOrCreateGoogleUser` arman el array a mano. Pero alcanza un `update($request->all())` futuro para tener escalada de privilegios. **Fix:** sacar `role` de `$fillable` y asignarlo explícito (`$user->role = …; $user->save()`) en factory y seeder.
- **[MEDIO]** `app/Http/Controllers/Controller.php:29,40` — `per_page` no tiene tope y `?pagination=0` devuelve la tabla entera, también en los endpoints públicos (`/api/public/products`, `families/{id}/products`). **Fix:** `min(max($request->integer('per_page', 10), 1), 100)` y permitir `pagination=false` solo con un flag (`allowUnpaginated: true`) que pasan los controllers admin.
- **[MEDIO]** `app/Modules/Users/Http/Controllers/UserController.php` (`profileResponse`), `Auth/Actions/LoginUser.php:21`, `RegisterUser.php:24`, `ExchangeGoogleLoginCode.php:23` — devuelven el `User` serializado entero (`role`, `google_id`, `email_verified_at`…). **Fix:** `UserResource` con lista blanca (incluye `profile_complete`), usado en las 4 salidas.
- **[BAJO]** `app/Modules/Users/Http/Controllers/CoverController.php:28,49` — se acepta `svg` y se sirve desde el dominio público: XSS almacenado vía SVG. **Fix:** quitar `svg`.
- **[BAJO]** `Categories/Http/Controllers/Public*Controller.php` (`ilike "%{$search}%"`) — `%` y `_` no se escapan (en `Shipping::scopeFilter` sí). No hay SQLi, pero se pueden forzar scans caros. **Fix:** el mismo `addcslashes` dentro de un scope reutilizable (ver §9).

## 2. Rutas y middleware

- **[ALTO]** `app/Modules/Products/Routes/api.php:8` — `Route::resource('/products', PublicProductController::class)` publica, sin auth, `store/update/destroy/create/edit` (500: los métodos no existen). **Fix:** `->only(['index', 'show'])`.
- **[MEDIO]** `app/Modules/Products/Routes/admin.php:23,26` — `Route::resource` en `options` y `features` registra `create/edit/show/update`, que no existen (500). **Fix:** `Route::apiResource(...)->only(['index','store','destroy'])`.
- **[MEDIO]** `app/Modules/Orders/Routes/admin.php:13` — `apiResource('orders')`: `show/update/destroy` devuelven 200 con body vacío (`OrderController.php:59-86`), y `store/update` dan 403 por `StoreOrderRequest/UpdateOrderRequest::authorize() === false`. **Fix:** `->only(['index'])` hasta implementar `show` con `OrderResource`. Borrar `app/Http/Requests/{Store,Update}OrderRequest.php`.
- **[MEDIO]** `app/Modules/Orders/Routes/api.php:8` y `routes/api.php:5` — `Route::get('/')` y `Route::get('/health')` no tienen acción: Laravel tira `LogicException "Route has no action"` → 500. **Fix:** borrar `/health` (ya existe `/up`) e implementar `GET /api/orders` para el usuario: `Order::query()->latest()` con el scope de dueño y `OrderResource`. El usuario hoy no puede ver sus pedidos.
- **[MEDIO]** `Orders/Routes/admin.php:15-16`, `Shippings/Routes/admin.php:12,15` — `{id}` y `{order}` sin restricción: con un id que no es uuid, Postgres tira `invalid input syntax for type uuid` → 500. **Fix:** `->whereUuid('id')` en las rutas de órdenes y `->whereNumber('id')` en las de shippings.
- **[MEDIO]** `Orders/Routes/admin.php:15` — el ticket usa binding `{order}`, que pasa por `OwnedByUserScope` (ver §3). **Fix:** `{id}` + `Order::findForAdminOrFail($id)`, igual que `updateStatus`. Falta también una ruta de ticket para el dueño: `GET /api/orders/{order}/ticket` con binding y scope.
- **[BAJO]** Prefijos inconsistentes: `AuthServiceProvider` y `UsersServiceProvider` ponen `prefix('api')` en el provider, y el resto lo pone en el archivo de rutas. Las carpetas `routes/` (Categories, Users) y `Routes/` (resto) mezclan mayúsculas. **Fix:** todo el prefijo en el archivo de rutas y carpeta `Routes/` en todos los módulos.
- **[BAJO]** `Orders/Routes/admin.php:5` importa un `TicketController` que no existe, y `OrdersServiceProvider.php:20` tiene el comentario "verificar un middleware diff". **Fix:** limpiar.
- **[BAJO]** `bootstrap/app.php:17` — `HandleCors` ya es middleware global en Laravel 11+; prependerlo al grupo `api` lo duplica. **Fix:** sacarlo.

## 3. Auth y autorización

- **[CRÍTICO]** `app/Concerns/Scopes/OwnedByUserScope.php:14-20`, aplicado por `BelongsToUser` en `Order`:
  - `Shippings/Actions/UpdateShippingStatus.php:45,50`: `$shipping->order` da `null` para el admin, y `PATCH /api/admin/shippings/{id}/status` con `completed` o `failed` tira **500**. No hay test que lo cubra.
  - `Orders/Http/Controllers/OrderController.php:26`: `Order::query()` en el index admin muestra solo las órdenes del admin.
  - `OrderController::downloadOrderTicket` (binding `{order}`) → 404.

  **Fix**, respetando "ningún otro módulo conoce `OwnedByUserScope`":
  ```php
  // Order.php
  public static function adminQuery(): Builder { return static::withoutGlobalScope(OwnedByUserScope::class); }
  public static function findForAdminOrFail(string $id): self { return static::adminQuery()->findOrFail($id); }
  // OrderController::index → Order::adminQuery()->latest()
  // UpdateShippingStatus → $order = Order::findForAdminOrFail($shipping->order_id);
  ```
  Primero el test que falla: completar un envío de una orden de otro usuario, autenticado como admin.
- **[ALTO]** `app/Modules/Auth/Http/Requests/RegisterRequest.php:19-23` — a `phone` le falta `unique:users,phone` y la columna es unique (`create_users_table.php:21`) → `UniqueConstraintViolationException` → 500. `CompleteProfileRequest` ya lo tiene. **Fix:** agregar `Rule::unique('users','phone')` y el test.
- **[MEDIO]** `app/Modules/Users/Policies/UserPolicy.php:14` — `$authUser->admin` no existe (rompe si alguien la usa). Además está registrada (`UsersServiceProvider.php:35`), pero ningún `authorize()` o `can()` la llama, y el resto devuelve `true`. **Fix:** borrarla. La autorización hoy es Gate `admin` más el scope de dueño, y conviene documentar esa decisión.
- **[BAJO]** `app/Policies/AdminPolicy.php` vive fuera de los módulos. **Fix:** moverla a `Modules/Users/Policies` o definir el Gate en `UsersServiceProvider` con un closure.
- **[BAJO]** `Auth/Actions/LoginUser.php:12` — `Auth::attempt` usa el guard `web` (sesión) en una API stateless, y el mensaje está en inglés. **Fix:** `User::where('email')->first()` + `Hash::check`, con el mensaje en español.
- **[BAJO]** `RegisterRequest.php:28` — `min:8` a secas. **Fix:** `Password::defaults()` (configurado en `AppServiceProvider`) y quitar la regla redundante de `password_confirmation`.
- **[BAJO]** `config/sanctum.php` (`stateful`, `guard: ['web']`) — el front llama server-to-server con bearer, así que el modo SPA-cookie no se usa. `config/cors.php` toma `FRONTEND_URL` con default `5173`, `config/app.php:58` con default `4321`, y `.env.example:67` lo deja vacío. **Fix:** un solo default coherente y documentado en `.env.example`. Evaluar `stateful => []`.
- **[BAJO]** FormRequests admin con `authorize(): $this->user() !== null` es redundante (la ruta ya tiene `can:admin`), y `RegisterRequest` no tiene `authorize`. **Fix:** convención: `return true` y la autorización vive en la ruta.

## 4. Controllers y respuestas (Resources)

- **[ALTO]** `app/Modules/Cart/Http/Controllers/CartController.php:110-123` — la validación de stock está comentada ("TODO … pruebas"), y `CreateOrderFromCart` nunca descuenta stock: sobreventa garantizada. **Fix:** reactivar la validación y descontar stock dentro de la transacción de `CapturePayment`, con `lockForUpdate()` sobre product o variant. Va como Action `Products/Actions/DecrementStock`, disparada por un evento `OrderPlaced` que escucha Products.
- **[ALTO]** `app/Modules/Orders/Actions/CreateOrderFromCart.php:17-27` — si el usuario no tiene dirección default, `address` queda `null` y `orders.address` es NOT NULL: **se cobra y la orden falla**. El pago queda `authorized` sin orden (el test lo documenta, pero no lo previene). **Fix:** en `StartPayment::handle`, validar que exista una dirección default antes de abrir la sesión (`ValidationException 'address'`).
- **[ALTO]** `app/Modules/Products/Http/Controllers/OptionProductController.php:42` — `removeFeature` busca solo por `option_id` → `firstOrFail()` agarra **el primer producto** con esa opción y le saca la feature. **Fix:** exigir `product_id` (como `removeOption`) y filtrar por los dos.
- **[MEDIO]** Modelos crudos donde debería haber Resource: `OrderController.php:97` (`response()->json($order)`), `OrderController::index`, `DriverController.php:30,37,44` (¡`DriverResource` existe y solo se usa en el index!), `ProductController` (store/update/variants), `Category/Family/SubcategoryController`, `CoverController`, `AddressController`, `PublicCategoryController::show` y `PublicSubcategoryController::show` (exponen `sku`, `stock` e `image_path` crudos). **Fix:** `OrderResource`, `AddressResource`, `CoverResource`, `CategoryResource`…, pasados como 4º parámetro de `paginated()`.
- **[MEDIO]** `OrderController.php:29-31` — filtro por la columna `key`, que no existe → 500 si llega `?key=`. **Fix:** sacarlo o reemplazarlo por `status`/`search` con un `ListOrdersRequest` y `Order::scopeFilter` (mismo patrón que Shippings).
- **[MEDIO]** `app/Modules/Orders/Observers/OrderObserver.php:12-25` — el PDF se genera sincrónico en `created`, o sea dentro de la transacción y del lock de `CapturePayment` (con timeout de 60 s). **Fix:** listener encolado (`ShouldQueue`, `afterCommit`) de un evento `OrderPlaced`.
- **[MEDIO]** Lógica que debería estar en Actions: `CartController::addItem` (`:85-143`) → `Cart/Actions/AddItemToCart`; `AddressController::store/update` → `CreateAddress` y `UpdateAddress`; `OptionController::store` (crea option + features sin transacción) → `CreateOption`; `OptionProductController::*` + `VariantService::generateForProduct` (borra y recrea variantes sin transacción) → Actions con `DB::transaction`.
- **[MEDIO]** `ProductController.php:40` guarda en el disco `public` y `:74-76` borra y guarda en el disco **default**. `CoverController` y `VariantService` también usan el default. Hoy funciona porque `.env.example` tiene `FILESYSTEM_DISK=public`; si cambia, las imágenes se rompen. **Fix:** `Storage::disk('public')` explícito en todos lados, o un helper del módulo.
- **[MEDIO]** `PublicProductController.php:45` — carga `variants` pero el Resource itera `variant->features` → N+1. **Fix:** `load(['variants.features', 'options'])`. En `:16` el `take(12)` no sirve (lo pisa `paginate`): borrarlo.
- **[BAJO]** `UserController.php:40-83` — stubs `create/store/show/edit/update/destroy` sin ruta. `:25-27` usa `like` (case-sensitive en Postgres). **Fix:** borrar los stubs y pasar a `ilike` con escape.
- **[BAJO]** Código muerto: `Products/Http/Controllers/VariantController.php` (sin ruta), `VariantService::updateVariant`, `Products/Services/ProductsService.php` y `Categories/Services/CategoriesService.php` (registrados como singleton, sin un solo uso). **Fix:** borrar.

## 5. Validación

- **[MEDIO]** Validación inline + `$request->all()` en `CategoryController.php:43,59`, `FamilyController.php:29,44`, `SubcategoryController.php:37,53`, `FeatureController.php:35`, `ProductController.php:44,80`, `CoverController`, `CartController::update`. **Fix:** un FormRequest por acción y `->validated()`. Con `$request->all()` y `image_path` en `$fillable`, el admin puede setear cualquier ruta.
- **[MEDIO]** `app/Modules/Products/Http/Controllers/OptionController.php:31` — el `foreach ($request->input('features'))` corre **antes** de `validate()`. Sin `features` → `ErrorException` → 500. **Fix:** `StoreOptionRequest` con `features.*.value` condicional (`Rule::when` o `withValidator`).
- **[BAJO]** `Cart/Http/Requests/AddToCartRequest.php` — `quantity` sin `max`; `CartController::update` (`increase`) ignora el stock. **Fix:** `max:99` y validar contra stock en la Action.
- **[BAJO]** `ProductController::variantsUpdate` exige `sku` y `VariantService` lo validaba `numeric`: reglas duplicadas y contradictorias. **Fix:** un solo `UpdateVariantRequest`.
- OK: los enums ya se validan con `Rule::enum` (Orders, Shippings, Drivers). El monto de Payments nunca viene del cliente. Bien.

## 6. Base de datos

- **[ALTO]** `Categories/database/migrations/160200_create_subcategories_table.php:16` + `Products/.../create_products_table.php:26` — la cascada `category → subcategories → products` hace que `CategoryController::destroy` (`:63`) borre productos sin avisar. **Fix:** chequear subcategorías antes de borrar (como `FamilyController::destroy`) y migrar las FKs `subcategory_id` y `category_id` a `restrictOnDelete()`.
- **[MEDIO]** Plata en `float`: `products.price` (`create_products_table.php:19`) y `orders.total` (`create_order_table.php:30`). `payments.amount` ya es `decimal(12,2)`. **Fix:** migración `->decimal('price', 12, 2)->change()` y lo mismo para `total`, con cast `decimal:2` en los modelos.
- **[MEDIO]** `Shippings/.../create_shipments_table.php:24` — `driver_id` con cascade: borrar un conductor borra el historial de envíos. `create_order_table.php:20`: `orders.user_id` hace cascade mientras `payments.user_id` hace restrict (inconsistente). **Fix:** `restrictOnDelete()` en las dos, y que `DriverController::destroy` devuelva 422 si el conductor tiene envíos.
- **[MEDIO]** `Orders/Actions/UpdateOrderStatus.php:19-31` — `canTransitionTo` se chequea fuera de la transacción y sin lock: dos admins pueden despachar la misma orden a la vez. **Fix:** dentro de `DB::transaction`, `$order = Order::adminQuery()->lockForUpdate()->findOrFail($order->id)` y recién ahí validar.
- **[MEDIO]** `option_product` y `feature_variant` no tienen unique compuesto: se puede asignar dos veces la misma opción y duplicar variantes. **Fix:** `unique(['option_id','product_id'])` y `unique(['feature_id','variant_id'])`, más validación en el request.
- **[BAJO]** `create_order_table.php:44` — `down()` dropea `order` en vez de `orders`.
- **[BAJO]** `Categories/database/migrations/1600*_*.php` — nombres sin fecha: hoy el orden alfabético funciona de casualidad. **Fix:** renombrar con timestamp anterior a `2026_04_05` (ojo con la tabla `migrations` en entornos ya migrados).
- **[BAJO]** `Cart/database/migrations/2026_09_21_000001_change_shoppingcart_content_to_text.php` — `CartServiceProvider` no hace `loadMigrationsFrom`, así que nunca corre. La tabla ya se crea como `longText`. **(verificar en prod)** y borrarla.
- **[BAJO]** `drivers.license_plate` es nullable y no tiene unique en la DB (el request sí lo exige); `drivers.user_id` no es unique. `addresses` no garantiza una sola default en la DB. **Fix:** uniques, más un índice parcial `CREATE UNIQUE INDEX … ON addresses(user_id) WHERE is_default`.
- OK: la idempotencia de Payments está bien: lock por `purchase_number`, más unique en `purchase_number`, `provider_transaction_id`, `order_id` y `orders.payment_id`, con replay del estado. No tocar.

## 7. Manejo de errores

- **[MEDIO]** Hay 500 evitables: uuid inválido (§2), columna `key` (§4), `OptionController` (§5), métodos de resource inexistentes (§2). Se resuelven en esas secciones.
- **[BAJO]** `bootstrap/app.php:20-24` — JSON para `api/*` está bien, pero `ModelNotFoundException` responde `"No query results for model [App\Modules\…]"`: filtra la estructura interna. **Fix:**
  ```php
  $exceptions->render(fn (NotFoundHttpException $e, Request $r) =>
      $r->is('api/*') ? response()->json(['message' => 'Recurso no encontrado.'], 404) : null);
  ```
- **[BAJO]** Forma de error inconsistente: `CartController` (`:44`) y `FamilyController::destroy` devuelven `{message}` 422 a mano, y el resto devuelve `ValidationException` (`{message, errors}`). **Fix:** usar siempre `ValidationException::withMessages`.
- **(verificar)** que prod tenga `APP_DEBUG=false`. `.env.example` trae `true`.

## 8. Tests faltantes (por riesgo)

1. `UpdateShippingStatus` completed/failed como admin sobre la orden de otro usuario: **hoy falla** (§3).
2. Admin orders: el index lista órdenes de todos, la descarga del ticket, uuid inválido → 404.
3. Register con teléfono duplicado → 422. Throttle de login → 429. Logout revoca el token.
4. Checkout sin dirección default → 422 en `/payments/session`. Validación y descuento de stock.
5. `CategoryController::destroy` con subcategorías → 422 (sin cascada).
6. `OptionProductController` (`store/removeFeature/removeOption`) + generación de variantes.
7. Cart: `store/merge/update/destroy/clear` (hoy solo `count`).
8. Products admin CRUD (tipos de imagen rechazados) y `PublicProductController` (`show` sin N+1, `per_page` con tope).
9. Smoke tests de los endpoints públicos (families, categories, subcategories, covers) y de los CRUD admin de Drivers y Covers.

## 9. Consistencia / deuda técnica

- **[BAJO]** Naming: la migración se llama `create_shipments_table` pero crea `shippings`. Otros: `ShippingsResource` (plural), `DriveTypeEnum` (debería ser `DriverTypeEnum`), `ShippingsShippingsServiceProvider` (alias en `bootstrap/providers.php:10`).
- **[BAJO]** `Shipping::findForAdminOrFail` (`Shipping.php:31`) quita un scope que Shipping no tiene. Es engañoso: dejá `findOrFail`. `Order` declara `user()` (`Order.php:47`), que ya viene de `BelongsToUser`.
- **[BAJO]** El orden y la búsqueda de los catálogos públicos están triplicados (`PublicCategoryController`, `PublicSubcategoryController`, `PublicFamilyProductController`). **Fix:** `Product::scopeSearch($term)` (con escape) y `scopeSortBy($orderBy)`.
- **[BAJO]** Respuestas mezcladas: `{data: …}` (`PublicCoverController`, `PublicFamilyController::show`, `PublicFamilyOptionController`) contra objetos crudos con `JsonResource::withoutWrapping()`. **Fix:** definir una convención (sugerencia: sin wrap para un item, paginador estándar para listas) y aplicarla al migrar a Resources.
- **[BAJO]** `App\Http\Requests\{Store,Update}OrderRequest` viven fuera del módulo (borrarlos, §2). El stub de `MakeModuleController` genera `$request->all()`: actualizarlo para que use FormRequest y Resource.
- **[BAJO]** `Payments/Services/NiubizClient.php:51-56` — los MDD antifraude están hardcodeados con valores de ejemplo (`integraciones@niubiz.com.pe`). Moneda `PEN`, pero `addresses.country` tiene default `EC` y los documentos son CI/RUC **(verificar con negocio)**.
- **[BAJO]** `AddressController.php:29` — `'receiver' => 1` hardcodeado. Al borrar la dirección default no se reasigna otra.
- Pendientes previos: migración del `slug` en products e instalar `laravel-ide-helper`.

## Orden sugerido para mañana

- [ ] **S** Test rojo: el admin completa el envío de una orden ajena (§8.1).
- [ ] **M** `Order::adminQuery()` + usarlo en `UpdateShippingStatus`, `OrderController::index` y ticket (`{id}` + `whereUuid`). Test verde.
- [ ] **S** Borrar `GET /payments/token` (verificar el front primero).
- [ ] **S** Rate limiters `auth` y `payments` + tests 429.
- [ ] **S** `RegisterRequest`: phone unique + `Password::defaults()` + test.
- [ ] **S** `PublicProductController` `->only(['index','show'])`; `options/features` `->only(...)`; orders `->only(['index'])`; borrar `/health` y el `GET /api/orders` vacío.
- [ ] **S** `ProductController` regla `image|mimes`; quitar `svg` de covers; `Storage::disk('public')` explícito.
- [ ] **S** `OptionProductController::removeFeature` filtra por `product_id` + test.
- [ ] **S** `CategoryController::destroy` bloquea si hay subcategorías + test.
- [ ] **M** `StartPayment` exige dirección default + test.
- [ ] **L** Stock: reactivar la validación en el carrito + descontar en la captura con lock (evento `OrderPlaced`).
- [ ] **S** Sacar `role` de `User::$fillable`; `UserResource` para me/login/register/exchange.
- [ ] **S** `paginated()`: tope de `per_page` y `pagination=false` solo para el admin.
- [ ] **S** Sanctum `expiration` + `sanctum:prune-expired` agendado.
- [ ] **M** `OrderResource` + `ListOrdersRequest`/`scopeFilter` (sacar `key`) + `GET /api/orders` del usuario.
- [ ] **S** `UpdateOrderStatus` con `lockForUpdate` dentro de la transacción.
- [ ] **M** Migraciones: decimal para `price` y `total`; `restrictOnDelete` en driver, user y subcategory; uniques en pivots, drivers y default address; fix del `down()` de orders.
- [ ] **M** FormRequests para Categories, Products, Options, Features, Covers y Cart update (incluye el bug del `foreach` en `OptionController`).
- [ ] **M** Resources para Drivers (store/show/update), Addresses, Covers y Categories.
- [ ] **M** PDF del ticket a un listener encolado `afterCommit`.
- [ ] **S** Handler 404 genérico; errores 422 vía `ValidationException`.
- [ ] **S** Limpieza: stubs de `UserController`, `UserPolicy`, `VariantController`, `*Service` sin uso, import de `TicketController`, `HandleCors` duplicado, migración muerta de Cart.
- [ ] **S** Naming: `Routes/` en todos los módulos, prefijos en los archivos de rutas, `DriverTypeEnum`, `ShippingResource`.
- [ ] **M** Tests restantes de §8 (cart, products admin, smoke público).
