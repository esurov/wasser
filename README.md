# wasser

A small Laravel web app that shows a live map of **drinking fountains and public toilets in Vienna**, centered on your current location, with community-contributed photos.

Data comes from the [City of Vienna Open Data portal](https://www.data.gv.at/katalog/dataset?organization=stadt-wien) (MA 31 drinking fountains, MA 48 public toilets). The map is rendered with [Leaflet](https://leafletjs.com/) on [OpenStreetMap](https://www.openstreetmap.org/) tiles.

Inspired by [this R/Leaflet analysis](https://rpubs.com/HN317/525002) of Vienna's fountain network.

## Features

- Full-page interactive map with auto-centering on your GPS location (falls back to the city center).
- Two layers with distinct markers:
  - 💧 **Drinking fountains** — `TRINKBRUNNENOGD` dataset, popup shows the `BASIS_TYP_TXT` (e.g. *Auslaufbrunnen mit Tränke*).
  - **WC** **Public toilets** — `WCANLAGEOGD` dataset, popup shows category, address, opening hours.
- Fountains within 2 km of you are sorted by distance; nearest one opens automatically.
- Each fountain popup has:
  - A Google Maps icon link → nearby user-submitted photos from Google.
  - Community photo uploads — click 📷 **Photo** to submit your own (JPEG / PNG / WebP, max 5 MB).
  - A fullscreen lightbox (keyboard, swipe, click-to-close) when there are multiple photos.

## Tech stack

- **PHP** 8.4+ / **Laravel** 13
- **Livewire** 4, **Tailwind** v4 (scaffolded; not heavily used yet)
- **Pest** 4 for tests
- **SQLite** by default (swap via `.env`)
- **Leaflet** 1.9 from CDN — no build step required

## Quick start

```bash
git clone git@github.com:esurov/wasser.git
cd wasser

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan storage:link

php artisan serve
```

Open <http://localhost:8000>. Geolocation needs HTTPS or `localhost`.

## Data sources

| Layer              | Vienna Open Data layer       | Key field           |
|--------------------|------------------------------|---------------------|
| Drinking fountains | `ogdwien:TRINKBRUNNENOGD`    | `BASIS_TYP_TXT`     |
| Public toilets     | `ogdwien:WCANLAGEOGD`        | `KATEGORIE`         |

Both are fetched client-side as GeoJSON and filtered by distance in the browser.

## User-uploaded photos

Photos are stored on the `public` disk under `storage/app/public/fountain_photos/{OBJECTID}/` and linked to the Vienna `OBJECTID` of the fountain (no user accounts yet).

| Method | Route                                     | Notes                         |
|--------|-------------------------------------------|-------------------------------|
| GET    | `/fountains/{objectId}/photos`            | Returns `{ data: Photo[] }`   |
| POST   | `/fountains/{objectId}/photos`            | Throttled 10/min per IP       |

Validation: `image`, `mimes:jpeg,png,webp`, `max:15360` KB.

## Deployment

Uses [spatie/scotty](https://github.com/spatie/scotty) for SSH-based deploys. See [`Scotty.sh`](Scotty.sh) for the pipeline (`pullCode` → `installDeps` → `migrate` → `optimize`).

```bash
scotty doctor        # verify SSH + environment
scotty run deploy    # deploy to production
scotty run rollback  # roll back one commit
```

## Tests

```bash
./vendor/bin/pest
```

CI runs the test suite against PHP 8.4 and 8.5 on every push / PR to `main`, `develop`, `master`, `workos` (see `.github/workflows/`).

## License

MIT
