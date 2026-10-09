# 8. Deuda técnica conocida

Relevado sobre el código actual. Ordenado por **riesgo**, no por esfuerzo. Cada ítem dice dónde está y en qué doc se explica la solución.

## 🔴 Crítico: plata o seguridad

| # | Problema | Dónde | Solución |
|---|---|---|---|
| 1 | ✅ **Resuelto** (`Payments/Actions/CapturePayment`). Pagos **no idempotentes**: dos capturas simultáneas pueden cobrar y crear dos órdenes | `PaymentController::capturePayment` | Tabla `payments`, `purchase_number` del servidor, lock, `UNIQUE` → [05](05-pagos-e-idempotencia.md) |
| 2 | ✅ **Resuelto**. `orders.payment_id` no es `unique` | migración de orders | Migración que agregue el índice único |
| 3 | ✅ **Resuelto** (falta el comando de reconciliación). Cobro aprobado + falla al crear la orden = plata cobrada sin registro | `capturePayment` | Registrar el pago antes, reconciliación → [05](05-pagos-e-idempotencia.md) |
| 4 | Token de sesión en la URL del callback de Google | `GoogleAuthController@callback` | Código de un solo uso → [09](09-seguridad.md#el-token-en-la-url-del-callback-de-google) |
| 5 | Tokens de Sanctum sin expiración | `config/sanctum.php` | `expiration` + `prune-expired` → [09](09-seguridad.md) |
| 6 | Sin rate limit en login/registro/pagos | rutas de Auth y Payments | `throttle` → [09](09-seguridad.md#rate-limiting) |
| 7 | `role` es mass-assignable | `User::$fillable` | Sacarlo del fillable → [09](09-seguridad.md#mass-assignment-del-rol-) |

## 🟠 Alto: datos incorrectos o pérdida de datos

| # | Problema | Dónde | Solución |
|---|---|---|---|
| 8 | El admin solo ve **sus propias** órdenes (el index está scopeado al usuario logueado) | `OrderController::index`, `downloadOrderTicket` | `Order::queryForAdmin()` → [07](07-filtrado-y-ownership.md) |
| 9 | Validación de stock del carrito **comentada** | `CartController::addItem` | Reactivar |
| 10 | `VariantService` **borra y regenera** todas las variantes: se pierden stock y SKU cargados | `VariantService::generateForProduct` | Sincronizar: crear las combinaciones nuevas, conservar las existentes, borrar solo las que ya no existen |
| 11 | Merge del carrito **suma** cantidades: no es idempotente | `CartController::merge` | `max()` en vez de suma → [06](06-carrito.md#1-el-merge-tiene-que-ser-idempotente) |
| 12 | Dinero en `float` | `products.price`, `orders.total` | `decimal(12,2)` → [03](03-modelo-de-datos.md#convenciones-de-datos) |
| 13 | Borrar un usuario borra sus órdenes (`cascade`) | migración de orders | `restrictOnDelete` o soft delete de usuarios |
| 14 | Borrar familia/categoría borra en cascada los productos (solo subcategoría tiene guard) | migraciones de Categories | Mismo evento/guard que `SubcategoryDeleting` |
| 15 | La orden puede crearse con `address = null` (no hay dirección por defecto) | `CreateOrderFromCart` | Validar dirección antes de cobrar → [`../addresses.md`](../addresses.md) |

## 🟡 Medio: arquitectura

| # | Problema | Dónde | Solución |
|---|---|---|---|
| 16 | Ciclo `Categories ↔ Products` | controllers públicos de Categories | Mover a Products → [02](02-comunicacion-entre-modulos.md#ciclos-actuales-y-cómo-romperlos) |
| 17 | Ciclo `Users ↔ Addresses` | `User::addresses()` | Borrar la relación → [02](02-comunicacion-entre-modulos.md) |
| 18 | Lógica de Niubiz inline en el controller | `PaymentController` | `NiubizClient` + Actions → [05](05-pagos-e-idempotencia.md#cómo-quedaría-el-módulo-payments) |
| 19 | `Route::resource` público expone POST/PUT/DELETE sin auth | `Products/Routes/api.php` | `->only(['index','show'])` |
| 20 | `Route::resource` en admin (options, features) expone `create`/`edit` | `Products/Routes/admin.php` | `apiResource` |
| 21 | `ProductsService`/`CategoriesService` son CRUD sin reglas y no todos los controllers los usan | Products, Categories | Eliminar o convertir en Actions con reglas |
| 22 | `per_page` y `pagination=false` sin tope | `Controller::paginated` | `min(per_page, 100)` → [07](07-filtrado-y-ownership.md) |

## 🟢 Bajo: prolijidad

| # | Problema | Dónde |
|---|---|---|
| 23 | Migración de Cart no se carga (falta `loadMigrationsFrom`) | `CartServiceProvider` |
| 24 | `loadMigrationsFrom` de una carpeta que no existe | `PaymentsServiceProvider` |
| 25 | `down()` de orders borra la tabla `order` (no `orders`) | migración de orders |
| 26 | Importa `TicketController` inexistente | `Orders/Routes/admin.php` |
| 27 | Stub `Route::get('/')` sin controller | `Orders/Routes/api.php` |
| 28 | Filtro muerto `where('key', ...)` | `OrderController::index` |
| 29 | `UserPolicy::view` usa una relación `admin` que no existe | `Users/Policies/UserPolicy` |
| 30 | Carpetas `routes/` en minúscula (resto usa `Routes/`) | Categories, Users |
| 31 | Migraciones de Categories sin fecha en el nombre | Categories |
| 32 | Login con Google: el **primer** ingreso falla (no setea `last_name`, `document_*`, `phone`, que son NOT NULL sin default), y si el email ya existe con contraseña choca con `email unique`. Hacer esas columnas nullable + "completar perfil" antes del checkout, y vincular por email | `GoogleAuthController@callback` |
| 33 | Métodos stub vacíos en `OrderController` (`store`, `show`, `update`, `destroy`) | `OrderController` |

## Sin tests

Products, Categories, Drivers, Users/Covers, captura de pago, merge del carrito. Antes de tocar cualquiera de los 🔴/🟠, escribir primero los tests que fijen el comportamiento (regla de oro 7).
