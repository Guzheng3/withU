// 地图卡片链接跳转辅助（关闭地图 + pjax 兼容跳转）
window._mapCardNav = function (url, e) {
    if (e) { e.stopPropagation(); e.preventDefault(); }
    if (window.LGMap) window.LGMap.close();
    if (window.jQuery && window.jQuery.pjax) {
        window.jQuery.pjax({ url: url, container: '#pjax-container', fragment: '#pjax-container' });
    } else {
        window.location.href = url;
    }
    return false;
};

// 全局事件委托：在捕获阶段拦截地图卡片链接点击（高德地图 InfoWindow 会吞掉冒泡事件）
document.addEventListener('click', function (e) {
    // 优先处理展开照片按钮（阻止外层 map-card-link 的跳转）
    const expandBtn = e.target.closest('.alb-expand-btn');
    if (expandBtn) {
        e.stopPropagation();
        e.stopImmediatePropagation();
        e.preventDefault();
        const code = expandBtn.getAttribute('data-album-code');
        const name = expandBtn.getAttribute('data-album-name');
        if (code && window._albumPhotosExpand) {
            window._albumPhotosExpand(code, name || '');
        }
        return;
    }

    const link = e.target.closest('.map-card-link');
    if (!link) return;
    e.stopPropagation();
    e.stopImmediatePropagation();
    e.preventDefault();
    const url = link.getAttribute('href');
    if (url) window._mapCardNav(url);
}, true); // true = 捕获阶段

// 安全经纬度转换，杜绝 LngLat(NaN, NaN) 崩溃，并供全局生成 Marker 使用
const safeLngLat = (lng, lat, defaultLng = 116.397428, defaultLat = 39.90923) => {
    const l = parseFloat(lng);
    const a = parseFloat(lat);
    if (isNaN(l) || isNaN(a)) {
        mapDebugWarn(`🗺️ [LGMap Debug] 检测到非法经纬度 LngLat(${lng}, ${lat})，已回退至默认值(天安门)以免地图组件崩溃`);
        return new window.AMap.LngLat(defaultLng, defaultLat);
    }
    return new window.AMap.LngLat(l, a);
};

const normalizeCoordinatePair = (lng, lat, options = {}) => {
    const allowZero = !!options.allowZero;
    const normalizedLng = parseFloat(lng);
    const normalizedLat = parseFloat(lat);

    if (!Number.isFinite(normalizedLng) || !Number.isFinite(normalizedLat)) {
        return null;
    }

    if (normalizedLat < -90 || normalizedLat > 90 || normalizedLng < -180 || normalizedLng > 180) {
        return null;
    }

    if (!allowZero && normalizedLng === 0 && normalizedLat === 0) {
        return null;
    }

    return [normalizedLng, normalizedLat];
};

const extractCoordinatePair = (positionLike, options = {}) => {
    if (!positionLike) {
        return null;
    }

    if (Array.isArray(positionLike)) {
        return normalizeCoordinatePair(positionLike[0], positionLike[1], options);
    }

    if (typeof positionLike.getLng === 'function' && typeof positionLike.getLat === 'function') {
        return normalizeCoordinatePair(positionLike.getLng(), positionLike.getLat(), options);
    }

    if (typeof positionLike.lng !== 'undefined' || typeof positionLike.lat !== 'undefined') {
        return normalizeCoordinatePair(positionLike.lng, positionLike.lat, options);
    }

    return null;
};

const setMapCenterSafely = (map, positionLike) => {
    const coords = extractCoordinatePair(positionLike);
    if (!coords) {
        mapDebugWarn('🗺️ [LGMap Debug] setCenter 收到非法坐标，已跳过:', positionLike);
        return false;
    }

    map.setCenter(coords);
    return true;
};

const setMapZoomAndCenterSafely = (map, zoom, positionLike, immediately, duration) => {
    const coords = extractCoordinatePair(positionLike);
    if (!coords) {
        mapDebugWarn('🗺️ [LGMap Debug] setZoomAndCenter 收到非法坐标，已跳过:', positionLike);
        return false;
    }

    map.setZoomAndCenter(zoom, coords, immediately, duration);
    return true;
};

const MAP_DEBUG = !!(window.LG_CONFIG && window.LG_CONFIG.debugMap);
const mapDebugLog = (...args) => {
    if (MAP_DEBUG && typeof console !== 'undefined' && typeof console.log === 'function') {
        console.log(...args);
    }
};
const mapDebugWarn = (...args) => {
    if (MAP_DEBUG && typeof console !== 'undefined' && typeof console.warn === 'function') {
        console.warn(...args);
    }
};

// 高德地图配置检测
const validateAmapConfig = () => {
    const config = window.LGMAP_CONFIG || {};
    const securityConfig = window._AMapSecurityConfig || {};

    const errors = [];

    // 检测 API Key
    if (!config.amapKey || config.amapKey.trim() === '') {
        errors.push({ type: 'key_empty', message: 'API Key 未配置' });
    }

    // 检测安全密钥
    if (!securityConfig.securityJsCode || securityConfig.securityJsCode.trim() === '') {
        errors.push({ type: 'security_empty', message: '安全密钥未配置' });
    }

    return errors;
};

// 显示配置错误提示
const showConfigError = (errors) => {
    // 判断错误类型
    let title = '配置缺失';
    let icon = 'ri-key-2-fill';
    let desc = '';

    const hasKeyError = errors.some(e => e.type === 'key_empty' || e.type === 'key_invalid');
    const hasSecurityError = errors.some(e => e.type === 'security_empty' || e.type === 'security_invalid');

    if (hasKeyError && hasSecurityError) {
        desc = '未检测到有效的高德地图 API Key 和安全密钥。<br>需配置后方可启用地图服务。';
    } else if (hasKeyError) {
        desc = '未检测到有效的高德地图 API Key。<br>需配置后方可启用地图服务。';
    } else if (hasSecurityError) {
        desc = '未检测到有效的高德地图安全密钥。<br>需配置后方可启用地图服务。';
    }

    // 创建遮罩层
    const overlay = document.createElement('div');
    overlay.className = 'amap-config-overlay';
    overlay.innerHTML = `
        <div class="amap-config-card">
            <div class="amap-config-icon-wrap">
                <i class="${icon} amap-config-icon"></i>
            </div>
            <h3 class="amap-config-title">${title}</h3>
            <p class="amap-config-desc">${desc}</p>
            <div class="amap-config-actions">
                <button class="amap-btn-base amap-btn-secondary" onclick="window.open('https://console.amap.com/')">
                    申请密钥 <i class="ri-external-link-line"></i>
                </button>
                <button class="amap-btn-base amap-btn-primary" onclick="window.location.reload()">
                    刷新页面
                </button>
            </div>
        </div>
    `;

    // 添加到 .lg-map 容器或 body
    const lgMapContainer = document.querySelector('.lg-map');
    (lgMapContainer || document.body).appendChild(overlay);
};

// 显示地图加载错误（API Key 或安全密钥无效）
const showMapLoadError = (errorInfo) => {
    let title = '加载失败';
    let icon = 'ri-error-warning-fill';
    let desc = '';

    if (errorInfo && errorInfo.includes('INVALID_USER_KEY')) {
        title = 'API Key 无效';
        icon = 'ri-key-2-fill';
        desc = '高德地图 API Key 验证失败。<br>请检查 Key 是否正确或已过期。';
    } else if (errorInfo && errorInfo.includes('INVALID_USER_SCODE')) {
        title = '安全密钥无效';
        icon = 'ri-shield-keyhole-fill';
        desc = '高德地图安全密钥验证失败。<br>请检查 securityJsCode 是否正确。';
    } else if (errorInfo && errorInfo.includes('USERKEY_PLAT_NOMATCH')) {
        title = '平台不匹配';
        icon = 'ri-error-warning-fill';
        desc = 'API Key 与当前平台不匹配。<br>请确认使用的是 Web 端 Key。';
    } else {
        desc = '高德地图加载失败。<br>请检查网络连接或配置信息。';
        if (errorInfo) {
            desc += `<br><small style="opacity:0.6">${errorInfo}</small>`;
        }
    }

    // 移除可能存在的旧遮罩
    const oldOverlay = document.querySelector('.amap-config-overlay');
    if (oldOverlay) oldOverlay.remove();

    const overlay = document.createElement('div');
    overlay.className = 'amap-config-overlay';
    overlay.innerHTML = `
        <div class="amap-config-card">
            <div class="amap-config-icon-wrap amap-config-icon-error">
                <i class="${icon} amap-config-icon"></i>
            </div>
            <h3 class="amap-config-title">${title}</h3>
            <p class="amap-config-desc">${desc}</p>
            <div class="amap-config-actions">
                <button class="amap-btn-base amap-btn-secondary" onclick="window.open('https://console.amap.com/')">
                    控制台 <i class="ri-external-link-line"></i>
                </button>
                <button class="amap-btn-base amap-btn-primary" onclick="window.location.reload()">
                    重新加载
                </button>
            </div>
        </div>
    `;

    // 添加到 .lg-map 容器或 body
    const lgMapContainer = document.querySelector('.lg-map');
    (lgMapContainer || document.body).appendChild(overlay);
};

// 验证高德地图 API Key（通过逆地理编码接口）
const validateAmapKey = () => {
    return new Promise((resolve, reject) => {
        AMap.plugin('AMap.Geocoder', () => {
            const geocoder = new AMap.Geocoder();
            // 使用一个固定坐标测试
            geocoder.getAddress([116.397428, 39.90923], (status, result) => {
                mapDebugLog('API Key 验证结果:', status, result);
                if (status === 'complete' && result.regeocode) {
                    resolve(true);
                } else if (status === 'error') {
                    reject(new Error(result || 'API Key 验证失败'));
                } else if (status === 'no_data') {
                    reject(new Error('API Key 或安全密钥无效'));
                } else {
                    reject(new Error(result || '验证失败: ' + status));
                }
            });
        });
    });
};

// ============================================
// 全局 API: window.LGMap
// 用法:
//   LGMap.open()                          — 打开地图（默认情侣模式）
//   LGMap.open({ mode: 'messages' })      — 打开地图并切换到留言模式
//   LGMap.open({ coords: [lng, lat], zoom: 15 }) — 打开并定位到指定坐标
//   LGMap.open({ mode: 'moments', coords: [lng, lat] }) — 打开指定模式并定位
//   LGMap.close()                         — 关闭地图
//   LGMap.isOpen()                        — 是否已打开
// ============================================
window.LGMap = (function () {
    let _initialized = false;
    let _mapInstance = null;
    let _pendingOptions = null;
    let _sdkLoaded = false;
    let _sdkLoading = false;
    let _mapVisible = false;

    const overlay = () => document.getElementById('lgMapOverlay');
    const mapContainer = () => document.getElementById('missing-pets-map');

    const waitForMapContainerReady = (timeoutMs = 1500) => {
        return new Promise((resolve) => {
            const startedAt = Date.now();

            const check = () => {
                const el = mapContainer();
                if (el) {
                    const rect = el.getBoundingClientRect();
                    if (rect.width > 0 && rect.height > 0) {
                        resolve(true);
                        return;
                    }
                }

                if (Date.now() - startedAt >= timeoutMs) {
                    mapDebugWarn('🗺️ [LGMap Debug] 地图容器在超时前仍未获得有效尺寸，继续后续流程。');
                    resolve(false);
                    return;
                }

                requestAnimationFrame(check);
            };

            check();
        });
    };

    // 动态加载高德地图 SDK
    const loadAmapSDK = () => {
        return new Promise((resolve, reject) => {
            if (_sdkLoaded || typeof AMap !== 'undefined') {
                _sdkLoaded = true;
                resolve();
                return;
            }
            if (_sdkLoading) {
                // 等待已有的加载完成
                const wait = setInterval(() => {
                    if (typeof AMap !== 'undefined') {
                        clearInterval(wait);
                        _sdkLoaded = true;
                        resolve();
                    }
                }, 100);
                return;
            }
            _sdkLoading = true;
            const script = document.createElement('script');
            script.src = ((window.LG_CONFIG && window.LG_CONFIG.assetBase) || '') + '/assets/js/lg-map-sdk.js';
            script.onload = () => {
                _sdkLoaded = true;
                _sdkLoading = false;
                resolve();
            };
            script.onerror = () => {
                _sdkLoading = false;
                reject(new Error('高德地图 SDK 加载失败'));
            };
            document.head.appendChild(script);
        });
    };

    // 初始化地图（仅首次）
    const _init = async () => {
        if (_initialized) return;
        _initialized = true;

        const configErrors = validateAmapConfig();
        if (configErrors.length > 0) {
            console.error('高德地图配置错误:', configErrors);
            showConfigError(configErrors);
            return;
        }

        try {
            await loadAmapSDK();
            mapDebugLog('高德地图 SDK 加载成功，开始验证 Key...');
            await validateAmapKey();
            mapDebugLog('API Key 验证通过');
            await waitForMapContainerReady();
            _mapInstance = await initializeApp();

            // 如果有待执行的定位操作
            if (_pendingOptions) {
                _applyOptions(_pendingOptions);
                _pendingOptions = null;
            }
        } catch (err) {
            console.error('地图初始化失败:', err);
            _initialized = false;
            showMapLoadError(err.message || '地图加载失败');
        }
    };

    // 验证地图容器是否可见
    const isVisible = () => _mapVisible;

    // 应用打开参数（模式切换 + 坐标定位 + 标记卡片）
    const _applyOptions = (opts) => {
        if (!opts) return;

        // 切换模式（用 switchToMode 而非 modeBtn.click，避免触发 setFitView 覆盖后续定位）
        if (opts.mode) {
            if (window._loversMapState && window._loversMapState.switchToMode) {
                window._loversMapState.switchToMode(opts.mode);
            } else {
                const modeBtn = document.querySelector(`.mode-btn[data-mode="${opts.mode}"]`);
                if (modeBtn) modeBtn.click();
            }
        }

        // 如果有 markerId，找到对应标记并模拟点击（定位+弹卡片）
        if (opts.markerId !== undefined && opts.markerId !== null) {
            const delay = opts.mode ? 600 : 300;
            setTimeout(() => {
                _triggerMarker(opts);
            }, delay);
            return;
        }

        // 定位到指定坐标
        if (opts.coords && Array.isArray(opts.coords) && opts.coords.length === 2) {
            const coords = extractCoordinatePair(opts.coords);

            mapDebugLog('🗺️ [LGMap Debug] 解析坐标结果:', coords, '(原始数据:', opts.coords, ')');

            if (!coords) {
                mapDebugWarn('🗺️ [LGMap Debug] 坐标无效，终止定位操作，避免 LngLat(NaN, NaN) 崩溃。');
                return;
            }
            const [lng, lat] = coords;

            const map = _getMapInstance();
            if (map) {
                const targetZoom = opts.zoom || 15;
                const baseDelay = opts.mode ? 500 : 200;

                // 如果指定了 albumCode，直接展开照片（expand 内部会处理定位和缩放）
                if (opts.albumCode) {
                    setTimeout(() => {
                        if (!_mapVisible) return;
                        // 阻止相册 updateDisplay 在展开期间重新显示标记
                        const modeData = window._lgMapModeData;
                        if (modeData && modeData.albums && modeData.albums.elements && modeData.albums.elements.setZoomingToShow) {
                            modeData.albums.elements.setZoomingToShow(true);
                        }
                        if (typeof AlbumPhotosManager !== 'undefined') {
                            AlbumPhotosManager.expand(opts.albumCode, opts.albumName || '', { skipFitView: true, targetCoords: [lng, lat] });
                        }
                        // expand 是 async 的，在它完成后恢复标志
                        setTimeout(() => {
                            if (modeData && modeData.albums && modeData.albums.elements && modeData.albums.elements.setZoomingToShow) {
                                modeData.albums.elements.setZoomingToShow(false);
                            }
                        }, 1500);
                    }, baseDelay);
                } else {
                    setTimeout(() => {
                        if (!_mapVisible) return;
                        map.setStatus({ animateEnable: true });
                        map.setZoom(targetZoom);
                        map.panTo([lng, lat]);
                    }, baseDelay);
                }
            }
        }
    };

    // 根据 markerId 找到标记并模拟点击
    const _triggerMarker = (opts) => {
        const modeData = window._lgMapModeData;
        if (!modeData) return;

        const mode = opts.mode || 'moments';
        const id = String(opts.markerId);
        const elements = modeData[mode]?.elements;
        if (!elements || !elements.markers) return;

        // 在标记列表中查找匹配的标记
        const targetMarker = elements.markers.find(m => {
            const data = m._bindData;
            if (!data) return false;
            // 支持多种 ID 字段：id, name, articletitle 等
            return String(data.id) === id ||
                String(data.name) === id ||
                String(data.articletitle) === id;
        });

        if (targetMarker) {
            // 提前设置标志，阻止 updateDisplay 在 click→zoom 链路中隐藏标记
            if (elements.setZoomingToShow) elements.setZoomingToShow(true);
            // 先确保标记可见（updateDisplay 可能还没执行完）
            targetMarker.show();
            // 触发标记的点击事件，复用已有的定位+弹卡片逻辑
            targetMarker.emit('click', { target: targetMarker });
        }
    };

    // 获取内部 AMap.Map 实例
    const _getMapInstance = () => {
        const mapEl = document.getElementById('missing-pets-map');
        // initializeApp 中 map 是局部变量，通过 DOM 获取不到
        // 我们在 initializeApp 返回时保存引用
        return _mapInstance;
    };

    // 打开地图弹窗
    const open = (options) => {
        mapDebugLog('🗺️ [LGMap Debug] LGMap.open 被调用, 参数:', options);
        const el = overlay();
        if (!el) return;

        el.style.display = 'flex';
        // 触发动画
        requestAnimationFrame(() => {
            el.classList.add('lg-map-overlay-show');
        });
        if (window.lgScrollLock) lgScrollLock();
        _mapVisible = true;

        if (!_initialized) {
            _pendingOptions = options || null;
            _init();
        } else {
            // 容器从 display:none 恢复后，高德地图需要重新计算尺寸
            if (_mapInstance) {
                waitForMapContainerReady().then(() => {
                    if (!_mapInstance || !_mapVisible) return;
                    _mapInstance.resize();
                    // 恢复轨迹动画
                    const modeData = window._lgMapModeData;
                    if (modeData?.lovers?.elements?.trail) {
                        modeData.lovers.elements.trail.start();
                    }
                    // resize 完成后再执行定位，避免 Pixel(NaN, NaN)
                    _applyOptions(options);
                });
            } else {
                _applyOptions(options);
            }
            // 重新触发 UI 元素显示动画（close 时已清理 show 类名）
            showElements();
        }
    };

    // 关闭地图弹窗
    const close = () => {
        const el = overlay();
        if (!el) return;

        // 停止轨迹动画，避免 display:none 后 Pixel(NaN, NaN)
        const modeData = window._lgMapModeData;
        if (modeData?.lovers?.elements?.trail) {
            modeData.lovers.elements.trail.stop();
        }

        // 关键修复: 隐藏前清除所有信息窗体，阻止在不可见时计算坐标
        if (_mapInstance) {
            _mapInstance.clearInfoWindow();
        }

        _mapVisible = false;

        // 先移除 show，加入 closing 触发反向动画
        el.classList.remove('lg-map-overlay-show');
        el.classList.add('lg-map-overlay-closing');
        setTimeout(() => {
            el.classList.remove('lg-map-overlay-closing');
            el.style.display = 'none';

            // 清理 UI show 状态，以便下次 open 时重新触发动画
            const showSelectors = [
                '.ui-footer-container', '.lovers-panel', '#anniversary-panel',
                '.mode-switcher', '.map-controls', '.zoom-controls'
            ];
            showSelectors.forEach(sel => {
                const target = el.querySelector(sel);
                if (target) target.classList.remove('show');
            });
            el.querySelectorAll('.control-btn, .zoom-controls button').forEach(btn => {
                btn.classList.remove('show');
            });

            // 彻底销毁地图，保证下次打开是全新干净的状态，避免遗留动画对象在后台抛出 NaN
            destroy();

        }, 700);
        if (window.lgScrollUnlock) lgScrollUnlock();
    };

    // 销毁地图实例（pjax 切换时调用，下次打开重新初始化）
    const destroy = () => {
        // 清理照片展开状态
        if (typeof AlbumPhotosManager !== 'undefined' && AlbumPhotosManager.isExpanded()) {
            AlbumPhotosManager.forceCollapse();
        }

        if (_mapInstance) {
            try { _mapInstance.destroy(); } catch (e) { }
            _mapInstance = null;
        }
        _initialized = false;
        _pendingOptions = null;

        // 清理 UI 状态
        const el = overlay();
        if (el) {
            el.classList.remove('lg-map-overlay-show', 'lg-map-overlay-closing');
            el.style.display = 'none';
            ['.ui-footer-container', '.lovers-panel', '#anniversary-panel',
                '.mode-switcher', '.map-controls', '.zoom-controls'].forEach(sel => {
                    const t = el.querySelector(sel);
                    if (t) t.classList.remove('show');
                });
            el.querySelectorAll('.control-btn, .zoom-controls button').forEach(b => b.classList.remove('show'));
        }
        if (window.lgScrollUnlock) lgScrollUnlock();

        // 重置地图容器 DOM（清除高德地图残留的内部元素）
        const mapEl = document.getElementById('missing-pets-map');
        if (mapEl) mapEl.innerHTML = '';

        // 重置 tab 高亮到默认模式
        const _defaultMode = 'lovers';
        const modeSwitcher = document.getElementById('mode-switcher');
        if (modeSwitcher) {
            modeSwitcher.querySelectorAll('.mode-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.mode === _defaultMode);
            });
        }
    };

    // 绑定事件
    document.addEventListener('DOMContentLoaded', () => {
        // ESC 键关闭
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay()?.classList.contains('lg-map-overlay-show')) {
                close();
            }
        });

        // 绑定导航栏地图图标
        const mapBtn = document.getElementById('lgMapOpenBtn');
        if (mapBtn) {
            mapBtn.addEventListener('click', (e) => {
                e.preventDefault();
                open();
            });
        }

        // pjax 切换时销毁地图，避免旧实例在新页面上出错
        if (window.jQuery) {
            jQuery(document).on('pjax:start', () => {
                if (_initialized) {
                    close();
                    destroy();
                }
            });
        }
    });

    return { open, close, destroy, isOpen: () => overlay()?.classList.contains('lg-map-overlay-show') || false, isVisible: () => _mapVisible, getMap: _getMapInstance };
})();

