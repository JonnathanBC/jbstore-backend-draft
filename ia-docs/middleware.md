# Middleware & Authorization

- Never check roles manually in controllers
- Use middleware like 'auth' and 'can'
- Prefer policies for complex logic

Example:

Route::middleware(['auth', 'can:admin'])->group(...)
