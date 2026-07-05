# Laravel Fingerprint Tracking

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dominservice/laravel-fingerprint-tracking.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-fingerprint-tracking)
[![Total Downloads](https://img.shields.io/packagist/dt/dominservice/laravel-fingerprint-tracking.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-fingerprint-tracking)
[![License](https://img.shields.io/packagist/l/dominservice/laravel-fingerprint-tracking.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-fingerprint-tracking)

*A lightweight Laravel package for browser fingerprinting, movement tracking, public form correlation and seamless cooperation with `dominservice/invis-captcha`.*

## Version Matrix

| Laravel | Supported? | Notes |
|---------|------------|-------|
| **10.x** | ✅ | Requires PHP ≥ 8.1 |
| **11.x** | ✅ | Streamlined structure supported |
| **12.x** | ✅ | Same wiring as 11 |
| **13.x** | ✅ | Requires the PHP version supported by Laravel 13 |

---

## ✨ Features

| Module | Purpose | Toggle |
|--------|---------|--------|
| **Browser fingerprint** | Creates a stable browser fingerprint and stores it in a cookie. | `enabled` |
| **Tracking endpoint** | Records public events such as `page_view` or custom events. | `enabled` |
| **Form correlation** | Injects hidden `fingerprint` and `tracking_event_ulid` fields into selected forms. | `tracking.attach_hidden_fields` |
| **Ready event** | Emits a browser event when tracking is initialized. | always on |
| **invis-captcha bridge** | Shares fingerprint and event correlation with `dominservice/invis-captcha`. | automatic |
| **Optional geo enrichment** | Can enrich events with IPRegistry security/geo data. | `geo.enabled` |
| **Public-safe identifiers** | Exposes only ULIDs publicly, never incremental database IDs. | always on |

---

## Installation

You can install the package via composer:

```bash
composer require dominservice/laravel-fingerprint-tracking
```

After installing, publish the package files:

```bash
php artisan vendor:publish --provider="Dominservice\\FingerprintTracking\\FingerprintTrackingServiceProvider"
php artisan migrate
```

Published assets will be available at:

`public/vendor/laravel-fingerprint-tracking/fingerprint-tracking.js`

## Configuration

The configuration file `config/fingerprint-tracking.php` allows you to customize:

- package enable/disable switch
- fingerprint cookie name and lifetime
- route prefix and middleware
- authenticated subject morph strategy (`id`, `uuid`, `ulid`, `string`)
- whether authenticated users should be bound automatically to tracked events
- automatic page-view tracking
- automatic hidden-field attachment
- target form selector
- optional IPRegistry enrichment

The config is safe for plain Laravel and for projects using `dominservice/laravel-config`.

---

## Framework-specific wiring

### Laravel ≤ 10 (classic structure)

No custom middleware alias is required for the base package.

Load the asset in your public layout:

```blade
@fingerprintTracking
```

### Laravel ≥ 11 (streamlined structure)

Load the asset exactly the same way:

```blade
@fingerprintTracking
```

The package registers its own route automatically through the service provider.

---

## Basic Usage

### 1. Add the Blade directive to your layout

```blade
@fingerprintTracking
```

### 2. Mark forms that should receive correlation fields

```html
<form method="POST" action="/submit" data-fingerprint-track>
    <!-- fields -->
</form>
```

The package will inject:

- `fingerprint`
- `tracking_event_ulid`

### 3. Trigger custom tracking events when needed

```javascript
window.DominserviceFingerprintTracking.track('form_view', {
    section: 'checkout'
});
```

### 4. Read the synchronized tracking context in protected requests

```php
$event = $request->attributes->get('fingerprint_tracking_event');
$payload = $request->attributes->get('fingerprint_tracking_payload');
```

---

## How It Works

1. The frontend generates a browser fingerprint.
2. The package sends a public tracking event to `POST /fingerprint-tracking/events`.
3. The server stores the fingerprint and the event internally using numeric IDs plus public ULIDs.
4. The public response exposes only ULIDs.
5. Selected forms receive the current fingerprint and `tracking_event_ulid`.
6. If an authenticated user already exists, the event can be bound through a configurable auth morph.
7. Later protected submits can resolve the same event again and enrich it with verification metadata.

---

## Public Endpoint

Default public endpoint:

`POST /fingerprint-tracking/events`

Example response:

```json
{
  "status": "OK",
  "fingerprint_ulid": "01JZC9E6D9V4M1Y3X6V8S2Q0AA",
  "tracking_event_ulid": "01JZC9E6F2Q3T6Z8R1N4B7M9CC"
}
```

The package intentionally does **not** expose incremental database IDs.

---

## Integration with invis-captcha

This package is designed to cooperate with `dominservice/invis-captcha`.

Recommended layout order:

```blade
@fingerprintTracking
@invisCaptcha
```

When both packages are present:

- fingerprint tracking starts as early as the public page loads,
- `invis-captcha` waits for the tracking package readiness promise,
- `invis-captcha` includes `fingerprint` and `tracking_event_ulid` in its signal payload,
- `invis.verify` resolves the stored tracking event by ULID,
- the request fingerprint, JWT fingerprint and stored fingerprint are cross-checked,
- the event is updated with `invis_score`, verification timestamp and request counters,
- authenticated users can be attached automatically through the configured auth morph,
- selected forms receive:
  - `invis_token`
  - `fingerprint`
  - `tracking_event_ulid`

The integration also emits:

- `dominservice:fingerprint-tracking:ready`
- `dominservice:invis:before-token`

so advanced projects can hook into the flow without patching package internals.

---

## Security Notes

- Public responses expose ULIDs, not incremental IDs.
- The browser fingerprint is not treated as a trusted identity on its own.
- Auth binding uses a configurable morph relation, so projects can follow integer IDs, UUIDs, ULIDs or generic string identifiers.
- The package is intended for correlation, enrichment and abuse mitigation support.
- For stronger bot protection, use it together with `dominservice/invis-captcha`.

---

## Compatibility and Support

- Works in standalone Laravel projects.
- Safe to use in projects with `dominservice/laravel-config`.
- Intended to be reusable in broader ecosystems such as DominPress modules.
- Does not assume a specific user primary-key format.
