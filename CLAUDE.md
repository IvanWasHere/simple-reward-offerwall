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
  - **User + admin apps** are **Vite + React** apps in `resources/apps/{user,admin}/`
    (source lives OUTSIDE `resources/assets/apps` so webpack's `autoEntries` ignores it),
    built to `public/apps/{user,admin}/` with Vite manifests. Both are served by a **full
    template takeover**: `plugin/Providers/SpaRouteServiceProvider.php` iterates a slug→app
    registry (`/reward`→user, `/offerwall-admin`→admin), registers a `^<slug>(/.*)?/?$`
    rewrite each, and on `template_redirect` renders a bare HTML shell (no theme
    header/footer) that reads that app's `.vite/manifest.json` and mounts the bundle. Client
    routing is `react-router` (basename = the slug). Auth is client-gated: the user app
    shows login/register on `/auth/me` → 401; the admin app additionally rejects non-admin
    sessions (`user.type !== 'admin'`). Both rewrites are flushed on activation. Slugs are
    `custom.reward_slug` / `custom.admin_slug`.
  - **Support app** remains a `@wordpress/scripts` bundle in
    `resources/assets/apps/support-app/index.tsx`, built to `public/apps/support-app.js`,
    mounted via the `[simple_ro_support_app]` shortcode on the auto-created
    `/offerwall-support` page.
  - The `window.SimpleRO` boot object (REST base, cookie/CSRF names, URLs, plus the global
    `appName` / `appIconUrl` branding) is shared by all via `plugin/Services/SpaBoot.php`.
    Admins set the app name + icon (picked from the WP media library via `GET /admin/media`,
    since the SPA has no `wp.media`) on the admin Settings page — `Services/Settings`
    (`app_name` / `app_icon_id`); the brand replaces the default "RewardVault" + vault icon
    across both SPAs and the takeover `<title>`. REST client: the user + admin apps each have their own
    `resources/apps/<app>/src/lib/api.ts`; the support app uses `resources/assets/apps/shared/api.ts`.
- **Providers are adapters.** `plugin/Providers/Contracts/ProviderAdapter.php` +
  `Adapters/{Iframe,OfferwallApi,Static}Adapter.php` + `ProviderAdapterFactory`. Types:
  `iframe` (offerwall in an `<iframe>`; the admin's provider URL carries inline
  macro tokens `{user_id}` / `{user_hash}` / `{session_id}` / `{adslot_id}` /
  `{external_id}` that `IframeAdapter`+`Services/MacroBuilder::substitute` replace
  URL-encoded per user — e.g. `…&sid={user_id}` → `…&sid=123`. `{external_id}` =
  `<prefix>-<user_id>-<user_hash>`, where the site-level prefix is set by admins via
  `GET|PUT /admin/settings` — `Services/Settings.php`, stored in the `simple_ro_settings`
  option), `offerwall_api` (live JSON proxy, 60s transient cache),
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
- `GET /wheel` + `POST /wheel/spin` — Lucky Wheel (`WheelController`). Server picks a segment
  by weight (`config custom.wheel.segments`) and credits coins; **one spin per 7-day rolling
  window** (checks the last spin's `created_at`; `nextSpinAt` tells the client when it reopens).
  Client never asserts the prize.
- `GET /leaderboard?period=today|week|month` — **top 25** for the period
  (`LeaderboardController`). Reads denormalised `earned_today/earned_week/earned_month` counters
  on `ro_users` (maintained by `LedgerService::addEarning` on every positive credit, reset when
  the period marker `earn_day`/`earn_week`(last Sunday)/`earn_month` rolls over) — **no
  ledger SUM/GROUP BY**. Exposes name + amount only.
- `GET /bonuses` + `POST /bonuses/{key}/claim` — daily/one_time/milestone bonuses
  (`config custom.bonuses`, `BonusController`); claims are idempotent ledger entries
  (`ref_type 'bonus'`, `ref_id` = 0 or YYYYMMDD).
- `GET /offerwalls` — `iframe`/`offerwall_api` providers with a `placement` field
  (`OfferwallsController`). Admins set `wall_placement` per provider (`'hot'` → Hot Walls +
  All Walls, `'all'` → All Walls only, `'none'` → hidden), persisted in provider `config`
  by `Admin\ProvidersController`. The Earn page renders **offerwall buttons** for `iframe`
  providers, bucketed by placement; clicking opens the per-user macro URL
  (`GET /offerwalls/{id}/url`) inside the in-app iframe view (`/reward/offerwall/:id`).
- `GET /me/referral` — code + share URL + stats (`ReferralController`/`ReferralService`).
  Register accepts `?ref=CODE` (`referred_by`); the referrer is paid `config
  custom.referral.bonus_coins` on the referred user's first approved reward (credited from
  `Admin\RewardsController::approve` → `ReferralService::creditReferrer`, idempotent).

## Data model (`wp_ro_*`)

`users` (incl. `referral_code`, `referred_by`), `sessions`, `password_resets`,
`login_attempts`, `providers`, `provider_callbacks`, `offers`, `clicked`, `callbacks`,
`rewards`, `coin_ledger`, `payouts`, `redemptions`, `wheel_spins`, `fingerprints`,
`support_requests`, `support_messages`. Money is integer minor units; coins are integers.

**Device fingerprinting**: the user SPA runs **ThumbmarkJS** (bundled into the Vite user
app — no external API) on each login and POSTs the result to `POST /me/fingerprint`
(`AccountController::storeFingerprint`), stored in `ro_fingerprints` (server adds IP +
request UA; `visitor_id` = the ThumbmarkJS hash). Admins view a user's fingerprints on the
**user detail page** (`/offerwall-admin/users/:id` — a real route, not a modal) alongside
their clicks, and can delete individual fingerprints
(`GET|DELETE /admin/users/{id}/fingerprints[/{fpId}]`).

## Working on this

- **Build**: user app → `npm run build:user`, admin app → `npm run build:admin` (Vite; dev
  servers `dev:user` / `dev:admin`); support app (webpack) → `npm run build` (or `npm run dev`).
  The PHP takeover reads each app's Vite manifest, so a rebuilt bundle is picked up without
  touching PHP.
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
