# Sistema de direcciones

Cómo funcionan las direcciones en JB Store y qué validar **siempre** antes de una compra.

> Relacionado: `ia-docs/user-ownership-scope.md` (scope global por usuario).

## Modelo elegido: "estilo Amazon"

- El usuario tiene **una libreta de direcciones**. Las direcciones **no tienen tipo** (no hay "envío" ni "facturación").
- Hay **UNA dirección predeterminada por usuario**. Es la de envío que aparece preseleccionada en el checkout.
- La **facturación NO se guarda en la libreta**. Va atada al **método de pago**, cuando exista: al cargar una tarjeta se elige cuál de las direcciones del usuario es su dirección de facturación (`billing_address_id`). Por defecto se sugiere la misma que la de envío.

### Por qué este modelo

| Alternativa | Problema |
|---|---|
| Columna `type` (envío/facturación) con una predeterminada por tipo | Obliga a duplicar la misma dirección si sirve para las dos cosas. Además, la facturación es un dato del **pago**, no del domicilio. |
| Punteros `default_shipping` / `default_billing` en el usuario (Magento) | Más flexible, pero más complejo de lo que el proyecto necesita hoy. |

## Cómo lo hacen los ecommerce grandes

Es una referencia general de cómo funcionan desde el lado del usuario. No describe su implementación interna.

| Ecommerce | Cómo maneja las direcciones |
|---|---|
| **Amazon** | Una libreta, una predeterminada de envío. La facturación va con cada tarjeta. En el checkout la dirección aparece preseleccionada y se puede cambiar. Al confirmar, la orden guarda una copia. |
| **Tiendamia** (compras internacionales hacia Latam) | Además de la dirección, pide **datos de identificación** (cédula) porque el paquete pasa por **aduana**. La dirección tiene que servirle al courier local: teléfono y referencias son clave. |
| **Mercado Libre** | Varias direcciones con predeterminada. Suma **puntos de retiro** (agencias o lockers) como alternativa a la entrega a domicilio, y usa la ubicación para calcular el costo y el tiempo de envío. |
| **Shopify** (tiendas chicas y medianas) | Una dirección predeterminada. Facturación "igual que el envío" por defecto, con opción de cambiarla en el checkout. |

**Lo que todos tienen en común** (y es lo que importa copiar):
1. Libreta de direcciones del usuario con **una predeterminada**.
2. Dirección **preseleccionada** en el checkout, fácil de cambiar.
3. Costo y cobertura de envío **calculados en el servidor** a partir de la dirección.
4. La orden guarda una **foto** de la dirección, no una referencia.
5. Facturación separada del envío. Por defecto es la misma, pero se puede cambiar.

## Cómo escalar (por etapas)

No construyas todo hoy. Cada etapa se apoya en la anterior **sin reescribir**:

| Etapa | Qué agregar | Qué cambia en el modelo |
|---|---|---|
| **1. Hoy** | Libreta + una predeterminada + foto en la orden | Lo que ya existe + `orders.shipping_address` (JSON) |
| **2. Checkout real** | Costo de envío por provincia o ciudad y cobertura | Tabla `shipping_zones` (provincia → costo, días, activa) |
| **3. Pagos** | Métodos de pago guardados con su dirección de facturación | `payment_methods.billing_address_id` (nullable, `nullOnDelete`) |
| **4. Facturación electrónica** | Cédula o RUC y razón social del comprador | Datos fiscales en el **cliente** o en la **orden**, no en la dirección |
| **5. Retiro en punto** | Agencias o lockers como alternativa al domicilio | Tabla `pickup_points`. La orden guarda la foto del punto elegido |
| **6. Precisión de entrega** | Geolocalización (lat/lng) y validación de dirección | Columnas `latitude` / `longitude` nullable en `addresses` |
| **7. Internacional** | Varios países, formatos distintos, aduana | `country` ya existe. Validación de campos **por país** e identificación para aduana |

**Por qué este diseño escala**: la dirección es solo *dónde vive el usuario*. El envío (zonas, puntos de retiro), el pago (facturación) y lo fiscal (cédula o RUC) viven en **sus propios módulos** y **referencian** la dirección. Así, agregar una etapa nueva no obliga a tocar la tabla `addresses` ni rompe las órdenes viejas, porque esas ya tienen su foto.

## Reglas de negocio

1. **Pertenencia**: cada dirección es de un usuario (`user_id`). El global scope `OwnedByUserScope` hace que un usuario **solo vea y toque las suyas**. Una dirección ajena devuelve **404**.
2. **Una sola predeterminada**: `Address::markAsDefault()` desmarca las demás del usuario y marca esta, dentro de una **transacción**.
3. **La primera es predeterminada**: si el usuario crea su primera dirección, queda predeterminada automáticamente.
4. **`is_default: true` al crear**: si se crea una dirección marcada como predeterminada, la anterior se desmarca. Es la misma regla que en el punto 2.
5. **El cliente nunca manda `user_id`**: lo asigna el servidor (el trait `BelongsToUser`). `user_id` no está en `$fillable`.

