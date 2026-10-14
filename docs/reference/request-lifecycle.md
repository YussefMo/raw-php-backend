# Request Lifecycle

How a request travels from a browser (or `curl`) to MySQL and back — and how
authentication and authorization fit into that journey.

Read this when you want to know **"what actually happens when I call the API?"**

---

## The big picture

Every request — public, login, protected, or invalid — follows the same path:

```
Browser / curl
      │  HTTP request
      ▼
PHP built-in dev server        php -S localhost:8000 -t public public/index.php
      │  the file IS the router script, so every URL lands here
      ▼
public/index.php               Front controller
      │  • Composer autoloader
      │  • Config (.env)
      │  • Database (PDO)
      ▼
App\Core\Application           Composition root
      │  builds objects, holds the route table, dispatches
      ▼
App\Http\Request::capture()    method, path, headers, JSON body
      ▼
App\Core\Router                match METHOD + path
      │  ── no match ──────────────► 404 (or 405 if path exists, method wrong)
      ▼
Middleware pipeline            CorsMiddleware → AuthMiddleware → RoleMiddleware
      │  ── rejected ─────────────► 401 / 403 returned immediately
      ▼
Controller                     read input, validate, call model
      ▼
Model (Post / User)            prepared SQL
      ▼
Database\Database (PDO) → MySQL
      ▼
Controller                     builds Response::json(...)
      ▼
App\Http\Response::send()      status + headers + JSON body
      ▼
Content-Type: application/json
```

---

## Step by step (a public read: `GET /posts/1`)

1. **The server hands every URL to `public/index.php`.** Because the dev server
   is started with `public/index.php` as its *router script*, there is no
   filesystem-based routing any more. This file is the single front controller.

2. **The front controller boots the app.** It loads Composer's autoloader (so
   `App\…` classes resolve), reads `.env` through `App\Config\Config`, opens a
   PDO connection through `App\Database\Database`, and builds
   `App\Core\Application`.

3. **The request is captured.** `App\Http\Request::capture()` reads the HTTP
   method, the path, the headers, and — for JSON bodies — decodes
   `php://input`. From here on, nothing else touches `$_GET`/`$_POST`.

4. **The router matches.** `Application` registered routes like
   `GET /posts/{id}`. The router compiles `{id}` into a regex, matches the path,
   and extracts `id = 1` into the request's params.

5. **Middleware runs.** The pipeline is `CorsMiddleware → (route middleware)`.
   For this public route there is no auth, so control passes straight through.

6. **The controller runs.** `PostController::show()` reads the id, calls the
   model, and returns a `Response`.

7. **The model queries MySQL.** `Post::find(1)` runs a prepared `SELECT` with a
   `LEFT JOIN categories`, returning a plain array (or `null`).

8. **The response is sent.** `Response::json()` encodes the data and
   `Response::send()` writes the status code, headers, and body.

---

## The authentication flow

Registration and login are deliberately two separate steps.

### 1. Register — `POST /auth/register`

```
Request body { name, email, password, role? }
   ▼
AuthController::register()
   • validate name / email / password / role  ──problem──► 422 + errors
   • role must be in Role::selfAssignable()   ──problem──► 422 (never admin)
   • email already taken?                     ──problem──► 409
   ▼
password_hash($password, PASSWORD_DEFAULT)          ← PHP built-in hashing
   ▼
User::create(...)  → INSERT INTO users (...)
   ▼
201 { "user": { id, name, email, role, created_at } }   ← no password_hash!
```

### 2. Login — `POST /auth/login`

```
AuthController::login()
   • User::findByEmail(email)
   • user missing OR !password_verify(...)  ──► 401 " Invalid email or password."
   ▼
JwtService::issue(id, role, email)          ← firebase/php-jwt signs the token
   ▼
200 { access_token, token_type: "Bearer", expires_in, user }
```

The token payload (`JWT::encode`) contains:

| claim   | meaning                                   |
| ------- | ----------------------------------------- |
| `iss`   | issuer (`JWT_ISSUER`)                     |
| `iat`   | issued-at timestamp                       |
| `exp`   | expiry (`iat + JWT_TTL`)                  |
| `sub`   | the user id — what auth reads on the way in |
| `role`  | convenience for the client (NOT trusted)  |
| `email` | convenience for the client                |

### 3. A protected request — `GET /admin/users`

```
Authorization: Bearer <token>
   ▼
Request::bearerToken()               strips "Bearer "
   ▼
AuthMiddleware
   • no token?                         ──► 401
   • JwtService::decode() throws?      ──► 401  (expired / tampered / malformed)
   • User::findById(sub) is null?      ──► 401  (account deleted)
   • otherwise: $request->setUser($user)   ← identity is now known
   ▼
RoleMiddleware([admin])
   • no user on request?               ──► 401
   • user.role not in [admin]?         ──► 403  ("we know you, but no")
   ▼
UserController::index()  → 200 { data: [ ...users, no hashes... ] }
```

### 401 vs 403 — a rule of thumb

- **401 Unauthorized** — "we don't know who you are." Missing/invalid token.
- **403 Forbidden** — "we know who you are, but your role can't do this."

The role is loaded fresh from the database on every request, so changing a
user's role takes effect on their next call without touching the token.

---

## The migration lifecycle (CLI, not HTTP)

`composer run migrate` never touches the web pipeline:

```
database/migrate.php
   ▼
Config + Database (same classes the web app uses)
   ▼
Migrator::run()
   1. create `migrations` ledger table if missing
   2. SELECT migration FROM migrations          ← what already ran
   3. for each file in database/migrations/*.php (sorted):
        already recorded?              → skip
        its table already exists in DB? → record + skip   ← "check DB first"
        otherwise                       → run up() + record
```

---

## Where to look in the code

| Concept                     | File                                             |
| --------------------------- | ------------------------------------------------ |
| Front controller            | `public/index.php`                               |
| Composition root + routes   | `src/Core/Application.php`                       |
| Route matching / pipeline   | `src/Core/Router.php`                            |
| Request / Response          | `src/Http/Request.php`, `src/Http/Response.php`  |
| CORS / auth / role          | `src/Http/Middleware/*.php`                      |
| JWT + roles                 | `src/Auth/JwtService.php`, `src/Auth/Role.php`   |
| Controllers                 | `src/Controllers/*.php`                          |
| Models                      | `src/Models/*.php`                               |
| PDO connection              | `src/Database/Database.php`                      |
| Migrations                  | `src/Database/Migrator.php`, `database/`         |

See also: [`architecture.md`](architecture.md) and [`glossary.md`](glossary.md).
