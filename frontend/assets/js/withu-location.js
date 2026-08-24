/**
 * 浏览器定位服务
 * 获取用户位置，用于天气和地图真实数据
 */
(function () {
    'use strict';

    var _cachedGeo = null;
    var _requesting = false;
    var _listeners = [];

    // 尝试从 localStorage 读取缓存
    function loadCache() {
        try {
            var raw = localStorage.getItem('withu_visitor_geo');
            if (raw) {
                var cached = JSON.parse(raw);
                if (cached && cached.lat && cached.lng && (Date.now() - cached.ts < 3600000)) {
                    return cached;
                }
            }
        } catch (e) {}
        return null;
    }

    // 保存到 localStorage
    function saveCache(geo) {
        try {
            localStorage.setItem('withu_visitor_geo', JSON.stringify(geo));
        } catch (e) {}
    }

    // 通知监听器
    function notifyListeners(geo) {
        _listeners.forEach(function (fn) {
            try { fn(geo); } catch (e) {}
        });
    }

    // 通过坐标反查城市名（使用 QWeather API 浏览器端直接调用）
    function reverseGeocode(lat, lng) {
        var key = (window.WITHU_CONFIG && window.WITHU_CONFIG.weatherToken) || '';
        if (!key) return Promise.resolve('');

        return fetch('https://geoapi.qweather.com/v2/city/lookup?location=' + lng + ',' + lat + '&key=' + key + '&number=1', {
            method: 'GET'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.code === '200' && data.location && data.location.length > 0) {
                return data.location[0].name || '';
            }
            return '';
        })
        .catch(function () { return ''; });
    }

    // 请求浏览器定位
    function requestLocation() {
        if (_requesting) return;
        if (!navigator.geolocation) {
            console.warn('[WithULocation] 浏览器不支持定位');
            return;
        }

        _requesting = true;

        // 检查权限
        var checkThenRequest = function () {
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    _requesting = false;
                    var geo = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        ts: Date.now()
                    };
                    _cachedGeo = geo;
                    saveCache(geo);

                    // 反查城市名
                    reverseGeocode(geo.lat, geo.lng).then(function (city) {
                        geo.city = city;
                        _cachedGeo = geo;
                        saveCache(geo);
                        notifyListeners(geo);
                        window.dispatchEvent(new CustomEvent('withu:location-ready', { detail: geo }));
                    });

                    notifyListeners(geo);
                    window.dispatchEvent(new CustomEvent('withu:location-ready', { detail: geo }));
                },
                function (err) {
                    _requesting = false;
                    console.warn('[WithULocation] 定位失败: ' + (err.message || err.code));
                    // 使用缓存
                    var cached = loadCache();
                    if (cached) {
                        _cachedGeo = cached;
                        notifyListeners(cached);
                        window.dispatchEvent(new CustomEvent('withu:location-ready', { detail: cached }));
                    }
                },
                {
                    enableHighAccuracy: false,
                    timeout: 10000,
                    maximumAge: 300000 // 5分钟缓存
                }
            );
        };

        if (navigator.permissions && typeof navigator.permissions.query === 'function') {
            navigator.permissions.query({ name: 'geolocation' }).then(function (result) {
                if (result.state === 'denied') {
                    _requesting = false;
                    console.warn('[WithULocation] 定位权限被拒绝');
                    return;
                }
                checkThenRequest();
            }).catch(function () {
                checkThenRequest();
            });
        } else {
            checkThenRequest();
        }
    }

    // 公开 API
    window.WithULocation = {
        /**
         * 获取当前定位（优先缓存，无缓存时请求）
         */
        get: function () {
            return _cachedGeo || loadCache() || null;
        },

        /**
         * 请求定位（异步）
         */
        request: function () {
            var cached = loadCache();
            if (cached) {
                _cachedGeo = cached;
                notifyListeners(cached);
                window.dispatchEvent(new CustomEvent('withu:location-ready', { detail: cached }));
            }
            requestLocation();
        },

        /**
         * 监听定位就绪
         */
        onReady: function (fn) {
            var geo = this.get();
            if (geo) {
                fn(geo);
            }
            _listeners.push(fn);
        }
    };

    // 自动初始化（仅当后台未设置固定位置时启用）
    function shouldAutoLocate() {
        var cfg = window.WITHU_CONFIG;
        if (!cfg) return true;
        // 如果后台设置了固定位置，不再触发浏览器定位
        if (cfg.weatherLocLat && cfg.weatherLocLng) return false;
        return true;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (shouldAutoLocate()) window.WithULocation.request();
        });
    } else {
        if (shouldAutoLocate()) window.WithULocation.request();
    }
})();