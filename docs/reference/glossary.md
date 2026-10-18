# Glossary

Plain-language definitions of every term used in this project. When you meet a
new word in the code or docs, it belongs here.

---

## HTTP & web basics

- **HTTP request** — a message a client (browser, `curl`) sends to a server. It
  has a **method**, a **path**, **headers**, and sometimes a **body**.
- **HTTP method / verb** — what you want to do: `GET`, `POST`, `PUT`/`PATCH`,
  `DELETE`, `OPTIONS`.
- **Path / URI** — the part after the host, e.g. `/posts/1`.
- **Query string** — the `?key=value` part; in PHP it appears in `$_GET`.
- **Route placeholder** — a variable part of a path, written `{id}` here, e.g.
  `/posts/{id}`.
- **Header** — metadata sent with a request or response. `Content-Type:
application/json` says the body is JSON.
- **Status code** — the 3-digit result: `200` OK, `201` Created, `204` No
  Content, `401` Unauthorized, `403` Forbidden, `404` Not Found, `405` Method Not
  Allowed, `409` Conflict, `422` Unprocessable Entity, `500` Server Error.
- **Body** — the actual data (here, the JSON payload).
- **JSON** — a text format for structured data; `json_encode()` built in,
  `json_decode()` to read.
- **CORS** — Cross-Origin Resource Sharing: headers that let a web page on
  another origin call this API. Browsers send an `OPTIONS` "preflight" first.
- **Superglobal** — a PHP array available everywhere: `$_GET`, `$_POST`,
  `$_SERVER`, `$_FILES`, `$_SESSION`, `$_COOKIE`.

---

## PHP language

- **`declare(strict_types=1);`** — enforce declared types strictly; must be the
  first statement.
- **Namespace** — a prefix that avoids name clashes, e.g. `App\Models\Post`.
- **PSR-4 autoloading** — maps namespaces to folders. `App\Models\Post` →
  `src/Models/Post.php`.
- **Composer** — PHP's dependency manager; also generates the autoloader.
- **Autoloader** — includes a class file on first use, so you don't `require`
  every class.
- **`require_once`** — include a file once.
- **Type declaration** — stating expected types, e.g. `connect(): PDO`.
- **Constructor / constructor property promotion** — `__construct()` plus the
  PHP 8 shorthand `public function __construct(private Config $config)` that
  declares _and_ assigns a property.
- **`use` statement** — imports a class so you can write short names.
- **`final` class** — a class that cannot be extended; used here by default for
  clarity.
- **Nullable type** — `?PDO` means "a `PDO` or `null`".
- **Enum (backed enum)** — a type-safe list of allowed values. `Role::Admin` is
  an enum case; `Role::Admin->value` is the string `"admin"`. Makes typos
  impossible.
- **Exception** — an error object that interrupts flow; handled with `try`/`catch`,
  raised with `throw`.
- **`RuntimeException` / `PDOException`** — generic runtime error / PDO error.
- **`HttpException`** — this project's exception that carries an HTTP status code.
- **Closure / arrow function** — an anonymous function, e.g.
  `fn (User $u): array => $u->toArray()`.
- **`match()` / `??` (null coalescing)** — value selection; `??` returns the
  right side when the left is null.

---

## Database

- **PDO** — PHP Data Objects, the standard database extension.
- **DSN** — connection string, e.g. `mysql:host=localhost;dbname=blogs`.
- **Prepare / execute / parameter binding** — send values separately from the SQL
  using placeholders (`?`). This is the defense against **SQL injection**.
- **`PDOStatement` / `fetch()` / `fetchAll()`** — the runnable statement and how
  you pull rows out of it.
- **`PDO::FETCH_ASSOC`** — rows as associative arrays keyed by column name.
- **`lastInsertId()`** — the auto-increment id of the row you just inserted.
- **Migration** — a versioned script that changes the database schema. Lives in
  `database/migrations/`.