### Dónde vive cada regla

- **Pertenencia** → `App\Concerns\BelongsToUser` + `OwnedByUserScope`.
- **Una sola predeterminada** → `Address::markAsDefault()`. Está en **un solo lugar** y lo usan `store` y `setDefault`.
- `markAsDefault()` filtra por el `user_id` de la propia dirección, **no depende del scope**. Así un job o un comando sin usuario autenticado no puede desmarcar las direcciones de todos.

## Endpoints

Todos van bajo `auth:sanctum`.

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | `/api/addresses` | Lista las direcciones del usuario. |
| `POST` | `/api/addresses` | Crea una dirección (y aplica las reglas 3 y 4). |
| `PATCH` | `/api/addresses/{address}/default` | La marca como predeterminada. |

Pendientes: `PATCH /api/addresses/{address}` (editar) y `DELETE /api/addresses/{address}` (borrar). Ver "Editar y borrar".

## Frontend (React Router 7)

- El listado viene del `loader` de la ruta `_app.address`.
- Las acciones por dirección (predeterminada, borrar) usan `fetcher.Form` con un **`intent`** en el `name`/`value` del botón. El `action` resuelve con un `switch (intent)`, y el caso `default` es la creación.
- Después de cada `action`, React Router **revalida el `loader` sola**, así que la lista se actualiza sin `setState`.

## Editar y borrar (a tener en cuenta)

- **Editar**: nunca debe cambiar lo que dice una orden ya hecha. Esto se cumple gracias a la *foto* (ver checklist).
- **Borrar la predeterminada**: si quedan otras direcciones, hay que promover una como nueva predeterminada, por ejemplo la más reciente. Si no, el usuario queda sin predeterminada y el checkout arranca vacío.
- **Borrado físico vs `SoftDeletes`**: como las órdenes guardan una copia de la dirección, se puede borrar físicamente sin romper el historial. Usá `SoftDeletes` solo si hace falta auditoría.
- **Dirección usada como facturación de una tarjeta**: cuando existan métodos de pago, borrarla tiene que pedir otra dirección de facturación para esa tarjeta, o impedir el borrado.

## ✅ Checklist: SIEMPRE antes de confirmar una compra

Esto va en el **backend**, al crear la orden. El frontend puede mostrar avisos, pero **no es la fuente de verdad**.

### 1. La dirección existe y es del usuario
- Buscala con `Address::findOrFail($id)`. El scope da 404 si es ajena.
- **Nunca** aceptes los datos de la dirección copiados del body del request. Solo el **id**, y los datos se leen de la base.

### 2. La dirección está completa
- Revisá que los campos obligatorios no estén vacíos: `address_line_1`, `city`, `province`, `country`, `phone`.
- El **teléfono** es clave para el courier. Sin teléfono no hay entrega.

### 3. Se puede enviar a esa dirección
- **Cobertura**: ¿llegás a esa provincia o ciudad? Si no, rechazá con un mensaje claro *antes* de cobrar.
- **Costo de envío**: se calcula en el **servidor** a partir de la dirección. Nunca tomes el costo que manda el cliente.

### 4. Revalidar al confirmar, no solo al elegir
Entre que el usuario elige la dirección y aprieta "Pagar" pueden pasar minutos, y en otra pestaña pudo haberla editado o borrado. Revalidá los puntos 1 a 3 **en el mismo request que crea la orden**.

### 5. Guardar una FOTO de la dirección en la orden
- La orden guarda una **copia** de la dirección (`shipping_address` y `billing_address` como JSON o columnas propias), **no solo el `address_id`**.
- **Por qué**: si el usuario después edita o borra la dirección, la orden de hace seis meses tiene que seguir diciendo a dónde se envió de verdad. También lo necesitás para facturas, reclamos y devoluciones.
- Si querés, guardá además el `address_id` como referencia, pero **nullable** (`nullOnDelete`).

### 6. Dirección de facturación
- Cuando haya métodos de pago, la facturación sale del **método de pago elegido**.
- Hasta entonces, por defecto se usa la **misma dirección de envío** (también copiada en la orden).
- En Ecuador, si se emite **factura electrónica**, la facturación además necesita los datos fiscales del comprador (cédula o RUC, razón social). Eso no es parte de la dirección: es un dato aparte del cliente o de la orden.

### 7. Todo en una transacción
Crear la orden, copiar las direcciones, descontar stock y registrar el pago van **dentro de la misma transacción**. Si algo falla, no queda una orden a medias.

## Tests que tienen que existir

- [ ] El usuario A no ve ni modifica direcciones de B (404).
- [ ] `setDefault` desmarca la anterior y no toca a otros usuarios.
- [ ] La primera dirección queda predeterminada.
- [ ] `POST` con `is_default: true` desmarca la anterior.
- [ ] (Checkout) Crear una orden con una dirección ajena → 404.
- [ ] (Checkout) Editar la dirección después de comprar **no** cambia la orden.
- [ ] (Checkout) Una dirección sin teléfono o fuera de cobertura → rechazada antes de cobrar.
