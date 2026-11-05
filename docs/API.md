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

More endpoints (designs, customers, profiles, NFC, exports) are added in later
phases and documented here.
