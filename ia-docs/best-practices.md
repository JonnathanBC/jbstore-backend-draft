# Laravel Best Practices

- Keep controllers thin
- Use Form Requests for validation
- Use policies for authorization
- Follow RESTful routes

## Authorization with Policies

Use `can:admin` middleware instead of manual role checks:

```php
// routes/admin.php
Route::middleware(['auth:sanctum', 'can:admin'])->group(...)

// AdminPolicy.php
public function admin(User $user): bool
{
    return $user->role === 'ROLE_ADMIN';
}
```

- Create policy with method (e.g., `admin()`)
- Register in `AppServiceProvider`
- Use `can:method` in routes
