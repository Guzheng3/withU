/**
 * 情侣头像位置更新
 * 根据情侣坐标逆地理编码，更新头像 hover 区域的位置文字
 */
(function () {
    'use strict';

    function getCoords(slot) {
        var cfg = window.WITHU_CONFIG;
        if (!cfg) return null;
        if (slot === 1) return cfg.boyCoords || null;
        if (slot === 2) return cfg.girlCoords || null;
        return null;
    }

    function updateLocation(emEl, lng, lat) {
        // 优先使用高德逆地理编码
        if (window.AMap && window.AMap.Geocoder) {
            try {
                var geocoder = new AMap.Geocoder({ extensions: 'base' });
                geocoder.getAddress([lng, lat], function (status, result) {
                    if (status === 'complete' && result.regeocode) {
                        var comp = result.regeocode.addressComponent || {};
                        var name = comp.township || comp.district || comp.street || '';
                        if (name) {
                            emEl.textContent = name;
                            return;
                        }
                    }
                    // 回退到坐标
                    emEl.textContent = lat.toFixed(2) + ', ' + lng.toFixed(2);
                });
            } catch (e) {
                emEl.textContent = lat.toFixed(2) + ', ' + lng.toFixed(2);
            }
        } else {
            // 无 AMap 时显示坐标
            emEl.textContent = lat.toFixed(2) + ', ' + lng.toFixed(2);
        }
    }

    function init() {
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
            if (em) {
                updateLocation(em, coords[0], coords[1]);
            }
        });
    }

    // 等 AMap 加载完成后执行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            // 延迟一下确保 AMap 已加载
            setTimeout(init, 500);
        });
    } else {
        setTimeout(init, 500);
    }

    // 也监听 AMap 加载完成事件
    window.addEventListener('load', function () {
        setTimeout(init, 300);
    });
})();