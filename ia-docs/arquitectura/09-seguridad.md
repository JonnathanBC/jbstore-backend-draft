# 9. Seguridad

> Un ecommerce maneja tres cosas que un atacante quiere: **plata** (pagos), **identidades** (tokens y datos personales) y **privilegios** (ser admin). Cada sección cubre una.

## Arquitectura: el navegador nunca habla con Laravel

```
 Navegador ──cookie de sesión httpOnly──▶ Servidor React Router ──Bearer token──▶ Laravel API
            (no contiene nada legible)    (tiene el token)                      (valida el token)
```

El token de Sanctum **vive solo en el servidor de React Router**, dentro de una cookie de sesión firmada y `httpOnly`. El JavaScript del navegador no lo puede leer, así que un XSS no puede robarlo.

Esto ya está bien hecho en el cliente (`app/server/session.server.ts`): `httpOnly`, `sameSite: 'lax'`, `secure` en producción, `maxAge` de 7 días, firmada con `SESSION_SECRET`.

**Nunca:** token en `localStorage`, `sessionStorage`, en una variable global de JS, ni en la URL.

---

## 1. Tokens (Sanctum)

| Práctica | Estado | Qué hacer |
|---|---|---|
| Token en cookie `httpOnly` del servidor RR | ✅ | — |
| Logout revoca el token actual | ✅ `currentAccessToken()->delete()` | — |
| Expiración del lado del servidor | ❌ `sanctum.expiration = null` → el token **nunca vence** | Poner `expiration` = vida de la cookie (ej. `60 * 24 * 7` minutos) y programar `sanctum:prune-expired` |
| Token fuera de la URL | ❌ El callback de Google redirige con `?token=...` | Ver abajo |
| Revocar todo al cambiar contraseña | — (no existe el flujo todavía) | `$user->tokens()->delete()` y emitir uno nuevo |
| Rate limit en login/registro | ❌ no hay `throttle` | Ver abajo |

### El token en la URL del callback de Google

Hoy `GoogleAuthController@callback` hace:

```php
return redirect("{$frontendUrl}/api/auth/google/callback?token={$token}");
```

Un token en la URL queda en: el historial del navegador, los logs de acceso de cualquier proxy/servidor y el header `Referer` si la página carga recursos externos. Es un token **de sesión completa**, válido para siempre (ver expiración).

**Solución: código de un solo uso.**

```php
// Callback de Google (Laravel)
$code = Str::random(48);
Cache::put("oauth-code:{$code}", $user->id, now()->addSeconds(60));
return redirect("{$frontendUrl}/auth/google/callback?code={$code}");
```

```php
// POST api/auth/google/exchange { code }   ← lo llama el SERVIDOR de RR, no el navegador
$userId = Cache::pull("oauth-code:{$code}");   // pull = lee y borra: un solo uso
abort_unless($userId, 401);
return ['token' => User::findOrFail($userId)->createToken('auth-token')->plainTextToken];
```

El código vive 60 segundos, sirve una sola vez y por sí solo no da acceso a nada.

### Rate limiting

Sin límite, alguien prueba miles de contraseñas por minuto contra un email.

```php
// AppServiceProvider::boot
RateLimiter::for('login', fn (Request $request) => [
    Limit::perMinute(5)->by(strtolower($request->input('email')).'|'.$request->ip()),
    Limit::perMinute(30)->by($request->ip()),
]);

// Auth/Routes/api.php
Route::post('/login', ...)->middleware('throttle:login');
Route::post('/register', ...)->middleware('throttle:login');
```

Mismo criterio para: `payments/session`, `payments/capture`, `cart/merge` y cualquier endpoint que envíe mails o SMS.

### Mensajes de error de login

✅ `LoginUser` responde siempre *"The provided credentials are incorrect."*. No digas "el email no existe" ni "contraseña incorrecta" por separado: eso le confirma a un atacante qué emails están registrados.

---

## 2. Tarjetas y pagos

Todo en [05-pagos-e-idempotencia.md](05-pagos-e-idempotencia.md). Resumen:

- La tarjeta la captura **el formulario de Niubiz**. Tu código nunca tiene un input de tarjeta → integración PCI **SAQ A**.
- Solo llega un `transactionToken` de un solo uso. No se guarda ni se loguea.
- De la tarjeta guardás solo lo que Niubiz devuelve **enmascarado** y la marca.
- El **monto lo calcula el servidor**, nunca el cliente.
- Cada pago tiene un `purchase_number` único generado por el servidor → idempotencia.
- En la página de checkout, la menor cantidad posible de scripts de terceros.

---

## 3. Autorización y privilegios

### Admin

- ✅ Gate `admin` (`AdminPolicy`) + middleware `can:admin` en todas las rutas `api/admin/*`.
- ✅ Los roles no se chequean a mano en controllers.