// 优化动画性能
const showElements = () => {
    // 添加初始类
    document.body.classList.add('theme-ready');

    // 动画序列
    const animationSequence = [
        {
            element: '.ui-footer-container',
            className: 'show',
            delay: 0
        },
        {
            element: '.lovers-panel',
            className: 'show',
            delay: 300
        },
        {
            element: '#anniversary-panel',
            className: 'show',
            delay: 450
        },
        {
            element: '.mode-switcher',
            className: 'show',
            delay: 500
        },
        {
            element: '.map-controls',
            className: 'show',
            delay: 500,
            callback: () => {
                // 依次显示控制按钮
                const buttons = document.querySelectorAll('.map-controls .control-btn');
                buttons.forEach((btn, index) => {
                    setTimeout(() => {
                        btn.classList.add('show');
                        // 添加缩放效果
                        btn.classList.add('scale-in');
                        // 移除缩放效果
                        setTimeout(() => btn.classList.remove('scale-in'), 300);
                    }, index * 100);
                });
            }
        },
        {
            element: '.zoom-controls',
            className: 'show',
            delay: 700,
            callback: () => {
                const zoomButtons = document.querySelectorAll('.zoom-controls button');
                zoomButtons.forEach((btn, index) => {
                    setTimeout(() => {
                        btn.classList.add('show');
                        btn.classList.add('slide-in');
                        setTimeout(() => btn.classList.remove('slide-in'), 300);
                    }, index * 100);
                });
            }
        }
    ];

    // 执行动画序列
    animationSequence.forEach(({ element, className, delay, callback }) => {
        setTimeout(() => {
            const el = document.querySelector(element);
            if (el) {
                el.classList.add(className);
                if (callback) {
                    callback();
                }
            }
        }, delay);
    });
};

// 图层配置
const layerConfig = {
    satellite: {
        zIndex: 0,
        opacity: 1
    },
    road: {
        zIndex: 1,
        opacity: 0.6,
        strokeColor: '#666666'
    },
    traffic: {
        zIndex: 2,
        opacity: 0.6
    }
};

// 优化地图移动
const moveToLocation = (map, position) => {
    return new Promise((resolve) => {
        // 增加防御检查
        const coords = extractCoordinatePair(position);
        if (!coords) {
            mapDebugWarn('🗺️ [LGMap Debug] moveToLocation 传入坐标无效:', position);
            resolve();
            return;
        }
        const [lng, lat] = coords;

        // 启用动画
        map.setStatus({ animateEnable: true });

        // 设置缩放级别
        map.setZoom(20);

        // 平移到目标位置
        map.panTo([lng, lat]);

        // 等待动画完成
        const checkAnimation = () => {
            if (!map.isMoving && !map.isZooming) {
                resolve();
            } else {
                requestAnimationFrame(checkAnimation);
            }
        };
        checkAnimation();
    });
};

// 优化标记点创建 - 显示头像
const createMarker = (spec) => {
    const markerContent = document.createElement('div');
    markerContent.className = 'custom-marker';

    const markerImage = document.createElement('div');
    markerImage.className = 'marker-image';

    const imageSrc = spec.image || spec.avatar || '';
    if (imageSrc) {
        const img = document.createElement('img');
        img.src = imageSrc;
        img.alt = spec.name || '留言';
        img.draggable = false;
        markerImage.appendChild(img);
    } else {
        markerImage.classList.add('is-empty');
    }

    markerContent.appendChild(markerImage);

    return markerContent;
};

// 创建情侣位置标记
const createLoverMarker = (lover) => {
    const markerContent = document.createElement('div');
    markerContent.className = 'lover-marker' + (lover.role ? ` is-${lover.role}` : '');

    markerContent.innerHTML = `
        <div class="lover-marker-content">
            <div class="lover-marker-avatar">
                <img src="${lover.avatar}" alt="${lover.name}" draggable="false">
            </div>
        </div>
    `;

    return markerContent;
};

// 计算两点之间的距离（公里）
const calculateDistance = (coords1, coords2) => {
    const R = 6371; // 地球半径（公里）
    const lat1 = coords1[1] * Math.PI / 180;
    const lat2 = coords2[1] * Math.PI / 180;
    const deltaLat = (coords2[1] - coords1[1]) * Math.PI / 180;
    const deltaLng = (coords2[0] - coords1[0]) * Math.PI / 180;

    const a = Math.sin(deltaLat / 2) * Math.sin(deltaLat / 2) +
        Math.cos(lat1) * Math.cos(lat2) *
        Math.sin(deltaLng / 2) * Math.sin(deltaLng / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c;
};

// 格式化距离显示
const formatDistance = (km) => {
    if (km < 1) {
        return `${Math.round(km * 1000)} m`;
    } else if (km < 100) {
        return `${km.toFixed(1)} KM`;
    } else {
        return `${Math.round(km)} KM`;
    }
};

// 天气图标映射
const weatherIconMap = {
    '晴': 'ri-sun-line',
    '多云': 'ri-cloudy-line',
    '阴': 'ri-cloudy-2-line',
    '小雨': 'ri-drizzle-line',
    '中雨': 'ri-rainy-line',
    '大雨': 'ri-heavy-showers-line',
    '暴雨': 'ri-thunderstorms-line',
    '雷阵雨': 'ri-thunderstorms-line',
    '阵雨': 'ri-showers-line',
    '小雪': 'ri-snowy-line',
    '中雪': 'ri-snowy-line',
    '大雪': 'ri-heavy-showers-line',
    '雾': 'ri-mist-line',
    '霾': 'ri-haze-line',
    '沙尘暴': 'ri-sun-foggy-line',
    '浮尘': 'ri-sun-foggy-line',
    '扬沙': 'ri-sun-foggy-line',
    '风': 'ri-windy-line',
    '大风': 'ri-windy-line'
};

// 获取天气图标类名
const getWeatherIcon = (weather) => {
    if (!weather) return 'ri-map-pin-line';
    for (const [key, icon] of Object.entries(weatherIconMap)) {
        if (weather.includes(key)) return icon;
    }
    return 'ri-sun-foggy-line';
};

// 通过经纬度获取天气信息（使用高德 JS API 插件）
const getWeatherByLocation = (lng, lat) => {
    return new Promise((resolve) => {
        // 先逆地理编码获取城市
        AMap.plugin('AMap.Geocoder', () => {
            const geocoder = new AMap.Geocoder();
            geocoder.getAddress([lng, lat], (status, result) => {
                mapDebugLog('📍 逆地理编码结果:', { status, result });

                if (status !== 'complete' || !result.regeocode) {
                    mapDebugWarn('逆地理编码失败:', status, result);
                    resolve(null);
                    return;
                }

                const addressComponent = result.regeocode.addressComponent;
                const city = addressComponent.city || addressComponent.province;
                const district = addressComponent.district;

                mapDebugLog('🏙️ 解析到城市:', { city, district, addressComponent });

                // 使用天气插件获取天气
                AMap.plugin('AMap.Weather', () => {
                    const weather = new AMap.Weather();
                    weather.getLive(city, (err, data) => {
                        mapDebugLog('🌤️ 天气数据:', { err, data });

                        if (err || !data) {
                            mapDebugWarn('天气获取失败:', err);
                            // 即使天气失败，也返回城市信息
                            resolve({
                                city: district || city,
                                weather: null,
                                temperature: null,
                                icon: 'ri-map-pin-line'
                            });
                            return;
                        }

                        const weatherResult = {
                            city: district || city,
                            weather: data.weather,
                            temperature: data.temperature,
                            icon: getWeatherIcon(data.weather)
                        };

                        mapDebugLog('✅ 最终天气结果:', weatherResult);
                        resolve(weatherResult);
                    });
                });
            });
        });
    });
};

// 更新情侣面板的距离模式
const updateLoversPanelMode = (distance) => {
    const panel = document.getElementById('lovers-panel');
    const distanceIcon = document.getElementById('distance-icon');
    const distanceText = document.getElementById('love-distance-text');
    const isSoloMode = !!(window.LGMAP_CONFIG && window.LGMAP_CONFIG.soloMode);

    if (!panel || !distanceIcon || !distanceText) return;

    // 移除所有模式类
    panel.classList.remove('mode-near', 'mode-together');

    if (!Number.isFinite(distance)) {
        distanceIcon.className = 'ri-user-location-fill distance-icon';
        distanceText.textContent = isSoloMode ? '等待定位' : '计算中...';
        return;
    }

    // 距离小于 50 米：身边模式
    if (distance < 0.05) {
        panel.classList.add('mode-together');
        distanceIcon.className = isSoloMode ? 'ri-navigation-fill distance-icon' : 'ri-heart-3-fill distance-icon';
        distanceText.textContent = isSoloMode ? '就在附近' : 'With You';
    }
    // 距离小于 1 公里：附近模式
    else if (distance < 1) {
        panel.classList.add('mode-near');
        distanceIcon.className = 'ri-navigation-fill distance-icon';
        distanceText.textContent = `${Math.round(distance * 1000)} m`;
    }
    // 异地模式
    else {
        distanceIcon.className = 'ri-map-pin-fill distance-icon';
        distanceText.textContent = formatDistance(distance);
    }
};

// 格式化时间
const formatTime = (timeString) => {
    if (!timeString) return '';
    try {
        const date = new Date(timeString);
        return date.toLocaleDateString('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        }).replace(/\//g, '-');
    } catch (e) {
        mapDebugWarn('时间格式化失败:', e);
        return timeString;
    }
};

// 创建同坐标卡片容器（带翻页）
const createLocationPickerCard = (items, type, createCardFn, infoWindow, pickerState) => {
    const state = pickerState || {
        currentIndex: 0,
        viewedSet: new Set()
    };

    const colors = {
        message: '#ff7043',
        album: '#7c4dff',
        event: '#2196f3',
        moment: '#667eea'
    };
    const themeColor = colors[type] || '#ff4081';

    // 创建容器
    const container = document.createElement('div');
    container.className = 'loc-picker-wrap';
    container.style.setProperty('--picker-color', themeColor);

    // 卡片内容区
    const cardArea = document.createElement('div');
    cardArea.className = 'loc-picker-card';
    container.appendChild(cardArea);

    // 多个时显示左右箭头和指示器
    let prevBtn, nextBtn, dots;
    if (items.length > 1) {
        // 左箭头
        prevBtn = document.createElement('button');
        prevBtn.className = 'loc-side-btn loc-side-prev';
        prevBtn.disabled = state.currentIndex === 0;
        prevBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>`;
        container.appendChild(prevBtn);

        // 右箭头
        nextBtn = document.createElement('button');
        nextBtn.className = 'loc-side-btn loc-side-next';
        nextBtn.disabled = state.currentIndex === items.length - 1;
        nextBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>`;
        container.appendChild(nextBtn);

        // 指示器
        dots = document.createElement('div');
        dots.className = 'loc-dots';
        for (let i = 0; i < items.length; i++) {
            const dot = document.createElement('span');
            dot.className = 'loc-dot' + (i === state.currentIndex ? ' active' : '');
            dots.appendChild(dot);
        }
        container.appendChild(dots);

        // 更新UI
        const updateUI = () => {
            prevBtn.disabled = state.currentIndex === 0;
            nextBtn.disabled = state.currentIndex === items.length - 1;
            dots.querySelectorAll('.loc-dot').forEach((d, i) => {
                d.classList.toggle('active', i === state.currentIndex);
            });
        };

        prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (state.currentIndex > 0) {
                state.currentIndex--;
                renderCard();
                updateUI();
            }
        });

        nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (state.currentIndex < items.length - 1) {
                state.currentIndex++;
                renderCard();
                updateUI();
            }
        });
    }

    // 渲染当前卡片
    const renderCard = () => {
        const item = items[state.currentIndex];
        cardArea.innerHTML = createCardFn(item);
        state.viewedSet.add(state.currentIndex);
    };

    renderCard();
    container.pickerState = state;
    return container;
};

// 优化信息窗口内容创建 HTML - 气泡对话风格
function createInfoWindow(spec) {
    const {
        id = '', parentId = null, image = '', avatar = '', name = '', city = '', address = '',
        description = '', text = '', textHtml = '', createTime = '', time = '', os = '', browser = ''
    } = spec;

    const displayText = textHtml || description || text || '';
    const displayLocation = address || city || '未知位置';
    const locationShort = displayLocation.includes('·') ? displayLocation.split('·')[1].trim() : displayLocation;
    const isMobile = os === 'iOS' || os === 'Android';

    const formatDate = (dateInput) => {
        if (!dateInput) return '';
        let date = typeof dateInput === 'number' ? new Date(dateInput * 1000) : new Date(dateInput);
        const days = Math.floor((new Date() - date) / (1000 * 60 * 60 * 24));
        if (days === 0) return '今天';
        if (days === 1) return '昨天';
        if (days < 7) return `${days}天前`;
        if (days < 30) return `${Math.floor(days / 7)}周前`;
        return date.toLocaleDateString('zh-CN', { month: 'short', day: 'numeric' });
    };

    const displayTime = formatDate(createTime || time);
    // 一级评论: #comment_{id}  二级评论: #reply_{parentId}_{id}
    const detailUrl = id ? (parentId ? `messages.php#reply_${parentId}_${id}` : `messages.php#comment_${id}`) : '';

    const cardContent = `
        <div class="msg-bubble-card">
            <div class="msg-top">
                <span class="msg-name">${name}</span>
                ${displayTime ? `<span class="msg-time">${displayTime}</span>` : ''}
            </div>
            <div class="msg-text">${displayText}</div>
            <div class="msg-footer">
                <span class="msg-footer-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    ${locationShort}
                </span>
                ${os ? `<span class="msg-footer-item">
                    ${isMobile
                ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>`
                : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>`
            }
                    ${os}
                </span>` : ''}
            </div>
        </div>
    `;

    if (detailUrl) {
        return `<a href="${detailUrl}" class="map-card-link" onclick="return window._mapCardNav('${detailUrl}',event)" style="text-decoration:none;color:inherit;display:block;cursor:pointer;position:relative;z-index:10;">${cardContent}</a>`;
    }
    return cardContent;
}

// 情侣详情信息窗口 - 精致纵向卡片
function createLoverInfoWindow(lover, otherLover, loveStartDate) {
    const formatCoord = (value, positiveDir, negativeDir) => {
        const numericValue = Number(value);
        if (!isFinite(numericValue)) {
            return '--';
        }
        return `${Math.abs(numericValue).toFixed(4)}° ${numericValue >= 0 ? positiveDir : negativeDir}`;
    };

    const latitudeText = formatCoord(lover.coords && lover.coords[1], 'N', 'S');
    const longitudeText = formatCoord(lover.coords && lover.coords[0], 'E', 'W');

    return `
        <div class="lover-profile-card">
            <div class="lpc-avatar-box">
                <div class="lpc-avatar" style="background-image: url('${lover.avatar}')"></div>
            </div>
            <div class="lpc-name">${lover.name}</div>
            <div class="lpc-location-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                ${lover.label}
            </div>
            <div class="lpc-dashboard">
                <div class="lpc-stat-item">
                    <span class="lpc-stat-label">LATITUDE</span>
                    <span class="lpc-stat-val lpc-stat-val--mono">${latitudeText}</span>
                </div>
                <div class="lpc-divider-v"></div>
                <div class="lpc-stat-item">
                    <span class="lpc-stat-label">LONGITUDE</span>
                    <span class="lpc-stat-val lpc-stat-val--mono">${longitudeText}</span>
                </div>
            </div>
        </div>
    `;
}

// 性能优化：使用防抖优化事件处理
const debounce = (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};


// 优化图层更新
const updateLayers = async (layerState, layers) => {
    return new Promise(resolve => {
        requestAnimationFrame(() => {
            // 处理基础图层
            if (layerState.baseLayer === 'satellite') {
                layers.satellite.show();
            } else {
                layers.satellite.hide();
            }

            // 错开叠加图层的更新时间
            setTimeout(() => {
                if (layerState.overlays.road) {
                    layers.road.show();
                } else {
                    layers.road.hide();
                }
            }, 100);

            setTimeout(() => {
                if (layerState.overlays.traffic) {
                    layers.traffic.show();
                } else {
                    layers.traffic.hide();
                }
                resolve();
            }, 200);
        });
    });
};


