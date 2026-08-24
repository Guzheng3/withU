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

    // 使用 AMap SDK 的 Geocoder 逆地理编码
    function reverseGeocodeSDK(lng, lat, callback) {
        if (!window.AMap || !window.AMap.Geocoder) {
            callback(null);
            return;
        }
        try {
            var geocoder = new AMap.Geocoder({ extensions: 'base' });
            geocoder.getAddress([lng, lat], function (status, result) {
                if (status === 'complete' && result.regeocode) {
                    var comp = result.regeocode.addressComponent || {};
                    // 优先显示乡镇/街道，其次区县
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
            if (em.textContent && em.textContent !== '加载中...' && em.textContent.indexOf(',') === -1) {
                return;
            }

            reverseGeocodeSDK(coords[0], coords[1], function (name) {
                if (name) {
                    em.textContent = name;
                } else {
                    // 保留坐标作为兜底
                    em.textContent = coords[1].toFixed(2) + ', ' + coords[0].toFixed(2);
                }
            });
        });
    }

    function tryInit() {
        if (_initialized) return;

        // 检查 AMap SDK 是否就绪
        if (window.AMap && window.AMap.Geocoder) {
            _initialized = true;
            updateLocations();
            return;
        }

        // 轮询等待 AMap 加载（最多等 10 秒）
        var attempts = 0;
        var maxAttempts = 20;
        var pollTimer = setInterval(function () {
            attempts++;
            if (window.AMap && window.AMap.Geocoder) {
                clearInterval(pollTimer);
                _initialized = true;
                updateLocations();
            } else if (attempts >= maxAttempts) {
                clearInterval(pollTimer);
                _initialized = true;
                // 超时后显示坐标兜底
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

    // 同时监听 AMap 加载完成事件
    window.addEventListener('load', function () {
        tryInit();
    });
})();