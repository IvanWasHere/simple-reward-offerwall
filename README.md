# Simple Reward Offerwall

A self-contained **reward offerwall** for WordPress. External *providers* supply offers,
users complete them, providers fire server-to-server (S2S) callbacks that become **coin
rewards**, and users redeem coins for **gift-card payouts** — all through three polished
**React single-page apps** (user, admin, support) rendered on the WordPress front end.

WordPress is only the host: the plugin ships its own accounts, sessions, REST API, and UI.
Built on the [WP Bones](https://wpbones.com/) framework. Namespace `SimpleRO`; REST API at
`/wp-json/simple-ro/v1`.

---

## What it does

- **Offerwall for end users** — a branded dashboard (`/reward`) to browse offers, open
  provider offerwalls, take surveys, spin a weekly Lucky Wheel, climb a leaderboard, claim
  bonuses, refer friends, withdraw coins for gift cards, and get support.
- **Admin dashboard** (`/offerwall-admin`) — manage providers & their S2S callbacks, ingest
  and toggle offers, approve/reject reward and redemption queues, run the payout catalog,
  manage users (with device-fingerprint history), review the callback audit log, and set
  site-wide branding.
- **Support console** (`/offerwall-support`) — a ticket queue for support staff.
- **Money-safe by design** — coins live in an append-only ledger; rewards and redemptions
  are admin-approved; redemptions reserve coins inside a locked DB transaction (no
  double-spend).

### Feature highlights

| Area | What you get |
| --- | --- |
| **Accounts & auth** | Custom `user` / `admin` / `support` accounts (independent of `wp_users`); opaque hashed session cookie + double-submit CSRF |
| **Providers** | `iframe` (embedded offerwall with per-user macro URLs), `offerwall_api` (live JSON proxy, cached), `static_api` (hourly ingest into a local offers table) |
| **Callbacks** | Per-provider S2S postback configs with signature verification (HMAC-SHA256 / md5 / none), idempotent reward creation |
| **Coins & payouts** | Append-only ledger, admin-approved rewards & redemptions, gift-card catalog with stock |
| **Engagement** | Surveys, weekly Lucky Wheel, Today/Week/Month leaderboards (top 25), daily/milestone bonuses, referral program |
| **Anti-fraud** | Device fingerprinting (ThumbmarkJS) captured on each login, viewable & deletable per user by admins |
| **Branding** | Set the app name + icon (from the WP media library) once — applied across every app |

---

## Requirements

- WordPress 6.2+, PHP 7.4+, a MySQL/MariaDB database (money tables are **InnoDB**).
- Pretty permalinks enabled (the `/reward` and `/offerwall-admin` routes use rewrite rules).
- Node.js 18+ only if you plan to rebuild the SPAs (prebuilt bundles ship in `public/apps`).

---

## Installation

1. Copy the plugin into `wp-content/plugins/simple-reward-offerwall/`.
2. Activate it (**Plugins** screen, or `wp plugin activate simple-reward-offerwall`).
   Activation runs the database migrations, registers the `/reward` and `/offerwall-admin`
   rewrite rules, flushes rewrites, and creates the `/offerwall-support` page.
3. Create at least one admin account (see **Staff accounts** below), then open
   `https://your-site/offerwall-admin` and sign in.

That's it — end users can register and sign in at `https://your-site/reward`.

### Staff accounts

Admin and support accounts are created from the CLI:

```bash
wp simple-ro make-admin --email=you@example.com --password=secret --type=admin
wp simple-ro make-admin --email=help@example.com --password=secret --type=support
```

`--type=admin` can do everything; `--type=support` can only work the support queue.

---

## Using it

### For end users — `https://your-site/reward`

Register or sign in, then:

- **Earn** — featured offers plus per-provider offerwall buttons (Hot Walls / All Walls).
- **Offers** — search & filter the full offer feed; open an offer to start it.
- **Surveys** — survey offers from survey-flagged providers.
- **Withdraw** — redeem coins from the gift-card catalog; view redemption history.
- **Bonus** — Leaderboard (Today / This Week / This Month), the weekly Lucky Wheel,
  claimable rewards, and your referral link.
- **Profile & Support** — edit name/email, and open support tickets (pick from the offers
  you clicked in the last 30 days).

Signing in stores a device fingerprint for fraud review. Admins who sign in here are sent
straight to the admin dashboard.

### For admins — `https://your-site/offerwall-admin`

- **Dashboard** — live counts and "needs attention" queues.
- **Providers** — add/edit providers, configure their **callbacks**, run **Ingest now** for
  `static_api` providers. For `iframe` offerwalls, set the per-user URL with inline macros
  (`{user_id}`, `{user_hash}`, `{session_id}`, `{external_id}`) and a **wall placement**
  (Hot Walls / All Walls / Hidden).
- **Offers** — enable/disable ingested offers.
- **Rewards / Redemptions** — approve or reject pending items (approval credits/settles coins).
- **Payouts** — manage the gift-card catalog (price in coins, value, stock).
- **Users** — search, change role, block/unblock, and open a **user detail page** with the
  user's clicks and device fingerprints (deletable).
- **Callbacks** — read-only S2S postback audit log.
- **Settings** — the site-level external-id prefix, and **branding** (app name + an icon
  chosen from the WP media library) that replaces the default across all apps.

### For support staff — `https://your-site/offerwall-support`

A ticket queue: view, assign, reply to, and close user support requests.

---

## How rewards flow

1. A user opens an offer → a click is recorded and they're sent to the provider.
2. The user completes the offer on the provider's side.
3. The provider fires an S2S callback to `/wp-json/simple-ro/v1/callback/{hash}`; the plugin
   verifies the signature and creates a **pending reward** (idempotent per transaction).
4. An admin **approves** the reward on the Rewards queue → coins are credited to the ledger.
5. The user **redeems** coins for a payout; coins are reserved immediately, and an admin
   approves the redemption to settle it (or rejects it to refund).

`iframe` offerwall providers identify the user via the URL macros you configure (prefer
`{external_id}` = `<prefix>-<user_id>-<user_hash>`, set in **Settings**), so their postbacks
map back to the right account.

---

## Configuration

Defaults live in `config/custom.php` (coin conversion, session tuning, slugs, wheel segments,
bonus definitions, referral bonus, app name). Runtime, admin-editable settings (external-id
prefix, app name, app icon) are stored in the `simple_ro_settings` option and managed on the
admin **Settings** page.

---

## Development

The user and admin apps are **Vite + React**; the support app is still `@wordpress/scripts`.

```bash
# Build the SPAs (output to public/apps/<app>/)
npm run build:user      # user app  → served at /reward
npm run build:admin     # admin app → served at /offerwall-admin
npm run build           # support app (webpack) → shortcode bundle

# Dev servers
npm run dev:user
npm run dev:admin

# Quality
npm run lint            # ESLint (support/webpack sources)
npm run lint:style      # Stylelint
npm test                # Jest
npx tsc -p resources/apps/user/tsconfig.json --noEmit    # typecheck a Vite app

# After adding PHP classes
composer dump-autoload -o
```

The `/reward` and `/offerwall-admin` PHP takeovers read each app's Vite manifest, so a rebuilt
bundle is picked up without touching PHP. Re-run migrations in dev with a
`wp plugin deactivate && wp plugin activate` cycle.

### Layout

```
api/simple-ro/v1/routes.php     REST routes → controllers
plugin/API/**                   Auth, User, Admin, Support, Callback controllers
plugin/Services/**              Ledger, MacroBuilder, Settings, offer ingestion, …
plugin/Providers/**             Provider adapters + the SPA route/shortcode providers
database/migrations/**          Schema (dbDelta, run on activation/update)
resources/apps/{user,admin}/    Vite React apps
resources/assets/apps/          Support app (@wordpress/scripts)
config/**                       Plugin + custom configuration
```

See [`CLAUDE.md`](CLAUDE.md) for a deeper architecture reference (data model, REST surface,
framework constraints).

---

## License

GPLv2 or later.
