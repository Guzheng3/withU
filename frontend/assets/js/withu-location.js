/**
 * 浏览器定位服务（高德定位）
 * 通过高德 Geolocation 插件获取用户位置（浏览器定位优先，失败回退高德 IP 定位），
 * 用于天气和地图真实数据；已登录用户会把坐标上报到 location-beacon，
 * 供首页展示双方实时位置与实时天气。
 */
(function () {
    'use strict';

    var _cachedGeo = null;
    var _requesting = false;
    var _listeners = [];

    var REPORT_INTERVAL_MS = 10 * 60 * 1000; // 距上次上报超过 10 分钟才再次上报
    var REPORT_MOVE_M = 150;                 // 或位移超过 150 米

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

    // 通过坐标反查城市名（使用本地 AMap SDK 逆地理编码；QWeather 旧版 geoapi 已停用）
    function reverseGeocode(lat, lng) {
        return new Promise(function (resolve) {
            function run() {
                try {
                    AMap.plugin('AMap.Geocoder', function () {
                        try {
                            var geocoder = new AMap.Geocoder({ extensions: 'base' });
                            geocoder.getAddress([lng, lat], function (status, result) {
                                if (status === 'complete' && result.regeocode) {
                                    var comp = result.regeocode.addressComponent || {};
                                    var cityPart = comp.city || comp.province || '';
                                    var townPart = comp.township || comp.district || '';
                                    if (townPart) townPart = townPart.replace(/街道$/, '');
                                    var name = townPart ? cityPart + ' · ' + townPart : cityPart;
                                    resolve(name || '');
                                    return;
                                }
                                resolve('');
                            });
                        } catch (e) { resolve(''); }
                    });
                } catch (e) { resolve(''); }
            }
            if (window.AMap) {
                run();
                return;
            }
            if (window.WithUAMapLoader) {
                window.WithUAMapLoader.ensure(function (ok) {
                    ok ? run() : resolve('');
                });
                return;
            }
            resolve('');
        });
    }

    // 两点间距（米，近似）
    function distanceM(lat1, lng1, lat2, lng2) {
        var dLat = (lat1 - lat2) * 111320;
        var dLng = (lng1 - lng2) * 111320 * Math.cos(lat1 * Math.PI / 180);
        return Math.sqrt(dLat * dLat + dLng * dLng);
    }

    // 已登录用户上报坐标（限频：10 分钟或移动 150 米以上）
    function reportIfNeed(lat, lng, acc) {
        var cfg = window.WITHU_CONFIG;
        if (!cfg || !cfg.loggedIn) return;
        var beacon = (cfg.endpoints && cfg.endpoints.locationBeacon) || '/services/location-beacon.php';
        try {
            var raw = localStorage.getItem('withu_geo_last_report');
            var last = raw ? JSON.parse(raw) : null;
            var now = Date.now();
            if (last && last.lat && (now - last.ts < REPORT_INTERVAL_MS) &&
                distanceM(lat, lng, last.lat, last.lng) < REPORT_MOVE_M) {
                return;
            }
        } catch (e) {}

        var body = new URLSearchParams();
        body.set('lat', lat);
        body.set('lng', lng);
        body.set('acc', acc || 0);
        fetch(beacon, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: body.toString(),
            credentials: 'same-origin',
        }).then(function (r) { return r.json().catch(function () { return null; }); })
          .then(function (data) {
              if (data && data.code === 200) {
                  try {
                      localStorage.setItem('withu_geo_last_report', JSON.stringify({ ts: Date.now(), lat: lat, lng: lng }));
                  } catch (e) {}
              }
          })
          .catch(function () {});
    }

    function handlePosition(lng, lat, acc) {
        _requesting = false;
        var geo = { lat: lat, lng: lng, ts: Date.now() };
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

        // 已登录用户上报实时位置
        reportIfNeed(lat, lng, acc);

        notifyListeners(geo);
        window.dispatchEvent(new CustomEvent('withu:location-ready', { detail: geo }));
    }

    var _ipFallbackTried = false;

    // 使用本地缓存的位置兜底
    function useCachedGeo() {
        var cached = loadCache();
        if (cached) {
            _cachedGeo = cached;
            notifyListeners(cached);
            window.dispatchEvent(new CustomEvent('withu:location-ready', { detail: cached }));
        }
    }

    function locateFail(reason) {
        _requesting = false;
        console.warn('[WithULocation] 高德定位失败: ' + reason);
        // IP 定位兜底：解析来访 IP 的城市级位置（成功后随登录一起上报）
        if (!_ipFallbackTried) {
            _ipFallbackTried = true;
            var cfg = window.WITHU_CONFIG;
            var beacon = (cfg && cfg.endpoints && cfg.endpoints.locationBeacon) || '/services/location-beacon.php';
            fetch(beacon + '?action=ip', { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.code === 200 && data.data && data.data.lng) {
                        handlePosition(data.data.lng, data.data.lat, 30000);
                    } else {
                        useCachedGeo();
                    }
                })
                .catch(function () { useCachedGeo(); });
            return;
        }
        useCachedGeo();
    }

    // WGS-84（浏览器原生定位）-> 高德 GCJ-02 坐标
    function toAmapCoords(lng, lat, acc) {
        var done = function (a, b) { handlePosition(a, b, acc); };
        var convert = function () {
            try {
                AMap.convertFrom([lng, lat], 'gps', function (status, result) {
                    if (status === 'complete' && result && result.info === 'ok' && result.locations && result.locations.length) {
                        var p = result.locations[0];
                        done(p.lng, p.lat);
                    } else {
                        done(lng, lat);
                    }
                });
            } catch (e) { done(lng, lat); }
        };
        if (window.AMap) {
            convert();
        } else if (window.WithUAMapLoader) {
            window.WithUAMapLoader.ensure(function (ok) {
                ok ? convert() : done(lng, lat);
            });
        } else {
            done(lng, lat);
        }
    }

    // 浏览器原生定位：触发系统授权弹窗，获取真实位置
    function locateByBrowser() {
        navigator.geolocation.getCurrentPosition(
            function (position) {
                toAmapCoords(position.coords.longitude, position.coords.latitude, position.coords.accuracy || 0);
            },
            function (err) {
                console.warn('[WithULocation] 浏览器定位失败(' + (err.code || '') + '): ' + (err.message || '') + '，回退高德定位');
                locateByAmapPlugin();
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000
            }
        );
    }

    // 高德 Geolocation 插件定位（浏览器定位失败时的回退，含高德 IP 定位兜底）
    function locateByAmapPlugin() {
        try {
            AMap.plugin('AMap.Geolocation', function () {
                try {
                    var geolocation = new AMap.Geolocation({
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 300000,
                        convert: true,        // 非高德坐标自动转换为高德坐标
                        GeoLocationFirst: true // 优先浏览器定位，获取真实位置
                    });
                    // AMap 2.0 插件用 getCurrentPosition(status, result)，1.4.x 用 getLocation
                    var call = typeof geolocation.getCurrentPosition === 'function'
                        ? function (cb) { geolocation.getCurrentPosition(cb); }
                        : function (cb) { geolocation.getLocation(cb); };
                    call(function (status, result) {
                        if (status === 'complete' && result && result.position) {
                            handlePosition(result.position.lng, result.position.lat, result.accuracy || 0);
                        } else {
                            locateFail((result && result.message) || status || 'unknown');
                        }
                    });
                } catch (e) { locateFail(e.message); }
            });
        } catch (e) { locateFail(e.message); }
    }

    // 请求定位：优先浏览器原生（弹权限、真实位置），失败回退高德
    function requestLocation() {
        if (_requesting) return;
        _requesting = true;

        if (navigator.geolocation) {
            locateByBrowser();
        } else if (window.AMap) {
            locateByAmapPlugin();
        } else if (window.WithUAMapLoader) {
            window.WithUAMapLoader.ensure(function (ok) {
                ok ? locateByAmapPlugin() : locateFail('AMap SDK 加载失败');
            });
        } else {
            locateFail('浏览器不支持定位');
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
