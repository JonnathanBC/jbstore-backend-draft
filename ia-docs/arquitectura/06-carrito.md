# 6. Carrito: invitado, usuario y merge

> Objetivo de producto: **el usuario nunca pierde lo que agregó.** Navega sin cuenta, agrega cosas, inicia sesión (o se registra, o entra con Google) y su carrito sigue ahí, sumado a lo que ya tenía guardado. Sin pantallas extra, sin preguntas.

Implementación detallada del paquete y de la alternativa sin paquete: [`../cart.md`](../cart.md). Acá va el **diseño** y las reglas para que funcione al 100%.

## Las dos formas de guardar el carrito del invitado

| | **A. Cookie en el front (hoy)** | **B. Tabla en el backend** |
|---|---|---|
| Dónde vive | Cookie firmada `guest_cart` del servidor React Router | Tabla `carts` con `guest_token` |
| Qué guarda | Solo `product_id`, `quantity`, `selected_features` | Lo mismo, en filas |
| Límite | ~4 KB por cookie | Ninguno práctico |
| Multi-dispositivo (invitado) | No | No (el token es por navegador igual) |
| Carritos abandonados (marketing) | No se ven | Se pueden analizar |
| Complejidad backend | Ninguna | Tabla + limpieza de carritos viejos + token |
| Seguridad | Firmada + `httpOnly`: el JS del navegador no la lee ni la falsifica | Token aleatorio en cookie `httpOnly`, guardado **hasheado** |

**Recomendación para este proyecto: quedarse con A** mientras no necesites analizar carritos abandonados o carritos de invitado enormes. Es simple y ya está bien hecha. Pasar a B cuando el negocio lo pida (ver abajo).

## Hoy (A): cómo funciona

### Invitado

`app/server/guestCart.server.ts` (cliente):

```ts
const guestCartCookie = createCookie('guest_cart', {
  httpOnly: true,                                    // el JS del navegador no la ve
  sameSite: 'lax',
  secrets: [process.env.SESSION_SECRET],             // firmada: no se puede editar
  secure: process.env.NODE_ENV === 'production',
  maxAge: 60 * 60 * 24 * 30,                         // 30 días
})
```

- Guarda **solo IDs y cantidades**. Nunca precios: el precio siempre sale de la base al mostrar y al cobrar.
- Para mostrar el carrito del invitado, el front pide los productos por ID (`api/public/products/by-ids`) y calcula con precios reales.

### Usuario logueado

- Tabla `shoppingcart`, una fila por usuario (`identifier = user_id`, `instance = shopping`).
- Todas las rutas `api/cart/*` requieren `auth:sanctum`.
- Persistido en DB → lo ve desde cualquier dispositivo.

### El merge (el momento clave)

```
Login / Registro / Google callback  (servidor React Router)
  │
  ├─ 1. guarda el token en la sesión
  ├─ 2. lee la cookie guest_cart
  ├─ 3. POST api/cart/merge { items }  (con el token del usuario)
  │        └─ Laravel: restaura el carrito del usuario, agrega cada item
  │           validando producto/variante, persiste
  ├─ 4. si salió bien → borra la cookie guest_cart
  └─ 5. redirect
```

Está en `createUserSession` → `mergeGuestCartOnLogin`. Al estar en el **mismo** punto que crea la sesión, cubre los tres caminos (login, registro, Google) sin que cada uno se acuerde de hacerlo.

Si el merge falla, la cookie **no se borra** → se reintenta en el próximo login. El usuario no pierde nada.

---

## Reglas para que el merge funcione al 100%

### 1. El merge tiene que ser idempotente

**Problema hoy:** `merge` llama a `addItem`, que **suma** cantidades. Si el merge se ejecuta dos veces con la misma cookie, se duplican:

- Laravel hizo el merge, pero la respuesta (con el `Set-Cookie` que borra la cookie) no llegó al navegador por un corte de red.
- El usuario vuelve a iniciar sesión → merge de nuevo → **2 remeras pasan a ser 4**.

**Solución:** para items que ya están en el carrito del usuario, usar **la cantidad mayor** en vez de sumar:

| Usuario tenía | Invitado agregó | Suma (hoy) | Máximo (recomendado) |
|---|---|---|---|
| — | 2 | 2 | 2 |
| 1 | 2 | 3 | 2 |
| 2 | 2 (merge repetido) | **4** ❌ | 2 ✅ |