### Mass assignment del rol ⚠️

`User::$fillable` incluye `role`. Hoy `RegisterUser` arma el array campo por campo, así que no es explotable. Pero el día que alguien escriba `User::create($request->validated())` o `$user->update($request->all())` en un "editar perfil", cualquiera se hace admin mandando `"role": "ROLE_ADMIN"`.

**Sacar `role` de `$fillable`** y asignarlo solo de forma explícita en una Action de admin:

```php
$user->role = UserRoleEnum::Admin;   // asignación explícita, auditada
$user->save();
```

Mismo criterio para cualquier campo sensible: `status` de órdenes (ya no es fillable ✅), `user_id`, montos, `is_verified`.

### Acceso a datos de otros usuarios (IDOR)

- Modelos del cliente con `BelongsToUser` → `Order::find($idAjeno)` da 404 ([07](07-filtrado-y-ownership.md)).
- Órdenes con **UUID** → no se pueden enumerar (`/orders/1`, `/orders/2`...).
- Toda Action que reciba un id del cliente filtra también por `user_id` (ej. `CapturePayment` busca el pago por `purchase_number` **y** `user_id`).

### Rutas expuestas sin querer ⚠️

`Route::resource('/products', PublicProductController::class)` en `api/public` registra también POST/PUT/DELETE **sin auth**. Hoy fallan porque los métodos no existen, pero el día que alguien los agregue "para probar" quedan públicos. Usá `->only(['index', 'show'])`.

Revisión periódica:

```bash
php artisan route:list --except-vendor --method=POST
php artisan route:list --except-vendor --method=DELETE
# Toda ruta de escritura tiene que tener auth:sanctum (y can:admin si es de admin)
```

---

## 4. Entrada de datos

- Siempre `FormRequest` + `$request->validated()`. Nunca `$request->all()` hacia un modelo.
- Columnas de orden y filtro: **whitelist** (ver [07](07-filtrado-y-ownership.md)). Nunca `orderBy($request->input('field'))`.
- Uploads de imágenes: validar `image|mimes:jpg,png,webp|max:2048`, guardar con nombre aleatorio (`store()` ya lo hace), nunca el nombre original del archivo.
- Redirects: solo rutas internas. ✅ Ya está `safeRedirect` en el cliente contra open redirect.
- `ilike "%{$term}%"` con bindings de Eloquent es seguro contra SQL injection. Lo que nunca se hace es concatenar input en `DB::raw`/`whereRaw`.

## 5. Transporte, cookies y CORS

- HTTPS obligatorio en producción. Cookies con `secure` (✅ en prod).
- `SameSite=Lax` en la sesión (✅): protege los POST de React Router contra CSRF desde otros sitios.
- CORS en Laravel: como el navegador no habla con Laravel (lo hace el servidor RR), `allowed_origins` debería ser **solo** el dominio del front, o directamente sin CORS abierto. Revisar `config/cors.php` y que no quede `'*'`.
- Si es posible, que la API de Laravel no sea accesible desde internet, salvo lo que lo necesite (callback de Google): solo desde el servidor de React Router.

## 6. Secretos y logs

- `.env` nunca en git. `SESSION_SECRET` y `APP_KEY` largos y aleatorios. `SESSION_SECRET` se puede rotar agregando el nuevo **primero** en el array `secrets` del cookie.
- Credenciales de Niubiz solo en `config/services.php` ← `env()`. Nunca `env()` fuera de `config/`.
- **Nunca loguear:** tokens, `transactionToken`, contraseñas, documentos de identidad, respuestas completas de la pasarela. Loguear **identificadores** (`purchase_number`, `order_id`, `user_id`).
- `APP_DEBUG=false` en producción: con debug, un error 500 muestra variables de entorno.

## 7. Dependencias

- `composer audit` y `npm audit` en CI.
- El paquete del carrito es un **fork** (`gloudemans/shoppingcart` para Laravel 13): seguir sus actualizaciones o planear la migración al carrito propio ([`../cart.md`](../cart.md), parte B).

---

## Checklist antes de producción

- [ ] `sanctum.expiration` configurado + `sanctum:prune-expired` programado
- [ ] Callback de Google con código de un solo uso (sin token en la URL)
- [ ] `throttle` en login, registro y pagos
- [ ] `role` fuera de `$fillable`
- [ ] Rutas públicas solo con `->only([...])`
- [ ] Ningún input de tarjeta propio; checkout con mínimos scripts de terceros
- [ ] `APP_DEBUG=false`, HTTPS, CORS cerrado
- [ ] Logs sin tokens ni datos de tarjeta
- [ ] Tests de aislamiento (usuario A no ve recursos de B) en cada módulo con ownership
