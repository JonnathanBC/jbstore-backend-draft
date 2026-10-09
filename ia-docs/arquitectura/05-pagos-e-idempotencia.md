# 5. Pagos e idempotencia

> En pagos hay dos errores imperdonables: **cobrar dos veces** y **ver una tarjeta**. Todo este documento es para que ninguno de los dos pueda pasar, ni siquiera con un usuario que hace doble click, una red que se corta o dos pestañas abiertas.

## Regla 1: la tarjeta NUNCA toca tu servidor

```
 Navegador                         Tu servidor (RR + Laravel)            Niubiz
 ─────────                         ──────────────────────────            ──────
 Formulario de Niubiz  ── número de tarjeta, CVV ─────────────────────▶  (lo guarda Niubiz)
 (lo dibuja el JS de Niubiz)  ◀───────────── transactionToken ─────────
       │
       └── transactionToken ──▶  POST /payments/capture ── token ──────▶  autoriza
                                                         ◀── aprobado ──
```

- El formulario de tarjeta lo **renderiza el script de Niubiz** (`url_js`). Tu código nunca tiene un `<input>` de número de tarjeta.
- Lo que llega a tu backend es un **`transactionToken`** de un solo uso: no sirve para nada fuera de esa compra.
- Lo único que guardás de la tarjeta es lo que Niubiz devuelve **enmascarado** (ej. `455170******8059`) y la marca, para el comprobante.

Esto es lo que el estándar **PCI DSS** llama integración **SAQ A**: todos los elementos que capturan datos de tarjeta vienen directamente del proveedor certificado, y el comercio no almacena, procesa ni transmite datos de tarjeta en sus sistemas. Es el nivel con menos obligaciones, y **se pierde** si algún script tuyo toca los campos de tarjeta.

### Prohibido

- ❌ Inputs propios para número/CVV/vencimiento, aunque "solo los reenvíes".
- ❌ Loguear el body de `/payments/capture` o la respuesta completa de Niubiz sin limpiar.
- ❌ Guardar `transactionToken` después de usarlo.
- ❌ Mandar el tracking de errores (Sentry, etc.) con el estado del formulario de pago.
- ❌ Scripts de terceros innecesarios en la página de checkout (cada script puede leer la página).

---

## Regla 2: el monto lo decide el servidor

✅ **Hoy ya está bien:** `POST /payments/session` y `/payments/capture` calculan el monto con `CartService::totalsFor($userId)`. El `amount` que manda el cliente se valida pero **se ignora**. Nunca confíes en un precio que viene del navegador.

---

## Regla 3: idempotencia

**Idempotente** = ejecutarlo 1 vez o 10 veces produce **el mismo resultado**. Un pago tiene que serlo, porque **los reintentos son normales**:

- Doble click en "Pagar".
- El navegador reenvía el POST al refrescar la página de resultado.
- Timeout: Niubiz cobró, pero la respuesta no llegó a tu servidor (o a tu front) → el usuario reintenta.
- Dos pestañas con el mismo carrito.

### Hoy (problemas)

`PaymentController::capturePayment`:

1. Llama a Niubiz para autorizar.
2. Si `ACTION_CODE === '000'` → `CreateOrderFromCart` → vacía carrito.

| Problema | Consecuencia |
|---|---|
| `purchaseNumber` lo genera el **cliente** y no se guarda | No hay forma de detectar "este pago ya lo procesé" |
| `orders.payment_id` **no es unique** | Dos requests concurrentes pueden crear dos órdenes por el mismo pago |
| Sin lock ni transacción | Dos capturas en paralelo leen el mismo carrito y ambas cobran |
| Si `CreateOrderFromCart` falla después del cobro | Plata cobrada sin orden y sin registro de que pasó |
| Token de acceso de Niubiz se pide en cada llamada, sin timeout | Lento y un cuelgue de Niubiz cuelga tu request |

El único freno actual es casual: como el carrito se vacía al crear la orden, un segundo intento **secuencial** recibe 422. Pero dos intentos **simultáneos** pasan los dos.

### Objetivo: tabla `payments`

```
payments
├── id                       uuid PK
├── user_id                  FK users (restrictOnDelete)
├── purchase_number          string UNIQUE   ← lo genera EL SERVIDOR
├── amount                   decimal(12,2)   ← foto del total al iniciar
├── currency                 char(3)         ← PEN
├── status                   enum: pending | authorized | rejected | error
├── provider                 string          ← niubiz
├── provider_transaction_id  string UNIQUE nullable
├── order_id                 uuid UNIQUE nullable FK orders
├── card_masked              string nullable ← 455170******8059
├── card_brand               string nullable
├── error_message            string nullable
├── provider_response        json nullable   ← SIN datos sensibles
└── timestamps
```

Y en `orders`: `payment_id` **unique**.

Los `UNIQUE` son la **última línea de defensa**: aunque falle toda la lógica, la base de datos no deja crear dos pagos con el mismo número ni dos órdenes por el mismo pago.

### Objetivo: flujo en dos pasos

**Paso 1: `POST /payments/session` → `StartPayment`**

```php
public function handle(int $userId): Payment
{
    $totals = $this->cartService->totalsFor($userId);
    abort_if($totals['subtotal'] <= 0, 422, 'El carrito está vacío.');

    $payment = Payment::create([
        'user_id' => $userId,
        'purchase_number' => $this->nextPurchaseNumber(), // servidor, no cliente
        'amount' => $totals['total'],
        'currency' => 'PEN',
        'status' => PaymentStatusEnum::Pending,
    ]);

    $payment->session_key = $this->niubiz->createSession($payment); // no se persiste
    return $payment;
}
```

