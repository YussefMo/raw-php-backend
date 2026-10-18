# AGENTS.md

> OpenCode loads this file automatically at the start of every session.
> It tells you (the AI agent) **what this project is, how it is built, what the
> conventions are, and how to explain your work** so the human can learn from it.

---

## 1. What this project is

This is a **learning project**. The author is teaching themselves PHP **from
scratch by building a REST API with raw PHP** (no framework). The point is to
understand how each piece works under the hood, not to ship a product quickly.

It is a small **blog/posts API** with a hand-written **authentication and
authorization system** on top of MySQL via PDO.

**Endpoints**

| Method   | Path               | Auth | Allowed roles     | Purpose                                       |
| -------- | ------------------ | ---- | ----------------- | --------------------------------------------- |
| `GET`    | `/`                | no   | anyone            | List all posts                                |
| `GET`    | `/posts`           | no   | anyone            | List all posts                                |
| `GET`    | `/posts/{id}`      | no   | anyone            | Get one post                                  |
| `GET`    | `/id?id=1`         | no   | anyone            | Legacy alias for a single post                |
| `POST`   | `/auth/register`   | no   | anyone            | Create an account                             |
| `POST`   | `/auth/login`      | no   | anyone            | Get a JWT access token                        |
| `GET`    | `/auth/me`         | yes  | any authenticated | Current user                                  |
| `GET`    | `/me/posts`        | yes  | any authenticated | Posts owned by the current user ("My Posts")  |
| `PATCH`  | `/posts/{id}`      | yes  | `admin` or owner  | Update a post (partial)                       |
| `DELETE` | `/posts/{id}`      | yes  | `admin` or owner  | Delete a post                                 |
| `POST`   | `/posts`           | yes  | `admin`, `author` | Create a post (`category_id` required)        |
| `GET`    | `/categories`      | yes  | `admin`, `author` | List categories (fills the new-post dropdown) |
| `POST`   | `/categories`      | yes  | `admin`           | Create a category                             |
| `DELETE` | `/categories/{id}` | yes  | `admin`           | Delete a category (409 if it has posts)       |
| `GET`    | `/admin/users`     | yes  | `admin`           | List all users                                |

**Roles** (`App\Auth\Role`, stored in `users.role`):

- `admin` — full access, including user administration.
- `author` — can publish posts.
- `user` — default on sign-up; can read and view their own profile.

**Primary goal:** readable, beginner-friendly code that teaches a concept.
**Secondary goal:** working endpoints.

---

## 2. Core rules for the AI agent (read these first)

These override your default habits. When they conflict with "clean code"
instincts, these win.

1. **Stay raw.** Do **not** introduce a framework, ORM, DI container, or a new
   Composer/npm package unless the human explicitly asks. Solve problems with the
   standard library first. `firebase/php-jwt` is the one sanctioned dependency
   (see §10) — use it, don't add another.
2. **Explain, don't just deliver.** After writing code, explain _what_ changed,
   _why_ it works, and _where it fits in the request lifecycle_ (§5). Assume a
   beginner is reading.
3. **Comment the "why", not the obvious.** Short, plain-language comments at the
   top of each file/class and on non-obvious blocks. Explain the _reason_
   ("prepared statements stop SQL injection"), not the syntax.
4. **Keep it small and explicit.** Several obvious steps beat one clever line.
   Name advanced constructs and add them to `docs/reference/glossary.md`.
5. **Follow the conventions** in §6 exactly.
6. **Never silently skip safety.** Prepared statements for all SQL. Validate and
   cast all input. Say so in a comment when a shortcut is only acceptable here.
7. **Ask before big architectural moves.** Adding a layer or a third-party
   package is fine only when asked.
8. **Authentication & authorization rules (non-negotiable):**
   - **Tokens:** always create/verify JWTs through `App\Auth\JwtService`
     (backed by `firebase/php-jwt`). **Never** hand-roll token encoding/decoding
     or roll your own signing.
   - **Passwords:** always use PHP's built-in `password_hash()` /
     `password_verify()`. **Never** `md5`, `sha1`, a custom hash, or a hashing
     library.
   - **Authorization happens in middleware,** via `RoleMiddleware`, declared on
     the route. Do not scatter role checks inside controllers.
   - **A client must never grant itself a role.** Public sign-up is limited to
     `Role::selfAssignable()` (`user`, `author`); `admin` is never self-assignable
     and is only created by the migration seed or promoted by an admin.
   - **Never return `password_hash`.** Users leave the API only through
     `User::toArray()`.
