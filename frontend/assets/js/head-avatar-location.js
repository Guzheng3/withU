/**
 * 情侣头像位置更新
 * 更新头像 hover 区域的位置文字（坐标来自实时上报或后台配置，反查地名需加载地图 SDK）
 */
(function () {
    'use strict';
    var _initialized = false;

    // 共享的 AMap SDK 按需加载器（本地自带 SDK 包，地图页外的功能也复用）
    // 供 head-avatar-location / page-index / withu-location 共同使用：
    // withu-location 在浏览器定位后必须经 AMap.convertFrom 把 WGS-84 转成高德 GCJ-02，
    // 缺少此加载器时会上报未转换的原始坐标，导致地图标记偏差数百米
    var _sdkQueue = [];
    var _sdkLoading = false;
    function ensureAMap(callback) {
        if (window.AMap) {
            callback(true);
            return;
        }
        _sdkQueue.push(callback);
        if (_sdkLoading) return;
        _sdkLoading = true;
        var base = (window.WITHU_CONFIG && window.WITHU_CONFIG.assetBase) || '';
        var s = document.createElement('script');
        s.src = base + 'assets/js/map-sdk.js';
        s.onload = function () {
            _sdkLoading = false;
            var ok = !!window.AMap;
            _sdkQueue.forEach(function (cb) { try { cb(ok); } catch (e) {} });
            _sdkQueue = [];
        };
        s.onerror = function () {
            _sdkLoading = false;
            _sdkQueue.forEach(function (cb) { try { cb(false); } catch (e) {} });
            _sdkQueue = [];
        };
        document.head.appendChild(s);
    }
    window.WithUAMapLoader = { ensure: ensureAMap };

    // 共享的实时位置模块：从 location-beacon 读取双方最新高德定位上报
    // slot1 -> user1（男/我），slot2 -> user2（女/TA）
    var LIVE_GEO_TTL = 60 * 1000;      // 页面内缓存 60s
    var LIVE_GEO_FRESH = 12 * 3600 * 1000; // 上报超过 12 小时视为过期
    var _liveGeoData = null;
    var _liveGeoTs = 0;
    var _liveGeoFetching = null;
    var _liveGeoSeq = 0;

    function fetchLiveGeo(force) {
        var now = Date.now();
        if (!force && _liveGeoData && (now - _liveGeoTs) < LIVE_GEO_TTL) {
            return Promise.resolve(_liveGeoData);
        }
        if (_liveGeoFetching && !force) return _liveGeoFetching;
        var base = (window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase) || '';
        var seq = ++_liveGeoSeq;
        var promise = fetch(base + '/services/location-beacon.php', { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                // 只让最新一次请求写缓存，避免 GPS 上报前的旧请求晚到覆盖新坐标
                if (seq === _liveGeoSeq) {
                    _liveGeoData = (data && data.code === 200 && data.data) ? data.data : null;
                    _liveGeoTs = Date.now();
                }
                return _liveGeoData;
            })
            .catch(function () { return null; })
            .then(function (v) {
                if (seq === _liveGeoSeq) {
                    _liveGeoFetching = null;
                }
                return v;
            });
        _liveGeoFetching = promise;
        return promise;
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
        if (slot === 1 && cfg.boyCoords) return cfg.boyCoords;
        if (slot === 2 && cfg.girlCoords) return cfg.girlCoords;
        // 没有地图坐标时，用后台「位置」兜底（两个头像同一地点）
        if (cfg.weatherLocLat && cfg.weatherLocLng) {
            var lat = Number(cfg.weatherLocLat);
            var lng = Number(cfg.weatherLocLng);
            if (isFinite(lat) && isFinite(lng)) {
                return [lng, lat];
            }
        }
        return null;
    }

    // 位置文案：头像位置与天气共用同一位置来源（高德定位 SDK / location-beacon 实时上报），
    // 实时坐标优先，其次后台配置坐标；坐标反查地名失败时展示坐标
    function formatCoords(coords) {
        return coords[1].toFixed(2) + ', ' + coords[0].toFixed(2);
    }

    // 球面距离（km）
    function distanceKm(lat1, lng1, lat2, lng2) {
        var R = 6371;
        var toRad = function (v) { return v * Math.PI / 180; };
        var dLat = toRad(lat2 - lat1);
        var dLng = toRad(lng2 - lng1);
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2))
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // 首页情侣头像之间的「相距」气泡：使用双方实时定位，不再显示写死数值
    function updateDistanceBubble() {
        var bubble = document.querySelector('.distance-bubble[data-live-distance="1"]');
        if (!bubble) return;
        var textEl = bubble.querySelector('.distance-text');
        if (!textEl) return;

        // 实时 GPS 上报优先；没有上报时用地图/后台配置坐标兜底
        var c1 = slotLiveCoords(1) || getCoords(1);
        var c2 = slotLiveCoords(2) || getCoords(2);
        if (!c1 || !c2) {
            textEl.innerHTML = '<span class="distance-text-sm">相距</span><span class="km-value">--</span><span class="distance-text-sm">km</span>';
            return;
        }

        var km = distanceKm(c1[1], c1[0], c2[1], c2[0]);
        if (!isFinite(km)) {
            textEl.innerHTML = '<span class="distance-text-sm">相距</span><span class="km-value">--</span><span class="distance-text-sm">km</span>';
            return;
        }
        if (km < 0.01) {
            textEl.innerHTML = '<span class="km-value">就在身边</span>';
            return;
        }

        var value, unit;
        if (km < 1) {
            value = Math.round(km * 1000);
            unit = 'm';
        } else if (km < 100) {
            value = km.toFixed(1);
            unit = 'km';
        } else {
            value = Math.round(km);
            unit = 'km';
        }
        textEl.innerHTML = '<span class="distance-text-sm">相距</span><span class="km-value">' + value + '</span><span class="distance-text-sm">' + unit + '</span>';
    }

    // 坐标反查地名（AMap.Geocoder 逆地理编码，需 SDK 已加载）
    function reverseGeocode(lng, lat, callback) {
        try {
            AMap.plugin('AMap.Geocoder', function () {
                try {
                    var geocoder = new AMap.Geocoder({ extensions: 'base' });
                    geocoder.getAddress([lng, lat], function (status, result) {
                        if (status === 'complete' && result && result.regeocode) {
                            var comp = result.regeocode.addressComponent || {};
                            var cityPart = comp.city || comp.province || '';
                            var townPart = comp.township || comp.district || '';
                            if (townPart) townPart = townPart.replace(/街道$/, '');
                            callback(townPart ? cityPart + ' · ' + townPart : cityPart);
                        } else {
                            callback('');
                        }
                    });
                } catch (e) { callback(''); }
            });
        } catch (e) { callback(''); }
    }

    function updateLocations(force) {
        var cfg = window.WITHU_CONFIG || {};
        var locEls = document.querySelectorAll('.withu-head-avatar-location[data-location-slot]');
        locEls.forEach(function (locEl) {
            var slot = parseInt(locEl.getAttribute('data-location-slot'), 10);
            var em = locEl.querySelector('em');
            if (!em) return;
            // 实时上报优先，其次后台配置坐标
            var coords = (window.WithULiveGeo && window.WithULiveGeo.slotCoords(slot)) || getCoords(slot);
            if (!coords || coords.length < 2) {
                em.textContent = '未知';
                return;
            }
            // 已有地名（非占位文案、非坐标格式）不重复反查；实时位置更新时强制刷新
            var current = em.textContent || '';
            if (!force && current && current !== '未知' && current !== '加载中...' && current.indexOf(',') === -1) {
                return;
            }
            // 坐标反查地名
            ensureAMap(function (ok) {
                if (!ok) {
                    em.textContent = formatCoords(coords);
                    return;
                }
                reverseGeocode(coords[0], coords[1], function (name) {
                    em.textContent = name || formatCoords(coords);
                });
            });
        });
    }

    function tryInit() {
        if (_initialized) return;
        _initialized = true;
        // 先取双方实时位置（有上报则用实时坐标），再更新地名和相距距离
        var kick = function () { updateLocations(); updateDistanceBubble(); };
        if (window.WithULiveGeo) {
            window.WithULiveGeo.refresh().then(kick).catch(kick);
            // 兜底：实时位置拉取慢时不阻塞地名显示
            setTimeout(kick, 2500);
        } else {
            kick();
        }
    }

    // 当前设备 GPS 上报成功后，强制重新拉取双方位置，刷新地名与距离
    window.addEventListener('withu:geo-reported', function () {
        if (!window.WithULiveGeo) return;
        window.WithULiveGeo.refresh(true).then(function () {
            updateLocations(true);
            updateDistanceBubble();
        }).catch(function () {});
    });

    // 实时位置缓存 60s，这里每 65s 刷新一次距离气泡
    setInterval(function () {
        if (!window.WithULiveGeo) return;
        window.WithULiveGeo.refresh().then(updateDistanceBubble).catch(function () {});
    }, 65000);

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