// 初始化应用
const initializeApp = async () => {
    try {
        // 等待地图数据从 API 加载完成
        if (window.LGMAP_DATA_READY) {
            try {
                await window.LGMAP_DATA_READY;
            } catch (e) {
                mapDebugWarn('地图数据预加载失败，使用默认空数据:', e);
            }
        }

        // 创建地图实例（打开即全屏，直接启用所有交互）
        const map = new AMap.Map('missing-pets-map', {
            zoom: 5,
            center: [104.0668, 30.5728],
            mapStyle: window.LGMAP_CONFIG.mapStyle || 'amap://styles/normal',
            viewMode: '3D',
            pitch: 0,
            features: ['bg', 'road', 'building', 'point'],
            showBuildingBlock: true
        });

        // 绑定缩放按钮事件
        const zoomMapBtn = document.getElementById('map-zoom');

        // 用于存储信息窗口实例
        let infoWindow;

        // 用于存储当前打开的标记
        let currentMarker = null;

        // 重置地图到初始状态的函数（稍后在情侣标记创建后绑定）
        let resetMapView = () => {
            // 关闭信息窗口
            if (infoWindow) {
                infoWindow.close();
                currentMarker = null;
            }
            map.setZoomAndCenter(5, [104.0668, 30.5728]);
        };

        zoomMapBtn.onclick = () => resetMapView();

        const fullscreenBtn = document.getElementById('full-screen-button');

        // 全屏按钮 → 关闭弹窗
        fullscreenBtn.onclick = () => {
            if (window.LGMap && typeof window.LGMap.close === 'function') {
                window.LGMap.close();
            }
        };

        // 将全屏按钮图标改为"退出/关闭"样式
        fullscreenBtn.innerHTML = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="control-icon">
  <path d="M3.28 2.22a.75.75 0 0 0-1.06 1.06L5.44 6.5H2.75a.75.75 0 0 0 0 1.5h4.5A.75.75 0 0 0 8 7.25v-4.5a.75.75 0 0 0-1.5 0v2.69L3.28 2.22ZM13.5 2.75a.75.75 0 0 0-1.5 0v4.5c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-2.69l3.22-3.22a.75.75 0 0 0-1.06-1.06L13.5 5.44V2.75ZM3.28 17.78l3.22-3.22v2.69a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.69l-3.22 3.22a.75.75 0 1 0 1.06 1.06ZM13.5 14.56l3.22 3.22a.75.75 0 1 0 1.06-1.06l-3.22-3.22h2.69a.75.75 0 0 0 0-1.5h-4.5a.75.75 0 0 0-.75.75v4.5a.75.75 0 0 0 1.5 0v-2.69Z" />
</svg>`;

        // 等待地图加载完成
        await new Promise((resolve) => {
            map.on('complete', resolve);
        });

        // 创建图层
        const layers = {
            satellite: new AMap.TileLayer.Satellite(),
            road: new AMap.TileLayer.RoadNet(),
            traffic: new AMap.TileLayer.Traffic()
        };

        // 添加图层到地图
        Object.values(layers).forEach(layer => {
            map.add(layer);
            layer.hide();
        });

        // 初始化地图功能
        initializeMapFeatures(map, layers);

        // 存储各模式的标记和元素
        const modeData = {
            lovers: { markers: [], elements: [] },
            messages: { markers: [], elements: [] },
            albums: { markers: [], elements: [] },
            events: { markers: [], elements: [] },
            moments: { markers: [], elements: null }
        };

        // 当前模式
        const _isSoloMode = !!(window.LGMAP_CONFIG && window.LGMAP_CONFIG.soloMode);
        let currentMode = 'lovers';

        // 切换防抖定时器
        let switchTimer = null;

        // 各模式的标题和描述配置（从配置中读取，提供默认值）
        const defaultModeConfig = {
            lovers: _isSoloMode
                ? { title: '访客模式', desc: '看看此刻你与站点主人的距离' }
                : { title: '情侣模式', desc: '无论相隔多远，心始终在一起' },
            moments: { title: '点点滴滴', desc: '记录我们的每一个美好瞬间' },
            messages: { title: '留言模式', desc: '来自世界各地的温暖祝福' },
            albums: { title: '相册模式', desc: '用照片定格我们的回忆' },
            events: { title: '事件清单', desc: '一起完成的每一个小目标' }
        };
        const modeFooterConfig = {
            ...defaultModeConfig,
            ...(window.LGMAP_CONFIG?.modeConfig || {})
        };

        // 获取各模式的内容数量
        const getModeCount = (mode) => {
            const config = window.LGMAP_CONFIG || {};
            switch (mode) {
                case 'lovers':
                    return (config.lovers || []).length;
                case 'moments':
                    return (config.moments || []).length;
                case 'messages':
                    return (config.footprints || config.messages || []).length;
                case 'albums':
                    return (config.albums || []).length;
                case 'events':
                    // 只统计已完成的事件
                    return (config.events || []).filter(e => e.done && e.coords).length;
                default:
                    return 0;
            }
        };

        // 更新底部信息栏
        const updateFooterInfo = (mode) => {
            const config = modeFooterConfig[mode] || modeFooterConfig.lovers;
            const titleEl = document.getElementById('footer-title');
            const descEl = document.getElementById('footer-desc');
            const count = getModeCount(mode);

            if (titleEl) {
                // 情侣模式不显示数量，其他模式显示徽章
                if (mode === 'lovers') {
                    titleEl.innerHTML = config.title;
                } else {
                    titleEl.innerHTML = `${config.title} <span class="footer-count-badge">${count}</span>`;
                }
            }
            if (descEl) descEl.textContent = config.desc;
        };

        const updateModeButtonState = (mode) => {
            const switcher = document.getElementById('mode-switcher');
            if (!switcher) {
                return;
            }

            switcher.querySelectorAll('.mode-btn').forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.mode === mode);
            });
        };

        // 切换到指定模式的函数
        const switchToMode = (newMode) => {
            if (newMode === currentMode) {
                updateModeButtonState(newMode);
                updatePanels(newMode);
                updateFooterInfo(newMode);
                return;
            }

            // 取消之前的延迟操作
            if (switchTimer) clearTimeout(switchTimer);

            // 先隐藏所有模式（确保干净）
            ['lovers', 'moments', 'messages', 'albums', 'events'].forEach(mode => {
                if (mode !== newMode) {
                    hideMode(mode, modeData, map);
                }
            });

            updateModeButtonState(newMode);

            // 显示新模式
            showMode(newMode, modeData, map);

            // 更新面板显示
            updatePanels(newMode);

            // 更新底部信息栏
            updateFooterInfo(newMode);

            currentMode = newMode;
        };

        // 暴露给头像点击事件使用
        window._loversMapState = {
            getCurrentMode: () => currentMode,
            switchToMode: switchToMode
        };

        // 添加情侣标记和连线
        const lovers = window.LGMAP_CONFIG.lovers || [];
        let loverMarkers = [];
        if (lovers.length >= 2) {
            modeData.lovers.elements = addLoversMarkers(map, lovers);
            loverMarkers = modeData.lovers.elements.markers.slice(0, 2); // 只取前两个情侣标记
        }

        // 重置到情侣视图的函数（用于情侣面板中心区域点击）
        const resetToLoversView = () => {
            // 关闭信息窗口
            if (infoWindow) {
                infoWindow.close();
                currentMarker = null;
            }
            // 关闭情侣信息窗口
            if (modeData.lovers.elements && modeData.lovers.elements.infoWindow) {
                modeData.lovers.elements.infoWindow.close();
            }
            // 使用 setFitView 自动适配两个情侣标记的边界
            if (loverMarkers.length >= 2) {
                map.setFitView(loverMarkers, false, [100, 100, 100, 100]);
            }
        };

        // 根据当前模式重置视图的函数（用于刷新按钮）
        resetMapView = () => {
            // 关闭信息窗口
            if (infoWindow) {
                infoWindow.close();
                currentMarker = null;
            }
            // 关闭情侣信息窗口
            if (modeData.lovers.elements && modeData.lovers.elements.infoWindow) {
                modeData.lovers.elements.infoWindow.close();
            }

            // 根据当前模式获取对应的标记
            let markersToFit = [];
            switch (currentMode) {
                case 'lovers':
                    markersToFit = loverMarkers;
                    break;
                case 'messages':
                    if (modeData.messages.elements && modeData.messages.elements.markers) {
                        markersToFit = modeData.messages.elements.markers;
                    }
                    break;
                case 'albums':
                    // 如果照片展开中，先收起
                    if (typeof AlbumPhotosManager !== 'undefined' && AlbumPhotosManager.isExpanded()) {
                        AlbumPhotosManager.collapse();
                    }
                    if (modeData.albums.elements && modeData.albums.elements.markers) {
                        markersToFit = modeData.albums.elements.markers;
                    }
                    break;
                case 'events':
                    if (modeData.events.elements && modeData.events.elements.markers) {
                        markersToFit = modeData.events.elements.markers;
                    }
                    break;
                case 'moments':
                    if (modeData.moments.elements && modeData.moments.elements.markers) {
                        markersToFit = modeData.moments.elements.markers;
                    }
                    break;
            }

            // 如果有标记则适配视图，否则回到默认视图
            if (markersToFit && markersToFit.length > 0) {
                map.setFitView(markersToFit, false, [100, 100, 100, 100]);
            } else {
                map.setZoomAndCenter(5, [104.0668, 30.5728]);
            }
        };

        // 绑定情侣面板中心区域点击事件（重置地图到情侣视图）
        const loveDistanceCenter = document.querySelector('.love-distance-center');
        if (loveDistanceCenter) {
            loveDistanceCenter.onclick = resetToLoversView;
        }

        // 初始化情侣模式丰富内容
        initLoversModePanels(window.LGMAP_CONFIG);

        // 添加留言标记（带聚合）
        const markersData = window.LGMAP_CONFIG.footprints || window.LGMAP_CONFIG.messages || [];
        modeData.messages.elements = addFootprintMarkers(map, markersData);

        // 添加相册标记（带聚合）
        const albumsData = window.LGMAP_CONFIG.albums || [];
        modeData.albums.elements = addAlbumMarkers(map, albumsData);

        // 添加事件标记
        const eventsData = window.LGMAP_CONFIG.events || [];
        modeData.events.elements = addEventMarkers(map, eventsData);

        // 初始化点点滴滴模式（地图标记+聚合）
        const momentsData = window.LGMAP_CONFIG.moments || [];
        modeData.moments.elements = addMomentsMarkers(map, momentsData);

        // 缩放倍数显示器
        const zoomIndicator = document.getElementById('zoom-indicator');
        const zoomCurrentEl = document.getElementById('zoom-current');
        const updateZoomIndicator = () => {
            const zoom = Math.round(map.getZoom());
            if (zoomCurrentEl) zoomCurrentEl.textContent = zoom;
        };
        updateZoomIndicator();

        // 缩放事件监听 - 根据当前模式更新聚合
        let zoomTimer = null;
        const updateDelay = 100; // 降低延迟，提升响应速度

        map.on('zoomend', () => {
            updateZoomIndicator();
            // 情侣轨迹节点随缩放动态更新
            if (modeData.lovers.elements && modeData.lovers.elements.trail && modeData.lovers.elements.trail.updateForZoom) {
                modeData.lovers.elements.trail.updateForZoom();
            }
            if (zoomTimer) clearTimeout(zoomTimer);
            zoomTimer = setTimeout(() => {
                if (!window.LGMap.isVisible()) return; // 若地图不可见，跳过更新以免报错 Pixel(NaN,NaN)
                if (currentMode === 'messages' && modeData.messages.elements && modeData.messages.elements.updateDisplay) {
                    modeData.messages.elements.updateDisplay();
                } else if (currentMode === 'albums') {
                    // 照片展开时不更新相册聚合
                    if (typeof AlbumPhotosManager !== 'undefined' && AlbumPhotosManager.isExpanded()) return;
                    if (modeData.albums.elements && modeData.albums.elements.updateDisplay) {
                        modeData.albums.elements.updateDisplay();
                    }
                } else if (currentMode === 'events' && modeData.events.elements && modeData.events.elements.updateDisplay) {
                    modeData.events.elements.updateDisplay();
                } else if (currentMode === 'moments' && modeData.moments.elements && modeData.moments.elements.updateDisplay) {
                    modeData.moments.elements.updateDisplay();
                }
            }, updateDelay);
        });

        map.on('moveend', () => {
            if (zoomTimer) clearTimeout(zoomTimer);
            zoomTimer = setTimeout(() => {
                if (!window.LGMap.isVisible()) return; // 若地图不可见，跳过更新以免报错 Pixel(NaN,NaN)
                if (currentMode === 'messages' && modeData.messages.elements && modeData.messages.elements.updateDisplay) {
                    modeData.messages.elements.updateDisplay();
                } else if (currentMode === 'albums') {
                    // 照片展开时不更新相册聚合
                    if (typeof AlbumPhotosManager !== 'undefined' && AlbumPhotosManager.isExpanded()) return;
                    if (modeData.albums.elements && modeData.albums.elements.updateDisplay) {
                        modeData.albums.elements.updateDisplay();
                    }
                } else if (currentMode === 'events' && modeData.events.elements && modeData.events.elements.updateDisplay) {
                    modeData.events.elements.updateDisplay();
                } else if (currentMode === 'moments' && modeData.moments.elements && modeData.moments.elements.updateDisplay) {
                    modeData.moments.elements.updateDisplay();
                }
            }, updateDelay);
        });

        // 初始隐藏非当前模式
        if (_isSoloMode) {
            hideMode('messages', modeData, map);
            hideMode('albums', modeData, map);
            hideMode('events', modeData, map);
            hideMode('moments', modeData, map);
            showMode('lovers', modeData, map);
            updateModeButtonState('lovers');
            updateFooterInfo('lovers');
        } else {
            hideMode('messages', modeData, map);
            hideMode('albums', modeData, map);
            hideMode('events', modeData, map);
            hideMode('moments', modeData, map);
            updateModeButtonState('lovers');
        }

        // 模式切换逻辑（用事件委托避免重复绑定）
        const modeSwitcher = document.getElementById('mode-switcher');
        if (modeSwitcher) {
            modeSwitcher.onclick = (e) => {
                const btn = e.target.closest('.mode-btn');
                if (!btn) return;
                const newMode = btn.dataset.mode;
                if (newMode === currentMode) {
                    updateModeButtonState(newMode);
                    updatePanels(newMode);
                    updateFooterInfo(newMode);
                    return;
                }

                // 取消之前的延迟操作
                if (switchTimer) clearTimeout(switchTimer);

                // 先隐藏所有模式（确保干净）
                ['lovers', 'moments', 'messages', 'albums', 'events'].forEach(mode => {
                    if (mode !== newMode) {
                        hideMode(mode, modeData, map);
                    }
                });

                // 更新按钮状态
                updateModeButtonState(newMode);

                // 显示新模式
                showMode(newMode, modeData, map);

                // 更新面板显示
                updatePanels(newMode);

                // 更新底部信息栏
                updateFooterInfo(newMode);

                currentMode = newMode;

                // 根据新模式的标记自动适配边界
                let markersToFit = [];
                switch (newMode) {
                    case 'lovers':
                        markersToFit = loverMarkers;
                        break;
                    case 'messages':
                        if (modeData.messages.elements && modeData.messages.elements.markers) {
                            markersToFit = modeData.messages.elements.markers;
                        }
                        break;
                    case 'albums':
                        if (modeData.albums.elements && modeData.albums.elements.markers) {
                            markersToFit = modeData.albums.elements.markers;
                        }
                        break;
                    case 'events':
                        if (modeData.events.elements && modeData.events.elements.markers) {
                            markersToFit = modeData.events.elements.markers;
                        }
                        break;
                    case 'moments':
                        if (modeData.moments.elements && modeData.moments.elements.markers) {
                            markersToFit = modeData.moments.elements.markers;
                        }
                        break;
                }

                // 如果有标记则适配视图，否则回到默认视图
                if (markersToFit && markersToFit.length > 0) {
                    map.setFitView(markersToFit, false, [100, 100, 100, 100]);
                } else {
                    map.setZoomAndCenter(5, [104.0668, 30.5728]);
                }

                // 延迟更新聚合（可被下次点击取消）
                switchTimer = setTimeout(() => {
                    if (!window.LGMap.isVisible()) return; // 若地图不可见，跳过更新以免报错 Pixel(NaN,NaN)
                    if (modeData[newMode].elements && modeData[newMode].elements.updateDisplay) {
                        modeData[newMode].elements.updateDisplay();
                    }
                }, 250);
            };
        }

        // 显示界面元素
        showElements();

        // 初始化爱心互动效果
        initHeartInteraction();

        // 暴露 modeData 供全局 API 使用（标记定位 + 卡片弹出）
        window._lgMapModeData = modeData;

        // 返回 map 实例供全局 API 使用
        return map;

    } catch (error) {
        console.error('初始化地图时发生错误:', error);
        return null;
    }
};


// 添加情侣标记和连线
const addLoversMarkers = (map, lovers) => {
    if (lovers.length < 2) return;

    const lover1 = lovers[0];
    const lover2 = lovers[1];
    const lover1Coords = extractCoordinatePair(lover1.coords);
    const lover2Coords = extractCoordinatePair(lover2.coords);
    const lover1Located = lover1.located !== false && !!lover1Coords;
    const lover2Located = lover2.located !== false && !!lover2Coords;
    const bothLocated = lover1Located && lover2Located;
    const loveStartDate = window.LGMAP_CONFIG.loveStartDate;

    // 检测是否在同一位置
    const isSameLocation =
        bothLocated &&
        Math.abs(lover1Coords[0] - lover2Coords[0]) < 0.0001 &&
        Math.abs(lover1Coords[1] - lover2Coords[1]) < 0.0001;

    // 计算距离
    const distance = bothLocated ? (isSameLocation ? 0 : calculateDistance(lover1Coords, lover2Coords)) : null;

    // 更新顶部情侣面板信息
    const loversPanel = document.getElementById('lovers-panel');
    if (loversPanel) {
        document.getElementById('lover-left-avatar').src = lover1.avatar;
        document.getElementById('lover-left-name').textContent = lover1.name;
        document.getElementById('lover-left-location').textContent = lover1.label;

        document.getElementById('lover-right-avatar').src = lover2.avatar;
        document.getElementById('lover-right-name').textContent = lover2.name;
        document.getElementById('lover-right-location').textContent = lover2.label;

        // 点击左侧头像/卡片，定位到"我"的位置
        const leftCard = document.getElementById('lover-left');
        if (leftCard) {
            leftCard.style.cursor = 'pointer';
            leftCard.onclick = () => {
                if (!lover1Coords) return;
                if (window._loversMapState && window._loversMapState.getCurrentMode() !== 'lovers') {
                    window._loversMapState.switchToMode('lovers');
                }
                setMapZoomAndCenterSafely(map, 18, lover1Coords, false, 500);
            };
        }

        // 点击右侧头像/卡片，定位到"TA"的位置
        const rightCard = document.getElementById('lover-right');
        if (rightCard) {
            rightCard.style.cursor = 'pointer';
            rightCard.onclick = () => {
                if (!lover2Coords) return;
                if (window._loversMapState && window._loversMapState.getCurrentMode() !== 'lovers') {
                    window._loversMapState.switchToMode('lovers');
                }
                setMapZoomAndCenterSafely(map, 18, lover2Coords, false, 500);
            };
        }

        // 更新距离模式
        updateLoversPanelMode(distance);

        // 异步获取天气信息（使用 JS API 插件）
        // 获取左侧天气
        if (lover1Located) {
            getWeatherByLocation(lover1Coords[0], lover1Coords[1]).then(weather => {
                if (weather) {
                    const leftIcon = document.getElementById('lover-left-weather-icon');
                    const leftLocation = document.getElementById('lover-left-location');
                    if (leftIcon) leftIcon.className = weather.icon;
                    if (leftLocation) {
                        leftLocation.textContent = weather.weather
                            ? `${weather.city} · ${weather.weather}`
                            : weather.city;
                    }
                }
            });
        }

        // 获取右侧天气
        if (lover2Located) {
            getWeatherByLocation(lover2Coords[0], lover2Coords[1]).then(weather => {
                if (weather) {
                    const rightIcon = document.getElementById('lover-right-weather-icon');
                    const rightLocation = document.getElementById('lover-right-location');
                    if (rightIcon) rightIcon.className = weather.icon;
                    if (rightLocation) {
                        rightLocation.textContent = weather.weather
                            ? `${weather.city} · ${weather.weather}`
                            : weather.city;
                    }
                }
            });
        }

        setTimeout(() => {
            loversPanel.classList.add('show');
        }, 500);
    }

    // 创建情侣信息窗口
    const loverInfoWindow = new AMap.InfoWindow({
        isCustom: true,
        autoMove: false,
        offset: new AMap.Pixel(0, -55)
    });

    const openLoverCard = (marker, currentLover, otherLover, coords) => {
        if (!setMapZoomAndCenterSafely(map, 18, coords, false, 300)) {
            return;
        }

        setTimeout(() => {
            loverInfoWindow.setContent(createLoverInfoWindow(currentLover, otherLover, loveStartDate));
            loverInfoWindow.open(map, marker.getPosition());
        }, 700);
    };

    const createPositionedLoverMarker = (currentLover, otherLover, coords) => {
        const marker = new AMap.Marker({
            position: safeLngLat(coords[0], coords[1]),
            content: createLoverMarker(currentLover),
            anchor: 'center'
        });

        marker.on('click', () => {
            openLoverCard(marker, currentLover, otherLover, coords);
        });

        return marker;
    };

    // 点击地图关闭信息窗口
    map.on('click', () => {
        loverInfoWindow.close();
    });

    // 缩放地图时自动关闭信息窗口
    map.on('zoomstart', () => {
        loverInfoWindow.close();
    });

    if (!bothLocated) {
        mapDebugWarn('🗺️ [LGMap Debug] 情侣模式存在无效坐标，已回退到单点展示，避免轨迹计算出现 NaN。', {
            lover1: lover1.coords,
            lover2: lover2.coords
        });

        const fallbackMarkers = [];
        if (lover1Coords) {
            fallbackMarkers.push(createPositionedLoverMarker(lover1, lover2, lover1Coords));
        }
        if (lover2Coords) {
            fallbackMarkers.push(createPositionedLoverMarker(lover2, lover1, lover2Coords));
        }

        if (fallbackMarkers.length === 0) {
            return {
                markers: [],
                polylines: [],
                infoWindow: loverInfoWindow
            };
        }

        map.add(fallbackMarkers);

        if (fallbackMarkers.length > 1) {
            map.setFitView(fallbackMarkers, false, [100, 100, 100, 100]);
        } else {
            setMapZoomAndCenterSafely(map, 15, lover1Coords || lover2Coords);
        }

        return {
            markers: fallbackMarkers,
            polylines: [],
            infoWindow: loverInfoWindow
        };
    }

    // 同一位置时，创建合并的双人标记
    if (isSameLocation) {
        const isSoloMode = !!(window.LGMAP_CONFIG && window.LGMAP_CONFIG.soloMode);
        const togetherEl = document.createElement('div');
        togetherEl.className = 'lovers-together-marker' + (isSoloMode ? ' is-solo' : '');
        togetherEl.innerHTML = `
            <div class="together-avatars">
                <div class="together-avatar left">
                    <img src="${lover1.avatar}" alt="${lover1.name}" draggable="false">
                </div>
                <div class="together-avatar right">
                    <img src="${lover2.avatar}" alt="${lover2.name}" draggable="false">
                </div>
                ${isSoloMode
                    ? `<div class="together-center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg></div>`
                    : `<div class="together-heart"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></div>`
                }
            </div>
            <div class="together-label">${isSoloMode ? 'AROUND YOU' : 'WITH YOU'}</div>
        `;

        const togetherMarker = new AMap.Marker({
            position: safeLngLat(lover1Coords[0], lover1Coords[1]),
            content: togetherEl,
            anchor: 'center'
        });

        togetherMarker.on('click', () => {
            setMapZoomAndCenterSafely(map, 18, lover1Coords, false, 300);
        });

        map.add(togetherMarker);
        setMapZoomAndCenterSafely(map, 15, lover1Coords);

        return {
            markers: [togetherMarker],
            polylines: [],
            infoWindow: loverInfoWindow
        };
    }

    // 不同位置时，创建两个独立标记
    const marker1 = createPositionedLoverMarker(lover1, lover2, lover1Coords);
    const marker2 = createPositionedLoverMarker(lover2, lover1, lover2Coords);

    map.add([marker1, marker2]);

    // 创建轨迹 - 使用精确坐标，并获取返回的元素
    const trailElements = createLoveTrail(map, lover1Coords, lover2Coords);

    // 调整地图视野
    map.setFitView([marker1, marker2], false, [100, 100, 100, 100]);

    // 返回所有元素引用 - 包含所有轨迹元素
    return {
        markers: [marker1, marker2, trailElements.mainHeart, ...trailElements.heartMarkers],
        polylines: [trailElements.thinLine, trailElements.glowLine],
        infoWindow: loverInfoWindow,
        trail: trailElements
    };
};

// 创建浪漫爱心轨迹 - 散落光点版
const createLoveTrail = (map, coords1, coords2) => {
    const isSoloMode = !!(window.LGMAP_CONFIG && window.LGMAP_CONFIG.soloMode);
    const trailColor = isSoloMode ? '#7aa2ff' : '#ff4081';
    // 计算贝塞尔曲线控制点
    const midLng = (coords1[0] + coords2[0]) / 2;
    const midLat = (coords1[1] + coords2[1]) / 2;
    const dx = coords2[0] - coords1[0];
    const dy = coords2[1] - coords1[1];
    const dist = Math.sqrt(dx * dx + dy * dy);
    const offset = dist * 0.12;
    const ctrlLng = midLng - dy * offset / dist;
    const ctrlLat = midLat + dx * offset / dist;

    // 生成曲线路径点（用于底层轨道）
    const curvePoints = [];
    const segments = 60;
    for (let i = 0; i <= segments; i++) {
        const t = i / segments;
        const t1 = 1 - t;
        const lng = t1 * t1 * coords1[0] + 2 * t1 * t * ctrlLng + t * t * coords2[0];
        const lat = t1 * t1 * coords1[1] + 2 * t1 * t * ctrlLat + t * t * coords2[1];
        curvePoints.push(safeLngLat(lng, lat));
    }

    // 底层细线条轨迹（浅色）
    const thinLine = new AMap.Polyline({
        path: curvePoints,
        strokeColor: trailColor,
        strokeWeight: isSoloMode ? 3 : 2,
        strokeOpacity: isSoloMode ? 0.22 : 0.15,
        lineJoin: 'round',
        lineCap: 'round'
    });

    map.add(thinLine);

    // 流光线条（跟随爱心的进度条效果）
    const glowLine = new AMap.Polyline({
        path: [curvePoints[0]],
        strokeColor: trailColor,
        strokeWeight: isSoloMode ? 3 : 2,
        strokeOpacity: isSoloMode ? 0.95 : 0.8,
        lineJoin: 'round',
        lineCap: 'round'
    });

    map.add(glowLine);

    // 根据实际公里数计算爱心节点池
    const toRad = (deg) => deg * Math.PI / 180;
    const haversineKm = (c1, c2) => {
        const R = 6371;
        const dLat = toRad(c2[1] - c1[1]);
        const dLng = toRad(c2[0] - c1[0]);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(c1[1])) * Math.cos(toRad(c2[1])) * Math.sin(dLng / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };
    const distKm = haversineKm(coords1, coords2);
    // 池子大小：每15km一个节点，最少5个最多35个
    const poolSize = Math.min(35, Math.max(5, Math.round(distKm / 15)));

    const heartSvg = isSoloMode
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>`
        : `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>`;

    // 弧长参数化：密集采样计算累积弧长（墨卡托投影修正，经度方向乘cos(lat)）
    const arcSamples = 200;
    const arcLengths = [0];
    const midLatRad = ((coords1[1] + coords2[1]) / 2) * Math.PI / 180;
    const cosLat = Math.cos(midLatRad); // 经度方向的投影缩放因子
    let prevPt = { lng: coords1[0], lat: coords1[1] };
    for (let i = 1; i <= arcSamples; i++) {
        const t = i / arcSamples;
        const t1 = 1 - t;
        const pt = {
            lng: t1 * t1 * coords1[0] + 2 * t1 * t * ctrlLng + t * t * coords2[0],
            lat: t1 * t1 * coords1[1] + 2 * t1 * t * ctrlLat + t * t * coords2[1]
        };
        const dLng = (pt.lng - prevPt.lng) * cosLat; // 修正经度距离
        const dLat = pt.lat - prevPt.lat;
        arcLengths.push(arcLengths[i - 1] + Math.sqrt(dLng * dLng + dLat * dLat));
        prevPt = pt;
    }
    const totalArc = arcLengths[arcLengths.length - 1];

    // 根据目标弧长比例反查参数t
    const arcToT = (targetArc) => {
        for (let i = 1; i < arcLengths.length; i++) {
            if (arcLengths[i] >= targetArc) {
                const segFrac = (targetArc - arcLengths[i - 1]) / (arcLengths[i] - arcLengths[i - 1]);
                return (i - 1 + segFrac) / arcSamples;
            }
        }
        return 1;
    };

    // 获取弧长位置对应的经纬度
    const getArcPoint = (fraction) => {
        const t = arcToT(fraction * totalArc);
        const t1 = 1 - t;
        return {
            lng: t1 * t1 * coords1[0] + 2 * t1 * t * ctrlLng + t * t * coords2[0],
            lat: t1 * t1 * coords1[1] + 2 * t1 * t * ctrlLat + t * t * coords2[1],
            t
        };
    };

    // 创建最大数量的标记对象（池子），按需显示和重定位
    const maxHearts = poolSize - 1;
    const heartMarkers = [];
    for (let i = 0; i < maxHearts; i++) {
        const heartEl = document.createElement('div');
        heartEl.className = 'trail-dot' + (isSoloMode ? ' trail-dot--solo' : '');
        heartEl.innerHTML = heartSvg;

        const marker = new AMap.Marker({
            position: safeLngLat(coords1[0], coords1[1]),
            content: heartEl,
            anchor: 'center',
            offset: new AMap.Pixel(0, 0)
        });
        marker.hide();
        map.add(marker);
        heartMarkers.push({ marker, element: heartEl, t: 0, passed: false, visible: false });
    }

    // 根据缩放等级计算目标数量，重新定位到等弧长位置
    const updateHeartLayout = () => {
        const zoom = map.getZoom();
        // zoom 5→~3个, zoom 8→~6个, zoom 12→~15个, zoom 16→全部
        const ratio = Math.min(1, Math.max(0.1, (zoom - 3) / 14));
        const targetCount = Math.max(2, Math.min(maxHearts, Math.round(maxHearts * ratio)));

        heartMarkers.forEach((h, idx) => {
            if (idx < targetCount) {
                // 等弧长分布：第i个节点在弧长的 (i+1)/(targetCount+1) 处
                const fraction = (idx + 1) / (targetCount + 1);
                const pt = getArcPoint(fraction);
                h.marker.setPosition(safeLngLat(pt.lng, pt.lat));
                h.t = pt.t;
                h.passed = false;
                h.element.classList.remove('active');
                if (!h.visible) { h.marker.show(); h.visible = true; }
            } else {
                if (h.visible) { h.marker.hide(); h.visible = false; }
            }
        });
    };
    updateHeartLayout();

    // 创建主流动爱心
    const heartEl = document.createElement('div');
    heartEl.className = 'trail-heart-main' + (isSoloMode ? ' trail-heart-main--solo' : '');
    heartEl.innerHTML = isSoloMode
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>`
        : `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>`;

    const mainHeart = new AMap.Marker({
        position: safeLngLat(coords1[0], coords1[1]),
        content: heartEl,
        anchor: 'center',
        offset: new AMap.Pixel(0, 0)
    });

    map.add(mainHeart);

    // 计算贝塞尔曲线上的点
    const getPointOnCurve = (t) => {
        const t1 = 1 - t;
        return {
            lng: t1 * t1 * coords1[0] + 2 * t1 * t * ctrlLng + t * t * coords2[0],
            lat: t1 * t1 * coords1[1] + 2 * t1 * t * ctrlLat + t * t * coords2[1]
        };
    };

    // 沿曲线平滑移动
    let progress = 0;
    let direction = 1;
    let lastDirection = 1;
    let _animationStopped = false;

    const moveAlongCurve = () => {
        if (_animationStopped) return;

        // 地图容器不可见时暂停动画帧，等 start() 重新启动
        const overlay = document.getElementById('lgMapOverlay');
        if (!overlay || overlay.style.display === 'none' || overlay.classList.contains('lg-map-overlay-closing')) {
            _animationStopped = true;
            return;
        }

        try {
            // 根据地图缩放级别动态调整速度（每放大一级，屏幕距离翻倍，速度减半）
            const zoom = map.getZoom();
            const baseZoom = 7;
            const baseSpeed = isSoloMode ? 0.006 : 0.003;
            const speed = Math.max(0.0003, baseSpeed * Math.pow(0.5, Math.max(0, zoom - baseZoom)));

            progress += speed * direction;

            if (progress >= 1) {
                progress = 1;
                direction = -1;
            } else if (progress <= 0) {
                progress = 0;
                direction = 1;
            }

            // 方向改变时重置所有小爱心状态
            if (direction !== lastDirection) {
                heartMarkers.forEach(h => {
                    h.passed = false;
                    h.element.classList.remove('active');
                });
                lastDirection = direction;
            }

            // 缓动
            const ease = progress < 0.5
                ? 2 * progress * progress
                : 1 - Math.pow(-2 * progress + 2, 2) / 2;

            const currentPoint = getPointOnCurve(ease);
            mainHeart.setPosition(safeLngLat(currentPoint.lng, currentPoint.lat));

            // 更新流光线条路径
            const glowPath = [];
            const glowSegments = Math.floor(ease * segments);

            if (direction > 0) {
                for (let i = 0; i <= glowSegments; i++) {
                    const t = i / segments;
                    const point = getPointOnCurve(t);
                    glowPath.push(safeLngLat(point.lng, point.lat));
                }
                glowPath.push(safeLngLat(currentPoint.lng, currentPoint.lat));
            } else {
                glowPath.push(safeLngLat(currentPoint.lng, currentPoint.lat));
                for (let i = glowSegments + 1; i <= segments; i++) {
                    const t = i / segments;
                    const point = getPointOnCurve(t);
                    glowPath.push(safeLngLat(point.lng, point.lat));
                }
            }
            glowLine.setPath(glowPath);

            // 检测主爱心是否经过小爱心，经过后点亮
            heartMarkers.forEach((h) => {
                if (h.passed) return;
                const isPassed = direction > 0
                    ? ease > h.t + 0.02
                    : ease < h.t - 0.02;
                if (isPassed) {
                    h.passed = true;
                    h.element.classList.add('active');
                }
            });
        } catch (e) {
            // SDK 异常（如容器尺寸为 0）时停止动画，等 start() 重新启动
            _animationStopped = true;
            return;
        }

        if (!_animationStopped) {
            requestAnimationFrame(moveAlongCurve);
        }
    };

    requestAnimationFrame(moveAlongCurve);

    return {
        thinLine,
        glowLine,
        heartMarkers: heartMarkers.map(h => h.marker),
        mainHeart,
        stop() { _animationStopped = true; },
        start() {
            if (_animationStopped) {
                // 重置所有节点状态，避免上次点亮的残留
                progress = 0;
                direction = 1;
                lastDirection = 1;
                heartMarkers.forEach(h => {
                    h.passed = false;
                    h.element.classList.remove('active');
                });
                _animationStopped = false;
                requestAnimationFrame(moveAlongCurve);
            }
        },
        updateForZoom() { updateHeartLayout(); }
    };
};

// 兼容旧函数
const createFlowingHeart = (map, coords1, coords2) => { };
const createFlowingHeartWithDistance = (map, coords1, coords2, text) => { };

// 添加留言标记（带简单聚合）
const addFootprintMarkers = (map, markersData) => {
    if (!Array.isArray(markersData) || markersData.length === 0) {
        mapDebugWarn('标记数据为空或格式不正确');
        return { markers: [], infoWindow: null, updateDisplay: null };
    }

    const infoWindow = new AMap.InfoWindow({
        isCustom: true,
        autoMove: false,
        offset: new AMap.Pixel(0, -45)
    });

    let currentMarker = null;

    // 点击地图空白处关闭信息窗口
    map.on('click', () => {
        infoWindow.close();
        currentMarker = null;
    });

    // 缩放地图时自动关闭信息窗口（编程式缩放时跳过，避免干扰标记定位流程）
    map.on('zoomstart', () => {
        if (isZoomingToShow) return;
        infoWindow.close();
        currentMarker = null;
    });

    const openInfoWindow = (position, content) => {
        infoWindow.setContent(content);
        infoWindow.open(map, position);
        // 触发全局懒加载，让气泡内的 lazy img 生效
        setTimeout(() => {
            if (window.lazyLoadInstance) window.lazyLoadInstance.update();
        }, 50);
    };

    const markersInfo = [];
    markersData.forEach(item => {
        let longitude, latitude, markerData;

        if (item.spec) {
            longitude = parseFloat(item.spec.longitude);
            latitude = parseFloat(item.spec.latitude);
            markerData = item.spec;
        } else if (item.coords && Array.isArray(item.coords)) {
            longitude = parseFloat(item.coords[0]);
            latitude = parseFloat(item.coords[1]);
            markerData = {
                id: item.id || '',
                parentId: item.parentId || null,
                name: item.name || '未知',
                image: item.avatar || '',
                avatar: item.avatar || '',
                address: item.city || '',
                city: item.city || '',
                description: item.text || '',
                text: item.text || '',
                textHtml: item.textHtml || '',
                createTime: item.time || '',
                time: item.time || '',
                os: item.os || '',
                browser: item.browser || ''
            };
        } else {
            return;
        }
        if (isNaN(longitude) || isNaN(latitude)) return;
        markersInfo.push({ longitude, latitude, markerData });
    });

    const allMarkers = [];
    let currentClusters = [];
    let lastZoom = map.getZoom();

    // 创建所有单个标记
    markersInfo.forEach((info, index) => {
        const position = safeLngLat(info.longitude, info.latitude);
        const marker = new AMap.Marker({
            position: position,
            content: createMarker(info.markerData),
            anchor: 'bottom-center',
            offset: new AMap.Pixel(0, 0)
        });

        marker._bindData = info.markerData;
        marker._bindPosition = position;
        marker._index = index;
        marker._info = info;

        marker.on('click', () => {
            // 如果点击同一个标记，关闭卡片
            if (currentMarker === marker) {
                infoWindow.close();
                currentMarker = null;
                return;
            }

            // 关闭之前的卡片
            if (currentMarker) infoWindow.close();
            currentMarker = marker;

            const currentZoom = map.getZoom();
            const targetZoom = 20;

            // 如果已经在目标倍数附近，直接显示卡片
            if (currentZoom >= targetZoom - 1) {
                map.setCenter([info.longitude, info.latitude]);
                const content = createInfoWindow(marker._bindData);
                openInfoWindow(marker._bindPosition, content);
            } else {
                // 需要放大，设置标志阻止 updateDisplay
                isZoomingToShow = true;
                map.setZoomAndCenter(targetZoom, [info.longitude, info.latitude], false, 300);
                setTimeout(() => {
                    isZoomingToShow = false;
                    const content = createInfoWindow(marker._bindData);
                    openInfoWindow(marker._bindPosition, content);
                }, 700);
            }
        });

        allMarkers.push(marker);
    });

    map.add(allMarkers);
    allMarkers.forEach(m => m.hide());

    // 聚合算法
    const computeClusters = (gridSize) => {
        const clusters = [];
        const used = new Set();

        markersInfo.forEach((point, i) => {
            if (used.has(i)) return;

            const cluster = {
                indices: [i],
                center: { lng: point.longitude, lat: point.latitude },
                bounds: {
                    minLng: point.longitude, maxLng: point.longitude,
                    minLat: point.latitude, maxLat: point.latitude
                }
            };
            used.add(i);

            markersInfo.forEach((other, j) => {
                if (used.has(j)) return;
                if (Math.abs(point.longitude - other.longitude) < gridSize &&
                    Math.abs(point.latitude - other.latitude) < gridSize) {
                    cluster.indices.push(j);
                    used.add(j);
                    cluster.bounds.minLng = Math.min(cluster.bounds.minLng, other.longitude);
                    cluster.bounds.maxLng = Math.max(cluster.bounds.maxLng, other.longitude);
                    cluster.bounds.minLat = Math.min(cluster.bounds.minLat, other.latitude);
                    cluster.bounds.maxLat = Math.max(cluster.bounds.maxLat, other.latitude);
                }
            });

            if (cluster.indices.length > 1) {
                let sumLng = 0, sumLat = 0;
                cluster.indices.forEach(idx => {
                    sumLng += markersInfo[idx].longitude;
                    sumLat += markersInfo[idx].latitude;
                });
                cluster.center.lng = sumLng / cluster.indices.length;
                cluster.center.lat = sumLat / cluster.indices.length;
            }

            clusters.push(cluster);
        });

        return clusters;
    };

    // 创建聚合标记DOM - 胶囊药丸风格
    const createClusterEl = (count, animClass = '') => {
        const div = document.createElement('div');
        div.className = `cluster-marker ${animClass}`;
        // 根据数量调整宽度
        const width = count > 99 ? 44 : count > 9 ? 38 : 32;
        div.innerHTML = `
            <div class="cluster-content" style="width:${width}px;height:32px;">
                <span class="cluster-count">${count}</span>
            </div>
        `;
        return div;
    };

    // 更新显示状态
    let isUpdating = false;
    let isZoomingToShow = false; // 标志：正在放大以显示卡片

    // 设置放大显示标志的方法（供外部调用）
    const setZoomingToShow = (value) => {
        isZoomingToShow = value;
    };

    const updateDisplay = () => {
        if (isUpdating || isZoomingToShow) return;
        isUpdating = true;

        const zoom = map.getZoom();
        const isZoomingIn = zoom > lastZoom;
        lastZoom = zoom;

        // 保存旧的聚合标记引用，用于动画后移除
        const oldClusters = [...currentClusters];
        currentClusters = [];

        // 移除旧的聚合标记（带动画）
        if (oldClusters.length > 0) {
            oldClusters.forEach(c => {
                const el = c.marker.getContent();
                if (el && isZoomingIn) {
                    el.classList.add('cluster-explode');
                }
            });
            // 延迟移除旧标记
            setTimeout(() => {
                oldClusters.forEach(c => {
                    try {
                        map.remove(c.marker);
                    } catch (e) { }
                });
            }, isZoomingIn ? 280 : 0);
        }

        // 先隐藏所有单个标记
        allMarkers.forEach(m => {
            const el = m.getContent();
            if (el) el.classList.remove('marker-pop-in', 'marker-fly-out');
            m.hide();
        });

        if (zoom >= 10) {
            // 检测同坐标的标记
            const sameLocationGroups = new Map();
            markersInfo.forEach((info, idx) => {
                const key = `${info.longitude.toFixed(4)}_${info.latitude.toFixed(4)}`;
                if (!sameLocationGroups.has(key)) {
                    sameLocationGroups.set(key, []);
                }
                sameLocationGroups.get(key).push(idx);
            });

            // 显示标记，同坐标的只显示一个聚合标记
            const shownLocations = new Set();
            markersInfo.forEach((info, i) => {
                const key = `${info.longitude.toFixed(4)}_${info.latitude.toFixed(4)}`;
                const group = sameLocationGroups.get(key);

                if (group.length > 1) {
                    // 同坐标多个标记，只显示一个聚合标记
                    if (!shownLocations.has(key)) {
                        shownLocations.add(key);
                        const clusterMarker = new AMap.Marker({
                            position: safeLngLat(info.longitude, info.latitude),
                            content: createClusterEl(group.length, 'cluster-collect'),
                            anchor: 'center'
                        });

                        // 保存选择器状态
                        clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };

                        clusterMarker.on('click', () => {
                            const pos = clusterMarker.getPosition();
                            const itemsData = group.map(idx => allMarkers[idx]._bindData);
                            const currentZoom = map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData,
                                    'message',
                                    (item) => createInfoWindow(item),
                                    infoWindow,
                                    clusterMarker._pickerState
                                );
                                infoWindow.setContent(pickerCard);
                                infoWindow.open(map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(map, pos)) return;
                                showPicker();
                            } else {
                                isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(map, targetZoom, pos, false, 300)) {
                                    isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        });

                        map.add(clusterMarker);
                        currentClusters.push({ marker: clusterMarker, cluster: { indices: group } });
                    }
                } else {
                    // 单个标记正常显示
                    const m = allMarkers[i];
                    const el = m.getContent();
                    if (el && isZoomingIn) {
                        el.classList.add('marker-pop-in');
                        el.style.animationDelay = `${Math.min(i * 15, 300)}ms`;
                    }
                    m.show();
                }
            });
        } else {
            // 计算聚合
            const gridSize = zoom < 5 ? 10 : zoom < 7 ? 5 : 2;
            const clusters = computeClusters(gridSize);

            clusters.forEach((cluster, i) => {
                if (cluster.indices.length === 1) {
                    const marker = allMarkers[cluster.indices[0]];
                    marker.show();
                } else {
                    const clusterMarker = new AMap.Marker({
                        position: safeLngLat(cluster.center.lng, cluster.center.lat),
                        content: createClusterEl(cluster.indices.length, 'cluster-collect'),
                        anchor: 'center'
                    });

                    clusterMarker.on('click', () => {
                        // 检查是否所有点都在同一位置（或非常接近）
                        const threshold = 0.0001; // 约10米
                        const firstPoint = markersInfo[cluster.indices[0]];
                        const allSameLocation = cluster.indices.every(idx => {
                            const p = markersInfo[idx];
                            return Math.abs(p.longitude - firstPoint.longitude) < threshold &&
                                Math.abs(p.latitude - firstPoint.latitude) < threshold;
                        });

                        if (allSameLocation && cluster.indices.length > 1) {
                            // 同坐标：先放大居中，再显示卡片选择器
                            if (!clusterMarker._pickerState) {
                                clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };
                            }
                            const pos = clusterMarker.getPosition();
                            const itemsData = cluster.indices.map(idx => allMarkers[idx]._bindData);
                            const currentZoom = map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData,
                                    'message',
                                    (item) => createInfoWindow(item),
                                    infoWindow,
                                    clusterMarker._pickerState
                                );
                                infoWindow.setContent(pickerCard);
                                infoWindow.open(map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(map, pos)) return;
                                showPicker();
                            } else {
                                isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(map, targetZoom, pos, false, 300)) {
                                    isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        } else {
                            // 不同坐标：放大查看
                            const bounds = new AMap.Bounds(
                                [cluster.bounds.minLng - 0.005, cluster.bounds.minLat - 0.005],
                                [cluster.bounds.maxLng + 0.005, cluster.bounds.maxLat + 0.005]
                            );
                            map.setBounds(bounds, false, [60, 60, 60, 60]);
                        }
                    });

                    map.add(clusterMarker);
                    currentClusters.push({ marker: clusterMarker, cluster });
                }
            });
        }

        isUpdating = false;
    };

    // 清除所有聚合标记
    const clearClusters = () => {
        currentClusters.forEach(c => {
            try { map.remove(c.marker); } catch (e) { }
        });
        currentClusters = [];
    };

    mapDebugLog('标记创建成功:', markersInfo.length);

    // 返回元素引用
    return {
        markers: allMarkers,
        getClusters: () => currentClusters,
        clearClusters: clearClusters,
        infoWindow: infoWindow,
        updateDisplay: updateDisplay,
        setZoomingToShow: setZoomingToShow
    };
};

// 性能优化：将地图功能初始化封装为单独的函数
const initializeMapFeatures = (map, layers) => {
    // 使用防抖优化事件处理
    const debounce = (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    // 优化比例尺更新
    const updateScaleText = debounce(() => {
        requestAnimationFrame(() => {
            const originalScaleText = document.querySelector('.amap-scale-text');
            if (originalScaleText) {
                document.querySelector('.map-controls .amap-scale-text').textContent = originalScaleText.textContent;
                const originalScale = document.querySelector('.amap-scale');
                if (originalScale) {
                    originalScale.style.display = 'none';
                }
            }
        });
    }, 100);

    // 添加事件监听
    map.on('zoom', updateScaleText);
    map.on('moveend', updateScaleText);

    // 优化图层控制
    const layerState = {
        baseLayer: 'normal',
        overlays: {
            road: false,
            traffic: false
        }
    };


    // 初始化图层状态
    updateLayers(layerState, layers);
};


// ========================================
// 模式切换相关函数
// ========================================

// 隐藏指定模式的内容
const hideMode = (mode, modeData, map) => {
    const data = modeData[mode];
    if (!data) return;

    // 相册模式：收起展开的照片
    if (mode === 'albums' && typeof AlbumPhotosManager !== 'undefined' && AlbumPhotosManager.isExpanded()) {
        AlbumPhotosManager.forceCollapse();
    }

    // 隐藏元素
    if (data.elements) {
        // 隐藏所有标记
        if (data.elements.markers) {
            data.elements.markers.forEach(m => {
                if (m && m.hide) m.hide();
            });
        }

        // 清除聚合标记
        if (data.elements.clearClusters) {
            data.elements.clearClusters();
        }

        // 隐藏轨迹线
        if (data.elements.polylines) {
            data.elements.polylines.forEach(p => {
                if (p && p.hide) p.hide();
            });
        }

        // 关闭信息窗口
        if (data.elements.infoWindow) {
            data.elements.infoWindow.close();
        }
    }

    // 隐藏面板
    if (mode === 'lovers') {
        const loversPanel = document.getElementById('lovers-panel');
        if (loversPanel) loversPanel.classList.remove('show');
        // 隐藏情侣模式丰富内容
        toggleLoversModePanels(false);
    }
};

// 显示指定模式的内容
const showMode = (mode, modeData, map) => {
    const data = modeData[mode];
    if (!data) return;

    // 显示元素并触发聚合更新
    if (data.elements) {
        // 先触发聚合更新（这会显示正确的标记或聚合）
        if (data.elements.updateDisplay) {
            data.elements.updateDisplay();
        } else {
            // 如果没有聚合更新函数，直接显示所有标记
            if (data.elements.markers) {
                data.elements.markers.forEach(m => {
                    if (m && m.show) m.show();
                });
            }
        }

        // 显示轨迹线
        if (data.elements.polylines) {
            data.elements.polylines.forEach(p => {
                if (p && p.show) p.show();
            });
        }
    }

    // 显示面板
    if (mode === 'lovers') {
        const loversPanel = document.getElementById('lovers-panel');
        if (loversPanel) loversPanel.classList.add('show');
        // 显示情侣模式丰富内容
        toggleLoversModePanels(true);
    }

    if (mode === 'events') {
        // events模式不再需要显示清单面板
    }
};

// 更新面板显示
const updatePanels = (mode) => {
    const loversPanel = document.getElementById('lovers-panel');

    // lovers-panel 在所有模式下都显示
    if (loversPanel) loversPanel.classList.add('show');

    // 隐藏其他面板
    toggleLoversModePanels(false);

    // 显示对应模式的面板
    if (mode === 'lovers') {
        toggleLoversModePanels(true);
    }
};

// 初始化爱心互动效果
const initHeartInteraction = () => {
    const heartIcon = document.querySelector('.heart-icon');
    if (!heartIcon) return;

    heartIcon.onclick = (e) => {
        // 添加点击波纹效果
        heartIcon.classList.remove('clicked');
        void heartIcon.offsetWidth; // 触发重绘
        heartIcon.classList.add('clicked');

        // 创建飘散的小爱心
        for (let i = 0; i < 6; i++) {
            const particle = document.createElement('div');
            particle.className = 'heart-particle';
            particle.innerHTML = `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>`;

            // 随机位置和旋转
            const angle = (i / 6) * 360;
            const distance = 20 + Math.random() * 20;
            const x = Math.cos(angle * Math.PI / 180) * distance;
            const rotate = -30 + Math.random() * 60;

            particle.style.cssText = `
                left: 50%;
                top: 50%;
                margin-left: ${x}px;
                --rotate: ${rotate}deg;
                animation-delay: ${i * 0.05}s;
            `;

            heartIcon.appendChild(particle);

            // 动画结束后移除
            setTimeout(() => particle.remove(), 1000);
        }
    };
};

// 创建相册标记
const createAlbumMarker = (album) => {
    const markerContent = document.createElement('div');
    markerContent.className = 'album-marker';
    markerContent.innerHTML = `
        <div class="album-marker-content">
            <img src="${album.thumb || album.image}" alt="${album.name}" draggable="false">
        </div>
    `;
    return markerContent;
};

// 创建相册信息窗口 - 堆叠照片风格
const createAlbumInfoWindow = (album) => {
    const locationShort = album.city ? (album.city.split('·')[1] || album.city).trim() : '';
    const detailUrl = album.code ? `album-detail.php?code=${album.code}` : '';
    const coverImg = album.thumb || album.image;
    const geoCount = album.photoCount || 0;

    const cardContent = `
        <div class="album-stack-card">
            <div class="alb-stack">
                <div class="alb-cover" style="background-image: url('${coverImg}')"></div>
            </div>
            <div class="alb-info">
                <div class="alb-header">
                    <span class="alb-title">${album.name}</span>
                    ${album.count ? `<span class="alb-count">${album.count} P</span>` : ''}
                </div>
                <div class="alb-meta">
                    ${album.date ? `<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg><i class="alb-text">${album.date}</i></span>` : ''}
                    ${locationShort ? `<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg><i class="alb-text">${locationShort}</i></span>` : ''}
                </div>
                ${geoCount > 0 ? `<div class="alb-actions">
                    <button class="alb-expand-btn" data-album-code="${album.code}" data-album-name="${album.name}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        定位 · ${geoCount}
                    </button>
                </div>` : ''}
            </div>
        </div>
    `;

    if (detailUrl) {
        return `<a href="${detailUrl}" class="map-card-link" onclick="return window._mapCardNav('${detailUrl}',event)" style="text-decoration:none;color:inherit;display:block;cursor:pointer;position:relative;z-index:10;">${cardContent}</a>`;
    }
    return cardContent;
};

// 添加相册标记
const addAlbumMarkers = (map, albumsData) => {
    if (!Array.isArray(albumsData) || albumsData.length === 0) {
        return { markers: [], infoWindow: null, updateDisplay: null };
    }

    const infoWindow = new AMap.InfoWindow({
        isCustom: true,
        autoMove: false,
        offset: new AMap.Pixel(0, -30)
    });

    let currentMarker = null;

    // 点击地图空白处关闭信息窗口
    map.on('click', () => {
        infoWindow.close();
        currentMarker = null;
    });

    // 缩放时关闭信息窗口（编程式缩放时跳过）
    map.on('zoomstart', () => {
        if (isZoomingToShow) return;
        infoWindow.close();
        currentMarker = null;
    });

    const albumsInfo = [];
    albumsData.forEach(album => {
        if (!album.coords || !Array.isArray(album.coords)) return;
        albumsInfo.push({
            longitude: album.coords[0],
            latitude: album.coords[1],
            albumData: album
        });
    });

    const allMarkers = [];
    let currentClusters = [];
    let lastZoom = map.getZoom();

    // 提前定义标志变量，供标记点击和 updateDisplay 共享
    let isZoomingToShow = false;

    // 创建所有单个标记
    albumsInfo.forEach((info, index) => {
        const position = safeLngLat(info.longitude, info.latitude);
        const marker = new AMap.Marker({
            position: position,
            content: createAlbumMarker(info.albumData),
            anchor: 'center'
        });

        marker._bindData = info.albumData;
        marker._bindPosition = position;

        marker.on('click', () => {
            if (currentMarker === marker) {
                infoWindow.close();
                currentMarker = null;
                return;
            }

            currentMarker = marker;
            const currentZoom = map.getZoom();
            const targetZoom = 15;

            if (currentZoom >= targetZoom - 1) {
                map.setCenter([info.longitude, info.latitude]);
                infoWindow.setContent(createAlbumInfoWindow(info.albumData));
                infoWindow.open(map, position);
            } else {
                isZoomingToShow = true;
                map.setZoomAndCenter(targetZoom, [info.longitude, info.latitude], false, 300);
                setTimeout(() => {
                    isZoomingToShow = false;
                    infoWindow.setContent(createAlbumInfoWindow(info.albumData));
                    infoWindow.open(map, position);
                }, 700);
            }
        });

        allMarkers.push(marker);
        map.add(marker);
    });

    allMarkers.forEach(m => m.hide());

    // 聚合算法
    const computeClusters = (gridSize) => {
        const clusters = [];
        const used = new Set();

        albumsInfo.forEach((point, i) => {
            if (used.has(i)) return;

            const cluster = {
                indices: [i],
                center: { lng: point.longitude, lat: point.latitude },
                bounds: {
                    minLng: point.longitude, maxLng: point.longitude,
                    minLat: point.latitude, maxLat: point.latitude
                }
            };
            used.add(i);

            albumsInfo.forEach((other, j) => {
                if (used.has(j)) return;
                if (Math.abs(point.longitude - other.longitude) < gridSize &&
                    Math.abs(point.latitude - other.latitude) < gridSize) {
                    cluster.indices.push(j);
                    used.add(j);
                    cluster.bounds.minLng = Math.min(cluster.bounds.minLng, other.longitude);
                    cluster.bounds.maxLng = Math.max(cluster.bounds.maxLng, other.longitude);
                    cluster.bounds.minLat = Math.min(cluster.bounds.minLat, other.latitude);
                    cluster.bounds.maxLat = Math.max(cluster.bounds.maxLat, other.latitude);
                }
            });

            if (cluster.indices.length > 1) {
                let sumLng = 0, sumLat = 0;
                cluster.indices.forEach(idx => {
                    sumLng += albumsInfo[idx].longitude;
                    sumLat += albumsInfo[idx].latitude;
                });
                cluster.center.lng = sumLng / cluster.indices.length;
                cluster.center.lat = sumLat / cluster.indices.length;
            }

            clusters.push(cluster);
        });

        return clusters;
    };

    // 创建相册聚合标记 - 堆叠照片风格
    const createAlbumClusterEl = (count, animClass = '') => {
        const div = document.createElement('div');
        div.className = `album-cluster-marker ${animClass}`;
        const size = count > 20 ? 42 : count > 5 ? 38 : 34;
        div.innerHTML = `
            <div class="album-cluster-content" style="width:${size}px;height:${size}px;">
                <span class="album-cluster-count">${count}</span>
            </div>
        `;
        return div;
    };

    // 更新显示状态
    let isUpdating = false;
    // isZoomingToShow 已在前面定义

    // 设置放大显示标志的方法
    const setZoomingToShow = (value) => {
        isZoomingToShow = value;
    };

    const updateDisplay = () => {
        if (isUpdating || isZoomingToShow) return;
        isUpdating = true;

        const zoom = map.getZoom();
        const isZoomingIn = zoom > lastZoom;
        lastZoom = zoom;

        // 移除旧的聚合标记
        const oldClusters = [...currentClusters];
        currentClusters = [];

        if (oldClusters.length > 0) {
            oldClusters.forEach(c => {
                const el = c.marker.getContent();
                if (el && isZoomingIn) {
                    el.classList.add('cluster-explode');
                }
            });
            setTimeout(() => {
                oldClusters.forEach(c => {
                    try { map.remove(c.marker); } catch (e) { }
                });
            }, isZoomingIn ? 280 : 0);
        }

        // 隐藏所有单个标记
        allMarkers.forEach(m => {
            const el = m.getContent();
            if (el) el.classList.remove('marker-pop-in');
            m.hide();
        });

        if (zoom >= 8) {
            // 检测同坐标的标记
            const sameLocationGroups = new Map();
            albumsInfo.forEach((info, idx) => {
                const key = `${info.longitude.toFixed(4)}_${info.latitude.toFixed(4)}`;
                if (!sameLocationGroups.has(key)) {
                    sameLocationGroups.set(key, []);
                }
                sameLocationGroups.get(key).push(idx);
            });

            // 显示标记，同坐标的只显示一个聚合标记
            const shownLocations = new Set();
            albumsInfo.forEach((info, i) => {
                const key = `${info.longitude.toFixed(4)}_${info.latitude.toFixed(4)}`;
                const group = sameLocationGroups.get(key);

                if (group.length > 1) {
                    // 同坐标多个标记，只显示一个聚合标记
                    if (!shownLocations.has(key)) {
                        shownLocations.add(key);
                        const clusterMarker = new AMap.Marker({
                            position: safeLngLat(info.longitude, info.latitude),
                            content: createAlbumClusterEl(group.length, 'cluster-collect'),
                            anchor: 'center'
                        });

                        // 保存选择器状态
                        clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };

                        clusterMarker.on('click', () => {
                            const pos = clusterMarker.getPosition();
                            const itemsData = group.map(idx => albumsInfo[idx].albumData);
                            const currentZoom = map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData,
                                    'album',
                                    (item) => createAlbumInfoWindow(item),
                                    infoWindow,
                                    clusterMarker._pickerState
                                );
                                infoWindow.setContent(pickerCard);
                                infoWindow.open(map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(map, pos)) return;
                                showPicker();
                            } else {
                                isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(map, targetZoom, pos, false, 300)) {
                                    isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        });

                        map.add(clusterMarker);
                        currentClusters.push({ marker: clusterMarker, cluster: { indices: group } });
                    }
                } else {
                    // 单个标记正常显示
                    const m = allMarkers[i];
                    const el = m.getContent();
                    if (el && isZoomingIn) {
                        el.classList.add('marker-pop-in');
                        el.style.animationDelay = `${Math.min(i * 30, 300)}ms`;
                    }
                    m.show();
                }
            });
        } else {
            // 计算聚合
            const gridSize = zoom < 4 ? 15 : zoom < 6 ? 8 : 4;
            const clusters = computeClusters(gridSize);

            clusters.forEach((cluster) => {
                if (cluster.indices.length === 1) {
                    allMarkers[cluster.indices[0]].show();
                } else {
                    const clusterMarker = new AMap.Marker({
                        position: safeLngLat(cluster.center.lng, cluster.center.lat),
                        content: createAlbumClusterEl(cluster.indices.length, 'cluster-collect'),
                        anchor: 'center'
                    });

                    clusterMarker.on('click', () => {
                        // 检查是否所有点都在同一位置
                        const threshold = 0.0001;
                        const firstPoint = albumsInfo[cluster.indices[0]];
                        const allSameLocation = cluster.indices.every(idx => {
                            const p = albumsInfo[idx];
                            return Math.abs(p.longitude - firstPoint.longitude) < threshold &&
                                Math.abs(p.latitude - firstPoint.latitude) < threshold;
                        });

                        if (allSameLocation && cluster.indices.length > 1) {
                            // 同坐标：先放大居中，再显示卡片选择器
                            if (!clusterMarker._pickerState) {
                                clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };
                            }
                            const pos = clusterMarker.getPosition();
                            const itemsData = cluster.indices.map(idx => albumsInfo[idx].albumData);
                            const currentZoom = map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData,
                                    'album',
                                    (item) => createAlbumInfoWindow(item),
                                    infoWindow,
                                    clusterMarker._pickerState
                                );
                                infoWindow.setContent(pickerCard);
                                infoWindow.open(map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(map, pos)) return;
                                showPicker();
                            } else {
                                isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(map, targetZoom, pos, false, 300)) {
                                    isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        } else {
                            const bounds = new AMap.Bounds(
                                [cluster.bounds.minLng - 0.01, cluster.bounds.minLat - 0.01],
                                [cluster.bounds.maxLng + 0.01, cluster.bounds.maxLat + 0.01]
                            );
                            map.setBounds(bounds, false, [60, 60, 60, 60]);
                        }
                    });

                    map.add(clusterMarker);
                    currentClusters.push({ marker: clusterMarker, cluster });
                }
            });
        }

        isUpdating = false;
    };

    // 清除所有聚合标记
    const clearClusters = () => {
        currentClusters.forEach(c => {
            try { map.remove(c.marker); } catch (e) { }
        });
        currentClusters = [];
    };

    return {
        markers: allMarkers,
        getClusters: () => currentClusters,
        clearClusters: clearClusters,
        infoWindow: infoWindow,
        updateDisplay: updateDisplay,
        setZoomingToShow: setZoomingToShow
    };
};

// ========================================
// 事件模式相关函数
// ========================================

// 创建事件标记
const createEventMarker = (event) => {
    const markerContent = document.createElement('div');
    markerContent.className = 'event-marker';
    const eventImage = event.thumb || event.image || '';
    markerContent.innerHTML = `
        <div class="event-marker-content">
            ${eventImage ? `<img src="${eventImage}" alt="${event.name}" draggable="false">` : ''}
            <div class="event-marker-check">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
        </div>
    `;
    return markerContent;
};

// 创建事件信息窗口 - 胶囊横向布局
const createEventInfoWindow = (event) => {
    const locationShort = event.city ? (event.city.split('·')[1] || event.city).trim() : '';
    const isDone = event.done;
    const detailUrl = event.id ? `lovelist.php#event-${event.id}` : '';
    const displayDate = event.date ? event.date.replace(/\s+\d{2}:\d{2}(:\d{2})?$/, '') : '';

    const cardContent = `
        <div class="event-pill-card ${isDone ? 'done' : ''}">
            <div class="evt-thumb">
                ${event.image ? `<img src="${event.thumb || event.image}" alt="${event.name}" draggable="false">` :
            `<svg viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>`}
            </div>
            <div class="evt-info">
                <span class="evt-title">${event.name}</span>
                <div class="evt-meta-row">
                    <span class="evt-location">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        ${locationShort || '未知位置'}
                    </span>
                    ${displayDate ? `<span class="evt-divider"></span><span class="evt-date"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>${displayDate}</span>` : ''}
                </div>
            </div>
            <div class="evt-status-icon">
                ${isDone ?
            `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>` :
            `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>`
        }
            </div>
        </div>
    `;

    if (detailUrl) {
        return `<a href="${detailUrl}" class="map-card-link" onclick="return window._mapCardNav('${detailUrl}',event)" style="text-decoration:none;color:inherit;display:block;cursor:pointer;position:relative;z-index:10;">${cardContent}</a>`;
    }
    return cardContent;
};

// 添加事件标记（带聚合）
const addEventMarkers = (map, eventsData) => {
    if (!Array.isArray(eventsData) || eventsData.length === 0) {
        return { markers: [], infoWindow: null, updateDisplay: null };
    }

    // 只显示已完成且有坐标的事件
    const completedEvents = eventsData.filter(e => e.done && e.coords && Array.isArray(e.coords));

    if (completedEvents.length === 0) {
        return { markers: [], infoWindow: null, updateDisplay: null };
    }

    const infoWindow = new AMap.InfoWindow({
        isCustom: true,
        autoMove: false,
        offset: new AMap.Pixel(0, -30)
    });

    let currentMarker = null;

    // 点击地图空白处关闭信息窗口
    map.on('click', () => {
        infoWindow.close();
        currentMarker = null;
    });

    // 缩放时关闭信息窗口（编程式缩放时跳过）
    map.on('zoomstart', () => {
        if (isZoomingToShow) return;
        infoWindow.close();
        currentMarker = null;
    });

    const eventsInfo = [];
    completedEvents.forEach(event => {
        eventsInfo.push({
            longitude: event.coords[0],
            latitude: event.coords[1],
            eventData: event
        });
    });

    const allMarkers = [];
    let currentClusters = [];
    let lastZoom = map.getZoom();

    // 提前定义标志变量，供标记点击和 updateDisplay 共享
    let isZoomingToShow = false;

    // 创建所有单个标记
    eventsInfo.forEach((info, index) => {
        const position = safeLngLat(info.longitude, info.latitude);
        const marker = new AMap.Marker({
            position: position,
            content: createEventMarker(info.eventData),
            anchor: 'center'
        });

        marker._bindData = info.eventData;
        marker._bindPosition = position;

        marker.on('click', () => {
            if (currentMarker === marker) {
                infoWindow.close();
                currentMarker = null;
                return;
            }

            currentMarker = marker;
            const currentZoom = map.getZoom();
            const targetZoom = 15;

            if (currentZoom >= targetZoom - 1) {
                map.setCenter([info.longitude, info.latitude]);
                infoWindow.setContent(createEventInfoWindow(info.eventData));
                infoWindow.open(map, position);
            } else {
                isZoomingToShow = true;
                map.setZoomAndCenter(targetZoom, [info.longitude, info.latitude], false, 300);
                setTimeout(() => {
                    isZoomingToShow = false;
                    infoWindow.setContent(createEventInfoWindow(info.eventData));
                    infoWindow.open(map, position);
                }, 700);
            }
        });

        allMarkers.push(marker);
        map.add(marker);
    });

    allMarkers.forEach(m => m.hide());

    // 聚合算法
    const computeClusters = (gridSize) => {
        const clusters = [];
        const used = new Set();

        eventsInfo.forEach((point, i) => {
            if (used.has(i)) return;

            const cluster = {
                indices: [i],
                center: { lng: point.longitude, lat: point.latitude },
                bounds: {
                    minLng: point.longitude, maxLng: point.longitude,
                    minLat: point.latitude, maxLat: point.latitude
                }
            };
            used.add(i);

            eventsInfo.forEach((other, j) => {
                if (used.has(j)) return;
                if (Math.abs(point.longitude - other.longitude) < gridSize &&
                    Math.abs(point.latitude - other.latitude) < gridSize) {
                    cluster.indices.push(j);
                    used.add(j);
                    cluster.bounds.minLng = Math.min(cluster.bounds.minLng, other.longitude);
                    cluster.bounds.maxLng = Math.max(cluster.bounds.maxLng, other.longitude);
                    cluster.bounds.minLat = Math.min(cluster.bounds.minLat, other.latitude);
                    cluster.bounds.maxLat = Math.max(cluster.bounds.maxLat, other.latitude);
                }
            });

            if (cluster.indices.length > 1) {
                let sumLng = 0, sumLat = 0;
                cluster.indices.forEach(idx => {
                    sumLng += eventsInfo[idx].longitude;
                    sumLat += eventsInfo[idx].latitude;
                });
                cluster.center.lng = sumLng / cluster.indices.length;
                cluster.center.lat = sumLat / cluster.indices.length;
            }

            clusters.push(cluster);
        });

        return clusters;
    };

    // 创建事件聚合标记 - 徽章风格
    const createEventClusterEl = (count, animClass = '') => {
        const div = document.createElement('div');
        div.className = `event-cluster-marker ${animClass}`;
        const size = count > 10 ? 40 : count > 5 ? 36 : 32;
        div.innerHTML = `
            <div class="event-cluster-content" style="width:${size}px;height:${size}px;">
                <span class="event-cluster-count">${count}</span>
            </div>
        `;
        return div;
    };

    // 更新显示状态
    let isUpdating = false;
    // isZoomingToShow 已在前面定义

    // 设置放大显示标志的方法
    const setZoomingToShow = (value) => {
        isZoomingToShow = value;
    };

    const updateDisplay = () => {
        if (isUpdating || isZoomingToShow) return;
        isUpdating = true;

        const zoom = map.getZoom();
        const isZoomingIn = zoom > lastZoom;
        lastZoom = zoom;

        // 移除旧的聚合标记
        const oldClusters = [...currentClusters];
        currentClusters = [];

        if (oldClusters.length > 0) {
            oldClusters.forEach(c => {
                const el = c.marker.getContent();
                if (el && isZoomingIn) {
                    el.classList.add('cluster-explode');
                }
            });
            setTimeout(() => {
                oldClusters.forEach(c => {
                    try { map.remove(c.marker); } catch (e) { }
                });
            }, isZoomingIn ? 280 : 0);
        }

        // 隐藏所有单个标记
        allMarkers.forEach(m => {
            const el = m.getContent();
            if (el) el.classList.remove('marker-pop-in');
            m.hide();
        });

        if (zoom >= 8) {
            // 检测同坐标的标记
            const sameLocationGroups = new Map();
            eventsInfo.forEach((info, idx) => {
                const key = `${info.longitude.toFixed(4)}_${info.latitude.toFixed(4)}`;
                if (!sameLocationGroups.has(key)) {
                    sameLocationGroups.set(key, []);
                }
                sameLocationGroups.get(key).push(idx);
            });

            // 显示标记，同坐标的只显示一个聚合标记
            const shownLocations = new Set();
            eventsInfo.forEach((info, i) => {
                const key = `${info.longitude.toFixed(4)}_${info.latitude.toFixed(4)}`;
                const group = sameLocationGroups.get(key);

                if (group.length > 1) {
                    // 同坐标多个标记，只显示一个聚合标记
                    if (!shownLocations.has(key)) {
                        shownLocations.add(key);
                        const clusterMarker = new AMap.Marker({
                            position: safeLngLat(info.longitude, info.latitude),
                            content: createEventClusterEl(group.length, 'cluster-collect'),
                            anchor: 'center'
                        });

                        // 保存选择器状态
                        clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };

                        clusterMarker.on('click', () => {
                            const pos = clusterMarker.getPosition();
                            const itemsData = group.map(idx => eventsInfo[idx].eventData);
                            const currentZoom = map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData,
                                    'event',
                                    (item) => createEventInfoWindow(item),
                                    infoWindow,
                                    clusterMarker._pickerState
                                );
                                infoWindow.setContent(pickerCard);
                                infoWindow.open(map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(map, pos)) return;
                                showPicker();
                            } else {
                                isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(map, targetZoom, pos, false, 300)) {
                                    isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        });

                        map.add(clusterMarker);
                        currentClusters.push({ marker: clusterMarker, cluster: { indices: group } });
                    }
                } else {
                    // 单个标记正常显示
                    const m = allMarkers[i];
                    const el = m.getContent();
                    if (el && isZoomingIn) {
                        el.classList.add('marker-pop-in');
                        el.style.animationDelay = `${Math.min(i * 30, 300)}ms`;
                    }
                    m.show();
                }
            });
        } else {
            // 计算聚合
            const gridSize = zoom < 4 ? 15 : zoom < 6 ? 8 : 4;
            const clusters = computeClusters(gridSize);

            clusters.forEach((cluster) => {
                if (cluster.indices.length === 1) {
                    allMarkers[cluster.indices[0]].show();
                } else {
                    const clusterMarker = new AMap.Marker({
                        position: safeLngLat(cluster.center.lng, cluster.center.lat),
                        content: createEventClusterEl(cluster.indices.length, 'cluster-collect'),
                        anchor: 'center'
                    });

                    clusterMarker.on('click', () => {
                        // 检查是否所有点都在同一位置
                        const threshold = 0.0001;
                        const firstPoint = eventsInfo[cluster.indices[0]];
                        const allSameLocation = cluster.indices.every(idx => {
                            const p = eventsInfo[idx];
                            return Math.abs(p.longitude - firstPoint.longitude) < threshold &&
                                Math.abs(p.latitude - firstPoint.latitude) < threshold;
                        });

                        if (allSameLocation && cluster.indices.length > 1) {
                            // 同坐标：先放大居中，再显示卡片选择器
                            if (!clusterMarker._pickerState) {
                                clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };
                            }
                            const pos = clusterMarker.getPosition();
                            const itemsData = cluster.indices.map(idx => eventsInfo[idx].eventData);
                            const currentZoom = map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData,
                                    'event',
                                    (item) => createEventInfoWindow(item),
                                    infoWindow,
                                    clusterMarker._pickerState
                                );
                                infoWindow.setContent(pickerCard);
                                infoWindow.open(map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(map, pos)) return;
                                showPicker();
                            } else {
                                isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(map, targetZoom, pos, false, 300)) {
                                    isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        } else {
                            const bounds = new AMap.Bounds(
                                [cluster.bounds.minLng - 0.01, cluster.bounds.minLat - 0.01],
                                [cluster.bounds.maxLng + 0.01, cluster.bounds.maxLat + 0.01]
                            );
                            map.setBounds(bounds, false, [60, 60, 60, 60]);
                        }
                    });

                    map.add(clusterMarker);
                    currentClusters.push({ marker: clusterMarker, cluster });
                }
            });
        }

        isUpdating = false;
    };

    // 清除所有聚合标记
    const clearClusters = () => {
        currentClusters.forEach(c => {
            try { map.remove(c.marker); } catch (e) { }
        });
        currentClusters = [];
    };

    return {
        markers: allMarkers,
        getClusters: () => currentClusters,
        clearClusters: clearClusters,
        infoWindow: infoWindow,
        updateDisplay: updateDisplay,
        setZoomingToShow: setZoomingToShow
    };
};

