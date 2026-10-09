# 2. Comunicación entre módulos

> Separar en carpetas es fácil. Lo difícil (y lo que hace que sea **modular de verdad**) es controlar **quién conoce a quién**.

## La regla: dependencias en una sola dirección

Si `A` importa algo de `B` (`use App\Modules\B\...`), entonces **A depende de B**. Está permitido, pero:

1. **Nunca en las dos direcciones.** Si A conoce a B y B conoce a A, no son dos módulos: es uno partido en dos carpetas. No podés cambiar, testear ni borrar uno sin el otro.
2. **Hacia el núcleo.** Los módulos "base" (Users, Products, Orders) no saben que existen los que se apoyan en ellos (Shippings, Payments, Coupons...).

### Capas del ecommerce (de núcleo a periferia)

```
Nivel 0  Users                                  ← no depende de nadie
Nivel 1  Auth · Addresses · Categories · Drivers
Nivel 2  Products
Nivel 3  Cart
Nivel 4  Orders
Nivel 5  Payments · Shippings · (Coupons, Reviews, Notifications...)
```

Un módulo solo puede depender de módulos de **nivel menor**. Si te encontrás importando hacia arriba, algo está mal ubicado.

### ¿Cómo sé si una dependencia está bien?

Preguntate: **"¿puede existir A sin B?"**

- ¿Puede existir una **orden** sin envíos? Sí (retiro en tienda, productos digitales). → `Orders` no debe conocer `Shippings`.
- ¿Puede existir un **envío** sin orden? No. → `Shippings → Orders` es natural.
- ¿Puede existir un **usuario** sin direcciones? Sí. → `Users` no debería conocer `Addresses`.

---

## ¿Qué puede usar un módulo de otro?

| Se puede usar desde afuera | No se debe usar desde afuera |
|---|---|
| **Actions** (`Orders\Actions\UpdateOrderStatus`) | Asignar atributos y `save()` sobre modelos ajenos |
| **Enums** (`OrderStatusEnum::Shipped`) | Scopes internos (`OwnedByUserScope`) |
| **Modelos para leer/relacionar** (`belongsTo(Order::class)`) | Services internos que no son API pública |
| **Métodos con nombre** (`Order::findForAdminOrFail`) | Controllers, Requests, Resources de otro módulo |
| **Eventos** (escucharlos) | Tablas ajenas con queries crudas (`DB::table('orders')->update`) |

Ejemplo correcto (hoy en el código):

```php
// Shippings/Actions/CreateShipping.php
$this->updateOrderStatus->handle($order, OrderStatusEnum::Shipped); // ✅ pide a Orders
Shipping::create(['order_id' => $order->id, 'driver_id' => $driverId]); // ✅ crea lo suyo
```

Ejemplo incorrecto (cómo estaba antes):

```php
// Shippings tocando las tripas de Orders ❌
$order = Order::withoutGlobalScope(OwnedByUserScope::class)->findOrFail($id);
if (! $order->status->canTransitionTo(...)) { ... }   // regla duplicada
$order->status = OrderStatusEnum::Shipped;
$order->save();
$order->shippings()->create([...]);                    // Orders conocía a Shippings
```

### Relaciones Eloquent entre módulos

Solo en la dirección de la dependencia:

```php
// ✅ Shippings/Models/Shipping.php
public function order() { return $this->belongsTo(Order::class); }

// ❌ Orders/Models/Order.php
public function shippings() { return $this->hasMany(Shipping::class); }
```

¿Y si necesito "los envíos de esta orden"? Es una consulta **del módulo Shippings**:

```php
Shipping::where('order_id', $order->id)->get();
// o un método con nombre: Shipping::forOrder($order)
```

Las **foreign keys** entre tablas de distintos módulos están bien: son integridad de datos, no acoplamiento de código.

---

## Action o evento

Dos formas de que un módulo provoque algo en otro:

| | **Llamar una Action** | **Emitir un evento** |
|---|---|---|
| Dirección | El que llama conoce al llamado | El que emite **no** conoce a quien escucha |
| Flujo | **Explícito**: leés el código y lo ves | **Implícito**: hay que buscar listeners |
| Transacción | Natural (mismo `DB::transaction`) | Hay que cuidarla (`afterCommit`) |
| Errores | Se propagan y hacen rollback | Pueden quedar silenciados |
| Cola | No | Fácil (`ShouldQueue`) |

**Regla práctica:**

- **Action** cuando el resultado es **parte del caso de uso** y tiene que ser atómico. "Asignar conductor = crear envío + orden a shipped". Si uno falla, no pasa nada.
- **Evento** cuando es un **efecto secundario** que puede fallar o demorarse sin romper el caso principal, o cuando el emisor **no debe** conocer al receptor. "Orden pagada → mandar mail", "Subcategoría borrándose → que Products opine".

```php
// Orders emite (no sabe quién escucha)
OrderPaid::dispatch($order);

// Notifications escucha (en SU provider)
Event::listen(OrderPaid::class, SendOrderConfirmationEmail::class);
```

---

## Ciclos actuales y cómo romperlos

### 1. `Categories ↔ Products`

**Hoy:**
- `Products → Categories`: `Product::subcategory()` (✅ natural: un producto pertenece a una subcategoría).
- `Categories → Products`: `PublicCategoryController`, `PublicSubcategoryController`, `PublicFamilyProductController`, `PublicFamilyOptionController` importan `Product`, `Option`, `Feature` (❌).

**Solución:** los endpoints que **listan productos** son del módulo **Products**, aunque la URL hable de familias:

```
GET api/public/families/{family}/products   → Products/Http/Controllers/PublicFamilyProductController
GET api/public/families/{family}/options    → Products/Http/Controllers/PublicFamilyOptionController
```

La URL describe el recurso; el módulo se elige por **qué datos devuelve**. Categories queda con families/categories/subcategories puros. El evento `SubcategoryDeleting` ya está bien resuelto (Categories emite, Products escucha).

### 2. `Users ↔ Addresses`

**Hoy:** `User::addresses()` importa `Address`; `Address` (vía `BelongsToUser`) importa `User`.

**Solución:** borrar `User::addresses()`. Las direcciones del usuario se consultan desde Addresses: `Address::query()->get()` ya viene filtrado por el scope de ownership.

---

## Señales de que un módulo está mal cortado

- Dos módulos que **siempre** cambian juntos en cada feature → probablemente son uno.
- Un módulo que necesita conocer **casi todos** los demás → es un "orquestador" (ej. Checkout) y debería estar en el nivel más alto.
- Un módulo sin tablas ni reglas propias que solo reenvía llamadas → no es un módulo.
- Imports cruzados en ambas direcciones → ciclo, ver arriba.

### Chequeo rápido de dependencias

```bash
# ¿Quién importa a Orders?
rg -l "App\\\\Modules\\\\Orders" app/Modules --glob '!app/Modules/Orders/**'

# ¿Orders importa a módulos "de arriba"? (no debería)
rg -n "App\\\\Modules\\\\(Shippings|Payments)" app/Modules/Orders
```
