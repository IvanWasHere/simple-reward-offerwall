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
- **Three SPAs, two toolchains.**
  - **User app** ("RewardVault") is a **Vite + React** app in `resources/apps/user/`
    (source lives OUTSIDE `resources/assets/apps` so webpack's `autoEntries` ignores it),
    built to `public/apps/user/` with a Vite manifest. It is served at **`/reward`** by a
    **full template takeover**: `plugin/Providers/SpaRouteServiceProvider.php` registers a
    `^reward(/.*)?/?$` rewrite rule, and on `template_redirect` renders a bare HTML shell
    (no theme header/footer) that reads `.vite/manifest.json` and mounts the bundle.
    Client routing is `react-router` (basename `/reward`); auth is client-gated (the SPA
    shows login/register when `/auth/me` → 401). The `/reward` rewrite is flushed on
    activation (`plugin/activation.php`).
  - **Admin & support apps** remain `@wordpress/scripts` bundles in
    `resources/assets/apps/{admin,support}-app/index.tsx`, built to `public/apps/<name>.js`,
    mounted via shortcodes (`[simple_ro_admin_app]` / `_support_app`) from
    `plugin/Providers/AppShortcodesServiceProvider.php` on the auto-created
    `/offerwall-admin` / `/offerwall-support` pages.
  - The `window.SimpleRO` boot object (REST base, cookie/CSRF names, URLs) is shared by
    both via `plugin/Services/SpaBoot.php`. Shared REST client: the user app has its own
    `resources/apps/user/src/lib/api.ts`; staff apps use `resources/assets/apps/shared/api.ts`.
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

## Engagement endpoints (user role)

Beyond offers/clicks/balance/payouts, the user API also serves the RewardVault
"Bonus"/"Surveys" surfaces (all under `Guard::role('user')`):
- `GET /surveys` — offers from providers whose `config` JSON has `{"survey":true}`
  (`SurveysController`), same normalized shape as `/offers`.
- `GET /wheel` + `POST /wheel/spin` — daily Lucky Wheel (`WheelController`). Server picks a
  segment by weight (`config custom.wheel.segments`) and credits coins; one spin per UTC day
  via `UNIQUE(user_id, spin_date)` on `ro_wheel_spins`. Client never asserts the prize.
- `GET /leaderboard` — top 10 by positive ledger deltas (`LeaderboardController`); exposes
  name + amount only.
- `GET /bonuses` + `POST /bonuses/{key}/claim` — daily/one_time/milestone bonuses
  (`config custom.bonuses`, `BonusController`); claims are idempotent ledger entries
  (`ref_type 'bonus'`, `ref_id` = 0 or YYYYMMDD).
- `GET /me/referral` — code + share URL + stats (`ReferralController`/`ReferralService`).
  Register accepts `?ref=CODE` (`referred_by`); the referrer is paid `config
  custom.referral.bonus_coins` on the referred user's first approved reward (credited from
  `Admin\RewardsController::approve` → `ReferralService::creditReferrer`, idempotent).

## Data model (`wp_ro_*`)

`users` (incl. `referral_code`, `referred_by`), `sessions`, `password_resets`,
`login_attempts`, `providers`, `provider_callbacks`, `offers`, `clicked`, `callbacks`,
`rewards`, `coin_ledger`, `payouts`, `redemptions`, `wheel_spins`, `support_requests`,
`support_messages`. Money is integer minor units; coins are integers.

## Working on this

- **Build**: user app (Vite) → `npm run build:user` (dev server: `npm run dev:user`); staff
  apps (webpack) → `npm run build` (or `npm run dev`). The `/reward` PHP takeover reads the
  Vite manifest, so a rebuilt user bundle is picked up without touching PHP.
- **Lint**: `npm run lint` (staff JS, ~slow, ESLint type-aware, scoped to `resources/assets`)
  + `npm run lint:style`. **Tests**: `npm test` (jest). Auto-fix JS style with
  `npx wp-scripts lint-js resources/assets/ --fix` (the WP prettier config is strict).
  The Vite app typechecks via `npx tsc -p resources/apps/user/tsconfig.json --noEmit`.
- **Activate / migrate**: `wp plugin activate simple-reward-offerwall` (runs migrations +
  flushes the `/reward` rewrite). In dev, re-run migrations with a
  `wp plugin deactivate && wp plugin activate` cycle; `wp rewrite flush` alone re-registers
  the rewrite.
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