// ========================================
// 情侣模式丰富内容
// ========================================

// 计算在一起的天数
const calculateDaysTogether = (startDate) => {
    const start = new Date(startDate);
    const now = new Date();
    const diff = now - start;
    return Math.floor(diff / (1000 * 60 * 60 * 24));
};

// 计算距离下一个纪念日的天数
const calculateDaysToAnniversary = (anniversaryDate) => {
    const anniversary = new Date(anniversaryDate);
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    anniversary.setHours(0, 0, 0, 0);
    const diff = anniversary - now;
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
};

// 情话列表
const loveQuotes = [
    "遇见你是故事的开始，走到底是余生的欢喜。",
    "想把世界上最好的都给你，却发现世界上最好的就是你。",
    "我想和你一起生活，在某个小镇，共享无尽的黄昏和绵绵不绝的钟声。",
    "我见过春日夏风秋叶冬雪，也踏遍南水北山东麓西岭，可这四季春秋，苍山泱水，都不及你冲我展眉一笑。",
    "希望你的眼睛，只看得到笑容；希望你流下的每一滴泪，都是喜极而泣。",
    "我想要的很简单，时光还在，你还在。",
    "最美的不是下雨天，是曾与你躲过雨的屋檐。",
    "你是我的今天，以及所有的明天。",
    "世界上最幸福的事，就是和你一起慢慢变老。",
    "你笑起来真好看，像春天的花一样。"
];

