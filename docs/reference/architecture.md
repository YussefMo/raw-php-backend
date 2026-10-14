# Architecture

How the project is organised, why, and how to add new behavior without making a
mess. Pair this with [`request-lifecycle.md`](request-lifecycle.md).

---

## Layers and their responsibilities

The app is split into layers. Each layer has **one job** and only talks to the
layer directly beneath it.

```
public/                 ← HTTP entry point (thin bootstrap only)
   │
src/Core/               ← Application (wiring + routes), Router, HttpException
   │
src/Http/               ← Request, Response, Middleware (CORS / Auth / Role)
   │
src/Controllers/        ← HTTP layer: validate input, call models, build Response
   │
src/Models/             ← Data layer: SQL + mapping rows to data
   │
src/Database/           ← PDO connection + migration runner
   │
src/Config/             ← Environment / settings
```

| Layer              | Key files                                             | Responsibility                                            | Must NOT do                     |
| ------------------ | ----------------------------------------------------- | --------------------------------------------------------- | ------------------------------- |
| `public/`          | `index.php`                                           | Bootstrap, run the app                                    | business logic, SQL             |
| `Core`             | `Application`, `Router`, `HttpException`              | Wire objects, match routes, run middleware pipeline       | SQL, business rules             |
| `Http`             | `Request`, `Response`, `Middleware/*`                 | Represent HTTP; cross-cutting concerns (CORS, auth, roles)| SQL, business rules             |
| `Auth`             | `Role`, `JwtService`                                  | Roles and JWT issuing/verifying                           | SQL, echoing output             |
| `Controllers`      | `AuthController`, `PostController`, `UserController`  | Read input, validate, call models, return `Response`      | write SQL, **echo**             |
| `Models`           | `User`, `Post`                                        | Own the SQL, map rows to objects/arrays                   | know about HTTP, echo           |
| `Database`         | `Database`, `Migrator`, `MigrationInterface`          | Connection + schema changes                               | business rules                  |
| `Config`           | `Config`                                              | Read `.env`, hand out values                              | anything else                   |

### The one-way rule

Data flows **down** the layers; nothing downstream reaches back up:

```
public → Core(Application/Router) → Middleware → Controller → Model → Database → MySQL
```

A model never prints JSON. A controller never writes SQL and never `echo`es.
`public/index.php` never implements business rules. Keeping this clean is the
whole point of the exercise.

---

## Composition root

`src/Core/Application.php` is where everything is built and wired **by hand**
(no DI container) and where the whole route table lives:

```php
$jwt = new JwtService($config);
$users = new User($db);
$posts = new Post($db);

$authController = new AuthController($users, $jwt);
$postController = new PostController($posts);

$auth = new AuthMiddleware($jwt, $users);

$this->router = new Router([new CorsMiddleware()]);      // global middleware

$this->router->get('/posts/{id}', [$postController, 'show']);          // public
$this->router->get('/auth/me', [$authController, 'me'], [$auth]);      // authenticated
$this->router->get('/admin/users', [$userController, 'index'], [       // admin only
    $auth,
    new RoleMiddleware([Role::Admin->value]),
]);
```

Read this file first — it is the map of the API.

---

## The middleware pipeline

Middleware wraps the controller like the layers of an onion. The first
middleware runs first and its response unwinds last:

```
CorsMiddleware
  └─ AuthMiddleware
       └─ RoleMiddleware
            └─ Controller  → Response
```

Each middleware implements:

```php
public function handle(Request $request, callable $next): Response
```

- Call `$next($request)` to continue to the next layer.
- **Return a `Response` early** to short-circuit (that is how a `401` stops the
  request before the controller runs).

The router builds the chain from the route's middleware list (plus the global
middleware) and runs it.

---

## How to add an endpoint

1. **Add a model method** in `src/Models/` (SQL only, returns data).
2. **Add a controller method** in `src/Controllers/` that receives
   `Request`, validates input, calls the model, and returns
   `Response::json(...)`. Use `HttpException` for expected failures.
3. **Register the route** in `src/Core/Application.php`:
   ```php
   $this->router->get('/posts/{id}/comments', [$commentController, 'index'], [$auth]);
   ```
   Route placeholders use `{name}` and are read with `$request->param('name')`.
4. **Choose middleware** for the route:
   - public → none
   - any logged-in user → `[$auth]`
   - specific roles → `[$auth, new RoleMiddleware([Role::Admin->value])]`
5. **Test it** with `curl` and run the PHP formatter.

Do not add role checks inside the controller — put them on the route.

---

## How to add a migration

1. Create `database/migrations/YYYY_MM_DD_NNNNNN_description.php`.
2. Return an anonymous class implementing `App\Database\MigrationInterface`:
   ```php
   return new class () implements MigrationInterface {
       public function table(): ?string { return 'comments'; }
       public function up(PDO $db, Config $config): void { /* CREATE TABLE ... */ }
       public function down(PDO $db): void { /* DROP TABLE ... */ }
   };
   ```
3. Run `composer run migrate`.
4. Keep it **idempotent** and never touch tables owned by other migrations.

See `database/migrations/2026_10_14_000001_create_users_table.php` for a working
example that also seeds the admin from `.env`.

---

## Design principles used here (and why)

- **Single Responsibility.** One class ≈ one job (`Config` reads config,
  `Router` matches routes, `Post` queries posts).
- **Constructor injection.** Classes receive dependencies (`AuthMiddleware` gets
  `JwtService` + `User`), which makes them explicit and testable.
- **Explicit over magic.** No reflection, annotations, or auto-wiring.
- **Fail loud.** `Config`/`Database` throw; `HttpException` maps controller
  failures to status codes; unexpected errors become a 500.
- **Prepared statements everywhere.** User input never gets concatenated into SQL.
- **Authorization at the edge.** Roles are enforced by middleware on the route,
  not deep inside business logic.

---

## Testing

There is no test suite yet. Verify changes with `curl` (see AGENTS.md §4). When
tests are added, prefer plain PHPUnit: unit-test models and pure functions, then
add a few integration tests for the auth flow.