9. **Verify.** After changing PHP, run `composer test` and the formatter (§4),
   and say how to check it by hand (curl). Never claim something works without a
   way to check it.
10. **Fix small rough edges you touch** (§12) and mention the fix.

If a request is ambiguous, ask a short clarifying question rather than guessing.

---

## 3. Tech stack

| Piece            | Choice                                                                 |
| ---------------- | ---------------------------------------------------------------------- |
| Language         | PHP `^8.3`                                                             |
| Autoloading      | Composer **PSR-4**: `App\` → `src/`                                    |
| Database         | MySQL via **PDO** (`mysql:` DSN)                                       |
| Auth tokens      | **`firebase/php-jwt`** (HS256), wrapped by `App\Auth\JwtService`       |
| Passwords        | PHP built-in `password_hash()` / `password_verify()`                   |
| Web server (dev) | PHP built-in server with `public/index.php` as the router script       |
| Env loading      | Hand-rolled `App\Config\Config` reading `.env` via `parse_ini_file`    |
| Migrations       | Hand-rolled runner in `App\Database\Migrator` + `database/migrations/` |
| PHP formatting   | `friendsofphp/php-cs-fixer` (PSR-12 + extras)                          |
| JS/MD formatting | Prettier + `@prettier/plugin-php`                                      |
| Tests            | **PHPUnit 12** in `tests/` — feature tests, run with `composer test`   |
| Frontend         | React 18 + Vite 5 in `frontend/`, calling this API over CORS           |

> No framework and no DI container. Everything except the JWT library is written
> by hand on purpose.

---

## 4. Commands

Run these from the project root. **This repo lives in WSL**, so Composer/PHP
commands must run inside WSL (Windows Composer cannot use the `\\wsl.localhost`
UNC path):

```bash
wsl -d Ubuntu -- bash -lc "cd ~/projects/1st-api && <command>"
```

| Task                      | Command                                              |
| ------------------------- | ---------------------------------------------------- |
| Start the dev API         | `composer run dev`                                   |
| Run migrations            | `composer run migrate`                               |
| Reset DB (drop + migrate) | `composer run migrate:fresh` — destructive, dev only |
| Run tests                 | `composer test` (alias: `npm test`)                  |
| Format all PHP            | `npm run format:php` (`php-cs-fixer fix`)            |
| Check PHP formatting      | `npm run format:php:check`                           |
| Format JS/JSON/MD         | `npm run format` (Prettier)                          |
| Frontend dev (port 5173)  | `npm run dev` — run from the repo root               |
| Install frontend deps     | `npm --prefix frontend install` (once)               |
| Frontend production build | `npm run build:frontend`                             |

`composer run dev` runs
`php -S localhost:8000 -t public public/index.php` — note `public/index.php` is
passed as the **router script**, so _every_ URL goes through the front
controller instead of being mapped to a file.

**Always run the PHP formatter after editing `.php` files.** OpenCode also runs
it automatically after edits (configured in `opencode.json`).

### Smoke-testing

```bash
# public
curl http://localhost:8000/
curl http://localhost:8000/posts/1