// 创建纪念日倒计时面板
const createAnniversaryPanel = (anniversary) => {
    if (!anniversary || !anniversary.date) return;

    const daysLeft = calculateDaysToAnniversary(anniversary.date);
    if (daysLeft < 0) return;

    const container = document.createElement('div');
    container.className = 'anniversary-panel';
    container.id = 'anniversary-panel';

    container.innerHTML = `
        <div class="anniversary-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </div>
        <div class="anniversary-content">
            <div class="anniversary-title">${anniversary.title}</div>
            <div class="anniversary-countdown">
                <span class="countdown-number">${daysLeft}</span>
                <span class="countdown-unit">天后</span>
            </div>
        </div>
    `;

    // 添加到 .lg-map 容器或 body
    const lgMapContainer = document.querySelector('.lg-map');
    (lgMapContainer || document.body).appendChild(container);
    return container;
};

// 初始化情侣模式丰富内容
const initLoversModePanels = (config) => {
    if (config.nextAnniversary) {
        createAnniversaryPanel(config.nextAnniversary);
    }
};

// 显示/隐藏情侣模式面板
const toggleLoversModePanels = (show) => {
    const panelIds = ['anniversary-panel'];
    panelIds.forEach(id => {
        const panel = document.getElementById(id);
        if (panel) {
            if (show) {
                panel.classList.add('show');
            } else {
                panel.classList.remove('show');
            }
        }
    });
};


