/**
 * 情侣头像位置更新
 * 根据情侣坐标逆地理编码，更新头像 hover 区域的位置文字
 */
(function () {
    'use strict';
    var _initialized = false;

    function getCoords(slot) {
        var cfg = window.WITHU_CONFIG;
        if (!cfg) return null;
        if (slot === 1) return cfg.boyCoords || null;
        if (slot === 2) return cfg.girlCoords || null;
        return null;
    }

    // 加载 AMap.Geocoder 插件并逆地理编码
    function reverseGeocode(lng, lat, callback) {
        if (!window.AMap) {
            callback(null);
            return;
        }
        try {
            AMap.plugin('AMap.Geocoder', function () {
                try {
                    var geocoder = new AMap.Geocoder({ extensions: 'base' });
                    geocoder.getAddress([lng, lat], function (status, result) {
                        if (status === 'complete' && result.regeocode) {
                            var comp = result.regeocode.addressComponent || {};
                            var name = comp.township || comp.district || '';
                            if (name && name.length > 0) {
                                name = name.replace(/街道$/, '');
                                callback(name);
                                return;
                            }
                        }
                        callback(null);
                    });
                } catch (e) {
                    callback(null);
                }
            });
        } catch (e) {
            callback(null);
        }
    }

    function updateLocations() {
        var locEls = document.querySelectorAll('.withu-head-avatar-location[data-location-slot]');
        locEls.forEach(function (locEl) {
            var slot = parseInt(locEl.getAttribute('data-location-slot'), 10);
            var coords = getCoords(slot);
            if (!coords || coords.length < 2) {
                var em = locEl.querySelector('em');
                if (em) em.textContent = '未知';
                return;
            }
            var em = locEl.querySelector('em');
            if (!em) return;

            // 已经有地名了就不重复更新
            var current = em.textContent || '';
            if (current && current !== '加载中...' && current.indexOf(',') === -1 && current.length > 1) {
                return;
            }

            reverseGeocode(coords[0], coords[1], function (name) {
                if (name) {
                    em.textContent = name;
                } else {
                    em.textContent = coords[1].toFixed(2) + ', ' + coords[0].toFixed(2);
                }
            });
        });
    }

    function tryInit() {
        if (_initialized) return;

        if (window.AMap) {
            _initialized = true;
            updateLocations();
            return;
        }

        // 轮询等待 AMap SDK 加载（最多等 15 秒）
        var attempts = 0;
        var maxAttempts = 30;
        var pollTimer = setInterval(function () {
            attempts++;
            if (window.AMap) {
                clearInterval(pollTimer);
                _initialized = true;
                updateLocations();
            } else if (attempts >= maxAttempts) {
                clearInterval(pollTimer);
                _initialized = true;
                updateLocations();
            }
        }, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(tryInit, 500);
        });
    } else {
        setTimeout(tryInit, 500);
    }

    window.addEventListener('load', function () {
        tryInit();
    });
})();