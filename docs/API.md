# SmartCard Studio REST API

Base URL: `https://your-domain.com/api/v1`

All endpoints return JSON and require a **Sanctum personal access token**.

## Authentication

1. Sign in and open **Settings → API tokens**.
2. Create a token, choose its abilities (`read`, `write`) and an expiry.
3. Copy the token (it is shown once) and send it as a bearer token:

```bash
curl -H "Authorization: Bearer 1|xxxxxxxx" -H "Accept: application/json" \
     https://your-domain.com/api/v1/me
```

Tokens stop working immediately when they expire, are revoked, or when the
owning account is deactivated by an administrator.

| Status | Meaning |
| ------ | ------- |
| 401 | Missing, invalid or expired token |
| 403 | Token lacks the required ability, or the account is deactivated |
| 404 | Resource not found (or not yours) |
| 422 | Validation error (`errors` object lists fields) |
| 429 | Rate limit exceeded (60 requests / minute per user) |

Public identifiers are ULIDs. Internal sequential ids are never exposed.

## Endpoints

### `GET /me` (ability: `read`)

Returns the authenticated user.

```json
{
  "data": {
    "id": "01JC2Y8F4T6Q0ZQ3N4S9W8X7V6",
    "name": "Dana Designer",
    "email": "designer@smartcard.test",
    "phone": null,
    "avatar_url": null,
    "roles": ["designer"],
    "permissions": ["dashboard.view", "themes.manage", "designs.assigned", "exports.manage"],
    "is_active": true,
    "created_at": "2026-11-05T08:00:00+00:00"
  }
}
```

### `GET /dashboard` (ability: `read`)

Staff accounts (`dashboard.view` permission) receive platform-wide statistics;
`stats.revenue` is only included with the `revenue.view` permission.
Customer accounts receive statistics for their own cards only.

```json
{
  "data": {
    "scope": "platform",
    "stats": {
      "customers": { "value": 41, "change": 20.0 },
      "cards": { "value": 78, "change": 87.5 },
      "active_profiles": { "value": 27, "total": 31 },
      "nfc_cards": { "value": 80, "active": 21 },
      "qr_scans": { "value": 331, "change": 1.9 },
      "downloads": { "value": 241 }
    },
    "card_generation": { "labels": ["Dec 25", "..."], "values": [6, "..."] },
    "engagement": { "labels": ["Oct 7", "..."], "series": { "profile_view": [], "qr_scan": [], "nfc_tap": [] } }
  }
}
```

### `GET /themes` (ability: `read`)

Published themes plus your own private themes, paginated (`per_page` max 100).

Query parameters: `category`, `search`, `favorites=1`, `per_page`.

```json
{
  "data": [{
    "id": "01M6...", "name": "Corporate Executive", "slug": "corporate-executive",
    "category": "corporate", "status": "published", "is_premium": false, "is_system": true, "is_favorite": false,
    "size": { "width_mm": 85.6, "height_mm": 54, "orientation": "landscape" },
    "palette": { "primary": "#0f2747", "secondary": "#1e3a5f", "accent": "#c9a227", "background": "#ffffff", "text": "#0f172a", "muted": "#64748b" },
    "fonts": { "heading": "Montserrat", "body": "Inter" },
    "layout": { "archetype": "executive", "bleed_mm": 3, "safe_mm": 3, "corner_radius_mm": 0 },
    "preview": { "front_svg": "https://.../themes/01M6.../preview/front.svg", "back_svg": "..." }
  }],
  "links": {}, "meta": {}
}
```

### `GET /themes/{id}` (ability: `read`)

Same fields plus the full editable `front` and `back` scenes (see
`app/Support/Scene/Scene.php` for the scene format) and `based_on`.

### `POST /themes/{id}/favorite` (ability: `write`)

Toggles the theme in your favourites. Returns `{ "data": { "favorite": true } }`.

### Customers