// ========================================
// 点点滴滴模式
// ========================================

// 创建点点滴滴标记 - 圆形图标风格
const createMomentMarker = (moment) => {
    const isEncrypted = moment.encryptionSwitch === 1;
    const markerContent = document.createElement('div');
    markerContent.className = 'moment-marker' + (isEncrypted ? ' encrypted' : '');
    markerContent.innerHTML = `
        <div class="moment-marker-content">
            ${isEncrypted
            ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                   </svg>`
            : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                   </svg>`
        }
        </div>
    `;
    return markerContent;
};

// 创建点点滴滴信息窗口 - 胶囊横向布局
const createMomentInfoWindow = (moment) => {
    const isEncrypted = moment.encryptionSwitch === 1;
    const detailUrl = isEncrypted ? '' : `page.php?id=${moment.id}`;

    // 格式化时间
    const formatMomentTime = (timeStr) => {
        if (!timeStr) return '';
        const date = new Date(timeStr);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}.${month}.${day}`;
    };

    const cardContent = `
        <div class="moment-pill-card${isEncrypted ? ' encrypted' : ''}">
            <div class="mmt-icon">
                ${isEncrypted
            ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                       </svg>`
            : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                       </svg>`
        }
            </div>
            <div class="mmt-info">
                <span class="mmt-title">${isEncrypted ? '已加密内容' : moment.articletitle}</span>
                <div class="mmt-meta-row">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        ${formatMomentTime(moment.articletime)}
                    </span>
                    <span class="mmt-divider"></span>
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        ${moment.ipcity || ''}
                    </span>
                </div>
            </div>
        </div>
    `;

    if (detailUrl) {
        return `<a href="${detailUrl}" class="map-card-link" onclick="return window._mapCardNav('${detailUrl}',event)" style="text-decoration:none;color:inherit;display:block;cursor:pointer;position:relative;z-index:10;">${cardContent}</a>`;
    }
    return cardContent;
};

// 添加点点滴滴标记（带聚合）
const addMomentsMarkers = (map, momentsData) => {
    if (!Array.isArray(momentsData) || momentsData.length === 0) {
        return { markers: [], infoWindow: null, updateDisplay: null };
    }

    const infoWindow = new AMap.InfoWindow({
        isCustom: true,
        autoMove: false,
        offset: new AMap.Pixel(0, -30)
    });

    let currentMarker = null;

    // 点击地图空白处关闭信息窗口
    map.on('click', () => {
        infoWindow.close();
        currentMarker = null;
    });

    // 缩放时关闭信息窗口（编程式缩放时跳过）
    map.on('zoomstart', () => {
        if (isZoomingToShow) return;
        infoWindow.close();
        currentMarker = null;
    });

    const momentsInfo = [];
    momentsData.forEach(moment => {
        if (!moment.coords || !Array.isArray(moment.coords)) return;
        momentsInfo.push({
            longitude: moment.coords[0],
            latitude: moment.coords[1],
            momentData: moment
        });
    });

    const allMarkers = [];
    let currentClusters = [];
    let lastZoom = map.getZoom();
    let isZoomingToShow = false;

    // 创建所有单个标记
    momentsInfo.forEach((info, index) => {
        const position = safeLngLat(info.longitude, info.latitude);
        const marker = new AMap.Marker({
            position: position,
            content: createMomentMarker(info.momentData),
            anchor: 'center'
        });

        marker._bindData = info.momentData;
        marker._bindPosition = position;

        marker.on('click', () => {
            if (currentMarker === marker) {
                infoWindow.close();
                currentMarker = null;
                return;
            }

            currentMarker = marker;
            const currentZoom = map.getZoom();
            const targetZoom = 15;

            if (currentZoom >= targetZoom - 1) {
                map.setCenter([info.longitude, info.latitude]);
                infoWindow.setContent(createMomentInfoWindow(info.momentData));
                infoWindow.open(map, position);
            } else {
                isZoomingToShow = true;
                map.setZoomAndCenter(targetZoom, [info.longitude, info.latitude], false, 300);
                setTimeout(() => {
                    isZoomingToShow = false;
                    infoWindow.setContent(createMomentInfoWindow(info.momentData));
                    infoWindow.open(map, position);
                }, 700);
            }
        });

        allMarkers.push(marker);
        map.add(marker);
    });

    allMarkers.forEach(m => m.hide());

    // 聚合算法
    const computeClusters = (gridSize) => {
        const clusters = [];
        const used = new Set();

        momentsInfo.forEach((point, i) => {
            if (used.has(i)) return;

            const cluster = {
                indices: [i],
                center: { lng: point.longitude, lat: point.latitude },
                bounds: {
                    minLng: point.longitude, maxLng: point.longitude,
                    minLat: point.latitude, maxLat: point.latitude
                }
            };
            used.add(i);

            momentsInfo.forEach((other, j) => {
                if (used.has(j)) return;
                if (Math.abs(point.longitude - other.longitude) < gridSize &&
                    Math.abs(point.latitude - other.latitude) < gridSize) {
                    cluster.indices.push(j);
                    used.add(j);
                    cluster.bounds.minLng = Math.min(cluster.bounds.minLng, other.longitude);
                    cluster.bounds.maxLng = Math.max(cluster.bounds.maxLng, other.longitude);
                    cluster.bounds.minLat = Math.min(cluster.bounds.minLat, other.latitude);
                    cluster.bounds.maxLat = Math.max(cluster.bounds.maxLat, other.latitude);
                }
            });

            if (cluster.indices.length > 1) {
                let sumLng = 0, sumLat = 0;
                cluster.indices.forEach(idx => {
                    sumLng += momentsInfo[idx].longitude;
                    sumLat += momentsInfo[idx].latitude;
                });
                cluster.center.lng = sumLng / cluster.indices.length;
                cluster.center.lat = sumLat / cluster.indices.length;
            }

            clusters.push(cluster);
        });

        return clusters;
    };

    // 创建点点滴滴聚合标记
    const createMomentClusterEl = (count, animClass = '') => {
        const div = document.createElement('div');
        div.className = `moment-cluster-marker ${animClass}`;
        const size = count > 20 ? 42 : count > 5 ? 38 : 34;
        div.innerHTML = `
            <div class="moment-cluster-content" style="width:${size}px;height:${size}px;">
                <span class="moment-cluster-count">${count}</span>
            </div>
        `;
        return div;
    };

    // 更新显示状态
    let isUpdating = false;

    const setZoomingToShow = (value) => {
        isZoomingToShow = value;
    };

    const updateDisplay = () => {
        if (isUpdating || isZoomingToShow) return;
        isUpdating = true;

        const zoom = map.getZoom();
        const isZoomingIn = zoom > lastZoom;
        lastZoom = zoom;

        // 移除旧的聚合标记
        const oldClusters = [...currentClusters];
        currentClusters = [];

        if (oldClusters.length > 0) {
            oldClusters.forEach(c => {
                const el = c.marker.getContent();
                if (el && isZoomingIn) {
                    el.classList.add('cluster-explode');
                }
            });
            setTimeout(() => {
                oldClusters.forEach(c => {
                    try { map.remove(c.marker); } catch (e) { }
                });
            }, isZoomingIn ? 280 : 0);
        }

        // 隐藏所有单个标记
        allMarkers.forEach(m => {
            const el = m.getContent();
            if (el) el.classList.remove('marker-pop-in');
            m.hide();
        });

        if (zoom >= 8) {
            // 检测同坐标的标记
            const sameLocationGroups = new Map();
            momentsInfo.forEach((info, idx) => {
                const key = `${info.longitude.toFixed(4)}_${info.latitude.toFixed(4)}`;
                if (!sameLocationGroups.has(key)) {
                    sameLocationGroups.set(key, []);
                }
                sameLocationGroups.get(key).push(idx);
            });

            // 显示标记，同坐标的只显示一个聚合标记
            const shownLocations = new Set();
            momentsInfo.forEach((info, i) => {
                const key = `${info.longitude.toFixed(4)}_${info.latitude.toFixed(4)}`;
                const group = sameLocationGroups.get(key);

                if (group.length > 1) {
                    if (!shownLocations.has(key)) {
                        shownLocations.add(key);
                        const clusterMarker = new AMap.Marker({
                            position: safeLngLat(info.longitude, info.latitude),
                            content: createMomentClusterEl(group.length, 'cluster-collect'),
                            anchor: 'center'
                        });

                        clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };

                        clusterMarker.on('click', () => {
                            const pos = clusterMarker.getPosition();
                            const itemsData = group.map(idx => momentsInfo[idx].momentData);
                            const currentZoom = map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData,
                                    'moment',
                                    (item) => createMomentInfoWindow(item),
                                    infoWindow,
                                    clusterMarker._pickerState
                                );
                                infoWindow.setContent(pickerCard);
                                infoWindow.open(map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(map, pos)) return;
                                showPicker();
                            } else {
                                isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(map, targetZoom, pos, false, 300)) {
                                    isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        });

                        map.add(clusterMarker);
                        currentClusters.push({ marker: clusterMarker, cluster: { indices: group } });
                    }
                } else {
                    const m = allMarkers[i];
                    const el = m.getContent();
                    if (el && isZoomingIn) {
                        el.classList.add('marker-pop-in');
                        el.style.animationDelay = `${Math.min(i * 30, 300)}ms`;
                    }
                    m.show();
                }
            });
        } else {
            // 计算聚合
            const gridSize = zoom < 4 ? 15 : zoom < 6 ? 8 : 4;
            const clusters = computeClusters(gridSize);

            clusters.forEach((cluster) => {
                if (cluster.indices.length === 1) {
                    allMarkers[cluster.indices[0]].show();
                } else {
                    const clusterMarker = new AMap.Marker({
                        position: safeLngLat(cluster.center.lng, cluster.center.lat),
                        content: createMomentClusterEl(cluster.indices.length, 'cluster-collect'),
                        anchor: 'center'
                    });

                    clusterMarker.on('click', () => {
                        const threshold = 0.0001;
                        const firstPoint = momentsInfo[cluster.indices[0]];
                        const allSameLocation = cluster.indices.every(idx => {
                            const p = momentsInfo[idx];
                            return Math.abs(p.longitude - firstPoint.longitude) < threshold &&
                                Math.abs(p.latitude - firstPoint.latitude) < threshold;
                        });

                        if (allSameLocation && cluster.indices.length > 1) {
                            if (!clusterMarker._pickerState) {
                                clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };
                            }
                            const pos = clusterMarker.getPosition();
                            const itemsData = cluster.indices.map(idx => momentsInfo[idx].momentData);
                            const currentZoom = map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData,
                                    'moment',
                                    (item) => createMomentInfoWindow(item),
                                    infoWindow,
                                    clusterMarker._pickerState
                                );
                                infoWindow.setContent(pickerCard);
                                infoWindow.open(map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(map, pos)) return;
                                showPicker();
                            } else {
                                isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(map, targetZoom, pos, false, 300)) {
                                    isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        } else {
                            const bounds = new AMap.Bounds(
                                [cluster.bounds.minLng - 0.01, cluster.bounds.minLat - 0.01],
                                [cluster.bounds.maxLng + 0.01, cluster.bounds.maxLat + 0.01]
                            );
                            map.setBounds(bounds, false, [60, 60, 60, 60]);
                        }
                    });

                    map.add(clusterMarker);
                    currentClusters.push({ marker: clusterMarker, cluster });
                }
            });
        }

        isUpdating = false;
    };

    // 清除所有聚合标记
    const clearClusters = () => {
        currentClusters.forEach(c => {
            try { map.remove(c.marker); } catch (e) { }
        });
        currentClusters = [];
    };

    return {
        markers: allMarkers,
        getClusters: () => currentClusters,
        clearClusters: clearClusters,
        infoWindow: infoWindow,
        updateDisplay: updateDisplay,
        setZoomingToShow: setZoomingToShow
    };
};


// ========================================
// 相册照片展开系统（二级下钻）
// ========================================

// 创建照片标记 - 小圆形缩略图
const createPhotoMarker = (photo) => {
    const el = document.createElement('div');
    el.className = 'photo-pin-marker';
    const isVideo = photo.isVideo;
    el.innerHTML = `
        <div class="photo-pin-content">
            <img src="${photo.thumb || photo.url}" alt="" draggable="false">
            ${isVideo ? '<div class="photo-pin-video"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg></div>' : ''}
        </div>
    `;
    return el;
};

// 创建照片信息窗口 - 暗黑主题卡片，可点击跳转详情
const createPhotoInfoWindow = (photo, albumName) => {
    const locationShort = photo.location || '';
    const detailUrl = photo.albumCode ? `album-detail.php?code=${photo.albumCode}` : '';
    const cardContent = `
        <div class="photo-detail-card">
            <div class="phd-preview" style="background-image: url('${photo.thumb || photo.url}')">
                ${photo.isVideo ? '<div class="phd-video-badge"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg></div>' : ''}
            </div>
            <div class="phd-info">
                <div class="phd-album-tag">${albumName}</div>
                <div class="phd-meta">
                    ${photo.date ? `<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>${photo.date}</span>` : ''}
                    ${locationShort ? `<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>${locationShort}</span>` : ''}
                </div>
            </div>
        </div>
    `;
    if (detailUrl) {
        return `<a href="${detailUrl}" class="map-card-link" onclick="return window._mapCardNav('${detailUrl}',event)" style="text-decoration:none;color:inherit;display:block;cursor:pointer;">${cardContent}</a>`;
    }
    return cardContent;
};

// 相册照片展开管理器
const AlbumPhotosManager = (() => {
    let _expanded = false;
    let _currentCode = '';
    let _currentAlbumName = '';
    let _photoMarkers = [];
    let _photoClusters = [];
    let _photosInfo = [];
    let _photoInfoWindow = null;
    let _backBtn = null;
    let _map = null;
    let _albumElements = null;
    let _loading = false;
    let _mapClickHandler = null;
    let _zoomStartHandler = null;
    let _zoomHandler = null;
    let _moveHandler = null;
    let _isZoomingToShow = false;
    let _lastZoom = 0;
    let _isUpdating = false;
    let _currentPhotoMarker = null;

    // 获取 API 基础路径
    const getApiBase = () => {
        const config = window.LGMAP_CONFIG || {};
        if (config._apiBase) {
            return config._apiBase;
        }

        const lgConfig = window.LG_CONFIG || {};
        if (lgConfig.endpoints && lgConfig.endpoints.mapApi) {
            return lgConfig.endpoints.mapApi;
        }

        const siteBase = lgConfig.siteBase || '';
        return siteBase ? siteBase + '/assets/map-api.php' : 'assets/map-api.php';
    };

    // 加载照片数据
    const fetchPhotos = async (code) => {
        const url = getApiBase() + '?module=album_photos&code=' + encodeURIComponent(code);
        const resp = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        if (!resp.ok) throw new Error(resp.status);
        const data = await resp.json();
        return data.photos || [];
    };

    // 清理 map click/zoomstart 监听
    const _removeMapClickHandler = () => {
        if (_mapClickHandler && _map) {
            _map.off('click', _mapClickHandler);
        }
        _mapClickHandler = null;
        if (_zoomStartHandler && _map) {
            _map.off('zoomstart', _zoomStartHandler);
        }
        _zoomStartHandler = null;
    };

    // 展开照片
    const expand = async (code, albumName, opts) => {
        const _opts = opts || {};
        if (_loading) return;
        if (_expanded && _currentCode === code) return;
        _loading = true;

        const modeData = window._lgMapModeData;
        if (!modeData || !modeData.albums) { _loading = false; return; }

        _map = (window.LGMap && window.LGMap.getMap) ? window.LGMap.getMap() : null;
        if (!_map && modeData.albums.elements && modeData.albums.elements.markers && modeData.albums.elements.markers.length > 0) {
            _map = modeData.albums.elements.markers[0].getMap();
        }
        if (!_map) { _loading = false; return; }

        _albumElements = modeData.albums.elements;

        // 先收起之前的展开（同步清理）
        if (_expanded) {
            _doCleanup();
        }

        // 关闭当前信息窗口
        if (_albumElements && _albumElements.infoWindow) {
            _albumElements.infoWindow.close();
        }

        try {
            const photos = await fetchPhotos(code);
            if (photos.length === 0) {
                _loading = false;
                return;
            }

            _currentCode = code;
            _currentAlbumName = albumName;
            _expanded = true;

            // 隐藏所有相册标记和聚合
            if (_albumElements) {
                if (_albumElements.markers) {
                    _albumElements.markers.forEach(m => m.hide());
                }
                if (_albumElements.clearClusters) {
                    _albumElements.clearClusters();
                }
            }

            // 找到当前相册标记并高亮显示为锚点
            let anchorMarker = null;
            if (_albumElements && _albumElements.markers) {
                anchorMarker = _albumElements.markers.find(m => {
                    const d = m._bindData;
                    return d && d.code === code;
                });
            }
            if (anchorMarker) {
                anchorMarker.show();
                const el = anchorMarker.getContent();
                if (el) el.classList.add('album-anchor-active');
            }

            // 创建照片信息窗口
            _photoInfoWindow = new AMap.InfoWindow({
                isCustom: true,
                autoMove: false,
                offset: new AMap.Pixel(0, -25)
            });

            _currentPhotoMarker = null;

            // 注册 map click（保存引用以便清理）
            _removeMapClickHandler();
            _mapClickHandler = () => {
                if (_photoInfoWindow) _photoInfoWindow.close();
                _currentPhotoMarker = null;
            };
            _map.on('click', _mapClickHandler);

            // 缩放时关闭卡片（编程式缩放时跳过）
            _zoomStartHandler = () => {
                if (_isZoomingToShow) return;
                if (_photoInfoWindow) _photoInfoWindow.close();
                _currentPhotoMarker = null;
            };
            _map.on('zoomstart', _zoomStartHandler);

            // 创建照片标记（注入 albumCode 用于详情页跳转）
            _photosInfo = [];
            photos.forEach(photo => {
                const lng = parseFloat(photo.coords[0]);
                const lat = parseFloat(photo.coords[1]);
                if (isNaN(lng) || isNaN(lat)) return;
                photo.albumCode = code;
                _photosInfo.push({ longitude: lng, latitude: lat, photoData: photo });
            });

            _lastZoom = _map.getZoom();

            _photosInfo.forEach((info) => {
                const position = safeLngLat(info.longitude, info.latitude);
                const marker = new AMap.Marker({
                    position: position,
                    content: createPhotoMarker(info.photoData),
                    anchor: 'center'
                });

                marker._bindData = info.photoData;
                marker._bindPosition = position;
                marker._info = info;

                marker.on('click', () => {
                    if (_currentPhotoMarker === marker) {
                        _photoInfoWindow.close();
                        _currentPhotoMarker = null;
                        return;
                    }
                    _currentPhotoMarker = marker;
                    const currentZoom = _map.getZoom();
                    const targetZoom = 20;

                    if (currentZoom >= targetZoom - 1) {
                        _map.setCenter([info.longitude, info.latitude]);
                        _photoInfoWindow.setContent(createPhotoInfoWindow(info.photoData, _currentAlbumName));
                        _photoInfoWindow.open(_map, position);
                    } else {
                        _isZoomingToShow = true;
                        _map.setZoomAndCenter(targetZoom, [info.longitude, info.latitude], false, 300);
                        setTimeout(() => {
                            _isZoomingToShow = false;
                            _photoInfoWindow.setContent(createPhotoInfoWindow(info.photoData, _currentAlbumName));
                            _photoInfoWindow.open(_map, position);
                        }, 700);
                    }
                });

                _map.add(marker);
                _photoMarkers.push(marker);
            });

            // 先隐藏所有标记，由 updateDisplay 控制显示
            _photoMarkers.forEach(m => m.hide());

            // 注册 zoomend/moveend 驱动聚合更新
            _removeZoomMoveHandlers();
            let _photoZoomTimer = null;
            const _photoUpdateDelay = 100;
            _zoomHandler = () => {
                if (_photoZoomTimer) clearTimeout(_photoZoomTimer);
                _photoZoomTimer = setTimeout(() => {
                    if (_expanded) _updatePhotoDisplay();
                }, _photoUpdateDelay);
            };
            _moveHandler = () => {
                if (_photoZoomTimer) clearTimeout(_photoZoomTimer);
                _photoZoomTimer = setTimeout(() => {
                    if (_expanded) _updatePhotoDisplay();
                }, _photoUpdateDelay);
            };
            _map.on('zoomend', _zoomHandler);
            _map.on('moveend', _moveHandler);

            // 适配视图到照片标记，或定位到指定坐标
            if (_opts.targetCoords && Array.isArray(_opts.targetCoords)) {
                // 从媒体卡片进入：放大到最大倍数，定位到指定坐标，弹出最近的照片卡片
                const tLng = parseFloat(_opts.targetCoords[0]);
                const tLat = parseFloat(_opts.targetCoords[1]);
                _isZoomingToShow = true;
                _map.setZoomAndCenter(20, [tLng, tLat], false, 300);
                setTimeout(() => {
                    _isZoomingToShow = false;
                    _updatePhotoDisplay();
                    // 找到距离目标坐标最近的照片标记并弹出卡片
                    _triggerNearestPhoto(tLng, tLat);
                }, 700);
            } else if (!_opts.skipFitView && _photoMarkers.length > 0) {
                _isZoomingToShow = true;
                _map.setFitView(_photoMarkers, false, [80, 80, 80, 120]);
                setTimeout(() => {
                    _isZoomingToShow = false;
                    _updatePhotoDisplay();
                }, 800);
            } else {
                _updatePhotoDisplay();
            }

            // 显示返回按钮
            _showBackButton();

        } catch (err) {
            console.error('加载相册照片失败:', err);
        }

        _loading = false;
    };

    // 照片聚合算法（参考 addFootprintMarkers 的 computeClusters）
    const _computePhotoClusters = (gridSize) => {
        const clusters = [];
        const used = new Set();

        _photosInfo.forEach((point, i) => {
            if (used.has(i)) return;
            const cluster = {
                indices: [i],
                center: { lng: point.longitude, lat: point.latitude },
                bounds: {
                    minLng: point.longitude, maxLng: point.longitude,
                    minLat: point.latitude, maxLat: point.latitude
                }
            };
            used.add(i);

            _photosInfo.forEach((other, j) => {
                if (used.has(j)) return;
                if (Math.abs(point.longitude - other.longitude) < gridSize &&
                    Math.abs(point.latitude - other.latitude) < gridSize) {
                    cluster.indices.push(j);
                    used.add(j);
                    cluster.bounds.minLng = Math.min(cluster.bounds.minLng, other.longitude);
                    cluster.bounds.maxLng = Math.max(cluster.bounds.maxLng, other.longitude);
                    cluster.bounds.minLat = Math.min(cluster.bounds.minLat, other.latitude);
                    cluster.bounds.maxLat = Math.max(cluster.bounds.maxLat, other.latitude);
                }
            });

            if (cluster.indices.length > 1) {
                let sumLng = 0, sumLat = 0;
                cluster.indices.forEach(idx => {
                    sumLng += _photosInfo[idx].longitude;
                    sumLat += _photosInfo[idx].latitude;
                });
                cluster.center.lng = sumLng / cluster.indices.length;
                cluster.center.lat = sumLat / cluster.indices.length;
            }
            clusters.push(cluster);
        });
        return clusters;
    };

    // 创建照片聚合标记 DOM
    const _createPhotoClusterEl = (count, animClass) => {
        const div = document.createElement('div');
        div.className = `photo-cluster-marker ${animClass || ''}`;
        const size = count > 10 ? 40 : count > 5 ? 36 : 32;
        div.innerHTML = `
            <div class="photo-cluster-content" style="width:${size}px;height:${size}px;">
                <span class="photo-cluster-count">${count}</span>
            </div>
        `;
        return div;
    };

    // 完整的照片聚合更新（参考 addFootprintMarkers.updateDisplay）
    const _updatePhotoDisplay = () => {
        if (_isUpdating || _isZoomingToShow || !_map) return;
        _isUpdating = true;

        const zoom = _map.getZoom();
        const isZoomingIn = zoom > _lastZoom;
        _lastZoom = zoom;

        // 移除旧聚合标记（带动画）
        const oldClusters = [..._photoClusters];
        _photoClusters = [];

        if (oldClusters.length > 0) {
            oldClusters.forEach(c => {
                const el = c.getContent();
                if (el && isZoomingIn) el.classList.add('cluster-explode');
            });
            setTimeout(() => {
                oldClusters.forEach(c => {
                    try { _map.remove(c); } catch (e) { }
                });
            }, isZoomingIn ? 280 : 0);
        }

        // 先隐藏所有单个标记
        _photoMarkers.forEach(m => {
            const el = m.getContent();
            if (el) el.classList.remove('marker-pop-in', 'marker-fade-out');
            m.hide();
        });

        if (zoom >= 12) {
            // 高缩放级别：检测同坐标，其余直接显示
            const sameLocationGroups = new Map();
            _photosInfo.forEach((info, idx) => {
                const key = info.longitude.toFixed(4) + '_' + info.latitude.toFixed(4);
                if (!sameLocationGroups.has(key)) sameLocationGroups.set(key, []);
                sameLocationGroups.get(key).push(idx);
            });

            const shownLocations = new Set();
            _photosInfo.forEach((info, i) => {
                const key = info.longitude.toFixed(4) + '_' + info.latitude.toFixed(4);
                const group = sameLocationGroups.get(key);

                if (group.length > 1) {
                    if (!shownLocations.has(key)) {
                        shownLocations.add(key);
                        const clusterMarker = new AMap.Marker({
                            position: safeLngLat(info.longitude, info.latitude),
                            content: _createPhotoClusterEl(group.length, 'cluster-collect'),
                            anchor: 'center'
                        });
                        clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };

                        clusterMarker.on('click', () => {
                            const pos = clusterMarker.getPosition();
                            const itemsData = group.map(idx => _photoMarkers[idx]._bindData);
                            const currentZoom = _map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData, 'album',
                                    (item) => createPhotoInfoWindow(item, _currentAlbumName),
                                    _photoInfoWindow, clusterMarker._pickerState
                                );
                                _photoInfoWindow.setContent(pickerCard);
                                _photoInfoWindow.open(_map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(_map, pos)) return;
                                showPicker();
                            } else {
                                _isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(_map, targetZoom, pos, false, 300)) {
                                    _isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    _isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        });

                        _map.add(clusterMarker);
                        _photoClusters.push(clusterMarker);
                    }
                } else {
                    const m = _photoMarkers[i];
                    const el = m.getContent();
                    if (el && isZoomingIn) {
                        el.classList.add('marker-pop-in');
                        el.style.animationDelay = `${Math.min(i * 30, 300)}ms`;
                    }
                    m.show();
                }
            });
        } else {
            // 低缩放级别：基于网格的聚合
            const gridSize = zoom < 6 ? 5 : zoom < 8 ? 2 : zoom < 10 ? 0.5 : 0.05;
            const clusters = _computePhotoClusters(gridSize);

            clusters.forEach((cluster) => {
                if (cluster.indices.length === 1) {
                    const m = _photoMarkers[cluster.indices[0]];
                    const el = m.getContent();
                    if (el && isZoomingIn) {
                        el.classList.add('marker-pop-in');
                    }
                    m.show();
                } else {
                    const clusterMarker = new AMap.Marker({
                        position: safeLngLat(cluster.center.lng, cluster.center.lat),
                        content: _createPhotoClusterEl(cluster.indices.length, 'cluster-collect'),
                        anchor: 'center'
                    });

                    clusterMarker.on('click', () => {
                        const threshold = 0.0001;
                        const firstPoint = _photosInfo[cluster.indices[0]];
                        const allSameLocation = cluster.indices.every(idx => {
                            const p = _photosInfo[idx];
                            return Math.abs(p.longitude - firstPoint.longitude) < threshold &&
                                Math.abs(p.latitude - firstPoint.latitude) < threshold;
                        });

                        if (allSameLocation && cluster.indices.length > 1) {
                            if (!clusterMarker._pickerState) {
                                clusterMarker._pickerState = { currentIndex: 0, viewedSet: new Set() };
                            }
                            const pos = clusterMarker.getPosition();
                            const itemsData = cluster.indices.map(idx => _photoMarkers[idx]._bindData);
                            const currentZoom = _map.getZoom();
                            const targetZoom = 20;

                            const showPicker = () => {
                                const pickerCard = createLocationPickerCard(
                                    itemsData, 'album',
                                    (item) => createPhotoInfoWindow(item, _currentAlbumName),
                                    _photoInfoWindow, clusterMarker._pickerState
                                );
                                _photoInfoWindow.setContent(pickerCard);
                                _photoInfoWindow.open(_map, pos);
                            };

                            if (currentZoom >= targetZoom - 1) {
                                if (!setMapCenterSafely(_map, pos)) return;
                                showPicker();
                            } else {
                                _isZoomingToShow = true;
                                if (!setMapZoomAndCenterSafely(_map, targetZoom, pos, false, 300)) {
                                    _isZoomingToShow = false;
                                    return;
                                }
                                setTimeout(() => {
                                    _isZoomingToShow = false;
                                    showPicker();
                                }, 700);
                            }
                        } else {
                            // 不同坐标：用真实边界 setBounds 一步到位
                            _isZoomingToShow = true;
                            const bounds = new AMap.Bounds(
                                [cluster.bounds.minLng, cluster.bounds.minLat],
                                [cluster.bounds.maxLng, cluster.bounds.maxLat]
                            );
                            _map.setBounds(bounds, false, [80, 80, 80, 80]);
                            // setBounds 完成后 zoomend/moveend 会触发，但被 _isZoomingToShow 阻止
                            // 用一次性 moveend 监听来在动画真正结束后重置并更新
                            const _onBoundsSet = () => {
                                _map.off('moveend', _onBoundsSet);
                                _isZoomingToShow = false;
                                _updatePhotoDisplay();
                            };
                            _map.on('moveend', _onBoundsSet);
                        }
                    });

                    _map.add(clusterMarker);
                    _photoClusters.push(clusterMarker);
                }
            });
        }

        _isUpdating = false;
    };

    // 清理 zoomend/moveend 监听
    const _removeZoomMoveHandlers = () => {
        if (_zoomHandler && _map) _map.off('zoomend', _zoomHandler);
        if (_moveHandler && _map) _map.off('moveend', _moveHandler);
        _zoomHandler = null;
        _moveHandler = null;
    };

    // 同步清理所有展开状态（无动画）
    const _doCleanup = () => {
        _loading = false;
        _isZoomingToShow = false;
        _isUpdating = false;
        _currentPhotoMarker = null;
        _removeMapClickHandler();
        _removeZoomMoveHandlers();

        if (_photoInfoWindow) {
            _photoInfoWindow.close();
            _photoInfoWindow = null;
        }

        _photoMarkers.forEach(m => {
            try { _map.remove(m); } catch (e) { }
        });
        _photoMarkers = [];

        _photoClusters.forEach(c => {
            try { _map.remove(c); } catch (e) { }
        });
        _photoClusters = [];
        _photosInfo = [];

        if (_albumElements && _albumElements.markers) {
            _albumElements.markers.forEach(m => {
                const el = m.getContent();
                if (el) el.classList.remove('album-anchor-active');
            });
        }

        _hideBackButton();
        _expanded = false;
        _currentCode = '';
        _currentAlbumName = '';
    };

    // 收起照片（带淡出动画）
    const collapse = (silent) => {
        if (!_expanded) return;

        // 立即标记为未展开
        _expanded = false;
        _currentCode = '';
        _currentAlbumName = '';
        _loading = false;
        _isZoomingToShow = false;
        _isUpdating = false;
        _currentPhotoMarker = null;

        _removeMapClickHandler();
        _removeZoomMoveHandlers();

        if (_photoInfoWindow) {
            _photoInfoWindow.close();
            _photoInfoWindow = null;
        }

        // 淡出动画
        _photoMarkers.forEach(m => {
            const el = m.getContent();
            if (el) el.classList.add('marker-fade-out');
        });
        _photoClusters.forEach(c => {
            const el = c.getContent();
            if (el) {
                el.style.transition = 'opacity 0.25s ease-out';
                el.style.opacity = '0';
            }
        });

        const markersToRemove = [..._photoMarkers];
        const clustersToRemove = [..._photoClusters];
        _photoMarkers = [];
        _photoClusters = [];
        _photosInfo = [];

        setTimeout(() => {
            markersToRemove.forEach(m => {
                try { _map.remove(m); } catch (e) { }
            });
            clustersToRemove.forEach(c => {
                try { _map.remove(c); } catch (e) { }
            });
        }, 280);

        if (_albumElements && _albumElements.markers) {
            _albumElements.markers.forEach(m => {
                const el = m.getContent();
                if (el) el.classList.remove('album-anchor-active');
            });
        }

        _hideBackButton();

        if (!silent && _albumElements) {
            if (_albumElements.updateDisplay) {
                _albumElements.updateDisplay();
            } else if (_albumElements.markers) {
                _albumElements.markers.forEach(m => m.show());
            }
            if (_albumElements.markers && _albumElements.markers.length > 0) {
                _map.setFitView(_albumElements.markers, false, [100, 100, 100, 100]);
            }
        }
    };

    // 找到距离目标坐标最近的照片标记并弹出卡片
    const _triggerNearestPhoto = (lng, lat) => {
        if (!_photoMarkers.length || !_photoInfoWindow || !_map) return;
        let bestMarker = null;
        let bestDist = Infinity;
        _photoMarkers.forEach(m => {
            const info = m._info;
            if (!info) return;
            const dx = info.longitude - lng;
            const dy = info.latitude - lat;
            const d = dx * dx + dy * dy;
            if (d < bestDist) {
                bestDist = d;
                bestMarker = m;
            }
        });
        if (bestMarker) {
            _currentPhotoMarker = bestMarker;
            _photoInfoWindow.setContent(createPhotoInfoWindow(bestMarker._bindData, _currentAlbumName));
            _photoInfoWindow.open(_map, bestMarker._bindPosition);
        }
    };

    // 显示返回按钮
    const _showBackButton = () => {
        if (_backBtn) return;
        const lgMap = document.querySelector('.lg-map');
        if (!lgMap) return;

        _backBtn = document.createElement('button');
        _backBtn.className = 'album-back-btn';
        _backBtn.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <span>返回相册</span>
        `;
        _backBtn.onclick = () => collapse(false);
        lgMap.appendChild(_backBtn);
        requestAnimationFrame(() => {
            if (_backBtn) _backBtn.classList.add('show');
        });
    };

    // 隐藏返回按钮
    const _hideBackButton = () => {
        if (_backBtn) {
            const btn = _backBtn;
            _backBtn = null;
            btn.classList.remove('show');
            setTimeout(() => {
                if (btn.parentNode) btn.parentNode.removeChild(btn);
            }, 300);
        }
    };

    const isExpanded = () => _expanded;
    const forceCollapse = () => { if (_expanded) _doCleanup(); };

    return { expand, collapse: () => collapse(false), forceCollapse, isExpanded };
})();

// 暴露给 HTML onclick 使用
window._albumPhotosExpand = (code, albumName) => {
    AlbumPhotosManager.expand(code, albumName);
};
