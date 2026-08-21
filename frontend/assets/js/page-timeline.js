/**
 * withU 时间轴页面模块
 * @version 2.0.0
 * @description timeline.php 页面的 JS 逻辑
 * @requires WaveSurfer.js (动态加载)
 */

; (function (window, $) {
    'use strict';

    // ============================================
    // 配置常量
    // ============================================
    const _assetBase = (window.WITHU_CONFIG && window.WITHU_CONFIG.assetBase) || '';
    const TIMELINE_CONFIG = {
        enableDiffusedLight: true,
        waveSurferUrl: _assetBase + 'Style/js/wavesurfer.min.js',
        apiUrl: 'services/timeline.php'
    };

    const MONTH_CN_MAP = {
        '01': '一月', '02': '二月', '03': '三月', '04': '四月',
        '05': '五月', '06': '六月', '07': '七月', '08': '八月',
        '09': '九月', '10': '十月', '11': '十一月', '12': '十二月'
    };

    const TYPE_MAP = {
        'text': { name: '文字', icon: 'ph-pencil-simple' },
        'image': { name: '照片', icon: 'ph-image' },
        'video': { name: '视频', icon: 'ph-video' },
        'audio': { name: '语音', icon: 'ph-microphone' },
        'ticket': { name: '机票', icon: 'ph-airplane' },
        'list': { name: '清单', icon: 'ph-list-checks' },
        'gift': { name: '礼物', icon: 'ph-gift' },
        'milestone': { name: '里程碑', icon: 'ph-flag' },
        'map': { name: '地点', icon: 'ph-map-pin' }
    };

    // ============================================
    // 工具函数
    // ============================================
    const Utils = {
        /**
         * 格式化时间（秒 -> mm:ss）
         * @param {number} seconds
         * @returns {string}
         */
        formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        },

        /**
         * 计算相对时间
         * @param {string} year
         * @param {string} month
         * @param {string} day
         * @returns {string}
         */
        getTimeAgo(year, month, day, time) {
            const h = time ? parseInt(time.split(':')[0], 10) || 0 : 0;
            const m = time ? parseInt(time.split(':')[1], 10) || 0 : 0;
            const eventDate = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), h, m);
            const today = new Date();
            const diffMs = today - eventDate;

            if (diffMs < 0) {
                const futureDays = Math.ceil(Math.abs(diffMs) / (1000 * 60 * 60 * 24));
                if (futureDays <= 1) return '今天稍后';
                if (futureDays <= 2) return '明天';
                if (futureDays <= 7) return `${futureDays}天后`;
                return `${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            }

            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffMinutes = Math.floor(diffMs / (1000 * 60));

            if (diffMinutes < 1) return '刚刚';
            if (diffMinutes < 60) return `${diffMinutes}分钟前`;
            if (diffHours < 24) return `${diffHours}小时前`;
            if (diffDays === 1) return '昨天';
            if (diffDays < 7) return `${diffDays}天前`;
            if (diffDays < 30) return `${Math.floor(diffDays / 7)}周前`;
            if (diffDays < 365) return `${Math.floor(diffDays / 30)}个月前`;
            return `${Math.floor(diffDays / 365)}年前`;
        },

        /**
         * 生成弥散光样式
         * @returns {string}
         */
        getDiffusedLightStyle() {
            if (!TIMELINE_CONFIG.enableDiffusedLight) return '';

            const colors = [
                'rgba(254, 202, 202, 0.35)',
                'rgba(253, 186, 116, 0.35)',
                'rgba(249, 168, 212, 0.35)',
                'rgba(196, 181, 253, 0.35)',
                'rgba(147, 197, 253, 0.35)',
                'rgba(110, 231, 183, 0.35)'
            ];

            const color1 = colors[Math.floor(Math.random() * colors.length)];
            const color2 = colors[Math.floor(Math.random() * colors.length)];
            const pos1 = `top: ${Math.random() * -10}%; left: ${Math.random() * -10}%; width: 60%; height: 60%;`;
            const pos2 = `bottom: ${Math.random() * -10}%; right: ${Math.random() * -10}%; width: 60%; height: 60%;`;

            return `
                <div class="withu-time-line-diffused-light" style="${pos1} background: radial-gradient(circle, ${color1} 0%, transparent 70%);"></div>
                <div class="withu-time-line-diffused-light" style="${pos2} background: radial-gradient(circle, ${color2} 0%, transparent 70%);"></div>
            `;
        }
    };

    // ============================================
    // WaveSurfer 加载器
    // ============================================
    const WaveSurferLoader = {
        _loaded: false,
        _loading: false,
        _callbacks: [],

        /**
         * 加载 WaveSurfer 库
         * @param {Function} callback
         */
        load(callback) {
            if (typeof WaveSurfer !== 'undefined') {
                this._loaded = true;
                if (callback) callback();
                return;
            }

            if (this._loading) {
                if (callback) this._callbacks.push(callback);
                return;
            }

            this._loading = true;
            if (callback) this._callbacks.push(callback);

            const script = document.createElement('script');
            script.src = TIMELINE_CONFIG.waveSurferUrl;
            script.onload = () => {
                this._loaded = true;
                this._loading = false;
                this._callbacks.forEach(cb => cb());
                this._callbacks = [];
            };
            script.onerror = () => {
                console.error('[Timeline] Failed to load WaveSurfer');
                this._loading = false;
            };
            document.head.appendChild(script);
        },

        /**
         * 检查是否已加载
         * @returns {boolean}
         */
        isLoaded() {
            return this._loaded || typeof WaveSurfer !== 'undefined';
        }
    };

    // ============================================
    // 音频播放器管理
    // ============================================
    const AudioManager = {
        _instances: {},
        _counter: 0,

        /**
         * 获取下一个音频 ID
         * @returns {string}
         */
        getNextId() {
            return `audio-${this._counter++}`;
        },

        /**
         * 重置计数器
         */
        resetCounter() {
            this._counter = 0;
        },

        /**
         * 初始化所有音频播放器
         */
        initAll() {
            this.destroyAll();

            if (!WaveSurferLoader.isLoaded()) {
                WaveSurferLoader.load(() => this._initWaveSurfers());
            } else {
                this._initWaveSurfers();
            }
        },

        /**
         * 初始化 WaveSurfer 实例
         */
        _initWaveSurfers() {
            const wrappers = document.querySelectorAll('.withu-timeline-audio-wrapper');

            wrappers.forEach(wrapper => {
                const id = wrapper.id.replace('audio-player-', '');
                const waveContainer = wrapper.querySelector('.withu-timeline-audio-wave');
                const playBtnIcon = wrapper.querySelector('.withu-timeline-audio-play-btn i');
                const durationText = wrapper.querySelector('.withu-timeline-audio-duration');
                const audioUrl = wrapper.dataset.audioUrl;

                if (!waveContainer || !audioUrl) return;

                playBtnIcon.className = 'ph-bold ph-spinner';

                const ws = WaveSurfer.create({
                    container: waveContainer,
                    waveColor: '#cbd5e1',
                    progressColor: '#0f172a',
                    url: audioUrl,
                    height: 24,
                    barWidth: 1.5,
                    barRadius: 1,
                    barGap: 1.5,
                    cursorWidth: 0,
                    normalize: true,
                    interact: true,
                    backend: 'MediaElement'
                });

                this._instances[id] = ws;

                const waveArea = waveContainer.parentElement;

                ws.on('ready', () => {
                    wrapper.classList.remove('withu-timeline-audio-loading');
                    wrapper.classList.add('withu-timeline-audio-loaded');
                    playBtnIcon.className = 'ph-fill ph-play';
                    durationText.innerText = Utils.formatTime(ws.getDuration());
                    // Insert custom cursor line in wave-area (same as admin style)
                    if (waveArea && !waveArea.querySelector('.withu-timeline-audio-cursor')) {
                        const cursor = document.createElement('div');
                        cursor.className = 'withu-timeline-audio-cursor';
                        waveArea.appendChild(cursor);
                    }
                });

                ws.on('timeupdate', (currentTime) => {
                    const cursor = waveArea ? waveArea.querySelector('.withu-timeline-audio-cursor') : null;
                    if (!cursor) return;
                    const dur = ws.getDuration ? ws.getDuration() : 0;
                    if (!dur) return;
                    cursor.style.left = ((currentTime / dur) * 100) + '%';
                });

                ws.on('play', () => { playBtnIcon.className = 'ph-fill ph-pause'; });
                ws.on('pause', () => { playBtnIcon.className = 'ph-fill ph-play'; });
                ws.on('finish', () => {
                    playBtnIcon.className = 'ph-fill ph-play';
                    durationText.innerText = Utils.formatTime(ws.getDuration());
                });
                ws.on('audioprocess', (t) => { durationText.innerText = Utils.formatTime(t); });
                ws.on('interaction', (t) => { durationText.innerText = Utils.formatTime(t); });
            });
        },

        /**
         * 切换播放状态
         * @param {string} id
         */
        toggle(id) {
            const ws = this._instances[id];
            const wrapper = document.getElementById(`audio-player-${id}`);

            if (!ws || !wrapper || wrapper.classList.contains('withu-timeline-audio-loading')) return;

            if (ws.isPlaying()) {
                ws.pause();
            } else {
                // 暂停其他
                Object.keys(this._instances).forEach(otherId => {
                    if (otherId !== id && this._instances[otherId]) {
                        this._instances[otherId].pause();
                    }
                });
                ws.play();
            }
        },

        /**
         * 销毁所有实例
         */
        destroyAll() {
            Object.values(this._instances).forEach(ws => {
                try { ws.destroy(); } catch (e) { /* ignore */ }
            });
            this._instances = {};
        }
    };


    // ============================================
    // 卡片内容生成器
    // ============================================
    const CardGenerator = {
        _authors: {},

        /**
         * 设置作者配置
         * @param {Object} authors
         */
        setAuthors(authors) {
            this._authors = authors || {};
        },

        /**
         * 生成发布者头部
         * @param {Object} item
         * @returns {string}
         */
        getPublisherHeader(item) {
            const author = this._authors[item.author_id] || {};
            const hasGender = author.gender === 'male' || author.gender === 'female';
            const genderIcon = author.gender === 'male' ? 'ph-gender-male' : 'ph-gender-female';
            const genderClass = author.gender === 'male' ? 'male' : 'female';
            const eventType = TYPE_MAP[item.type] || { name: '记录', icon: 'ph-note' };
            const timeAgo = Utils.getTimeAgo(item.year, item.month, item.day, item.time);

            const metaItems = [];
            if (item.time) metaItems.push(`<div class="withu-time-line-meta-item"><i class="ph-fill ph-clock"></i><span>${item.time}</span></div>`);
            if (item.location) {
                const hasValidCoords = item.map_lat && item.map_lng && isFinite(item.map_lat) && isFinite(item.map_lng) && !(item.map_lat === 0 && item.map_lng === 0);
                if (hasValidCoords) {
                    metaItems.push(`<div class="withu-time-line-meta-item withu-tl-meta-location-link" onclick="event.stopPropagation(); if(typeof LGMiniMap!=='undefined') LGMiniMap.openFullscreen(${item.map_lat}, ${item.map_lng})" data-tooltip="${item.location}"><i class="ph-fill ph-map-pin"></i><span>${item.location}</span></div>`);
                } else {
                    metaItems.push(`<div class="withu-time-line-meta-item" data-tooltip="${item.location}"><i class="ph-fill ph-map-pin"></i><span>${item.location}</span></div>`);
                }
            }
            if (item.weather) {
                const wIcon = item.weatherIcon || 'ph-cloud-sun';
                metaItems.push(`<div class="withu-time-line-meta-item"><i class="ph-fill ${wIcon}"></i><span>${item.weather}</span></div>`);
            }
            if (item.moodLabel) {
                const mIconHtml = item.moodIconHtml || '<i class="ph-fill ph-smiley"></i>';
                metaItems.push(`<div class="withu-time-line-meta-item">${mIconHtml}<span>${item.moodLabel}</span></div>`);
            }

            return `
                <div class="withu-time-line-card-header">
                    <div class="withu-time-line-card-header-top">
                        <div class="withu-author${hasGender ? ' show-gender' : ''}">
                            <div class="withu-author__ring">
                                <img src="${author.avatar || ''}" class="withu-author__avatar">
                                ${hasGender ? `<div class="withu-author__badge ${genderClass}"><i class="ph-bold ${genderIcon}"></i></div>` : ''}
                            </div>
                            <div class="withu-author__text">
                                <span class="withu-author__name">${author.name || ''}</span>
                                <div class="withu-timeline-capsule style-contrast">
                                    <div class="withu-timeline-capsule-icon-box"><i class="ph-bold ${eventType.icon}"></i></div>
                                    <span class="withu-timeline-capsule-label">${eventType.name}</span>
                                    <div class="withu-timeline-capsule-divider"></div>
                                    <span class="withu-timeline-capsule-time">${timeAgo}</span>
                                </div>
                            </div>
                        </div>
                        <div class="withu-time-line-date-badge">
                            <span class="withu-time-line-date-day">${item.day}</span>
                            <div class="withu-time-line-date-divider"></div>
                            <span class="withu-time-line-date-month">${MONTH_CN_MAP[item.month] || ''}</span>
                        </div>
                    </div>
                    ${metaItems.length > 0 ? `<div class="withu-time-line-meta">${metaItems.join('')}</div>` : ''}
                </div>
            `;
        },

        /**
         * 生成卡片内容
         * @param {Object} item
         * @returns {string}
         */
        generate(item) {
            const diffusedLights = Utils.getDiffusedLightStyle();
            const author = this._authors[item.author_id] || this._authors[item.author] || {};
            const extraClass = !TIMELINE_CONFIG.enableDiffusedLight ? 'no-diffused-light' : '';
            const header = this.getPublisherHeader(item);

            // 统一提取标题和描述，放在所有卡片内容的顶部
            const isTicket = item.type === 'ticket';
            const titleAndDesc = `
                ${item.title ? `<h3 class="withu-time-line-card-title">${item.title}</h3>` : ''}
                ${item.desc ? `<p class="withu-time-line-card-desc">${item.desc}</p>` : ''}
                ${(item.title || item.desc) && isTicket ? `<div style="height:1px; background:linear-gradient(to right, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%); margin:12px 0 16px;"></div>` : ''}
            `;

            // 关联链接
            let linkHtml = '';
            if (item.linkType && item.linkType !== 'none' && item.linkPath) {
                const linkConf = {
                    article: { icon: 'ph-newspaper-clipping', label: '关联文章', colorClass: 'icon-rose', hoverClass: 'hover-rose' },
                    album: { icon: 'ph-images-square', label: '关联相册', colorClass: 'icon-blue', hoverClass: 'hover-blue' },
                    external: { icon: 'ph-arrow-square-out', label: '外部链接', colorClass: 'icon-slate', hoverClass: 'hover-slate' }
                };
                const lc = linkConf[item.linkType] || linkConf.external;
                const linkTitle = item.linkTitle || lc.label;
                const linkTarget = item.linkType === 'external' ? ' target="_blank" rel="noopener noreferrer"' : '';
                const displayPath = item.linkPath.length > 50 ? item.linkPath.substring(0, 50) + '...' : item.linkPath;
                const arrowIcon = item.linkType === 'external' ? 'ph-arrow-square-out' : 'ph-caret-right';
                linkHtml = `
                <a class="withu-tl-link-card${isTicket ? ' withu-tl-link-card-ticket' : ''}" href="${item.linkPath}"${linkTarget}>
                    <div class="withu-tl-link-card-icon ${lc.colorClass}"><i class="ph-fill ${lc.icon}"></i></div>
                    <div class="withu-tl-link-card-body">
                        <div class="withu-tl-link-card-title ${lc.hoverClass}">${linkTitle}</div>
                        <div class="withu-tl-link-card-path">${displayPath}</div>
                    </div>
                    <i class="ph-bold ${arrowIcon} withu-tl-link-card-${item.linkType === 'external' ? 'external' : 'arrow'}"></i>
                </a>`;
            }

            const likeHtml = '';

            switch (item.type) {
                case 'text':
                    return `
                    <div class="withu-time-line-card ${extraClass}">
                        ${diffusedLights}
                        <div class="withu-time-line-card-inner">
                            ${header}
                            <div class="withu-time-line-card-content">
                                ${titleAndDesc}
                                <div class="withu-time-line-card-footer">
                                    <div class="withu-time-line-quote-icon"><i class="ph-fill ph-quotes"></i></div>
                                    <div class="withu-time-line-signature">${item.signature ? ('— ' + item.signature) : ('By ' + (author.name || ''))}</div>
                                </div>
                                ${linkHtml}
                                ${likeHtml}
                            </div>
                        </div>
                    </div>`;

                case 'image':
                    const imgMeta = item.mediaMeta || {};
                    const imgMetaTags = [
                        imgMeta.format ? `<span class="withu-tl-media-meta-tag"><i class="ph-bold ph-file-image"></i>${imgMeta.format}</span>` : '',
                        imgMeta.resolution ? `<span class="withu-tl-media-meta-tag"><i class="ph-bold ph-frame-corners"></i>${imgMeta.resolution}</span>` : '',
                        imgMeta.fileSize ? `<span class="withu-tl-media-meta-tag"><i class="ph-bold ph-database"></i>${imgMeta.fileSize}</span>` : '',
                    ].filter(Boolean).join('');
                    return `
                    <div class="withu-time-line-card ${extraClass}">
                        ${diffusedLights}
                        <div class="withu-time-line-card-inner">
                            ${header}
                            <div class="withu-time-line-card-content">
                                ${titleAndDesc}
                                <div class="withu-time-line-media-container" style="position:relative; overflow:hidden;">
                                    <img src="${item.mediaUrl}" loading="lazy" class="view-image-media" style="width:100%; height:100%; object-fit:cover; cursor:pointer;">
                                    ${imgMetaTags ? `<div class="withu-tl-media-meta">${imgMetaTags}</div>` : ''}
                                </div>
                                ${linkHtml}
                                ${likeHtml}
                            </div>
                        </div>
                    </div>`;

                case 'video':
                    const videoCoverUrl = item.thumbUrl || '';
                    const vidMeta = item.mediaMeta || {};
                    const vidDuration = vidMeta.duration || item.duration || '';
                    const vidResLabel = vidMeta.resolutionLabel || '';
                    const vidMetaTags = [
                        vidMeta.format ? `<span class="withu-tl-media-meta-tag"><i class="ph-bold ph-film-strip"></i>${vidMeta.format}</span>` : '',
                        vidResLabel ? `<span class="withu-tl-media-meta-tag"><i class="ph-bold ph-monitor"></i>${vidResLabel}</span>` : (vidMeta.resolution ? `<span class="withu-tl-media-meta-tag"><i class="ph-bold ph-frame-corners"></i>${vidMeta.resolution}</span>` : ''),
                        vidMeta.fileSize ? `<span class="withu-tl-media-meta-tag"><i class="ph-bold ph-database"></i>${vidMeta.fileSize}</span>` : '',
                        vidDuration ? `<span class="withu-tl-media-meta-tag"><i class="ph-bold ph-timer"></i>${vidDuration}</span>` : '',
                    ].filter(Boolean).join('');
                    return `
                    <div class="withu-time-line-card ${extraClass}">
                        ${diffusedLights}
                        <div class="withu-time-line-card-inner">
                            ${header}
                            <div class="withu-time-line-card-content">
                                ${titleAndDesc}
                                <div class="withu-time-line-media-container video-player" data-url="${item.mediaUrl}" onclick="if(window.VideoModal){window.VideoModal.open('${item.mediaUrl}', '${videoCoverUrl}');}else{console.warn('VideoModal not loaded');}" style="cursor:pointer; position:relative; overflow:hidden; border-radius:12px; height:256px;">
                                    ${videoCoverUrl ? `<img src="${videoCoverUrl}" loading="lazy" style="width:100%; height:100%; object-fit:cover;">` : `<video src="${item.mediaUrl}" preload="metadata" muted playsinline style="width:100%; height:100%; object-fit:cover; pointer-events:none;"></video>`}
                                    <div class="withu-tl-video-play-icon"><i class="ph-fill ph-play"></i></div>
                                    ${vidMetaTags ? `<div class="withu-tl-media-meta">${vidMetaTags}</div>` : ''}
                                </div>
                                ${linkHtml}
                                ${likeHtml}
                            </div>
                        </div>
                    </div>`;

                case 'audio':
                    const audioId = AudioManager.getNextId();
                    return `
                    <div class="withu-time-line-card ${extraClass}">
                        ${diffusedLights}
                        <div class="withu-time-line-card-inner">
                            ${header}
                            <div class="withu-time-line-card-content">
                                ${titleAndDesc}
                                <div class="withu-timeline-audio-wrapper withu-timeline-audio-loading" id="audio-player-${audioId}" data-audio-url="${item.mediaUrl}">
                                    <div class="withu-timeline-audio-container">
                                        <div class="withu-timeline-audio-play-btn" onclick="window.toggleTimelineAudio('${audioId}')"><i class="ph-fill ph-play"></i></div>
                                        <div class="withu-timeline-audio-wave-area"><div class="withu-timeline-audio-wave" id="audio-wave-${audioId}"></div></div>
                                        <div class="withu-timeline-audio-duration">--:--</div>
                                    </div>
                                </div>
                                ${linkHtml}
                                ${likeHtml}
                            </div>
                        </div>
                    </div>`;

                case 'ticket':
                    return `
                    <div class="withu-time-line-card withu-time-line-ticket-card ${extraClass}">
                        <div class="withu-time-line-ticket-overlay"></div>
                        <div class="withu-time-line-card-inner">
                            ${header}
                            <div class="withu-time-line-ticket-notch withu-time-line-ticket-notch-left"></div>
                            <div class="withu-time-line-ticket-notch withu-time-line-ticket-notch-right"></div>
                            <div class="withu-time-line-ticket-content">
                                ${titleAndDesc}
                                <div class="withu-time-line-ticket-route">
                                    <div><div class="withu-time-line-ticket-city-code">${item.fromCode}</div><div class="withu-time-line-ticket-city-name">${item.from}</div></div>
                                    <div class="withu-time-line-ticket-plane"><i class="ph-fill ph-airplane"></i></div>
                                    <div style="text-align: right;"><div class="withu-time-line-ticket-city-code">${item.toCode}</div><div class="withu-time-line-ticket-city-name">${item.to}</div></div>
                                </div>
                                <div class="withu-time-line-ticket-divider"></div>
                                <div class="withu-time-line-ticket-details">
                                    <div class="withu-time-line-ticket-detail-item"><span class="withu-time-line-ticket-detail-label">Flight</span><span class="withu-time-line-ticket-detail-value">${item.flightNo}</span></div>
                                    <div class="withu-time-line-ticket-detail-item"><span class="withu-time-line-ticket-detail-label">Seat</span><span class="withu-time-line-ticket-detail-value withu-time-line-ticket-seat">${item.seat}</span></div>
                                    <div class="withu-time-line-ticket-qr"><i class="ph-fill ph-qr-code"></i></div>
                                </div>
                                ${linkHtml}
                                ${likeHtml}
                            </div>
                        </div>
                    </div>`;

                case 'list':
                    return `
                    <div class="withu-time-line-card ${extraClass}">
                        ${diffusedLights}
                        <div class="withu-time-line-card-inner">
                            ${header}
                            <div class="withu-time-line-card-content">
                                ${titleAndDesc}
                                <div class="withu-time-line-list-items">
                                    ${(item.items || []).map(todo => `
                                        <div class="withu-time-line-list-item">
                                            <div class="withu-time-line-list-checkbox ${todo.done ? 'checked' : ''}">${todo.done ? '<i class="ph-bold ph-check"></i>' : ''}</div>
                                            <span class="withu-time-line-list-text ${todo.done ? 'checked' : ''}">${todo.text}</span>
                                        </div>
                                    `).join('')}
                                </div>
                                ${linkHtml}
                                ${likeHtml}
                            </div>
                        </div>
                    </div>`;

                case 'gift':
                    const giftHasImage = item.giftImage && item.giftImage.trim();
                    const giftPartner = (() => {
                        const keys = Object.keys(this._authors);
                        for (const k of keys) {
                            if (String(k) !== String(item.author_id) && String(k) !== String(item.author)) return this._authors[k];
                        }
                        return {};
                    })();
                    const giftDir = giftPartner.name ? `赠予 ${giftPartner.name}` : '';

                    return `
                    <div class="withu-time-line-card ${extraClass}">
                        ${diffusedLights}
                        <div class="withu-time-line-card-inner">
                            ${header}
                            <div class="withu-time-line-card-content">
                                ${titleAndDesc}

                                ${giftHasImage ? `
                                <div class="withu-tl-gift-img-wrap">
                                    <div class="withu-tl-gift-img-inner">
                                        <img src="${item.giftThumbUrl || item.giftImage}" referrerpolicy="no-referrer" loading="lazy" class="view-image-media" style="cursor:pointer;">
                                    </div>
                                    <div class="withu-tl-gift-img-footer">
                                        <div class="withu-tl-gift-img-left">
                                            <i class="ph-fill ph-gift"></i>
                                            <span class="withu-tl-gift-img-name">${item.giftName || '神秘礼物'}</span>
                                        </div>
                                        ${item.giftPrice ? `<div class="withu-tl-gift-img-price"><i class="withu-tl-gift-sym"></i>${parseFloat(item.giftPrice).toFixed(2)}</div>` : ''}
                                    </div>
                                </div>
                                ` : `
                                <div class="withu-tl-gift-simple">
                                    <div class="withu-tl-gift-icon-box">
                                        <i class="ph-fill ph-gift"></i>
                                    </div>
                                    <div class="withu-tl-gift-info">
                                        <div class="withu-tl-gift-name">${item.giftName || '一份特别的礼物'}</div>
                                        ${item.giftPrice ? `<div class="withu-tl-gift-price"><i class="withu-tl-gift-sym"></i>${parseFloat(item.giftPrice).toFixed(2)}</div>` : ''}
                                    </div>
                                </div>
                                `}

                                ${giftDir ? `
                                <div class="withu-tl-gift-dir">
                                    <i class="ph-fill ph-heart"></i><span>${giftDir}</span>
                                </div>
                                ` : ''}

                                ${linkHtml}
                            </div>
                        </div>
                    </div>`;

                case 'milestone':
                    const msVal = item.milestoneValue || '';
                    const msUnit = item.milestoneUnit || '';
                    const msCat = (item.milestoneCategory && item.milestoneCategory !== 'default') ? item.milestoneCategory : '';
                    return `
                    <div class="withu-time-line-card ${extraClass}">
                        ${diffusedLights}
                        <div class="withu-time-line-card-inner">
                            ${header}
                            <div class="withu-time-line-card-content">
                                ${titleAndDesc}
                                <div class="withu-tl-ms-panel">
                                    <div class="withu-tl-ms-accent"></div>
                                    <span class="withu-tl-ms-corner-bl"></span>
                                    <span class="withu-tl-ms-corner-br"></span>
                                    ${msVal ? `
                                        <div class="withu-tl-ms-figure">
                                            <span class="withu-tl-ms-value">${msVal}</span>
                                            ${msUnit ? `<span class="withu-tl-ms-unit">${msUnit}</span>` : ''}
                                        </div>` : ''}
                                    ${msCat ? `
                                        <div class="withu-tl-ms-divider"></div>
                                        <div class="withu-tl-ms-category">
                                            <i class="ph-fill ph-flag-pennant"></i><span>${msCat}</span>
                                        </div>` : ''}
                                </div>
                                ${linkHtml}
                            </div>
                        </div>
                    </div>`;

                case 'map':
                    const hasCoords = item.map_lat && item.map_lng;
                    const mapId = 'tl-map-' + (item.id || Math.random().toString(36).substr(2, 6));
                    return `
                    <div class="withu-time-line-card ${extraClass}">
                        ${diffusedLights}
                        <div class="withu-time-line-card-inner">
                            ${header}
                            <div class="withu-time-line-card-content">
                                ${titleAndDesc}
                                ${hasCoords
                            ? `<div class="withu-tl-map-preview" id="${mapId}" data-lat="${item.map_lat}" data-lng="${item.map_lng}" style="margin-top:16px;"></div>`
                            : `<div class="withu-tl-map-empty" style="margin-top:16px;"><i class="ph-fill ph-map-pin"></i></div>`}
                                ${linkHtml}
                                ${likeHtml}
                            </div>
                        </div>
                    </div>`;

                default:
                    return `
                        <div class="withu-time-line-card ${extraClass}">
                            ${diffusedLights}
                            <div class="withu-time-line-card-inner">
                                ${header}
                                <div class="withu-time-line-card-content">
                                    ${titleAndDesc}
                                    ${linkHtml}
                                </div>
                            </div>
                        </div>`;
            }
        }
    };


    // ============================================
    // 筛选标签模块（已禁用）
    // ============================================
    const FilterTabs = {
        _currentFilter: 'all',

        init(onFilterChange) {
            // 筛选功能已禁用，不做任何操作
        },

        getCurrentFilter() {
            return 'all';
        },

        destroy() {
            // 无需清理
        }
    };

    // ============================================
    // 滚动监听模块
    // ============================================
    const ScrollSpy = {
        _handler: null,

        /**
         * 初始化滚动监听
         */
        init() {
            this.destroy();

            const sections = document.querySelectorAll('.withu-time-line-year-section');
            if (sections.length === 0) return;

            this._handler = () => {
                const triggerPoint = window.innerHeight / 2.5;
                let activeSection = null;

                sections.forEach((section, idx) => {
                    const rect = section.getBoundingClientRect();
                    const isLastSection = idx === sections.length - 1;

                    // 正常情况：section 在视口触发点范围内
                    if (rect.top <= triggerPoint && rect.bottom >= triggerPoint) {
                        activeSection = section;
                    }

                    // 特殊处理：最后一个 section，当其内部有节点被激活时也应该激活年份
                    if (isLastSection && !activeSection) {
                        const lastNode = section.querySelector('.withu-time-line-event:last-child .withu-time-line-node.active');
                        if (lastNode) {
                            activeSection = section;
                        }
                    }
                });

                if (activeSection) {
                    sections.forEach(el => el.classList.remove('active'));
                    activeSection.classList.add('active');
                }
            };

            window.addEventListener('scroll', this._handler, { passive: true });
            this._handler();
        },

        /**
         * 销毁
         */
        destroy() {
            if (this._handler) {
                window.removeEventListener('scroll', this._handler);
                this._handler = null;
            }
        }
    };

    // ============================================
    // 追光动画模块
    // ============================================
    const ChasingLight = {
        _cachedGroups: [],
        _cachedNodes: [],
        _resizeObserver: null,
        _scrollHandler: null,
        _resizeHandler: null,
        _intervalId: null,
        _ticking: false,

        /**
         * 初始化追光动画
         * @param {Array} timelineData - 时间轴数据
         */
        init(timelineData) {
            this.destroy();

            // 立即缓存布局并计算初始状态（无动画）
            this._cacheLayout(timelineData);
            // 同步执行一次更新，确保初始状态正确
            this._update();

            // 延迟绑定事件，等待 PJAX 滚动位置稳定
            setTimeout(() => {
                this._bindEvents(timelineData);
            }, 50);
        },

        /**
         * 缓存布局信息
         * @param {Array} timelineData
         */
        _cacheLayout(timelineData) {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            this._cachedGroups = [];
            timelineData.forEach(group => {
                const mobileContainer = document.getElementById(`events-container-${group.year}`);
                const section = document.getElementById(`year-${group.year}`);

                if (!section) return;

                const rect = section.getBoundingClientRect();
                const mobileRect = mobileContainer ? mobileContainer.getBoundingClientRect() : rect;
                const mobileTrackHeight = mobileRect.height - 41;

                this._cachedGroups.push({
                    id: group.year,
                    pcTop: rect.top + scrollTop,
                    pcHeight: rect.height,
                    mobileTop: mobileRect.top + scrollTop + 41,
                    mobileTrackHeight: mobileTrackHeight,
                    pcLine: document.getElementById(`progress-line-pc-${group.year}`),
                    mobileLine: document.getElementById(`progress-line-mobile-${group.year}`)
                });
            });

            const rows = document.querySelectorAll('.withu-time-line-event');
            this._cachedNodes = Array.from(rows).map(row => {
                const nodeId = row.getAttribute('data-node-id');
                const rect = row.getBoundingClientRect();
                const absoluteTop = rect.top + scrollTop + 41;
                const pcNode = document.getElementById(`pc-${nodeId}`);

                return {
                    absoluteTop: absoluteTop,
                    pcNode: pcNode,
                    mobileNode: document.getElementById(`mobile-${nodeId}`),
                    card: document.getElementById(`card-${nodeId}`),
                    connector: row.querySelector('.withu-time-line-connector'),
                    activated: false  // 始终从 false 开始，由滚动逻辑决定激活
                };
            });
        },

        /**
         * 更新动画状态
         */
        _update() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const windowHeight = window.innerHeight;
            const triggerLine = scrollTop + (windowHeight * 0.5);

            // 先更新节点激活状态（一旦激活就保持，不会因为滚动回去而隐藏）
            this._cachedNodes.forEach(node => {
                const isTriggered = triggerLine > (node.absoluteTop - 15);

                if (isTriggered && !node.activated) {
                    node.activated = true;
                    if (node.pcNode) node.pcNode.classList.add('active');
                    if (node.mobileNode) node.mobileNode.classList.add('active');
                    if (node.connector) node.connector.classList.add('active');
                    const memoryCard = node.card ? node.card.querySelector('.withu-time-line-card') : null;
                    if (memoryCard) memoryCard.classList.add('active');
                }
                // 移除了滚动回去时取消激活的逻辑，卡片一旦显示就保持显示
            });

            // 更新进度条
            const totalGroups = this._cachedGroups.length;
            const totalNodes = this._cachedNodes.length;
            // 检查最后一个节点是否已激活
            const lastNodeActivated = totalNodes > 0 && this._cachedNodes[totalNodes - 1].activated;

            this._cachedGroups.forEach((group, groupIdx) => {
                const isLastGroup = groupIdx === totalGroups - 1;

                if (group.pcLine) {
                    let pcProgress = 0;
                    if (triggerLine > group.pcTop) {
                        const distance = triggerLine - group.pcTop;
                        pcProgress = (distance / group.pcHeight) * 100;
                    }
                    // 最后一组：当最后一个节点已激活时直接填满
                    if (isLastGroup && lastNodeActivated) {
                        pcProgress = 100;
                    }
                    pcProgress = Math.max(0, Math.min(100, pcProgress));
                    group.pcLine.style.height = `${pcProgress}%`;
                }

                if (group.mobileLine) {
                    let mobileProgress = 0;
                    if (triggerLine > group.mobileTop) {
                        if (group.mobileTrackHeight > 0) {
                            mobileProgress = ((triggerLine - group.mobileTop) / group.mobileTrackHeight) * 100;
                        }
                    }
                    // 最后一组：当最后一个节点已激活时直接填满
                    if (isLastGroup && lastNodeActivated) {
                        mobileProgress = 100;
                    }
                    mobileProgress = Math.max(0, Math.min(100, mobileProgress));
                    group.mobileLine.style.height = `${mobileProgress}%`;
                }
            });
        },

        /**
         * 绑定事件
         * @param {Array} timelineData
         */
        _bindEvents(timelineData) {
            const self = this;

            // ResizeObserver
            const timelineContainer = document.getElementById('timeline-container');
            if (timelineContainer && typeof ResizeObserver !== 'undefined') {
                this._resizeObserver = new ResizeObserver(() => {
                    self._cacheLayout(timelineData);
                    self._update();
                });
                this._resizeObserver.observe(timelineContainer);
            }

            // 窗口 resize
            this._resizeHandler = () => {
                self._cacheLayout(timelineData);
                self._update();
            };
            window.addEventListener('resize', this._resizeHandler);

            // 滚动事件
            this._scrollHandler = () => {
                if (!self._ticking) {
                    window.requestAnimationFrame(() => {
                        self._update();
                        self._ticking = false;
                    });
                    self._ticking = true;
                }
            };
            window.addEventListener('scroll', this._scrollHandler, { passive: true });

            // 定时刷新（处理动态内容）
            this._intervalId = setInterval(() => {
                self._cacheLayout(timelineData);
                self._update();
            }, 1000);
        },

        /**
         * 销毁
         */
        destroy() {
            if (this._resizeObserver) {
                this._resizeObserver.disconnect();
                this._resizeObserver = null;
            }
            if (this._scrollHandler) {
                window.removeEventListener('scroll', this._scrollHandler);
                this._scrollHandler = null;
            }
            if (this._resizeHandler) {
                window.removeEventListener('resize', this._resizeHandler);
                this._resizeHandler = null;
            }
            if (this._intervalId) {
                clearInterval(this._intervalId);
                this._intervalId = null;
            }
            this._cachedGroups = [];
            this._cachedNodes = [];
        }
    };


    // ============================================
    // 数据服务模块
    // ============================================
    const DataService = {
        /**
         * 从后端获取时间轴数据（预留接口）
         * @returns {Promise<Object>}
         */
        fetchData() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: TIMELINE_CONFIG.apiUrl,
                    type: 'GET',
                    dataType: 'json',
                    success(res) {
                        if (res && res.code === 200) {
                            resolve({
                                authors: window.TIMELINE_AUTHORS || {},
                                data: res.data || []
                            });
                        } else {
                            reject(new Error((res && res.msg) || '获取数据失败'));
                        }
                    },
                    error(err) {
                        reject(err);
                    }
                });
            });
        },

        /**
         * 根据筛选条件过滤数据
         * @param {Array} rawData
         * @param {string} filter
         * @returns {Array}
         */
        filterData(rawData, filter) {
            if (filter === 'all') return rawData;
            return rawData.filter(item => String(item.author_id) === String(filter) || item.author === filter);
        },

        /**
         * 按年份分组数据
         * @param {Array} data
         * @returns {Array}
         */
        groupByYear(data) {
            const grouped = {};
            data.forEach(item => {
                if (!grouped[item.year]) grouped[item.year] = [];
                grouped[item.year].push(item);
            });
            return Object.keys(grouped).sort().reverse().map(year => ({
                year,
                events: grouped[year]
            }));
        }
    };

    // ============================================
    // 时间轴渲染器
    // ============================================
    const TimelineRenderer = {
        /**
         * 渲染时间轴
         * @param {Array} timelineData - 分组后的数据
         * @param {number} totalGroups - 总分组数
         */
        render(timelineData, totalGroups) {
            const container = document.getElementById('timeline-container');
            if (!container) return;

            if (timelineData.length === 0) {
                var hint = document.getElementById('timelineScrollHint');
                if (hint) hint.style.display = 'none';
                var _ab = (window.WITHU_CONFIG && window.WITHU_CONFIG.assetBase) || '';
                var _sb = (window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase) || '';
                container.innerHTML =
                    '<div class="withu-no-data withu-no-data--orange">' +
                    '<div class="withu-no-data-wrap"><div class="withu-no-data-content">' +
                    '<div class="withu-no-data-icon withu-no-data-icon--orange"><svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>' +
                    '<h3 class="withu-no-data-title">\u8fd8\u6ca1\u6709\u8bb0\u5f55\u4e0b\u4efb\u4f55\u8db3\u8ff9</h3>' +
                    '<p class="withu-no-data-desc">\u8fd9\u91cc\u8fd8\u6ca1\u6709\u65f6\u5149\u8f68\u8ff9\uff0c\u8fc7\u6bb5\u65f6\u95f4\u518d\u6765\u770b\u770b\u5427\u3002</p>' +
                    '<div class="withu-no-data-actions"><a class="withu-no-data-btn withu-no-data-btn-primary" href="' + _sb.replace(/\/$/, '') + '/index.php"><i class="ph ph-house"></i> \u8fd4\u56de\u9996\u9875</a></div>' +
                    '</div></div></div>';
                return;
            }

            // 重置音频计数器
            AudioManager.resetCounter();

            let html = '<div class="withu-time-line-wrapper">';
            let eventCounter = 0;

            timelineData.forEach((group, groupIdx) => {
                const isLastGroup = groupIdx === timelineData.length - 1;
                const trackClass = isLastGroup ? 'withu-time-line-track trail-mask' : 'withu-time-line-track';

                html += `
                    <div class="withu-time-line-year-section" id="year-${group.year}">
                        <div class="withu-time-line-year-badge-mobile">
                            <div class="withu-time-line-year-badge-inner">
                                <span class="withu-time-line-year-badge-year">${group.year}</span>
                                <div class="withu-time-line-year-badge-divider"></div>
                                <span class="withu-time-line-year-badge-chapter">Chapter ${totalGroups - groupIdx}</span>
                            </div>
                        </div>
                        <div class="withu-time-line-year-sidebar">
                            <div class="withu-time-line-year-sticky">
                                <div class="withu-time-line-year-number">
                                    ${group.year}
                                    <span class="withu-time-line-year-sparkle"><i class="ph-fill ph-sparkle"></i></span>
                                </div>
                                <p class="withu-time-line-year-chapter">Chapter ${totalGroups - groupIdx}</p>
                            </div>
                        </div>
                        <div class="withu-time-line-track-wrapper">
                            <div class="withu-time-line-track-inner ${trackClass}">
                                <div class="withu-time-line-progress" id="progress-line-pc-${group.year}"></div>
                            </div>
                        </div>
                        <div class="withu-time-line-events" id="events-container-${group.year}">
                            <div class="withu-time-line-track-mobile ${trackClass}">
                                <div class="withu-time-line-progress" id="progress-line-mobile-${group.year}"></div>
                            </div>
                            <div class="withu-time-line-events-inner">
                                ${group.events.map(item => {
                    const cardHTML = CardGenerator.generate(item);
                    const nodeId = `node-${eventCounter++}`;
                    return `
                                        <div class="withu-time-line-event" data-node-id="${nodeId}">
                                            <div class="withu-time-line-node-wrapper-pc">
                                                <div class="withu-time-line-node" id="pc-${nodeId}"></div>
                                                <div class="withu-time-line-connector"></div>
                                            </div>
                                            <div class="withu-time-line-node-wrapper-mobile">
                                                <div class="withu-time-line-node" id="mobile-${nodeId}"></div>
                                            </div>
                                            <div class="withu-time-line-card-wrapper" id="card-${nodeId}">
                                                ${cardHTML}
                                            </div>
                                        </div>`;
                }).join('')}
                            </div>
                        </div>
                    </div>`;
            });

            html += '</div>';
            container.innerHTML = html;

            // 初始化音频播放器
            AudioManager.initAll();

            // 加载点赞状态
            if (typeof LGInteraction !== 'undefined') LGInteraction.reinit();

            // 初始化地图卡片中的迷你地图
            if (typeof LGMiniMap !== 'undefined') {
                container.querySelectorAll('.withu-tl-map-preview').forEach(el => {
                    const lat = parseFloat(el.dataset.lat);
                    const lng = parseFloat(el.dataset.lng);
                    if (isFinite(lat) && isFinite(lng)) {
                        LGMiniMap.render({ el, lat, lng, zoom: 15 });
                    }
                });
            }
        }
    };

    // ============================================
    // 时间轴主模块
    // ============================================
    const TimelineModule = {
        _initialized: false,
        _authors: {},
        _rawData: [],

        /**
         * 初始化模块
         * @param {Object} options - 配置选项
         * @param {Object} options.authors - 作者配置
         * @param {Array} options.rawData - 原始数据
         */
        init(options = {}) {
            if (this._initialized) return;

            // 从参数或全局变量获取配置
            this._authors = options.authors || window.TIMELINE_AUTHORS || {};
            this._rawData = options.rawData || window.TIMELINE_RAW_DATA || [];

            // 设置作者配置
            CardGenerator.setAuthors(this._authors);

            // 初始化筛选标签
            FilterTabs.init((filter) => {
                this._onFilterChange(filter);
            });

            // 渲染时间轴
            this._renderTimeline();

            // 初始化滚动监听
            ScrollSpy.init();

            // 初始化追光动画
            const timelineData = DataService.groupByYear(this._rawData);
            ChasingLight.init(timelineData);

            // 入场动画
            this._playEntranceAnimation();

            this._initialized = true;
        },

        /**
         * 销毁模块
         */
        destroy() {
            FilterTabs.destroy();
            ScrollSpy.destroy();
            ChasingLight.destroy();
            AudioManager.destroyAll();
            this._initialized = false;
        },

        /**
         * 刷新模块（PJAX 切换后调用）
         */
        refresh() {
            this.destroy();
            this.init();
        },

        /**
         * 筛选变化处理
         * @param {string} filter
         */
        _onFilterChange(filter) {
            this._renderTimeline(filter);
            ScrollSpy.init();

            const filteredData = DataService.filterData(this._rawData, filter);
            const timelineData = DataService.groupByYear(filteredData);
            ChasingLight.init(timelineData);

            this._playEntranceAnimation();
        },

        /**
         * 渲染时间轴
         * @param {string} filter
         */
        _renderTimeline(filter = 'all') {
            const filteredData = DataService.filterData(this._rawData, filter);
            const timelineData = DataService.groupByYear(filteredData);
            const totalGroups = DataService.groupByYear(this._rawData).length;

            TimelineRenderer.render(timelineData, totalGroups);
        },

        /**
         * 播放入场动画
         */
        _playEntranceAnimation() {
            setTimeout(() => {
                document.querySelectorAll('.withu-time-line-event').forEach((el, idx) => {
                    el.style.animationDelay = `${idx * 0.1}s`;
                    el.classList.add('fade-in-up');
                });
            }, 50);
        },

        /**
         * 从后端加载数据
         * @returns {Promise}
         */
        loadFromServer() {
            // 销毁旧实例
            this.destroy();

            return DataService.fetchData().then(result => {
                this._authors = result.authors;
                this._rawData = result.data;
                CardGenerator.setAuthors(this._authors);

                // 初始化筛选标签
                FilterTabs.init((filter) => {
                    this._onFilterChange(filter);
                });

                this._renderTimeline();
                ScrollSpy.init();
                const timelineData = DataService.groupByYear(this._rawData);
                ChasingLight.init(timelineData);
                this._playEntranceAnimation();
                this._initialized = true;
            }).catch(err => {
                console.error('[Timeline] Load data error:', err);
                const container = document.getElementById('timeline-container');
                if (container) {
                    container.innerHTML = '<div style="text-align: center; padding: 100px 20px; color: #9ca3af;">暂无数据</div>';
                }
            });
        }
    };

    // ============================================
    // 自动初始化
    // ============================================
    $(function () {
        // 检测是否在时间轴页面
        if ($('#timeline-container').length > 0) {
            TimelineModule.loadFromServer();
        }
    });

    // PJAX 完成后重新初始化
    $(document).on('pjax:end.lgTimeline', function () {
        if ($('#timeline-container').length > 0) {
            TimelineModule.loadFromServer();
        }
    });

    // ============================================
    // 暴露到全局
    // ============================================
    window.toggleTimelineAudio = (id) => AudioManager.toggle(id);
    window.LGTimelineModule = TimelineModule;

    // 注册到 LGApp
    if (window.LGApp) {
        window.LGApp.register('timeline', TimelineModule);
    }

})(window, jQuery);
