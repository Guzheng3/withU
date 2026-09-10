
        window._AMapSecurityConfig = {"securityJsCode":"d4fe1ef6bb455368bc92d5fb577b2f3b"};
        window.WITHU_MAP_CONFIG = {"amapKey":"7d245650b5ba899ce4f025961613dcc5","modeConfig":{"lovers":{"title":"情侣模式","desc":"无论相隔多远，心始终在一起"},"moments":{"title":"点点滴滴","desc":"记录我们的每一个美好瞬间"},"messages":{"title":"留言模式","desc":"来自世界各地的温暖祝福"},"albums":{"title":"相册模式","desc":"用照片定格我们的回忆"},"events":{"title":"事件清单","desc":"一起完成的每一个小目标"}},"lovers":[],"milestones":[],"events":[],"albums":[],"messages":[],"moments":[],"loveStartDate":"","hsla":"345deg,70%,55%","mapStyle":"amap://styles/grey","soloMode":false,"_apiBase":"/assets/map-api.php"};
        window.WithUMapData = window.WithUMapData || {
            assign: function (data) {
                if (data.lovers) window.WITHU_MAP_CONFIG.lovers = data.lovers;
                if (typeof data.loveStartDate !== 'undefined') window.WITHU_MAP_CONFIG.loveStartDate = data.loveStartDate;
                if (data.milestones) window.WITHU_MAP_CONFIG.milestones = data.milestones;
                if (data.moments) window.WITHU_MAP_CONFIG.moments = data.moments;
                if (data.messages) window.WITHU_MAP_CONFIG.messages = data.messages;
                if (data.albums) window.WITHU_MAP_CONFIG.albums = data.albums;
                if (data.events) window.WITHU_MAP_CONFIG.events = data.events;
                return data;
            },
            fetchAll: function () {
                var apiUrl = new URL(window.WITHU_MAP_CONFIG._apiBase, window.location.origin);
                apiUrl.searchParams.set('module', 'all');
                return fetch(apiUrl.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                    .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                    .then(this.assign.bind(this));
            }
        };

        window.WITHU_MAP_DATA_READY = window.WithUMapData.fetchAll()
            .catch(function (err) {
                if (window.WITHU_CONFIG && window.WITHU_CONFIG.debugMap && window.console && typeof window.console.warn === 'function') {
                    window.console.warn('地图数据加载失败:', err);
                }
            });
    