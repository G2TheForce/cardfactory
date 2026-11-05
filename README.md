# SmartCard Studio

A Laravel SaaS application for designing, generating, managing and exporting
professional **NFC smart visiting cards**, their **QR codes** and the
**digital business profiles** they link to.

> Status: **Phases 1–3** complete (foundation, theme management, visual card designer). See [Roadmap](#roadmap).

## Stack

| Layer | Technology |
| ----- | ---------- |
| Backend | Laravel 13, PHP 8.3+, MySQL 8 (MariaDB 10.11+ also works) |
| Auth | Laravel Fortify (session) + Laravel Sanctum (API tokens) |
| Access control | spatie/laravel-permission (roles + permissions), policies |
| Frontend | Blade components, Tailwind CSS v4, Alpine.js, Chart.js, Lucide icons |
| Queues | Laravel queues (database driver by default) |

## Requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `gd` (or `imagick`), `intl`, `zip`, `bcmath`
- Composer 2
- Node.js 20+ and npm
- MySQL 8+ (or MariaDB 10.11+)

## Installation (local development)

```bash
git clone https://github.com/G2TheForce/cardfactory.git smartcard-studio
cd smartcard-studio

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Create a database and user, then put the credentials in `.env`:

```sql
CREATE DATABASE smartcard_studio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartcard'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT ALL ON smartcard_studio.* TO 'smartcard'@'localhost';
```

```bash
php artisan migrate --seed      # schema, roles, 20 themes, demo data (non-production only)
php artisan storage:link        # public uploads (avatars, logos, editor images and fonts)
npm run build                   # or: npm run dev
php artisan serve               # http://localhost:8000
php artisan queue:work          # in a second terminal (mail, exports later)
```

### Demo accounts (local development only)

All demo passwords are `password`. They are **not** seeded when `APP_ENV=production`.

| Role | Email |
| ---- | ----- |
| Super Admin | superadmin@smartcard.test |
| Admin | admin@smartcard.test |
| Designer | designer@smartcard.test |
| Sales Manager | sales@smartcard.test |
| Customer | customer@smartcard.test |

## Roles and permissions

Defined in [`config/smartcard.php`](config/smartcard.php) and synced to the
database by `RolesAndPermissionsSeeder` (safe to re-run any time):

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

- **Super Admin** bypasses every check (`Gate::before`).
- **Admin** manages everything except role permissions.
- **Designer** works on themes and assigned designs.
- **Sales Manager** manages customers, NFC cards, inventory and orders.
- **Customer** sees only their own cards, profiles and analytics.

Super Admins can change what each role may do under **Administration → Roles & permissions**.
Self-registered accounts get the `customer` role and a linked customer record.

## Card scenes and themes

Every card side (themes and designs) is stored as **scene JSON**,
documented in [`app/Support/Scene/Scene.php`](app/Support/Scene/Scene.php):
millimetre geometry, point font sizes, palette tokens such as `palette.primary`,
and merge fields such as `{{full_name}}` and `{{profile_url}}`.

- `SceneValidator` cleans and validates every scene before it is saved.
- `CardRenderingService` renders a scene to a millimetre-accurate SVG
  (backgrounds, gradients, text, shapes, lines, icons, social icons, images,
  vector QR codes, patterns, bleed and safe-area guides). Previews use it now;
  print exports will use the same renderer.
- The 20 system themes live in [`app/Support/Themes/ThemeLibrary.php`](app/Support/Themes/ThemeLibrary.php)
  and are synced by `php artisan db:seed --class=ThemeSeeder` (idempotent).

Users can customise colours, fonts and corner radius with a live preview and
**save as a new theme** (private to them), duplicate, favourite and reset to
the original. Users with `themes.manage` can create and edit themes, including
the scene JSON. Users with `themes.publish` can publish, unpublish and delete them.

## Configuration

| Variable | Purpose |
| -------- | ------- |
| `APP_URL` | Base URL used for public profile and QR links |
| `DB_*` | MySQL connection |
| `QUEUE_CONNECTION` | `database` by default; use `redis` in production |
| `MAIL_*` | Password reset and contact-form mail |
| `SMARTCARD_CONTACT_EMAIL` | Recipient for landing-page enquiries |
| `SEED_DEMO_DATA` | Force demo seeding in production (do not use on live sites) |

## Testing

Tests run against in-memory SQLite by default:

```bash
php artisan test
```

To run the same suite against MySQL:

```bash
DB_CONNECTION=mysql DB_DATABASE=smartcard_testing php artisan test
```

## Project structure

```
app/
  Actions/Fortify/        registration, profile and password actions
  Http/Controllers/       thin controllers (Admin/, Settings/, Api/V1/)
  Http/Middleware/        EnsureUserIsActive
  Http/Requests/          form request validation
  Http/Resources/         API resources
  Models/                 Eloquent models (public ULIDs via Concerns/HasPublicUlid)
  Policies/               authorization policies
  Services/               business logic (DashboardService, ActivityLogger, ...)
  Support/Navigation.php  role-aware sidebar
config/smartcard.php      roles, permissions, plans, card defaults
resources/views/
  components/             reusable Blade components and layouts
  dashboard/ admin/ settings/ marketing/ auth/
resources/js/modules/     frontend modules (charts, later: editor)
docs/                     API and deployment documentation
```

## Visual card designer

`/designs` lists the designs a user may see; **New design** starts from any theme or
a blank card (ISO/CR80, US, EU, Japan or square, landscape or portrait) and can be
attached to a customer so merge fields show their details.

The editor (`/designs/{ulid}/edit`, [`resources/js/editor`](resources/js/editor)) works like this:

- The card side is rendered **on the server** by `CardRenderingService` and shown as
  inline SVG, so the canvas is exactly what gets printed (fonts, QR codes, masks, patterns).
- A transparent **Fabric.js** canvas sits on top and provides selection, drag, resize,
  rotate, multi-select, snapping to the card centre, trim and safe area, and keyboard nudging.
  While dragging, the SVG group moves instantly; on release the scene is updated and re-rendered.
- Panels: Elements (shapes, lines, QR, logo/photo slots, patterns, social icons, 1,500+ Lucide
  icons), Text, Uploads (images and custom fonts), Background (solid, gradient, image with tint,
  design palette), Layers (order, lock, hide) and Fields (merge fields; show customer data,
  sample data or raw tags).
- Undo/redo (100 steps), copy/paste, duplicate, align, arrange, zoom, front/back, bleed and
  safe-area guides, a flip preview, card settings (size, orientation, bleed, corner radius, status)
  and autosave every 30 seconds. Saves send the version that was loaded; a newer version on the
  server returns 409 instead of overwriting.
- Every save goes through `SceneValidator`, increments `version` and mirrors the elements into
  `card_elements` (`CardDesignService::syncElements`).

Uploads (`AssetService`): JPG/PNG/WebP up to 10 MB are decoded and re-encoded with
Intervention Image (metadata stripped, scaled down to 4000 px); SVG uploads are refused.
Fonts (TTF/OTF/WOFF/WOFF2, 5 MB) are checked by file signature. Uploads belong to the
uploader; only they can list or delete them.

Access: owners and the customer the design belongs to can edit; `designs.manage` edits any
design, `designs.assigned` edits designs assigned to that designer, `designs.view-any` is
read-only.

## Security notes

- Passwords hashed with bcrypt; stronger password rules in production.
- CSRF protection on all web forms; Blade escaping for all output.
- Login throttled (5/min per email+IP); API throttled (60/min); contact form throttled.
- Deactivated users are signed out and their API tokens revoked.
- Upload validation on MIME type, extension, size and dimensions; images are re-encoded,
  SVG uploads are refused, fonts are checked by signature.
- Separate named rate limiters for editor previews, saves and uploads.
- Public resources use ULIDs; sequential ids never appear in URLs.
- Audit log records sign-ins and administrative changes (passwords redacted).

## Roadmap

1. **Foundation**: auth, roles, dashboard, base UI, full database schema ✅
2. **Theme management**: 20 editable themes, gallery, customiser, favourites, publishing ✅
3. **Visual card designer**: canvas editor, uploads, layers, undo/redo, front/back, save/load ✅
4. Customer management
5. Digital business cards (public profiles, vCard, QR, NFC URLs)
6. Export engine (PNG/SVG/PDF, 300 DPI, bleed, crop marks)
7. Bulk generation (CSV/Excel import, ZIP, combined PDF)
8. Inventory and analytics
9. Testing hardening, performance, deployment guide

See [docs/API.md](docs/API.md) for the REST API.
