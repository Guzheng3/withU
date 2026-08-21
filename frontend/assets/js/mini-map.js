/**
 * LGMiniMap - 轻量迷你地图组件
 * 
 * 专用于 EXIF 等场景的只读小地图预览 + 全屏查看。
 * 独立于 LGLocationPicker，不共享地图实例。
 * 
 * 用法：
 *   LGMiniMap.render({ el, lat, lng, zoom });
 *   LGMiniMap.openFullscreen(lat, lng);
 *   LGMiniMap.destroy(el);
 */
var LGMiniMap = (function() {
    'use strict';

    var MAP_DEBUG = !!(window.WITHU_CONFIG && window.WITHU_CONFIG.debugMap);
    function mapDebugWarn() {
        if (MAP_DEBUG && typeof console !== 'undefined' && typeof console.warn === 'function') {
            console.warn.apply(console, arguments);
        }
    }
    function mapDebugError() {
        if (MAP_DEBUG && typeof console !== 'undefined' && typeof console.error === 'function') {
            console.error.apply(console, arguments);
        }
    }

    var _instances = {};
    var _sdkReady = false;
    var _sdkLoading = false;
    var _pendingQueue = [];
    var _fullMap = null;
    var _fullOverlay = null;
    var _addressCache = {};

    // 定位标记 HTML（雷达波纹 + 玻璃核心，缩小版用于小地图）
    var MARKER_SMALL = '<div class="lgmini-marker">'
        + '<div class="lgmini-marker-radar"><div class="lgmini-radar-wave lgmini-rw1"></div><div class="lgmini-radar-wave lgmini-rw2"></div><div class="lgmini-radar-wave lgmini-rw3"></div></div>'
        + '<div class="lgmini-marker-core"><i class="ri-focus-3-line"></i></div></div>';

    // 全屏版标记（稍大）
    var MARKER_FULL = '<div class="lgmini-marker lgmini-marker-full">'
        + '<div class="lgmini-marker-radar lgmini-marker-radar-full"><div class="lgmini-radar-wave lgmini-rw1"></div><div class="lgmini-radar-wave lgmini-rw2"></div><div class="lgmini-radar-wave lgmini-rw3"></div></div>'
        + '<div class="lgmini-marker-core lgmini-marker-core-full"><i class="ri-focus-3-line"></i></div></div>';

    function ensureSDK(callback) {
        if (_sdkReady || typeof AMap !== 'undefined') {
            _sdkReady = true;
            callback();
            return;
        }
        _pendingQueue.push(callback);
        if (_sdkLoading) return;
        _sdkLoading = true;
        var s = document.createElement('script');
        s.src = ((window.WITHU_CONFIG && window.WITHU_CONFIG.assetBase) || '') + 'assets/js/map-sdk.js';
        s.onload = function() {
            _sdkReady = true;
            _sdkLoading = false;
            _pendingQueue.forEach(function(fn) { fn(); });
            _pendingQueue = [];
        };
        s.onerror = function() {
            _sdkLoading = false;
            mapDebugError('LGMiniMap: SDK 加载失败');
            _pendingQueue.forEach(function(fn) { fn('sdk_error'); });
            _pendingQueue = [];
        };
        document.head.appendChild(s);
    }

    function getMapStyle() {
        return (window.LGMAP_CONFIG && window.LGMAP_CONFIG.mapStyle) || 'amap://styles/dark';
    }

    // ========== 逆地理编码（带缓存） ==========
    function reverseGeocode(lng, lat, callback) {
        var cacheKey = lng.toFixed(6) + ',' + lat.toFixed(6);
        if (_addressCache[cacheKey]) {
            callback(_addressCache[cacheKey]);
            return;
        }
        try {
            AMap.plugin('AMap.Geocoder', function() {
                var geocoder = new AMap.Geocoder({ extensions: 'base' });
                geocoder.getAddress([lng, lat], function(status, result) {
                    if (status === 'complete' && result.regeocode) {
                        var comp = result.regeocode.addressComponent || {};
                        var info = {
                            address: result.regeocode.formattedAddress || '',
                            shortName: comp.district || comp.city || comp.province || '',
                            province: comp.province || '',
                            city: comp.city || '',
                            district: comp.district || '',
                            street: comp.street || '',
                            streetNumber: comp.streetNumber || ''
                        };
                        _addressCache[cacheKey] = info;
                        callback(info);
                    } else {
                        callback(null);
                    }
                });
            });
        } catch (e) {
            callback(null);
        }
    }

    // ========== 小地图渲染 ==========
    function render(options) {
        if (!options || !options.el) return;
        var el = typeof options.el === 'string' ? document.querySelector(options.el) : options.el;
        if (!el) return;

        var lat = parseFloat(options.lat), lng = parseFloat(options.lng), zoom = options.zoom || 18;
        if (!isFinite(lat) || !isFinite(lng) || (lat === 0 && lng === 0) || isNaN(lat) || isNaN(lng)) { el.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;font-size:12px;">坐标无效</div>'; return; }
        var id = el.id || ('lgmini-' + Date.now());
        if (!el.id) el.id = id;

        el.innerHTML = '';
        var wrap = document.createElement('div');
        wrap.className = 'withu-minimap-wrap';

        // 骨架屏（覆盖在地图上方，地图加载完后淡出）
        var skeleton = document.createElement('div');
        skeleton.className = 'lgmini-skeleton';
        skeleton.innerHTML = '<div class="lgmini-sk-radar">'
            + '<div class="lgmini-sk-ring"></div>'
            + '<div class="lgmini-sk-icon"><i class="fa-solid fa-location-arrow"></i></div>'
            + '</div>'
            + '<div class="lgmini-sk-text">定位中</div>';
        wrap.appendChild(skeleton);

        var mapDiv = document.createElement('div');
        mapDiv.className = 'withu-minimap-container';
        mapDiv.id = id + '-map';
        wrap.appendChild(mapDiv);

        // 小地图信息卡片（地名 + 经纬度）
        var infoCard = document.createElement('div');
        infoCard.className = 'lgmini-info-card';
        infoCard.innerHTML = '<div class="lgmini-info-left">'
            + '<i class="fa-solid fa-location-arrow lgmini-info-nav-icon"></i>'
            + '</div>'
            + '<div class="lgmini-info-right">'
            + '<span class="lgmini-info-name-text">定位中</span>'
            + '<span class="lgmini-info-coords-text">' + lat.toFixed(4) + ', ' + lng.toFixed(4) + '</span>'
            + '</div>';
        wrap.appendChild(infoCard);

        el.appendChild(wrap);

        // 点击打开全屏
        wrap.addEventListener('click', function() {
            openFullscreen(lat, lng);
        });

        // 超时保护：SDK 加载失败或地图初始化卡住时，显示降级 UI
        var _renderTimeout = setTimeout(function() {
            var sk = wrap.querySelector('.lgmini-skeleton');
            if (sk) {
                sk.classList.add('lgmini-skeleton-hide');
                setTimeout(function() { if (sk.parentNode) sk.parentNode.removeChild(sk); }, 600);
            }
            infoCard.classList.add('lgmini-info-show');
        }, 8000);

        ensureSDK(function(err) {
            if (err) {
                clearTimeout(_renderTimeout);
                var sk = wrap.querySelector('.lgmini-skeleton');
                if (sk) { sk.classList.add('lgmini-skeleton-hide'); setTimeout(function() { if (sk.parentNode) sk.parentNode.removeChild(sk); }, 600); }
                infoCard.querySelector('.lgmini-info-name-text').textContent = lat.toFixed(4) + ', ' + lng.toFixed(4);
                infoCard.classList.add('lgmini-info-show');
                return;
            }
            try {
                if (_instances[id]) { _instances[id].destroy(); delete _instances[id]; }

                var map = new AMap.Map(mapDiv.id, {
                    zoom: zoom, center: new AMap.LngLat(lng, lat), viewMode: '3D',
                    dragEnable: false, zoomEnable: false, doubleClickZoom: false,
                    keyboardEnable: false, scrollWheel: false, touchZoom: false,
                    showIndoorMap: false, showLabel: true, mapStyle: getMapStyle()
                });

                map.on('complete', function() {
                    try {
                        map.add(new AMap.Marker({
                            position: new AMap.LngLat(lng, lat), anchor: 'center', content: MARKER_SMALL
                        }));
                    } catch(e) {}
                    clearTimeout(_renderTimeout);
                    setTimeout(function() {
                        mapDiv.classList.add('lgmini-map-loaded');
                        var sk = wrap.querySelector('.lgmini-skeleton');
                        if (sk) {
                            sk.classList.add('lgmini-skeleton-hide');
                            setTimeout(function() { if (sk.parentNode) sk.parentNode.removeChild(sk); }, 600);
                        }
                    }, 400);
                });

                _instances[id] = map;

                reverseGeocode(lng, lat, function(info) {
                    var nameEl = infoCard.querySelector('.lgmini-info-name-text');
                    if (info && info.shortName) {
                        var displayName = info.shortName;
                        if (info.street) displayName += ' ' + info.street;
                        nameEl.textContent = displayName;
                    } else {
                        nameEl.textContent = '未知位置';
                    }
                    setTimeout(function() {
                        infoCard.classList.add('lgmini-info-show');
                    }, 800);
                });
            } catch(e) {
                clearTimeout(_renderTimeout);
                mapDebugWarn('[LGMiniMap] render error:', e);
                var sk = wrap.querySelector('.lgmini-skeleton');
                if (sk) { sk.classList.add('lgmini-skeleton-hide'); }
                infoCard.querySelector('.lgmini-info-name-text').textContent = '地图加载失败';
                infoCard.classList.add('lgmini-info-show');
            }
        });
    }

    // ========== 全屏地图 ==========

    function openFullscreen(lat, lng) {
        if (_fullOverlay) { closeFullscreen(); }

        var overlay = document.createElement('div');
        overlay.className = 'lgmini-fullscreen-overlay';
        overlay.id = 'lgmini-fullscreen';

        var mapDiv = document.createElement('div');
        mapDiv.className = 'lgmini-fullscreen-map';
        mapDiv.id = 'lgmini-fullscreen-map';
        mapDiv.style.visibility = 'hidden';
        overlay.appendChild(mapDiv);

        // 全屏 loading 指示器
        var loadingEl = document.createElement('div');
        loadingEl.className = 'lgmini-fullscreen-loading';
        loadingEl.id = 'lgmini-fullscreen-loading';
        loadingEl.innerHTML = '<div class="lgmini-loading-spinner"></div>';
        overlay.appendChild(loadingEl);

        // 关闭按钮
        var closeBtn = document.createElement('button');
        closeBtn.className = 'lgmini-fullscreen-close';
        closeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        closeBtn.addEventListener('click', closeFullscreen);
        overlay.appendChild(closeBtn);

        // 定位按钮
        var locateBtn = document.createElement('button');
        locateBtn.className = 'lgmini-fullscreen-locate';
        locateBtn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i>';
        locateBtn.title = '回到拍摄位置';
        overlay.appendChild(locateBtn);

        // 缩放指示器
        var zoomInd = document.createElement('div');
        zoomInd.className = 'lgmini-fullscreen-zoom';
        zoomInd.id = 'lgmini-fullscreen-zoom';
        zoomInd.textContent = '18';
        overlay.appendChild(zoomInd);

        // 全屏地址卡片
        var addressCard = document.createElement('div');
        addressCard.className = 'lgmini-addr-card';
        addressCard.id = 'lgmini-addr-card';
        addressCard.innerHTML = '<div class="lgmini-addr-left">'
            + '<div class="lgmini-addr-icon-wrap"><i class="fa-solid fa-location-arrow"></i></div>'
            + '</div>'
            + '<div class="lgmini-addr-right">'
            + '<div class="lgmini-addr-name">获取位置中...</div>'
            + '<div class="lgmini-addr-sub"></div>'
            + '</div>'
            + '<div class="lgmini-addr-divider"></div>'
            + '<div class="lgmini-addr-coords-block">'
            + '<div class="lgmini-addr-coord-row"><span class="lgmini-addr-coord-label">LAT</span><span class="lgmini-addr-coord-val">' + lat.toFixed(6) + '</span></div>'
            + '<div class="lgmini-addr-coord-row"><span class="lgmini-addr-coord-label">LNG</span><span class="lgmini-addr-coord-val">' + lng.toFixed(6) + '</span></div>'
            + '</div>';
        overlay.appendChild(addressCard);

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        _fullOverlay = overlay;

        // 点击遮罩背景关闭
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeFullscreen();
        });

        // 动画显示
        requestAnimationFrame(function() { overlay.classList.add('show'); });

        // 卡片显隐状态
        var cardVisible = false;
        var cardDataReady = false;
        var isAtMaxZoom = false;

        function showCard() {
            if (!cardDataReady) return;
            if (cardVisible) return;
            cardVisible = true;
            addressCard.classList.add('lgmini-addr-visible');
        }

        function hideCard() {
            if (!cardVisible) return;
            cardVisible = false;
            addressCard.classList.remove('lgmini-addr-visible');
        }

        ensureSDK(function() {
            var map = new AMap.Map('lgmini-fullscreen-map', {
                zoom: 14, center: [lng, lat], viewMode: '3D',
                dragEnable: true, zoomEnable: true, doubleClickZoom: true,
                keyboardEnable: true, scrollWheel: true, touchZoom: true,
                showIndoorMap: false, showLabel: true, mapStyle: getMapStyle(),
                animateEnable: true
            });

            map.on('complete', function() {
                setTimeout(function() {
                    var md = document.getElementById('lgmini-fullscreen-map');
                    if (md) md.style.visibility = 'visible';
                    // 淡出 loading
                    var loader = document.getElementById('lgmini-fullscreen-loading');
                    if (loader) {
                        loader.classList.add('lgmini-loading-hide');
                        setTimeout(function() { if (loader.parentNode) loader.parentNode.removeChild(loader); }, 500);
                    }
                }, 600);
                map.add(new AMap.Marker({
                    position: [lng, lat], anchor: 'center', content: MARKER_FULL
                }));
                // 平滑缩放到最大
                setTimeout(function() {
                    map.setZoomAndCenter(20, [lng, lat], false, 1500);
                }, 200);
            });

            // 缩放变化：更新指示器 + 控制卡片显隐
            map.on('zoomchange', function() {
                var el = document.getElementById('lgmini-fullscreen-zoom');
                var z = Math.round(map.getZoom());
                if (el) el.textContent = z;
                isAtMaxZoom = z >= 19;
                if (isAtMaxZoom) {
                    showCard();
                } else {
                    hideCard();
                }
            });

            // 拖拽开始：隐藏卡片
            map.on('dragstart', function() {
                hideCard();
            });

            // 拖拽结束：如果在最大缩放级别，重新显示
            map.on('dragend', function() {
                var z = Math.round(map.getZoom());
                if (z >= 19) {
                    setTimeout(showCard, 400);
                }
            });

            // 定位按钮
            locateBtn.addEventListener('click', function() {
                locateBtn.classList.add('lgmini-locate-pulse');
                hideCard();
                map.setZoomAndCenter(20, [lng, lat], false, 1200);
                setTimeout(function() {
                    locateBtn.classList.remove('lgmini-locate-pulse');
                    showCard();
                }, 1400);
            });

            _fullMap = map;

            // 逆地理编码（在 SDK 加载完成后执行，避免首次 AMap 未定义）
            reverseGeocode(lng, lat, function(info) {
                var nameEl = addressCard.querySelector('.lgmini-addr-name');
                var subEl = addressCard.querySelector('.lgmini-addr-sub');
                if (info) {
                    nameEl.textContent = info.district || info.city || info.province || '未知位置';
                    var detail = info.address || '';
                    if (info.province && detail.indexOf(info.province) === 0) detail = detail.substring(info.province.length);
                    if (info.city && detail.indexOf(info.city) === 0) detail = detail.substring(info.city.length);
                    subEl.textContent = detail || '';
                } else {
                    nameEl.textContent = '未知位置';
                    subEl.textContent = '';
                }
                cardDataReady = true;
                setTimeout(function() {
                    if (isAtMaxZoom) showCard();
                }, 300);
            });
        });
    }

    function closeFullscreen() {
        if (_fullOverlay) {
            _fullOverlay.classList.remove('show');
            setTimeout(function() {
                if (_fullMap) { _fullMap.destroy(); _fullMap = null; }
                if (_fullOverlay && _fullOverlay.parentNode) _fullOverlay.parentNode.removeChild(_fullOverlay);
                _fullOverlay = null;
            }, 250);
        }
        document.body.style.overflow = '';
    }

    function destroy(elOrId) {
        var id = typeof elOrId === 'string' ? elOrId.replace('#', '') : (elOrId.id || '');
        if (_instances[id]) { _instances[id].destroy(); delete _instances[id]; }
    }

    function destroyAll() {
        Object.keys(_instances).forEach(function(id) { _instances[id].destroy(); });
        _instances = {};
    }

    return { render: render, destroy: destroy, destroyAll: destroyAll, openFullscreen: openFullscreen, closeFullscreen: closeFullscreen };
})();