- **Migration ledger** — the `migrations` table that records which migrations
  already ran.
- **`information_schema`** — MySQL's own metadata tables; used to check whether a
  table already exists before creating it.

---

## Authentication & authorization

- **Authentication ("authn")** — proving _who_ you are (login, token).
- **Authorization ("authz")** — deciding _what_ you may do (roles).
- **Role** — `admin`, `author`, or `user`; stored in `users.role`.
- **RBAC** — Role-Based Access Control: permission is decided by role.
- **Password hashing** — turning a password into a one-way string you can store
  safely.
- **`password_hash()`** — PHP's built-in hashing function. `PASSWORD_DEFAULT`
  currently means **bcrypt**, and is designed to be upgraded over time.
- **bcrypt** — a deliberately _slow_ hashing algorithm, which makes brute-force
  attacks expensive. It embeds a random **salt** so identical passwords get
  different hashes.
- **`password_verify()`** — checks a plain password against a stored hash.
- **JWT (JSON Web Token)** — a signed, self-contained token with three
  dot-separated parts: header.payload.signature. Anyone can _read_ the payload;
  the signature proves it wasn't tampered with. Issued/verified here by
  `firebase/php-jwt` through `App\Auth\JwtService`.
- **Claims** — the fields inside a JWT payload: `iss` (issuer), `iat`
  (issued-at), `exp` (expiry), `sub` (subject = user id), `role`, `email`.
- **Access token** — the short-lived JWT returned at login.
- **Refresh token** — a longer-lived token used to get a new access token without
  logging in again. **Not implemented here (yet).**
- **Bearer token** — the token sent as `Authorization: Bearer <token>`.
- **HS256** — HMAC-SHA256, a symmetric signing algorithm using one shared secret
  (`JWT_SECRET`).
- **401 vs 403** — 401 = "you're not authenticated"; 403 = "authenticated, but
  not allowed".
- **Self-assignable role** — a role a public sign-up may choose. Here only
  `user` and `author`; `admin` is never self-assignable.

---

## Architecture terms

- **Front controller** — a single entry file all requests pass through:
  `public/index.php`.
- **Router script** — the file passed to `php -S` so every URL is handled by PHP
  instead of being mapped to a file on disk.
- **Router** — maps method + path to a handler (`src/Core/Router.php`).
- **Middleware** — code that runs _around_ a request (CORS, auth, roles). It can
  pass control on with `$next($request)` or short-circuit with a response.
- **Pipeline** — the ordered chain of middleware wrapping the controller.
- **Controller** — the HTTP-layer class (read input → call model → return
  `Response`).
- **Model** — the data-access class (`User`, `Post`).
- **Composition root** — the place where all objects are constructed and wired:
  `src/Core/Application.php`.
- **Dependency injection** — passing an object's dependencies into its
  constructor instead of creating them inside.
- **Layer** — a group of classes with one level of responsibility.
- **DTO / safe shape** — a simple structure passed between layers. `User::toArray()`
  is the safe shape used to send users to clients without the password hash.

---

## Tooling

- **php-cs-fixer** — reformats PHP to a chosen style (`.php-cs-fixer.dist.php`);
  run with `npm run format:php`.
- **PHPUnit** — the test framework (`tests/`, run with `composer test`).
- **Feature test** — a test that drives the whole stack (router → middleware →
  controller → model → DB) instead of one class. All tests here are feature tests.
- **Transaction rollback** — wrapping each test in a DB transaction and rolling
  it back so tests start clean; see `tests/TestCase.php`.
- **Prettier** — formatter for JS/JSON/Markdown.
- **PSR-12** — a widely used PHP coding style standard.
- **Dev server** — PHP's built-in web server (`php -S`); local only.
- **Document root** — the publicly served folder; here `public/`. Code in `src/`
  is never directly reachable by a URL.
- **Composer script** — a named command in `composer.json`, e.g.
  `composer run migrate`.
