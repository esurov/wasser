<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MyWasser</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="">

    <style>
        html, body { margin: 0; padding: 0; height: 100%; font-family: system-ui, sans-serif; }
        #map { position: absolute; inset: 0; }
        .panel {
            position: absolute; top: 12px; left: 12px; z-index: 1000;
            background: rgba(255,255,255,0.95); padding: 10px 14px; border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.15); font-size: 14px; max-width: 320px;
        }
        .panel h1 { margin: 0 0 4px 0; font-size: 16px; }
        .panel .status { color: #555; }
        .panel .error { color: #b00020; }
        .fountain-marker svg { width: 100%; height: 100%; display: block; }
        .fountain-popup b { display: block; margin-bottom: 2px; }
        .fountain-popup small { color: #666; }
        .fountain-popup .photos img { cursor: pointer; display: block; width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 4px; }
        .fountain-popup .actions { display: flex; justify-content: center; gap: 6px; margin-top: 6px; flex-wrap: nowrap; }
        .fountain-popup .pill-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; font-size: 14px;
            background: #eceff1; color: #555; border: none; border-radius: 50%; cursor: pointer;
            text-decoration: none;
        }
        .fountain-popup .pill-btn:hover { background: #cfd8dc; }
        .fountain-popup .pill-btn:disabled { opacity: 0.6; cursor: wait; }
        .fountain-popup .upload-msg { font-size: 12px; color: #b00020; margin-top: 4px; text-align: center; }

        .lightbox {
            position: fixed; inset: 0; background: rgba(0,0,0,0.92);
            z-index: 10000; display: none; align-items: center; justify-content: center;
            touch-action: manipulation;
        }
        .lightbox.open { display: flex; }
        .lightbox img {
            max-width: 100vw; max-height: 100vh; object-fit: contain;
            user-select: none; -webkit-user-drag: none;
        }
        .lightbox button {
            position: absolute; background: rgba(0,0,0,0.5); color: #fff;
            border: none; width: 48px; height: 48px; border-radius: 50%;
            font-size: 24px; cursor: pointer; display: flex;
            align-items: center; justify-content: center;
        }
        .lightbox .lb-close { top: 16px; right: 16px; }
        .lightbox .lb-prev  { left: 16px; top: 50%; transform: translateY(-50%); }
        .lightbox .lb-next  { right: 16px; top: 50%; transform: translateY(-50%); }
        .lightbox .lb-counter {
            position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%);
            color: #fff; background: rgba(0,0,0,0.5); padding: 4px 10px; border-radius: 12px;
            font-size: 13px;
        }
        .lightbox.single .lb-prev, .lightbox.single .lb-next, .lightbox.single .lb-counter { display: none; }
    </style>
</head>
<body>
    <div id="map"></div>
    <div class="panel">
        <h1>Drinking fountains <a href="#" id="recenter">near you</a></h1>
        <div id="status" class="status">Locating you…</div>
    </div>

    <div id="lightbox" class="lightbox" role="dialog" aria-label="Photo viewer">
        <button class="lb-prev" aria-label="Previous">&#10094;</button>
        <img id="lb-img" alt="">
        <button class="lb-next" aria-label="Next">&#10095;</button>
        <button class="lb-close" aria-label="Close">&times;</button>
        <div class="lb-counter"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>

    <script>
        const statusEl = document.getElementById('status');
        const searchRadiusMeters = 2000;
        const locationRefreshMs = 10000;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let currentUserLatLng = null;
        let userMarker = null;
        let userRadiusCircle = null;
        let nearbyLoaded = false;

        document.getElementById('recenter').addEventListener('click', e => {
            e.preventDefault();
            requestLocation({ recenter: true });
        });

        const map = L.map('map').setView([48.2082, 16.3738], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        const fountainSvg = @json(file_get_contents(resource_path('images/trinkbrunnen.svg')));
        const fountainIcon = L.divIcon({
            className: 'fountain-marker',
            html: `<div style="width:32px;height:32px;filter:drop-shadow(0 1px 3px rgba(0,0,0,.4));">${fountainSvg}</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16],
        });

        const toiletIcon = L.divIcon({
            className: 'toilet-marker',
            html: '<div style="background:#6d4c41;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,.3);font-size:11px;font-weight:700;letter-spacing:0.5px;">WC</div>',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });

        const userIcon = L.divIcon({
            className: 'user-marker',
            html: '<div style="background:#e53935;border:3px solid #fff;border-radius:50%;width:18px;height:18px;box-shadow:0 1px 4px rgba(0,0,0,.4);"></div>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        });

        function haversine(a, b) {
            const R = 6371000;
            const toRad = d => d * Math.PI / 180;
            const dLat = toRad(b.lat - a.lat);
            const dLon = toRad(b.lng - a.lng);
            const lat1 = toRad(a.lat);
            const lat2 = toRad(b.lat);
            const x = Math.sin(dLat/2) ** 2 + Math.sin(dLon/2) ** 2 * Math.cos(lat1) * Math.cos(lat2);
            return 2 * R * Math.asin(Math.sqrt(x));
        }

        async function fetchLayer(path) {
            const res = await fetch(path, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(`Layer request failed: ${res.status}`);
            const data = await res.json();
            return data.features || [];
        }

        const fetchFountains = () => fetchLayer('/api/fountains');
        const fetchToilets = () => fetchLayer('/api/toilets');

        async function sha1Hex(str) {
            const buf = new TextEncoder().encode(str);
            const hash = await crypto.subtle.digest('SHA-1', buf);
            return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
        }

        function shapeKey(el) {
            const shape = el.properties?.SHAPE;
            if (shape) return String(shape);
            return `${el.lat.toFixed(7)},${el.lon.toFixed(7)}`;
        }

        async function fetchPhotos(shapeHash) {
            const res = await fetch(`/fountains/${shapeHash}/photos`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Fetch photos failed: ' + res.status);
            const json = await res.json();
            return json.data || [];
        }

        async function resizeImage(file, maxEdge = 1600, quality = 0.85) {
            if (!file.type.startsWith('image/')) return file;

            let bitmap;
            try {
                bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
            } catch {
                return file;
            }

            const { width, height } = bitmap;
            const scale = Math.min(1, maxEdge / Math.max(width, height));

            if (scale === 1 && file.size <= 2 * 1024 * 1024) {
                bitmap.close();
                return file;
            }

            const w = Math.round(width * scale);
            const h = Math.round(height * scale);
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            canvas.getContext('2d').drawImage(bitmap, 0, 0, w, h);
            bitmap.close();

            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
            if (!blob) return file;

            const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
            return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
        }

        async function uploadPhoto(shapeHash, file) {
            const form = new FormData();
            form.append('photo', file);
            const res = await fetch(`/fountains/${shapeHash}/photos`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: form,
            });
            if (!res.ok) {
                let msg = 'Upload failed';
                try { const j = await res.json(); msg = j.message || msg; } catch {}
                throw new Error(msg);
            }
            const json = await res.json();
            return json.data;
        }

        function renderThumbnails(container, photos) {
            container.innerHTML = '';
            if (!photos.length) return;
            const thumb = document.createElement('img');
            thumb.src = photos[0].url;
            thumb.alt = '';
            thumb.addEventListener('click', () => openLightbox(photos, 0));
            container.appendChild(thumb);
            if (photos.length > 1) {
                const badge = document.createElement('small');
                badge.textContent = `+${photos.length - 1} more`;
                badge.style.cssText = 'display:block;color:#888;margin-top:2px;';
                container.appendChild(badge);
            }
        }

        async function loadPhotos(popupEl, shapeHash) {
            if (!popupEl) return;

            const container = popupEl.querySelector('.photos');
            const btn = popupEl.querySelector('.photo-btn');
            const input = popupEl.querySelector('.photo-input');
            const msg = popupEl.querySelector('.upload-msg');

            container.innerHTML = '<small style="color:#888;">Loading photos…</small>';
            try {
                const photos = await fetchPhotos(shapeHash);
                renderThumbnails(container, photos);
            } catch (e) {
                container.innerHTML = '<small style="color:#888;">Could not load photos.</small>';
            }

            btn.addEventListener('click', () => input.click());
            input.addEventListener('change', async () => {
                const file = input.files?.[0];
                if (!file) return;
                btn.disabled = true;
                msg.textContent = 'Processing…';
                msg.style.color = '#888';
                try {
                    const resized = await resizeImage(file);
                    msg.textContent = 'Uploading…';
                    await uploadPhoto(shapeHash, resized);
                    msg.textContent = '';
                    const latest = await fetchPhotos(shapeHash);
                    renderThumbnails(container, latest);
                } catch (e) {
                    msg.style.color = '';
                    msg.textContent = e.message;
                } finally {
                    btn.disabled = false;
                    input.value = '';
                }
            });
        }

        const lightbox = document.getElementById('lightbox');
        const lbImg = document.getElementById('lb-img');
        const lbCounter = lightbox.querySelector('.lb-counter');
        let lbImages = [];
        let lbIndex = 0;

        function showLightboxImage() {
            lbImg.src = lbImages[lbIndex].url;
            lbCounter.textContent = `${lbIndex + 1} / ${lbImages.length}`;
        }

        function openLightbox(images, index) {
            lbImages = images;
            lbIndex = index;
            lightbox.classList.toggle('single', images.length <= 1);
            lightbox.classList.add('open');
            showLightboxImage();
        }

        function closeLightbox() {
            lightbox.classList.remove('open');
            lbImg.src = '';
        }

        function stepLightbox(delta) {
            if (!lbImages.length) return;
            lbIndex = (lbIndex + delta + lbImages.length) % lbImages.length;
            showLightboxImage();
        }

        lightbox.querySelector('.lb-close').addEventListener('click', closeLightbox);
        lightbox.querySelector('.lb-prev').addEventListener('click', () => stepLightbox(-1));
        lightbox.querySelector('.lb-next').addEventListener('click', () => stepLightbox(1));
        lightbox.addEventListener('click', e => { if (e.target === lightbox) closeLightbox(); });
        document.addEventListener('keydown', e => {
            if (!lightbox.classList.contains('open')) return;
            if (e.key === 'Escape') closeLightbox();
            else if (e.key === 'ArrowLeft') stepLightbox(-1);
            else if (e.key === 'ArrowRight') stepLightbox(1);
        });

        let touchStartX = null;
        lbImg.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].clientX; }, { passive: true });
        lbImg.addEventListener('touchend', e => {
            if (touchStartX === null) return;
            const dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 40) stepLightbox(dx < 0 ? 1 : -1);
            touchStartX = null;
        });

        async function renderFountains(elements, userLatLng) {
            const withDistance = elements
                .map(e => ({ el: e, latlng: L.latLng(e.lat, e.lon) }))
                .map(o => ({ ...o, distance: haversine(userLatLng, o.latlng) }))
                .filter(o => o.distance <= searchRadiusMeters)
                .sort((a, b) => a.distance - b.distance);

            const decorated = await Promise.all(withDistance.map(async o => ({
                ...o,
                shapeHash: await sha1Hex(shapeKey(o.el)),
            })));

            decorated.forEach(({ el, latlng, distance, shapeHash }, idx) => {
                const name = el.properties?.BASIS_TYP_TXT || 'Drinking fountain';
                const googleUrl = `https://www.google.com/maps/place/${el.lat},${el.lon}/@${el.lat},${el.lon},19z`;
                const marker = L.marker(latlng, { icon: fountainIcon }).addTo(map);
                marker.bindPopup(`
                    <div class="fountain-popup">
                        <b>${name}</b>
                        <small>${Math.round(distance)} m away</small>
                        <div class="photos" style="margin-top:6px;"></div>
                        <div class="actions">
                            <a class="pill-btn" href="${googleUrl}" target="_blank" rel="noopener" title="Google Maps" aria-label="Google Maps">
                                <img src="https://www.gstatic.com/images/branding/product/2x/maps_48dp.png" alt="" style="width:18px;height:18px;">
                            </a>
                            <button class="pill-btn photo-btn" type="button" title="Add photo" aria-label="Add photo">📷</button>
                        </div>
                        <input class="photo-input" type="file" accept="image/jpeg,image/png,image/webp" style="display:none;">
                        <div class="upload-msg"></div>
                    </div>
                `);
                marker.on('popupopen', e => loadPhotos(e.popup.getElement(), shapeHash));
                if (idx === 0) marker.openPopup();
            });

            statusEl.textContent = decorated.length
                ? `Found ${decorated.length} fountain${decorated.length === 1 ? '' : 's'} within ${searchRadiusMeters / 1000} km.`
                : `No fountains found within ${searchRadiusMeters / 1000} km.`;
        }

        function renderToilets(elements, userLatLng) {
            elements
                .map(e => ({ el: e, latlng: L.latLng(e.lat, e.lon) }))
                .map(o => ({ ...o, distance: haversine(userLatLng, o.latlng) }))
                .filter(o => o.distance <= searchRadiusMeters)
                .forEach(({ el, latlng, distance }) => {
                    const t = el.properties || {};
                    const title = t.KATEGORIE || 'Public toilet';
                    const street = t.STRASSE ? `<div><small>${t.STRASSE}</small></div>` : '';
                    const hours = t.OEFFNUNGSZEIT ? `<div><small>🕑 ${t.OEFFNUNGSZEIT}</small></div>` : '';
                    L.marker(latlng, { icon: toiletIcon }).addTo(map).bindPopup(`
                        <div class="fountain-popup">
                            <b>${title}</b>
                            ${street}
                            ${hours}
                            <small>${Math.round(distance)} m away</small>
                        </div>
                    `);
                });
        }

        function placeUser(latLng) {
            currentUserLatLng = latLng;
            if (!userMarker) {
                userMarker = L.marker(latLng, { icon: userIcon, title: 'You are here' }).addTo(map);
                userRadiusCircle = L.circle(latLng, { radius: searchRadiusMeters, color: '#e53935', weight: 1, fillOpacity: 0.05 }).addTo(map);
            } else {
                userMarker.setLatLng(latLng);
                userRadiusCircle.setLatLng(latLng);
            }
        }

        function loadNearby(userLatLng) {
            statusEl.textContent = 'Loading nearby fountains & toilets…';
            Promise.allSettled([fetchFountains(), fetchToilets()])
                .then(([fountainsRes, toiletsRes]) => {
                    if (fountainsRes.status === 'fulfilled') {
                        renderFountains(fountainsRes.value, userLatLng);
                    }
                    if (toiletsRes.status === 'fulfilled') {
                        renderToilets(toiletsRes.value, userLatLng);
                    }
                    if (fountainsRes.status === 'rejected' && toiletsRes.status === 'rejected') {
                        statusEl.textContent = 'Could not load map data.';
                        statusEl.className = 'error';
                    }
                });
        }

        function requestLocation({ recenter = false } = {}) {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(
                pos => {
                    const latLng = L.latLng(pos.coords.latitude, pos.coords.longitude);
                    placeUser(latLng);
                    if (recenter || !nearbyLoaded) map.setView(latLng, 16);
                    if (!nearbyLoaded) {
                        nearbyLoaded = true;
                        loadNearby(latLng);
                    }
                },
                err => {
                    if (nearbyLoaded) return;
                    statusEl.textContent = 'Location unavailable (' + err.message + '). Showing Vienna.';
                    statusEl.className = 'error';
                    const fallback = L.latLng(48.2082, 16.3738);
                    placeUser(fallback);
                    map.setView(fallback, 16);
                    nearbyLoaded = true;
                    loadNearby(fallback);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }

        if (!navigator.geolocation) {
            statusEl.textContent = 'Geolocation is not supported by your browser.';
            statusEl.className = 'error';
        } else {
            requestLocation();
            setInterval(requestLocation, locationRefreshMs);
        }
    </script>
</body>
</html>