Staff with `customers.view-any` see every customer; designers see customers
assigned to them. Creating, updating and deleting needs `customers.manage`
(a customer account may update only its own record).

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/customers?search=&status=active&per_page=25` | `read` |
| `GET` | `/customers/{id}` | `read` |
| `POST` | `/customers` | `write` |
| `PATCH` / `PUT` | `/customers/{id}` | `write` |
| `DELETE` | `/customers/{id}` (soft delete) | `write` |

Request fields match the web form: `full_name` (required on create),
`professional_title`, `designation`, `department`, `mobile`, `whatsapp`,
`office_phone`, `email`, `website`, `office_address`, `city`, `province`,
`country`, `map_url`, `latitude`, `longitude`, `company_name`,
`company_website`, `social_links` (`{linkedin, facebook, instagram, x, tiktok,
youtube, github}`), `business` (`{description, services[], hours{monday…sunday}}`),
`custom_fields[] {label, value}`, `custom_links[] {label, url}`, `introduction`,
`portfolio_url`, `booking_url`, `visibility` (`{card: {field: bool}, profile: {field: bool}}`)
and, for staff, `status`, `assigned_designer` (user id) and `create_login`.
`photo` and `company_logo` can be sent as multipart uploads. With `PATCH`, groups
you leave out are kept as they are.

```json
{
  "data": {
    "id": "01M6NGK6K3CC98WC9M3C1CG6ER",
    "full_name": "Grace Hopper",
    "professional_title": "Rear Admiral",
    "photo_url": "https://.../storage/customers/.../01m6....jpg",
    "contact": { "mobile": "+1 202 555 0147", "email": "grace@navy.example", "...": null },
    "address": { "city": null, "country": null, "latitude": null, "longitude": null },
    "company": { "id": "01M6...", "name": "US Navy", "website": null, "logo_url": null },
    "social_links": {}, "business": {}, "custom_fields": [{ "label": "Languages", "value": "COBOL" }],
    "visibility": { "card": { "mobile": false, "...": true }, "profile": { "...": true } },
    "status": "active", "has_login": false
  }
}
```

More endpoints (designs, profiles, NFC, exports) are added in later phases and
documented here.

### Digital profiles

Everyone sees the profiles they may view (staff with `profiles.manage` or
`customers.view-any` see all; customer accounts see their own). Creating and
editing needs `profiles.manage` / `customers.manage`, or owning the customer record.

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/profiles?search=&active=1&customer={customer id}&per_page=25` | `read` |
| `GET` | `/profiles/{id}` | `read` |
| `GET` | `/profiles/{id}/qr.png?size=1024` (512, 1024, 2048, 4096) | `read` |
| `GET` | `/profiles/{id}/qr.svg?size=1024` (512, 1024) | `read` |
| `GET` | `/profiles/{id}/qr.pdf?size=50` (25, 50, 100 mm) | `read` |
| `POST` | `/profiles` `{customer, slug?, profile_theme?}` | `write` |
| `PATCH` / `PUT` | `/profiles/{id}` | `write` |
| `DELETE` | `/profiles/{id}` (soft delete; releases NFC cards) | `write` |

Update fields: `slug`, `profile_theme` (`classic`, `minimal`, `bold`, `midnight`,
`elegant`), `accent` (`#rrggbb`), `button_style` (`rounded`, `pill`, `square`),
`headline`, `is_active`, `allow_indexing`, `show_save_contact`, `show_qr`, `map_embed`,
`layout[] {key, enabled}` (keys: `actions`, `ctas`, `about`, `contact`, `social`,
`links`, `business`, `portfolio`, `map`), `ctas[] {label, url, style: solid|outline}`
(max 6), `card_design` (design id), and multipart `cover` / `portfolio[]` images.

```json
{
  "data": {
    "id": "01M6NHBPWXDC3BWK4RDZW0Z97Y",
    "slug": "casey-customer",
    "card_identifier": "P-UZB2HMXG",
    "is_active": true,
    "available": true,
    "customer": { "id": "01M6...", "full_name": "Casey Customer" },
    "theme": "classic", "accent": "#4f46e5", "button_style": "rounded",
    "urls": {
      "public": "https://example.com/card/casey-customer",
      "qr": "https://example.com/q/gmdcvwtzxe",
      "nfc": "https://example.com/c/P-UZB2HMXG",
      "vcard": "https://example.com/card/casey-customer/vcard",
      "qr_png": "...", "qr_svg": "...", "qr_pdf": "..."
    },
    "stats": { "views": 26, "qr_scans": 7 },
    "nfc_cards": [{ "card_identifier": "SC-YIVBR2XX", "uid": "F3:CB:AA:06:E0:1F:DC", "tag_type": "NTAG213",
                    "status": "active", "tag_url": "https://example.com/n/SC-YIVBR2XX" }]
  }
}
```
