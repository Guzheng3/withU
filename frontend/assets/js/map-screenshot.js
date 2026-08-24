/**
 * 地图截图功能 - 中国领土裁剪
 * 截图时自动调整视角到中国全图，标注情侣位置，境外透明
 */
(function () {
    'use strict';

    var chinaBoundary = null;
    var chinaLoaded = false;
    var chinaLoading = false;

    function initScreenshotBtn() {
        var container = document.querySelector('.full-screen-function');
        if (!container) {
            setTimeout(initScreenshotBtn, 500);
            return;
        }
        if (document.getElementById('withuMapScreenshotBtn')) return;

        var btn = document.createElement('button');
        btn.id = 'withuMapScreenshotBtn';
        btn.type = 'button';
        btn.className = 'control-icon-button withu-screenshot-btn';
        btn.title = '中国地图截图';
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>';
        btn.addEventListener('click', captureScreenshot);
        container.appendChild(btn);
    }

    function getMap() {
        try {
            if (window.WithUMap && typeof window.WithUMap.getMap === 'function') {
                return window.WithUMap.getMap();
            }
        } catch (e) {}
        return null;
    }

    function loadChinaBoundary() {
        return new Promise(function (resolve, reject) {
            if (chinaLoaded && chinaBoundary) {
                resolve(chinaBoundary);
                return;
            }
            if (chinaLoading) {
                var check = setInterval(function () {
                    if (chinaLoaded) { clearInterval(check); resolve(chinaBoundary); }
                }, 100);
                return;
            }
            chinaLoading = true;
            if (typeof AMap !== 'undefined' && AMap.DistrictSearch) {
                try {
                    var district = new AMap.DistrictSearch({
                        level: 'country',
                        subdistrict: 0,
                        extensions: 'all'
                    });
                    district.search('中国', function (status, result) {
                        if (status === 'complete' && result.districtList && result.districtList.length > 0) {
                            chinaBoundary = result.districtList[0].boundaries || [];
                            chinaLoaded = true;
                            chinaLoading = false;
                            resolve(chinaBoundary);
                        } else {
                            chinaLoading = false;
                            reject(new Error('无法获取中国边界数据'));
                        }
                    });
                } catch (e) {
                    chinaLoading = false;
                    reject(e);
                }
            } else {
                chinaLoading = false;
                reject(new Error('AMap 未加载'));
            }
        });
    }

    function isPointInChina(lng, lat) {
        if (!chinaBoundary || !chinaBoundary.length) return true;
        if (lng < 73 || lng > 135 || lat < 18 || lat > 54) return false;
        return true;
    }

    function hasOverseasLocation() {
        var mapConfig = window.WITHU_MAP_CONFIG || {};
        var lovers = mapConfig.lovers || [];
        for (var i = 0; i < lovers.length; i++) {
            var coords = lovers[i].coords;
            if (coords && coords.length === 2) {
                if (!isPointInChina(coords[0], coords[1])) return true;
            }
        }
        return false;
    }

    function getLoversCoords() {
        var mapConfig = window.WITHU_MAP_CONFIG || {};
        var lovers = mapConfig.lovers || [];
        var coords = [];
        for (var i = 0; i < lovers.length; i++) {
            if (lovers[i].coords && lovers[i].coords.length === 2) {
                coords.push(lovers[i].coords);
            }
        }
        return coords;
    }

    async function captureScreenshot() {
        var map = getMap();
        if (!map) {
            showToast('地图未加载', 'error');
            return;
        }

        try {
            showToast('正在生成中国地图截图...', 'info');

            // 保存当前地图状态
            var savedCenter = map.getCenter();
            var savedZoom = map.getZoom();

            var hasOverseas = hasOverseasLocation();
            var boundary = null;

            if (!hasOverseas) {
                try {
                    boundary = await loadChinaBoundary();
                } catch (e) {
                    console.warn('中国边界加载失败:', e.message);
                }
            }

            // 将地图视角调整到中国全图
            // 中国大致范围: lng 73-135, lat 18-54
            var chinaBounds = new AMap.Bounds(
                [70, 16],  // 西南角（留边距）
                [138, 56]  // 东北角（留边距）
            );

            map.setBounds(chinaBounds, false, [20, 20, 20, 20]);
            // 关闭 3D 视角
            if (map.getPitch() > 0) map.setPitch(0);

            // 等待地图瓦片加载完成
            await waitForTilesLoaded(map, 3000);

            // 截图
            var mapContainer = document.getElementById('missing-pets-map');
            var canvas = await captureCanvas(mapContainer);

            // 应用中国边界裁剪
            if (boundary && boundary.length > 0) {
                canvas = applyChinaMask(canvas, boundary, map);
            }

            // 恢复地图视角
            map.setZoomAndCenter(savedZoom, savedCenter);

            // 下载
            downloadCanvas(canvas, 'withU-中国地图.png');
            showToast('截图已保存', 'success');

        } catch (e) {
            console.error('截图失败:', e);
            showToast('截图失败: ' + e.message, 'error');
            // 尝试恢复地图
            try { map.setZoomAndCenter(5, [104, 35]); } catch (e2) {}
        }
    }

    function waitForTilesLoaded(map, timeoutMs) {
        return new Promise(function (resolve) {
            var start = Date.now();
            var completed = false;

            function check() {
                if (completed) return;
                if (Date.now() - start > timeoutMs) {
                    completed = true;
                    resolve();
                    return;
                }
                // 等待地图 complete 事件
                map.on('complete', function () {
                    if (!completed) {
                        completed = true;
                        // 再等一小段时间确保瓦片渲染完成
                        setTimeout(resolve, 500);
                    }
                });
                // 兜底：时间到了就继续
                setTimeout(function () {
                    if (!completed) { completed = true; resolve(); }
                }, timeoutMs);
            }

            check();
        });
    }

    function captureCanvas(container) {
        return new Promise(function (resolve, reject) {
            if (typeof html2canvas !== 'undefined') {
                html2canvas(container, {
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: null,
                    scale: 2
                }).then(resolve).catch(reject);
                return;
            }

            // 回退：直接截取地图 canvas
            var mapCanvas = container.querySelector('canvas');
            if (mapCanvas) {
                var canvas = document.createElement('canvas');
                canvas.width = mapCanvas.width;
                canvas.height = mapCanvas.height;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(mapCanvas, 0, 0);
                resolve(canvas);
                return;
            }

            reject(new Error('无法截取地图'));
        });
    }

    function applyChinaMask(canvas, boundaries, map) {
        var output = document.createElement('canvas');
        output.width = canvas.width;
        output.height = canvas.height;
        var ctx = output.getContext('2d');

        var mapBounds = map.getBounds();
        if (!mapBounds) {
            ctx.drawImage(canvas, 0, 0);
            return output;
        }

        var sw = mapBounds.getSouthWest();
        var ne = mapBounds.getNorthEast();
        var bounds = {
            sw: { lng: sw.lng, lat: sw.lat },
            ne: { lng: ne.lng, lat: ne.lat }
        };

        ctx.beginPath();

        boundaries.forEach(function (polygon) {
            if (Array.isArray(polygon)) {
                drawPolygon(ctx, polygon, bounds, canvas.width, canvas.height);
            }
        });

        ctx.closePath();
        ctx.clip();
        ctx.drawImage(canvas, 0, 0);

        return output;
    }

    function drawPolygon(ctx, polygon, bounds, width, height) {
        if (!Array.isArray(polygon) || polygon.length === 0) return;

        var first = polygon[0];
        if (typeof first === 'string' || (Array.isArray(first) && first.length === 2 && typeof first[0] === 'number')) {
            var started = false;
            for (var i = 0; i < polygon.length; i++) {
                var coord = polygon[i];
                var lng, lat;
                if (typeof coord === 'string') {
                    var parts = coord.split(',');
                    lng = parseFloat(parts[0]);
                    lat = parseFloat(parts[1]);
                } else {
                    lng = coord[0];
                    lat = coord[1];
                }

                var px = ((lng - bounds.sw.lng) / (bounds.ne.lng - bounds.sw.lng)) * width;
                var py = ((bounds.ne.lat - lat) / (bounds.ne.lat - bounds.sw.lat)) * height;

                if (!started) {
                    ctx.moveTo(px, py);
                    started = true;
                } else {
                    ctx.lineTo(px, py);
                }
            }
        } else {
            for (var j = 0; j < polygon.length; j++) {
                drawPolygon(ctx, polygon[j], bounds, width, height);
            }
        }
    }

    function downloadCanvas(canvas, filename) {
        var link = document.createElement('a');
        link.download = filename;
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    function showToast(msg, type) {
        if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
            Toastify.showScenario(type === 'error' ? 'error' : type, { text: msg });
            return;
        }

        var toast = document.createElement('div');
        toast.textContent = msg;
        toast.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:' +
            (type === 'error' ? '#e74c3c' : type === 'success' ? '#27ae60' : '#3498db') +
            ';color:#fff;padding:10px 24px;border-radius:20px;z-index:99999;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.2)';
        document.body.appendChild(toast);
        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function () { document.body.removeChild(toast); }, 300);
        }, 2000);
    }

    function init() {
        var checkInterval = setInterval(function () {
            if (document.querySelector('.full-screen-function')) {
                clearInterval(checkInterval);
                initScreenshotBtn();
            }
        }, 500);
        setTimeout(function () { clearInterval(checkInterval); }, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();