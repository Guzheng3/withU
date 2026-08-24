/**
 * 地图截图功能 - 中国领土裁剪
 * 使用 Amap DistrictSearch 获取中国边界，canvas 裁剪截图
 */
(function () {
    'use strict';

    // 中国边界多边形数据（简化版 GeoJSON，用于截图裁剪）
    // 包含中国大陆、台湾、南海诸岛等
    var chinaBoundary = null;
    var chinaLoaded = false;
    var chinaLoading = false;

    function initScreenshotBtn() {
        var container = document.querySelector('.full-screen-function');
        if (!container) {
            // 如果控件还没渲染，等待后重试
            setTimeout(initScreenshotBtn, 500);
            return;
        }
        if (document.getElementById('withuMapScreenshotBtn')) return;

        var btn = document.createElement('button');
        btn.id = 'withuMapScreenshotBtn';
        btn.type = 'button';
        btn.className = 'control-icon-button withu-screenshot-btn';
        btn.title = '截图（仅中国领土）';
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>';
        btn.addEventListener('click', captureScreenshot);

        container.appendChild(btn);
    }

    function loadChinaBoundary() {
        return new Promise(function (resolve, reject) {
            if (chinaLoaded && chinaBoundary) {
                resolve(chinaBoundary);
                return;
            }
            if (chinaLoading) {
                // 等待加载完成
                var check = setInterval(function () {
                    if (chinaLoaded) {
                        clearInterval(check);
                        resolve(chinaBoundary);
                    }
                }, 100);
                return;
            }

            chinaLoading = true;

            // 使用 Amap DistrictSearch 获取中国边界
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
        if (!chinaBoundary || !chinaBoundary.length) return true; // 默认在境内

        // 简单判断：中国经度范围 73-135，纬度范围 18-54
        if (lng < 73 || lng > 135 || lat < 18 || lat > 54) return false;
        return true;
    }

    function hasOverseasLocation() {
        // 检查是否有情侣定位在国外
        var mapConfig = window.WITHU_MAP_CONFIG || {};
        var lovers = mapConfig.lovers || [];
        for (var i = 0; i < lovers.length; i++) {
            var coords = lovers[i].coords;
            if (coords && coords.length === 2) {
                if (!isPointInChina(coords[0], coords[1])) {
                    return true;
                }
            }
        }
        return false;
    }

    async function captureScreenshot() {
        try {
            // 显示加载提示
            showToast('正在生成截图...', 'info');

            // 获取地图容器
            var mapContainer = document.getElementById('missing-pets-map');
            if (!mapContainer) {
                mapContainer = document.querySelector('.amap-container');
            }
            if (!mapContainer) {
                showToast('未找到地图容器', 'error');
                return;
            }

            // 尝试加载中国边界
            var hasOverseas = hasOverseasLocation();
            var boundary = null;
            if (!hasOverseas) {
                try {
                    boundary = await loadChinaBoundary();
                } catch (e) {
                    console.warn('中国边界加载失败，使用无裁剪截图:', e.message);
                }
            }

            // 使用 html2canvas 或直接截取
            var canvas = await captureMapCanvas(mapContainer);

            if (boundary && boundary.length > 0) {
                canvas = applyChinaMask(canvas, boundary);
            }

            // 下载
            downloadCanvas(canvas, 'withU-地图截图.png');
            showToast('截图已保存', 'success');

        } catch (e) {
            console.error('截图失败:', e);
            showToast('截图失败: ' + e.message, 'error');
        }
    }

    function captureMapCanvas(container) {
        return new Promise(function (resolve, reject) {
            // 优先使用 html2canvas
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

    function applyChinaMask(canvas, boundaries) {
        var output = document.createElement('canvas');
        output.width = canvas.width;
        output.height = canvas.height;
        var ctx = output.getContext('2d');

        // 创建裁剪路径
        ctx.beginPath();

        // 将地理坐标转换为画布坐标需要一个近似的投影
        // 使用简单的墨卡托投影
        var mapBounds = getMapBounds();
        if (!mapBounds) {
            // 如果没有地图边界，就直接使用原图
            ctx.drawImage(canvas, 0, 0);
            return output;
        }

        boundaries.forEach(function (polygon) {
            if (Array.isArray(polygon)) {
                // polygon 可能是坐标数组或嵌套数组
                drawPolygon(ctx, polygon, mapBounds, canvas.width, canvas.height);
            }
        });

        ctx.closePath();
        ctx.clip();

        // 绘制原图到裁剪区域
        ctx.drawImage(canvas, 0, 0);

        return output;
    }

    function getMapBounds() {
        // 尝试从 AMap 获取当前视图范围
        try {
            if (typeof window._lgMapInstance !== 'undefined' && window._lgMapInstance.getBounds) {
                var bounds = window._lgMapInstance.getBounds();
                return {
                    sw: { lng: bounds.getSouthWest().lng, lat: bounds.getSouthWest().lat },
                    ne: { lng: bounds.getNorthEast().lng, lat: bounds.getNorthEast().lat }
                };
            }
        } catch (e) {}
        return null;
    }

    function drawPolygon(ctx, polygon, bounds, width, height) {
        if (!Array.isArray(polygon) || polygon.length === 0) return;

        // 判断是坐标数组还是嵌套多边形
        // 坐标格式: [lng, lat] 或 "lng,lat"
        var first = polygon[0];
        if (typeof first === 'string' || (Array.isArray(first) && first.length === 2 && typeof first[0] === 'number')) {
            // 坐标数组
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
            // 嵌套多边形
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
        // 使用现有的 Toastify 或自定义提示
        if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
            Toastify.showScenario(type === 'error' ? 'error' : type, { text: msg });
            return;
        }

        // 简易 toast
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

    // 初始化
    function init() {
        // 等待地图加载完成后添加按钮
        var checkInterval = setInterval(function () {
            var mapContainer = document.querySelector('.amap-container, #mapContainer');
            if (mapContainer) {
                clearInterval(checkInterval);
                initScreenshotBtn();
            }
        }, 500);

        // 最多等待 30 秒
        setTimeout(function () { clearInterval(checkInterval); }, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();