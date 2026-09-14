
    (function () {
        var requestId = "eeb93a4af9c10b3216a41c00bc813c4e";
        var token = "156fbdab2af4fb85f26b3345684aef1ec279918d67230d7fbfbe4778af3fdf21";
        window.WITHU_CONFIG = Object.assign(window.WITHU_CONFIG || {}, {
            endpoints: Object.assign({}, (window.WITHU_CONFIG && window.WITHU_CONFIG.endpoints) || {}, {
                accessBeacon: "services/access-beacon.php"            })
        });

        var endpoint = (window.WITHU_CONFIG && window.WITHU_CONFIG.endpoints && window.WITHU_CONFIG.endpoints.accessBeacon) || '';
        if (!endpoint || !navigator.sendBeacon) {
            return;
        }

        var current = {
            requestId: '',
            token: ''
        };
        var startAt = Date.now();
        var reported = false;

        function isValidHex(value, len) {
            return new RegExp('^[a-f0-9]{' + len + '}$').test(String(value || ''));
        }

        function setContext(nextRequestId, nextToken) {
            var rid = String(nextRequestId || '').trim().toLowerCase();
            var t = String(nextToken || '').trim().toLowerCase();
            if (!isValidHex(rid, 32) || !isValidHex(t, 64)) {
                return false;
            }
            current.requestId = rid;
            current.token = t;
            startAt = Date.now();
            reported = false;
            return true;
        }

        function reportStay() {
            if (reported) {
                return;
            }
            if (!isValidHex(current.requestId, 32) || !isValidHex(current.token, 64)) {
                return;
            }
            reported = true;

            var staySeconds = Math.max(0, Math.round((Date.now() - startAt) / 1000));
            if (staySeconds > 86400) {
                staySeconds = 86400;
            }

            var formData = new FormData();
            formData.append('request_id', current.requestId);
            formData.append('stay_seconds', String(staySeconds));
            formData.append('token', current.token);
            navigator.sendBeacon(endpoint, formData);
        }

        setContext(requestId, token);

        window.addEventListener('pagehide', reportStay, { once: true });
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                reportStay();
            }
        }, { passive: true });

        if (window.jQuery && typeof window.jQuery.fn === 'object') {
            window.jQuery(document).on('pjax:send', function () {
                reportStay();
            });
            window.jQuery(document).on('pjax:complete', function (event, xhr) {
                if (!xhr || typeof xhr.getResponseHeader !== 'function') {
                    return;
                }
                var nextRequestId = xhr.getResponseHeader('X-WithU-Access-Request-Id') || '';
                var nextToken = xhr.getResponseHeader('X-WithU-Access-Beacon-Token') || '';
                setContext(nextRequestId, nextToken);
            });
        }
    })();
