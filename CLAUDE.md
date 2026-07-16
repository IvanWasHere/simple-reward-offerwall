# Simple Reward Offerwall

A WordPress **reward offerwall** plugin built on the **WPBones** framework. External
*providers* supply offers; users complete tasks; providers fire server-to-server (S2S)
callbacks we capture as **coin rewards**; users redeem coins for **payouts** (gift cards).
Three account types — **user / admin / support** — each with its own **React SPA on the
public front-end** (WordPress is only the host; wp-admin is not used for the app UI).

Namespace `SimpleRO` (PSR-4 → `plugin/`). REST API at `/wp-json/simple-ro/v1`.

## Architecture

- **Custom auth, not `wp_users`.** Accounts live in `wp_ro_users`. Sessions are opaque
  tokens (32 random bytes hex) in an httpOnly+Secure+SameSite=Strict `ro_session` cookie;
  only `sha256(token)` is stored. A non-httpOnly `ro_csrf` cookie is echoed in the
  `X-RO-CSRF` header (double-submit CSRF, checked on mutations). See
  `plugin/API/Auth/Guard.php` + `plugin/API/AuthController.php`.
- **REST is the backbone.** Routes in `api/simple-ro/v1/routes.php` map to controllers in
  `plugin/API/**` (`SimpleRO\API\...`). Every non-public route sets an explicit
  `permission_callback` = `Guard::role('user'|'admin'|'support')` / `Guard::authenticated()`
  (`admin` is a superset of `support`). Public: `health`, `auth/*`, `callback/{hash}`.
- **Three SPAs** in `resources/assets/apps/{user,admin,support}-app/index.tsx` (shared
  client `resources/assets/apps/shared/api.ts`), built by `@wordpress/scripts` to
  `public/apps/<name>.js`, mounted on front-end pages via shortcodes
  (`[simple_ro_user_app]` / `_admin_app` / `_support_app`) from
  `plugin/Providers/AppShortcodesServiceProvider.php`. Pages `/dashboard`,
  `/offerwall-admin`, `/offerwall-support` are auto-created on activation
  (`plugin/activation.php`).
- **Providers are adapters.** `plugin/Providers/Contracts/ProviderAdapter.php` +
  `Adapters/{Iframe,OfferwallApi,Static}Adapter.php` + `ProviderAdapterFactory`. Types:
  `iframe` (offerwall in an `<iframe>`, per-user URL via macro substitution —
  `Services/MacroBuilder.php`), `offerwall_api` (live JSON proxy, 60s transient cache),
  `static_api` (pulled hourly into `ro_offers` — `Services/OfferIngestionService.php` +
  `Providers/IngestOffersSchedule.php`, cron hook `simple_ro_ingest_offers`).
- **Callbacks** (`plugin/API/CallbackController.php`): `GET|POST /callback/{hash}` selects a
  `ro_provider_callbacks` config, verifies the signature
  (`Services/SignatureVerifier.php`, HMAC-SHA256 / md5 / none), maps params, and creates a
  **pending** reward. Idempotent via `UNIQUE(provider_id, transaction_id)`.
- **Coins = an append-only ledger.** `ro_coin_ledger` is the source of truth; balance =
  `SUM(delta)` (`Services/LedgerService.php`). Idempotent via
  `UNIQUE(ref_type, ref_id, reason)`. Rewards and redemptions are **admin-approved**.
  Redemption `store()` reserves/debits coins inside one InnoDB transaction with a
  `SELECT ... FOR UPDATE` mutex on the user row (no double-spend).

## Framework constraints (important)

- **The WPBones ORM does NOT escape `where()`/`update()` values.** This code therefore does
  **not** use the query builder — all DB access is `$wpdb->prepare()` or
  `$wpdb->insert/update/delete` with format arrays and int-cast keys. Keep it that way.
- **No transactions helper** — use `$wpdb->query('START TRANSACTION'|'COMMIT'|'ROLLBACK')`;
  money tables are InnoDB.
- **Migrations run on activation/update only** (no on-demand migrate). `database/migrations/*`
  are anonymous classes using `dbDelta` (additive — editing a migration to add a column is
  the intended pattern here). Bump the version header to force a re-run.

## Data model (`wp_ro_*`)

`users`, `sessions`, `password_resets`, `login_attempts`, `providers`, `provider_callbacks`,
`offers`, `clicked`, `callbacks`, `rewards`, `coin_ledger`, `payouts`, `redemptions`,
`support_requests`, `support_messages`. Money is integer minor units; coins are integers.

## Working on this

- **Build**: `npm run build` (or `npm run dev`). **Lint**: `npm run lint` (JS, ~slow, ESLint
  type-aware) + `npm run lint:style`. **Tests**: `npm test` (jest). Auto-fix JS style with
  `npx wp-scripts lint-js resources/ --fix` (the WP prettier config is strict).
- **Activate / migrate**: `wp plugin activate simple-reward-offerwall` (runs migrations).
- **Staff accounts**: `wp simple-ro make-admin --email= --password= [--type=admin|support]`
  (`plugin/Providers/CliServiceProvider.php`).
- **Verify** REST over HTTP with curl; on a local Herd/Valet site the cert is self-signed so
  use `curl -k`, and query the DB via `wp eval` (the `mysql` CLI may not be on PATH). Trigger
  the ingest cron with `wp eval "do_action('simple_ro_ingest_offers')"`.
- After adding PHP classes, run `composer dump-autoload -o`.

## Vestigial boilerplate

`resources/assets/apps/{app.tsx,use-counter.ts}` + its jest test and
`plugin/Http/Controllers/Dashboard/` are leftovers from the WPKirk React boilerplate this was
forked from. They're unused by the product (no wp-admin menu) but kept so `npm test` stays
green; remove them once real component tests exist.
