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

More endpoints (themes, designs, customers, profiles, NFC, exports) are added
in later phases and documented here.