`max(a, b)` aplicado dos veces da lo mismo → **idempotente por diseño**. Y casi nunca el usuario quiere el doble de lo mismo: si había agregado 2 remeras logueado y 2 como invitado, casi siempre es la misma intención.

En código: `MergeGuestCart` (Action en Cart) que, por cada item del invitado, busca la línea con la misma variante y hace `qty = max(actual, invitado)`; si no existe, la agrega.

### 2. Validar todo lo del invitado como si viniera de un desconocido

Porque viene de un desconocido. Cada item:

- Producto existe y está publicado → si no, se omite.
- Variante válida para las features elegidas → si no, se omite.
- Cantidad recortada al stock disponible (`clampToStock`). ⚠️ Hoy la validación de stock está **comentada** en `CartController::addItem`: reactivarla.
- Límite de líneas (hoy 50 en `MergeCartRequest`).

Items inválidos **se omiten** sin romper el login. Nunca un producto descontinuado puede impedir que alguien inicie sesión.

### 3. Contarle al usuario qué pasó (sin preguntarle)

El merge devuelve el carrito. El front compara y muestra un toast:

- "Sumamos 3 productos que agregaste antes de iniciar sesión."
- Si se omitieron: "1 producto ya no está disponible y lo quitamos."

No hace falta un modal de "¿qué carrito querés conservar?": agrega fricción justo en el login.

### 4. Precios y stock se revalidan al pagar

El carrito es una **intención**, no una reserva. Entre que agregó y que paga puede cambiar el precio o agotarse el stock:

- Al mostrar el carrito: precios actuales de la DB.
- Al iniciar el pago (`StartPayment`): se fija el monto en `payments.amount`.
- Al capturar: si el carrito cambió → no cobrar, pedir que revise (ver [05](05-pagos-e-idempotencia.md)).

### 5. Logout

- El carrito del usuario queda en `shoppingcart` (lo recupera al volver a entrar).
- La cookie de invitado arranca vacía. Lo que agregue deslogueado se mergea en su próximo login.

### 6. Tamaño de la cookie

Una cookie tiene un máximo de ~4 KB, **incluyendo la firma**. Con 50 líneas y varias features por línea te podés pasar, y el navegador **descarta la cookie entera en silencio**: el invitado pierde todo el carrito.

- Bajá el límite de líneas del invitado (ej. 20), o
- Comprimí el formato (`[[productId, qty, {opt: feat}], ...]` en vez de objetos con claves largas), o
- Pasá a la opción B.

---

## Cuándo pasar a la opción B (carrito de invitado en la base)

Cuando necesites **cualquiera** de estas:

- Analizar carritos abandonados de invitados / mandar recordatorios.
- Carritos de invitado grandes (B2B, mayoristas).
- Que el backend sea la única fuente de verdad del carrito (apps móviles que no pasan por el servidor de React Router).

Diseño:

```
carts
├── id
├── user_id          FK users nullable UNIQUE   ← un carrito por usuario
├── guest_token_hash string nullable UNIQUE     ← sha256 del token
├── expires_at       timestamp nullable          ← invitados: 30 días
└── timestamps

cart_items
├── id
├── cart_id          FK carts cascade
├── product_id       FK products
├── variant_id       FK variants nullable
├── quantity
└── UNIQUE (cart_id, product_id, variant_id)     ← una línea por variante
```

- El token del invitado: `Str::random(40)`, guardado **hasheado** en la DB y en claro en una cookie `httpOnly` del front. Si se filtra la base, los tokens no sirven.
- Merge en login: misma regla de `max()`, dentro de una transacción, y después `delete` del carrito invitado (idempotente: si ya no existe, no hay nada que mergear).
- Comando programado que borra carritos de invitado vencidos.
- El `UNIQUE (cart_id, product_id, variant_id)` hace que el merge concurrente no pueda duplicar líneas.

## Tests obligatorios

- [ ] Invitado agrega → login → el carrito del usuario tiene esos items.
- [ ] Usuario ya tenía la misma variante → queda `max(cantidades)`.
- [ ] **Merge ejecutado dos veces con los mismos items → mismo resultado.**
- [ ] Producto inexistente/sin variante válida en la cookie → se omite, el login funciona.
- [ ] Cantidad mayor al stock → se recorta.
- [ ] Más items que el límite → 422 sin romper la sesión.
- [ ] Merge falla → la cookie de invitado se conserva.