# login (admin is seeded from .env by the migration)
TOKEN=$(curl -s -X POST http://localhost:8000/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"admin12345"}' | jq -r .access_token)

# authenticated
curl http://localhost:8000/auth/me      -H "Authorization: Bearer $TOKEN"
curl http://localhost:8000/admin/users  -H "Authorization: Bearer $TOKEN"

# write (admin or author only)
curl -X POST http://localhost:8000/posts \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"title":"Hi","body":"...","category_id":1}'
```

---

## 5. The request lifecycle

Full detail with diagrams: [`docs/reference/request-lifecycle.md`](docs/reference/request-lifecycle.md).

```
Browser / curl
      │  HTTP request
      ▼
PHP dev server                    php -S … -t public public/index.php
      ▼
public/index.php                  Front controller: autoload + Config + Database
      ▼
App\Core\Application              Composition root (wires objects, route table)
      ▼
App\Http\Request::capture()       Reads method, path, headers, JSON body
      ▼
App\Core\Router                   METHOD + URI → [Controller, method] + middleware
      ▼
Middleware pipeline               Cors → Auth → Role  (can short-circuit 401/403)
      ▼
Controller                        Reads input, validates, calls the model
      ▼
Model (Post / User)               Prepared SQL
      ▼
Database\Database (PDO) → MySQL
      ▼
Controller                        Builds an App\Http\Response::json(...)
      ▼
Response::send()                  Status + headers + JSON body → client
```

---

## 6. Coding conventions

- Every `.php` file starts with `<?php`, a blank line, `declare(strict_types=1);`.
- **PSR-4**: `App\Models\Post` → `src/Models/Post.php`. One class per file.
- **Layering is strict.** Request/Response objects flow between layers:
  - `public/` stays thin (bootstrap + run).
  - Controllers: read input, validate, call models, return a `Response`. They
    **never** write SQL and **never** `echo`.
  - Models: SQL + mapping only. They **never** touch HTTP or echo.
  - Middleware: cross-cutting concerns only.
- Use `final` classes, constructor injection, promoted properties, short arrays
  `[]`, single quotes, PSR-12 (4 spaces).
- Return types on every method that returns something; typed parameters always.
- Use `HttpException($status, $message)` for expected failures; `Application`
  converts it into clean JSON. Unexpected errors become a 500.
- Use `Role::Admin->value` etc. for role strings — never hard-code `'admin'`.
- Prefer prepared statements. Table names may be interpolated only from private
  class constants, never user input.

---

## 7. Directory map

```
1st-api/
├── public/
│   └── index.php                    # front controller (the only web entry point)
├── src/
│   ├── Core/
│   │   ├── Application.php           # composition root + route table
│   │   ├── Router.php                # method+path → handler, middleware pipeline
│   │   └── HttpException.php         # exception carrying an HTTP status
│   ├── Http/
│   │   ├── Request.php               # reads superglobals; bearer token; path params
│   │   ├── Response.php              # json()/noContent(), send()
│   │   └── Middleware/
│   │       ├── MiddlewareInterface.php
│   │       ├── CorsMiddleware.php     # global; OPTIONS preflight
│   │       ├── AuthMiddleware.php     # verifies JWT → attaches User
│   │       └── RoleMiddleware.php     # 401/403 role gate
│   ├── Auth/
│   │   ├── Role.php                  # enum: admin | author | user
│   │   └── JwtService.php            # firebase/php-jwt wrapper
│   ├── Controllers/
│   │   ├── AuthController.php         # register / login / me
│   │   ├── PostController.php         # index / show / store
│   │   └── UserController.php         # index (admin)
│   ├── Models/
│   │   ├── User.php                  # users table (+ toArray() safe shape)
│   │   └── Post.php                  # posts table
│   ├── Database/
│   │   ├── Database.php              # PDO connection
│   │   ├── Migrator.php              # migration runner
│   │   └── MigrationInterface.php
│   └── Config/Config.php             # .env loader
├── database/
│   ├── migrate.php                   # `composer run migrate` entry point
│   └── migrations/                   # one file per migration
├── docs/reference/                   # see §11
├── tests/                            # PHPUnit suite (TestCase + *Test.php), uses .env.testing
├── frontend/                         # React + Vite SPA (api.js, auth.jsx, views/)
├── .env / .env.example               # config (JWT + admin seed live here)
└── opencode.json
```

---

## 8. Architecture (implemented)

```
public/index.php        Front controller
  → Core\Router         route matching + middleware pipeline
  → Http\Middleware[]   CORS → Auth → Role
  → Controllers\*       HTTP layer
  → Models\*            data layer
  → Database\Database   PDO → MySQL
  → Http\Response       JSON → client
```

Dependency direction is **one-way** (`public → Router → Middleware → Controller
→ Model → Database`). A lower layer never reaches back up. Details and a
step-by-step "how to add an endpoint": [`docs/reference/architecture.md`](docs/reference/architecture.md).

---

## 9. Database & migrations

**Every table is created by a migration** — there is no hand-made schema. The
whole schema is built from `database/migrations/`, plus a `migrations` ledger
table maintained by `Migrator`.

```
database/migrations/
  2026_10_14_000001_create_users_table.php        users + seeds the admin from .env
  2026_10_14_000002_create_categories_table.php   categories + 5 starter rows
  2026_10_14_000003_create_posts_table.php        posts (FKs to users + categories)
```

Each migration returns an anonymous class implementing
`App\Database\MigrationInterface`:

- `table(): ?string` — the main table it manages, or **`null` for an `ALTER` or
  data migration** (so the "already exists" shortcut does not apply).
- `up(PDO $db, Config $config): void` — apply.
- `down(PDO $db): void` — reverse.

### Commands

- `composer run migrate` → apply pending migrations.
- `composer run migrate:fresh` → `Migrator::fresh()`: drop **every** table, then
  migrate from zero. Destructive; dev only.

`Migrator::run()`:

1. Create the `migrations` ledger table if missing.
2. Load already-applied migration names.
3. For each migration file (sorted by name):
   - already recorded → skip;
   - its table already exists in the DB → record as applied and skip
     (only when `table()` is non-null);
   - otherwise → run `up()` and record it.

### Schema

All tables use `ENGINE=InnoDB` + `utf8mb4_unicode_ci` (uniform, so joins never
hit a collation mismatch).

```sql
users(id, name, email UNIQUE, password_hash, role ENUM('admin','author','user'),
      created_at, updated_at)

categories(id, name UNIQUE, created_at, updated_at)

posts(id,
      user_id     NOT NULL → users(id)      ON DELETE CASCADE,
      category_id NOT NULL → categories(id) ON DELETE RESTRICT,
      title, body, created_at, updated_at)
```

Design rules:

- **Nothing nullable where it matters.** `posts.user_id` and `posts.category_id`
  are `NOT NULL` with real FKs, so a post can never be orphaned.
- **No denormalised `author` column.** The author name is joined from `users`
  (`u.name AS author`), so renaming a user is reflected everywhere.
- Deleting a user cascades to their posts; deleting an in-use category is blocked
  by the FK (and the API returns 409 first).

---

## 10. Auth & authorization model

- **Registration** (`POST /auth/register`) validates name/email/password, hashes
  with `password_hash(..., PASSWORD_DEFAULT)`, and stores role from
  `Role::selfAssignable()` (default `user`). It does **not** return a token —
  registration and authentication are separate on purpose.
- **Login** (`POST /auth/login`) verifies with `password_verify()` and returns a
  signed JWT (`JwtService::issue()`) with `sub` = user id. One generic error
  message is used for both "unknown email" and "wrong password".
- **Authentication** (`AuthMiddleware`) verifies the bearer token, loads the user
  fresh from the DB (so role changes and deletions take effect immediately), and
  attaches it to the request. Failure → `401`.
- **Authorization** (`RoleMiddleware`) checks the attached user's role against the
  roles declared on the route. Failure → `403`.
- **JWT config** comes from `.env`: `JWT_SECRET`, `JWT_TTL`, `JWT_ISSUER`.
- **Admin seed** comes from `.env`: `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`
  (inserted by the users migration; local-dev only — change it).

Terms are defined in [`docs/reference/glossary.md`](docs/reference/glossary.md).

---

## 11. Documentation map (the "ref files")

Updating these is part of the deliverable, not an afterthought.

| File                                                                         | What it explains                                                                                           |
| ---------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| [`docs/reference/request-lifecycle.md`](docs/reference/request-lifecycle.md) | request → front controller → router → middleware → controller → model → DB → JSON, including the auth flow |
| [`docs/reference/architecture.md`](docs/reference/architecture.md)           | layers, responsibilities, dependency direction, how to add an endpoint                                     |
| [`docs/reference/glossary.md`](docs/reference/glossary.md)                   | plain-language definitions of every term (JWT, middleware, enum, migration, bcrypt…)                       |

Add new terms to the glossary; update the lifecycle/architecture docs when a
layer changes.

---

## 12. Known rough edges / next steps

- **No refresh tokens.** Access tokens simply expire after `JWT_TTL` seconds and
  the client must log in again.
- **No token revocation / logout.** A JWT stays valid until it expires.
- **No rate limiting** on `POST /auth/login` (brute-force protection).
- **No password reset / email verification.**
- **No pagination** on `GET /` and `GET /admin/users`.
- **Tests drive the API end-to-end** through `Application::handle()`; there are
  no unit tests for internal classes (Router, JwtService, …) yet.
- **Roles can't be changed through the API** — an admin would need SQL, or a new
  `PATCH /admin/users/{id}/role` endpoint (a good next exercise).
- The database is rebuilt entirely from migrations, with real FKs on
  `posts.user_id` and `posts.category_id`. Beyond the admin and the five starter
  categories there is **no seed data** — create content through the API/UI.

---

## 13. Definition of done for a change

1. Code follows §6 and stays raw (§2.1).
2. Auth rules in §2.8 are respected (no hand-rolled tokens, `password_hash`, no
   leaked hashes, no self-assigned admin).
3. Non-obvious parts have a short "why" comment.
4. `npm run format:php` run; files clean.
5. `composer test` passes; new behavior has tests in `tests/`.
6. Concrete verification given (curl command + expected status/body).
7. Explained where the change fits in the request lifecycle.
8. New concepts/layers added to the `docs/reference/` files.
