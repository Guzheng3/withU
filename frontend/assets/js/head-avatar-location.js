/**
 * 情侣头像位置更新
 * 更新头像 hover 区域的位置文字（坐标来自实时上报或后台配置，直接展示坐标，不加载地图 SDK）
 */
(function () {
    'use strict';
    var _initialized = false;

    // 共享的实时位置模块：从 location-beacon 读取双方最新高德定位上报
    // slot1 -> user1（男/我），slot2 -> user2（女/TA）
    var LIVE_GEO_TTL = 60 * 1000;      // 页面内缓存 60s
    var LIVE_GEO_FRESH = 12 * 3600 * 1000; // 上报超过 12 小时视为过期
    var _liveGeoData = null;
    var _liveGeoTs = 0;
    var _liveGeoFetching = null;

    function fetchLiveGeo() {
        var now = Date.now();
        if (_liveGeoData && (now - _liveGeoTs) < LIVE_GEO_TTL) {
            return Promise.resolve(_liveGeoData);
        }
        if (_liveGeoFetching) return _liveGeoFetching;
        var base = (window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase) || '';
        _liveGeoFetching = fetch(base + '/services/location-beacon.php', { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                _liveGeoData = (data && data.code === 200 && data.data) ? data.data : null;
                _liveGeoTs = Date.now();
                return _liveGeoData;
            })
            .catch(function () { return null; })
            .then(function (v) { _liveGeoFetching = null; return v; });
        return _liveGeoFetching;
    }

    function slotLiveCoords(slot) {
        slot = parseInt(slot, 10); // 调用方可能传入 getAttribute 的字符串
        if (!_liveGeoData) return null;
        var entry = slot === 1 ? _liveGeoData.user1 : _liveGeoData.user2;
        if (!entry || typeof entry.lat !== 'number' || typeof entry.lng !== 'number') return null;
        // 服务端 ts 为秒，转毫秒后比较
        if ((Date.now() - (entry.ts || 0) * 1000) > LIVE_GEO_FRESH) return null;
        // 统一为 [lng, lat]，与 WITHU_CONFIG.boyCoords/girlCoords 格式一致
        return [entry.lng, entry.lat];
    }

    window.WithULiveGeo = {
        refresh: fetchLiveGeo,
        slotCoords: function (slot) { return slotLiveCoords(slot); }
    };

    function getCoords(slot) {
        var cfg = window.WITHU_CONFIG;
        if (!cfg) return null;
        if (slot === 1) return cfg.boyCoords || null;
        if (slot === 2) return cfg.girlCoords || null;
        return null;
    }

    // 位置文案：后台设置的地名优先（WITHU_CONFIG.boyLocation/girlLocation），
    // 没设置时展示坐标；不加载地图 SDK
    function formatCoords(coords) {
        return coords[1].toFixed(2) + ', ' + coords[0].toFixed(2);
    }

    function updateLocations() {
        var cfg = window.WITHU_CONFIG || {};
        var locEls = document.querySelectorAll('.withu-head-avatar-location[data-location-slot]');
        locEls.forEach(function (locEl) {
            var slot = parseInt(locEl.getAttribute('data-location-slot'), 10);
            var em = locEl.querySelector('em');
            if (!em) return;
            // 后台设置的地名优先
            var configuredName = slot === 1 ? (cfg.boyLocation || '') : (cfg.girlLocation || '');
            if (configuredName) {
                em.textContent = configuredName;
                return;
            }
            // 后台没设置：坐标兜底（实时上报优先，其次后台配置坐标）
            var coords = (window.WithULiveGeo && window.WithULiveGeo.slotCoords(slot)) || getCoords(slot);
            em.textContent = (coords && coords.length >= 2) ? formatCoords(coords) : '未知';
        });
    }

    function tryInit() {
        if (_initialized) return;
        _initialized = true;
        // 先取双方实时位置（有上报则用实时坐标），再更新地名
        var kick = function () { updateLocations(); };
        if (window.WithULiveGeo) {
            window.WithULiveGeo.refresh().then(kick).catch(kick);
            // 兜底：实时位置拉取慢时不阻塞地名显示
            setTimeout(kick, 2500);
        } else {
            kick();
        }
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