Responde `{ sessionKey, purchaseNumber, amount }`. El front usa ese `purchaseNumber` en el formulario de Niubiz.

**Paso 2: `POST /payments/capture { purchaseNumber, transactionToken }` → `CapturePayment`**

```php
public function handle(int $userId, string $purchaseNumber, string $transactionToken): Payment
{
    // 1. Lock por pago: un segundo request ESPERA acá hasta que termine el primero
    return Cache::lock("payment:{$purchaseNumber}", 60)->block(15, function () use (...) {
        return $this->capture($userId, $purchaseNumber, $transactionToken);
    });
}

private function capture(int $userId, string $purchaseNumber, string $transactionToken): Payment
{
    $payment = Payment::where('purchase_number', $purchaseNumber)
        ->where('user_id', $userId)   // nunca el pago de otro usuario
        ->firstOrFail();

    // 2. Replay: ya se procesó → devolver el MISMO resultado, sin volver a cobrar
    if ($payment->status !== PaymentStatusEnum::Pending) {
        return $payment;
    }

    // 3. ¿Cambió el carrito desde que se inició el pago? → no cobrar
    if ($this->cartService->totalsFor($userId)['total'] != $payment->amount) {
        throw ValidationException::withMessages(['cart' => 'Tu carrito cambió. Revisalo y volvé a pagar.']);
    }

    // 4. Cobrar
    $result = $this->niubiz->authorize($payment, $transactionToken);

    if (! $result->approved) {
        $payment->update(['status' => PaymentStatusEnum::Rejected, ...]);
        return $payment;
    }

    // 5. Registrar pago + crear orden: TODO o NADA
    return DB::transaction(function () use ($payment, $result, $userId) {
        $payment->update([
            'status' => PaymentStatusEnum::Authorized,
            'provider_transaction_id' => $result->transactionId,
            'card_masked' => $result->cardMasked,
            'card_brand' => $result->brand,
        ]);

        $order = $this->createOrderFromCart->handle($payment->provider_transaction_id, $userId);
        $payment->update(['order_id' => $order->id]);

        return $payment;
    });
}
```

> El snippet es una guía de diseño: los nombres son orientativos y el `use (...)` hay que completarlo.

#### Lock: ¿base de datos o cache?

El llamado a Niubiz puede tardar segundos. Mantener una transacción de DB abierta mientras esperás una API externa es mala idea (conexiones bloqueadas). Lo práctico:

```php
Cache::lock("payment:{$purchaseNumber}", 30)->block(10, function () { ... });
```

- El primer request toma el lock y procesa.
- El segundo espera; cuando entra, ve `status = authorized` y devuelve el resultado (replay).
- Requiere un driver de cache con locks atómicos compartido (Redis o `database`).

### ¿Qué pasa si cobré pero no pude crear la orden?

Pasa (DB caída, bug). Por eso el pago se marca `authorized` **antes** de que algo más pueda fallar y el error queda registrado:

- El `payment` queda `authorized` con `order_id = null` → **es detectable**.
- Un comando programado (`payments:reconcile`) lista esos casos para reintentar crear la orden o anular el cobro.
- Nunca "tragarse" la excepción: log con `purchase_number`, sin datos de tarjeta.

### Idempotencia genérica para otros POST (opcional)

Para endpoints donde un doble envío es costoso (crear orden manual, reembolsos), el patrón estándar es el header **`Idempotency-Key`**:

1. El cliente genera un UUID por **intento de operación** (no por request) y lo manda en el header.
2. El servidor guarda `(key, user_id, response)` la primera vez.
3. Si vuelve la misma key → devuelve la respuesta guardada sin ejecutar nada.

Para pagos no hace falta: el `purchase_number` ya cumple ese rol.

---

## Cómo quedaría el módulo Payments

```
app/Modules/Payments/
├── PaymentsServiceProvider.php
├── Actions/
│   ├── StartPayment.php          ← crea Payment pending + sesión Niubiz
│   ├── CapturePayment.php        ← lock + replay + autorizar + crear orden
│   └── RefundPayment.php         ← (cuando haya devoluciones)
├── Services/
│   └── NiubizClient.php          ← HTTP: token (cacheado), sesión, autorización, timeouts
├── Enums/PaymentStatusEnum.php
├── Listeners/RefundOnOrderCancelled.php  ← escucha OrderCancelled (de Orders)
├── Models/Payment.php
├── Http/{Controllers,Requests}/
├── Console/ReconcilePayments.php
├── Routes/api.php
└── database/migrations/
```

Dependencias: `Payments → Orders` (usa `CreateOrderFromCart`, escucha `OrderCancelled`) y `Payments → Cart`. **Orders no conoce a Payments.**

`NiubizClient`:
- Cachear el access token hasta poco antes de su vencimiento (`Cache::remember`).
- `Http::timeout(15)->retry(...)` solo en llamadas **seguras de reintentar** (pedir token sí; **autorizar NO**: un retry ciego puede cobrar dos veces).
- Un DTO de respuesta (`AuthorizationResult`) en vez de arrays crudos de Niubiz por todo el código.

## Tests obligatorios (con `Http::fake`)

- [ ] Pago aprobado → 1 payment `authorized`, 1 orden, carrito vacío.
- [ ] **Mismo `purchaseNumber` dos veces → 1 sola orden, 1 solo llamado a Niubiz** (`Http::assertSentCount`).
- [ ] Pago rechazado → payment `rejected`, sin orden, carrito intacto.
- [ ] `purchaseNumber` de **otro usuario** → 404.
- [ ] Carrito cambió entre session y capture → 422 y **no** se llama a autorizar.
- [ ] Falla al crear la orden → payment `authorized` con `order_id` null (detectable).
- [ ] El `amount` enviado por el cliente no afecta lo cobrado.
