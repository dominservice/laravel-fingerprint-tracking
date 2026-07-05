(function () {
    'use strict';

    const config = Object.assign({
        endpoint: '/fingerprint-tracking/events',
        cookie_name: 'fingerprint',
        auto_page_view: true,
        attach_hidden_fields: true,
        form_selector: 'form[data-invis], form[data-fingerprint-track]',
        event_name: 'page_view',
        csrf_token: null
    }, window.dominserviceFingerprintTrackingConfig || {});

    const state = {
        fingerprint: null,
        trackingEventUlid: null,
        ready: null
    };

    function setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 86400000).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        return document.cookie.split('; ').reduce((found, part) => {
            if (found !== null) {
                return found;
            }

            const idx = part.indexOf('=');
            if (idx === -1) {
                return null;
            }

            const key = part.slice(0, idx);
            const value = part.slice(idx + 1);

            return key === name ? decodeURIComponent(value) : null;
        }, null);
    }

    function hashString(input) {
        let h1 = 0xdeadbeef ^ input.length;
        let h2 = 0x41c6ce57 ^ input.length;

        for (let i = 0; i < input.length; i += 1) {
            const ch = input.charCodeAt(i);
            h1 = Math.imul(h1 ^ ch, 2654435761);
            h2 = Math.imul(h2 ^ ch, 1597334677);
        }

        h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
        h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);

        return (4294967296 * (2097151 & h2) + (h1 >>> 0)).toString(16);
    }

    function canvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const txt = 'dominservice-fingerprint';
            ctx.textBaseline = 'top';
            ctx.font = "14px 'Arial'";
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText(txt, 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText(txt, 4, 17);

            return canvas.toDataURL();
        } catch (e) {
            return '';
        }
    }

    function guessMobile() {
        return /android|iphone|ipad|ipod|mobile|phone/i.test(navigator.userAgent || '');
    }

    function guessBrowser() {
        const ua = (navigator.userAgent || '').toLowerCase();

        if (ua.indexOf('edg/') > -1) return 'Edge';
        if (ua.indexOf('opr/') > -1 || ua.indexOf('opera') > -1) return 'Opera';
        if (ua.indexOf('chrome') > -1) return 'Chrome';
        if (ua.indexOf('firefox') > -1) return 'Firefox';
        if (ua.indexOf('safari') > -1) return 'Safari';

        return 'Unknown';
    }

    function guessOs() {
        const ua = navigator.userAgent || '';

        if (/windows/i.test(ua)) return 'Windows';
        if (/android/i.test(ua)) return 'Android';
        if (/iphone|ipad|ipod/i.test(ua)) return 'iOS';
        if (/mac/i.test(ua)) return 'Mac OS';
        if (/linux/i.test(ua)) return 'Linux';

        return 'Unknown';
    }

    function buildFingerprintData() {
        const screenWidth = window.screen && window.screen.width ? String(window.screen.width) : null;
        const screenHeight = window.screen && window.screen.height ? String(window.screen.height) : null;
        const browser = guessBrowser();
        const os = guessOs();
        const language = navigator.language || null;
        const mobile = guessMobile();
        const seed = [
            navigator.userAgent || '',
            language || '',
            screen.colorDepth || '',
            screenWidth || '',
            screenHeight || '',
            new Date().getTimezoneOffset(),
            navigator.platform || '',
            navigator.doNotTrack || '',
            canvasFingerprint()
        ].join('###');

        return {
            fingerprint: hashString(seed),
            mobile: mobile,
            browser: browser,
            browserVersion: null,
            os: os,
            osVersion: null,
            language: language,
            screenWidth: screenWidth,
            screenHeight: screenHeight
        };
    }

    function ensureFormFields() {
        if (!config.attach_hidden_fields) {
            return;
        }

        document.querySelectorAll(config.form_selector).forEach((form) => {
            let fingerprintField = form.querySelector('input[name="fingerprint"]');
            if (!fingerprintField) {
                fingerprintField = document.createElement('input');
                fingerprintField.type = 'hidden';
                fingerprintField.name = 'fingerprint';
                form.appendChild(fingerprintField);
            }
            fingerprintField.value = state.fingerprint || '';

            let trackingField = form.querySelector('input[name="tracking_event_ulid"]')
                || form.querySelector('input[name="tracking_event_id"]');
            if (!trackingField) {
                trackingField = document.createElement('input');
                trackingField.type = 'hidden';
                trackingField.name = 'tracking_event_ulid';
                form.appendChild(trackingField);
            }
            trackingField.name = 'tracking_event_ulid';
            trackingField.value = state.trackingEventUlid || '';
        });
    }

    async function sendEvent(eventName, extraContext) {
        const fingerprintData = buildFingerprintData();
        state.fingerprint = state.fingerprint || getCookie(config.cookie_name) || fingerprintData.fingerprint;

        const payload = Object.assign({}, fingerprintData, {
            fingerprint: state.fingerprint,
            eventName: eventName || config.event_name,
            url: window.location.href,
            referer: document.referrer || null,
            routeName: document.body ? document.body.dataset.routeName || null : null,
            context: extraContext || {}
        });

        const response = await fetch(config.endpoint, {
            method: 'POST',
            headers: Object.assign({
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }, config.csrf_token ? {'X-CSRF-TOKEN': config.csrf_token} : {}),
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        });

        if (!response.ok) {
            return null;
        }

        const json = await response.json();

        state.fingerprint = payload.fingerprint;
        state.trackingEventUlid = json.tracking_event_ulid || null;
        setCookie(config.cookie_name, state.fingerprint, 365);
        ensureFormFields();

        return json;
    }

    function emitReadyEvent() {
        window.dispatchEvent(new CustomEvent('dominservice:fingerprint-tracking:ready', {
            detail: {
                fingerprint: state.fingerprint,
                tracking_event_ulid: state.trackingEventUlid
            }
        }));
    }

    window.DominserviceFingerprintTracking = {
        state: state,
        track: sendEvent,
        ensureFormFields: ensureFormFields
    };

    document.addEventListener('DOMContentLoaded', async function () {
        ensureFormFields();

        if (config.auto_page_view) {
            state.ready = sendEvent(config.event_name, {})
                .then(function (result) {
                    emitReadyEvent();
                    return result;
                });
            await state.ready;
        } else {
            state.ready = Promise.resolve(null);
            emitReadyEvent();
        }

        document.querySelectorAll(config.form_selector).forEach((form) => {
            form.addEventListener('submit', function () {
                ensureFormFields();
            });
        });
    });
})();
