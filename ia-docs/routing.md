# Laravel Routing Best Practices

- Always group routes with middleware
- Use route prefixes for admin areas
- Use named routes
- Do not put logic in routes

Example:

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminController::class, 'index']);
    });