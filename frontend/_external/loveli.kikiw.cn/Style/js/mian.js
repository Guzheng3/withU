/*!
 *      _      ____ _   _ _____ _    _ _   _ ___
 *     | |    / ___| \ | | ____| |  | | | | |_ _|
 *     | |   | |  _|  \| |  _| | |  | | | | || |
 *     | |___| |_| | |\  | |___| |__| | |_| || |
 *     |_____|\____|_| \_|_____|____/ \___/|___|
 *
 *      ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 *
 * Project      : LGNewUi
 * Author       : Ki
 * Last Updated : 2026-03-31
 * Description  : A private place to preserve love, memory, and the
 *                years shared between two people.
 *
 * Copyright (c) 2022-2026 by Ki. All Rights Reserved.
 *
 * Type         : Native PHP web application built with PHP, HTML,
 *                CSS, JavaScript, and selected third-party libraries.
 * License      : This project is licensed for personal couple-record
 *                use only.
 * Restriction  : Commercial, corporate, organizational, platform,
 *                or enterprise use is strictly prohibited.
 * Restriction  : Redistribution, modification, repackaging, resale,
 *                or unauthorized public deployment without explicit
 *                written permission is strictly prohibited.
 *
 * Official Authorization Site:
 * https://auth-love.kikiw.cn/
 *
 * Warning      : LGNewUi is an original work. Any unauthorized
 *                copying, scraping, resale, redistribution, or
 *                commercial use is forbidden.
 * Notice       : Development and long-term maintenance require
 *                substantial time and effort. Please respect the
 *                work involved and the applicable authorization terms.
 *
 */
$(document).ready(function () {
    const audio = $('#music')[0];
    const assetBase = (window.LG_CONFIG && window.LG_CONFIG.assetBase) || '';
    const play_svg = assetBase + "/Style/img/pause.svg";
    const pause_svg = assetBase + "/Style/img/play.svg";
    const play_svg2 = assetBase + "/Style/img/pause2.svg";
    const pause_svg2 = assetBase + "/Style/img/play2.svg";
    const _defaultMusicCover = assetBase + "/Style/img/music-cover-default.webp";
    
    let currentUrl = ''; 
    let currentTime = 0;
    let _globalWasPlaying = false;
    var _activeCardBtn = null; // 当前正在播放的卡片按钮元素引用

    // 封面加载失败统一处理：img / 卡片背景 / data-url 全部回退默认封面
    window._mcCoverError = function(img) {
        img.onerror = null;
        // 清理懒加载属性，防止库再次干扰
        img.classList.remove('lazy');
        img.removeAttribute('data-src');
        img.src = _defaultMusicCover;
        // 更新 .music_card 的 data-url
        var mc = img.closest('.music_card');
        if (mc) mc.setAttribute('data-url', _defaultMusicCover);
        // 更新 .mian_music 的背景
        var mm = img.closest('.mian_music');
        if (mm) {
            mm.style.backgroundImage = "url('" + _defaultMusicCover + "')";
            mm.style.backgroundSize = 'cover';
        }
    };

    // HTML 转义，防止 XSS
    function _escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // 获取全局胶囊条 APlayer 实例
    function _getGlobalAPlayer() {
        const meting = document.querySelector('#nav-music meting-js');
        return meting && meting.aplayer ? meting.aplayer : null;
    }

    // 文章音乐播放前暂停全局胶囊条
    function pauseGlobalIfPlaying() {
        const ap = _getGlobalAPlayer();
        if (ap && ap.audio && !ap.audio.paused) {
            _globalWasPlaying = true;
            ap.pause();
        }
    }

    // 文章音乐停止后恢复全局胶囊条
    function resumeGlobalIfNeeded() {
        if (!_globalWasPlaying) return;
        _globalWasPlaying = false;
        const ap = _getGlobalAPlayer();
        if (ap) ap.play();
    }

    // 具名事件处理函数（防止 addEventListener 累积）
    function _fmtTime(sec) {
        if (!isFinite(sec) || sec < 0) return '';
        var t = Math.ceil(sec);
        var m = Math.floor(t / 60);
        var s = t % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }
    function _updateCapsuleTime() {
        var el = document.getElementById('musicInfoTime');
        if (!el) return;
        if (audio.paused || !isFinite(audio.duration) || audio.duration <= 0) {
            el.textContent = '';
            return;
        }
        var remaining = audio.duration - audio.currentTime;
        el.textContent = _fmtTime(remaining);
    }
    function onAudioTimeUpdate() {
        if (!audio.paused) currentTime = audio.currentTime;
        _updateCapsuleTime();
    }
    function onAudioEnded() {
        $(".music_info_btn_play").css('background-image', 'url(' + pause_svg2 + ')');
        _syncCardPlayState(false);
        audio.currentTime = 0;
        currentTime = 0;
        _updateCapsuleTime();
        resumeGlobalIfNeeded();
    }
    function onAudioError() {
        console.error('音频加载失败');
        _syncCardPlayState(false);
        $(".music_info_btn_play").css('background-image', 'url(' + pause_svg2 + ')');
        audio.currentTime = 0;
        currentTime = 0;
    }

    // 检测音频链接是否有效（仅校验非空+格式，不做预加载）
    function checkAudioUrl(url) {
        if (!url || typeof url !== 'string') return Promise.resolve(false);
        var u = url.trim();
        if (u === '') return Promise.resolve(false);
        // 接受 http/https 绝对地址 或 相对路径
        if (/^https?:\/\/.+/i.test(u) || /^\//.test(u) || /^\.\//i.test(u)) {
            return Promise.resolve(true);
        }
        // 其他格式（data:、blob: 等）也视为有效
        if (/^(data:|blob:)/i.test(u)) return Promise.resolve(true);
        return Promise.resolve(false);
    }

    function isTemporaryMusicProxy(url) {
        if (!url) {
            return false;
        }

        return /\/music-api\.php\?type=proxy\b/i.test(String(url));
    }

    function resolvePreferredMusicUrl(storedUrl, data) {
        const normalizedStoredUrl = (storedUrl || '').trim();
        const freshProxyUrl = data && data.proxy_url ? String(data.proxy_url).trim() : '';
        const freshUrl = data && data.url ? String(data.url).trim() : '';

        if (freshProxyUrl) {
            return freshProxyUrl;
        }

        if (freshUrl) {
            return freshUrl;
        }

        if (normalizedStoredUrl && !isTemporaryMusicProxy(normalizedStoredUrl)) {
            return normalizedStoredUrl;
        }

        return '';
    }

    window.getMusic = function () {
        const neteaseAudios = document.querySelectorAll('audio[data-type]');
    
        neteaseAudios.forEach(audioElement => {
            const id = audioElement.getAttribute('data-id');
            const dataType = audioElement.getAttribute('data-type');
            const amUrl = audioElement.getAttribute('data-url');
            const MusicCover = audioElement.getAttribute('data-cover') || '';
            const MusicAuthor = audioElement.getAttribute('data-author') || '';
            const MusicName = audioElement.getAttribute('data-name') || '';
    
            if (id && dataType) {
                fetch(`../../music-api.php?type=song&media=${dataType}&id=${id}`)
                    .then(response => response.ok ? response.json() : Promise.reject(response.status))
                    .then(async data => {
                        const dataurl = resolvePreferredMusicUrl(amUrl, data);
                        
                        // 先插入加载中状态的卡片
                        const _cover = _escHtml(data.cover || MusicCover);
                        const _bgCover = _cover || _defaultMusicCover;
                        const _name = _escHtml(data.name || MusicName);
                        const _author = _escHtml(data.author || MusicAuthor);
                        const _dataurl = _escHtml(dataurl);
                        const _imgTag = _cover
                            ? `<img class="cover lazy" src="${_defaultMusicCover}" data-src="${_cover}" onerror="window._mcCoverError(this)">`
                            : `<img class="cover" src="${_defaultMusicCover}">`;
                        const loadingStyle = `
                            <div class="mian_music" style="background: url('${_bgCover}') no-repeat center; background-size:cover;">
                                <div class="music_card" data-url="${_bgCover}" view-image>
                                    ${_imgTag}
                                    <div class="author">
                                        <span class="music_name">${_name}</span>
                                        <span class="user">${_author}</span>
                                    </div>
                                    <div class="music_play music_play_loading" data-music="${_dataurl}"></div>
                                </div>
                            </div>`;
                        const $musicCard = $(loadingStyle).insertBefore(audioElement);
                        if (window.lazyLoadInstance) window.lazyLoadInstance.update();
                        const $playBtn = $musicCard.find('.music_play');
                        
                        // 检测音频链接是否有效
                        const isValid = await checkAudioUrl(dataurl);
                        
                        // 移除加载状态
                        $playBtn.removeClass('music_play_loading');
                        
                        if (isValid) {
                            // 有效链接：设置正常状态
                            $playBtn.css('background-image', 'url(' + pause_svg + ')');
                            audioElement.src = dataurl;
                        } else {
                            // 无效链接：设置禁用状态
                            var _tip = (!dataurl || dataurl === '') ? '音频链接为空' : '音频地址无效';
                            $playBtn.addClass('music_play_disabled');
                            $playBtn.attr({'data-disabled': 'true', 'data-mc-msg': _tip});
                        }
                        
                        afterAjax();
                    })
                    .catch(e => {
                        console.error('获取音乐信息失败:', e);
                        // 即使获取失败也显示卡片，但禁用播放按钮
                        const _fCover = _escHtml(MusicCover);
                        const _fBgCover = _fCover || _defaultMusicCover;
                        const _fImgTag = _fCover
                            ? `<img class="cover lazy" src="${_defaultMusicCover}" data-src="${_fCover}" onerror="window._mcCoverError(this)">`
                            : `<img class="cover" src="${_defaultMusicCover}">`;
                        const musicStyle = `
                            <div class="mian_music" style="background: url('${_fBgCover}') no-repeat center; background-size:cover;">
                                <div class="music_card" data-url="${_fBgCover}" view-image>
                                    ${_fImgTag}
                                    <div class="author">
                                        <span class="music_name">${_escHtml(MusicName)}</span>
                                        <span class="user">${_escHtml(MusicAuthor)}</span>
                                    </div>
                                    <div class="music_play music_play_disabled" data-disabled="true" data-mc-msg="获取音乐信息失败"></div>
                                </div>
                            </div>`;
                        $(musicStyle).insertBefore(audioElement);
                        if (window.lazyLoadInstance) window.lazyLoadInstance.update();
                        afterAjax();
                    });
            } else {
                const dataurl = amUrl;
                
                // 先插入加载中状态的卡片
                const _eCover = _escHtml(MusicCover);
                const _eBgCover = _eCover || _defaultMusicCover;
                const _eImgTag = _eCover
                    ? `<img class="cover lazy" src="${_defaultMusicCover}" data-src="${_eCover}" onerror="window._mcCoverError(this)">`
                    : `<img class="cover" src="${_defaultMusicCover}">`;
                const loadingStyle = `
                    <div class="mian_music" style="background: url('${_eBgCover}') no-repeat center; background-size:cover;">
                        <div class="music_card" data-url="${_eBgCover}" view-image>
                            ${_eImgTag}
                            <div class="author">
                                <span class="music_name">${_escHtml(MusicName)}</span>
                                <span class="user">${_escHtml(MusicAuthor)}</span>
                            </div>
                            <div class="music_play music_play_loading" data-music="${_escHtml(dataurl)}"></div>
                        </div>
                    </div>`;
                const $musicCard = $(loadingStyle).insertBefore(audioElement);
                if (window.lazyLoadInstance) window.lazyLoadInstance.update();
                const $playBtn = $musicCard.find('.music_play');
                
                // 检测音频链接是否有效
                checkAudioUrl(dataurl).then(isValid => {
                    // 移除加载状态
                    $playBtn.removeClass('music_play_loading');
                    
                    if (isValid) {
                        // 有效链接：设置正常状态
                        $playBtn.css('background-image', 'url(' + pause_svg + ')');
                        audioElement.src = dataurl;
                    } else {
                        // 无效链接：设置禁用状态
                        var _tip2 = (!dataurl || dataurl === '') ? '音频链接为空' : '音频地址无效';
                        $playBtn.addClass('music_play_disabled');
                        $playBtn.attr({'data-disabled': 'true', 'data-mc-msg': _tip2});
                    }
                    
                    afterAjax();
                });
            }
        });
    };

    window.refreshMusicCards = function () {
        const musicCards = document.querySelectorAll('.mian_music[data-id][data-type]:not(.loading-music)');

        musicCards.forEach(card => {
            const id = card.getAttribute('data-id');
            const dataType = card.getAttribute('data-type');
            const vipUrl = card.getAttribute('data-vip-url') || '';

            if (isTemporaryMusicProxy(vipUrl)) {
                card.setAttribute('data-vip-url', '');
            }

            if (!id || !dataType || dataType === 'custom') {
                return;
            }

            const $card = $(card);
            const $playBtn = $card.find('.music_play');
            const $cover = $card.find('.cover');
            const $musicCard = $card.find('.music_card');
            const $name = $card.find('.music_name');
            const $author = $card.find('.user');

            $playBtn.addClass('music_play_loading');
            $playBtn.removeClass('music_play_disabled');
            $playBtn.attr('data-disabled', 'false');
            $playBtn.attr('data-music', '');

            fetch(`../../music-api.php?type=song&media=${dataType}&id=${id}`)
                .then(response => response.ok ? response.json() : Promise.reject(response.status))
                .then(async data => {
                    const resolvedCover = data.cover || card.getAttribute('data-cover') || '';
                    const resolvedName = data.name || card.getAttribute('data-name') || '';
                    const resolvedAuthor = data.author || card.getAttribute('data-author') || '';
                    const resolvedUrl = resolvePreferredMusicUrl(vipUrl, data);

                    if (resolvedCover) {
                        card.setAttribute('data-cover', resolvedCover);
                        card.style.backgroundImage = `url('${resolvedCover}')`;
                        card.style.backgroundSize = 'cover';
                        $musicCard.attr('data-url', resolvedCover);
                        // 懒加载：src=占位图 data-src=真实封面
                        $cover.addClass('lazy').attr({'data-src': resolvedCover, 'src': _defaultMusicCover});
                        $cover[0].onerror = function(){ window._mcCoverError(this); };
                        if (window.lazyLoadInstance) window.lazyLoadInstance.update();
                    } else {
                        card.style.backgroundImage = `url('${_defaultMusicCover}')`;
                        card.style.backgroundSize = 'cover';
                        $musicCard.attr('data-url', _defaultMusicCover);
                        $cover.removeClass('lazy').removeAttr('data-src');
                        $cover.attr('src', _defaultMusicCover);
                    }

                    if (resolvedName) {
                        card.setAttribute('data-name', resolvedName);
                        $name.text(resolvedName);
                    }

                    if (resolvedAuthor) {
                        card.setAttribute('data-author', resolvedAuthor);
                        $author.text(resolvedAuthor);
                    }

                    $playBtn.removeClass('music_play_loading');

                    if (!resolvedUrl) {
                        $playBtn.addClass('music_play_disabled');
                        $playBtn.attr({'data-disabled': 'true', 'data-mc-msg': '音频链接为空'});
                        afterAjax();
                        return;
                    }

                    const isValid = await checkAudioUrl(resolvedUrl);
                    if (isValid) {
                        $playBtn.attr('data-music', resolvedUrl);
                        $playBtn.css('background-image', 'url(' + pause_svg + ')');
                    } else {
                        $playBtn.addClass('music_play_disabled');
                        $playBtn.attr({'data-disabled': 'true', 'data-mc-msg': '音频地址无效'});
                    }

                    afterAjax();
                })
                .catch(e => {
                    console.error('刷新音乐卡片失败:', e);
                    card.style.backgroundImage = `url('${_defaultMusicCover}')`;
                    card.style.backgroundSize = 'cover';
                    $musicCard.attr('data-url', _defaultMusicCover);
                    $cover.removeClass('lazy').removeAttr('data-src');
                    $cover.attr('src', _defaultMusicCover);
                    $playBtn.removeClass('music_play_loading');
                    $playBtn.addClass('music_play_disabled');
                    $playBtn.attr({'data-disabled': 'true', 'data-mc-msg': '获取音乐信息失败'});
                    afterAjax();
                });
        });
    };

    // 给所有已有卡片的 cover img 绑定 onerror 回退默认封面
    document.querySelectorAll('.mian_music .cover').forEach(function(img) {
        img.onerror = function(){ window._mcCoverError(this); };
        // 已经加载失败的图片（naturalWidth=0 且 src 不为空且不是默认封面）立即触发回退
        if (img.src && img.complete && img.naturalWidth === 0 && img.src.indexOf('music-cover-default') === -1) {
            window._mcCoverError(img);
        }
    });

    getMusic();
    refreshMusicCards();

    $(".music_info_btn_play").click(function () {
        const musicUrl = $(this).attr('data-music');
        
        if (currentUrl !== musicUrl) {
            audio.pause();
            audio.currentTime = 0;
            currentUrl = musicUrl;
            audio.src = musicUrl;
        }

        if (audio.paused) {
            pauseGlobalIfPlaying();
            audio.play();
            $(this).css('background-image', 'url(' + play_svg2 + ')');
            // 同步卡片状态：当前活跃卡片显示播放
            _syncCardPlayState(true);
        } else {
            audio.pause();
            resumeGlobalIfNeeded();
            $(this).css('background-image', 'url(' + pause_svg2 + ')');
            // 同步卡片状态：全部恢复暂停
            _syncCardPlayState(false);
        }
        _updateCapsuleTime();
    });

    // 同步文章内音乐卡片与胶囊的播放状态（用元素引用，确保唯一）
    function _syncCardPlayState(isPlaying) {
        // 先把所有卡片重置为暂停图标
        $('.music_play:not(.music_play_disabled):not(.music_play_loading)').css('background-image', 'url(' + pause_svg + ')');
        // 只有当前活跃的那个卡片按钮显示播放
        if (isPlaying && _activeCardBtn) {
            $(_activeCardBtn).css('background-image', 'url(' + play_svg + ')');
        }
    }

    function afterAjax() {
        setTimeout(function () {
            // 只为非禁用和非加载中的按钮设置样式
            $('.music_play:not(.music_play_disabled):not(.music_play_loading)').css('background-image', 'url(' + pause_svg + ')');

            $('.music_play').off('click').click(function () {
                // 检查是否被禁用或正在加载
                if ($(this).attr('data-disabled') === 'true' || $(this).hasClass('music_play_loading')) {
                    var _msg = $(this).attr('data-mc-msg') || '无法播放';
                    if (typeof Toastify !== 'undefined' && Toastify.showScenario) {
                        Toastify.showScenario('warning', { text: _msg });
                    }
                    return false;
                }
                
                // 点击缩放动画
                var $btn = $(this);
                $btn.css({'transform': 'translateY(-50%) scale(0.78)', 'transition': 'transform 0.1s ease'});
                setTimeout(function(){ $btn.css({'transform': 'translateY(-50%) scale(1)', 'transition': 'transform 0.2s cubic-bezier(0.34,1.56,0.64,1)'}); }, 100);
                
                $(".music_info").show();
                
                const musicUrl = $(this).attr('data-music');
                const covUrl = $(this).parent().attr('data-url') || _defaultMusicCover;
                
                $('.music_info_btn_play').attr('data-music', musicUrl);
                $('.music_info').css({'background-image': 'url(' + covUrl + ')', 'background-size': 'cover', 'background-position': 'center'});

                if (currentUrl !== musicUrl) {
                    audio.pause();
                    audio.currentTime = 0;
                    currentTime = 0;
                    currentUrl = musicUrl;
                    audio.src = musicUrl;
                }

                // 记录当前活跃的卡片按钮
                _activeCardBtn = this;

                if (audio.paused) {
                    pauseGlobalIfPlaying();
                    setTimeout(() => audio.play().catch(e => {
                        console.error('播放失败:', e);
                        _syncCardPlayState(false);
                        $(".music_info_btn_play").css('background-image', 'url(' + pause_svg2 + ')');
                    }), 10);
                    $(".music_info_btn_play").css('background-image', 'url(' + play_svg2 + ')');
                    _syncCardPlayState(true);
                } else {
                    audio.pause();
                    resumeGlobalIfNeeded();
                    $(".music_info_btn_play").css('background-image', 'url(' + pause_svg2 + ')');
                    _syncCardPlayState(false); 
                }
            });

            // 使用具名函数 + 先 remove 再 add，防止累积
            audio.removeEventListener('timeupdate', onAudioTimeUpdate);
            audio.addEventListener('timeupdate', onAudioTimeUpdate);

            audio.removeEventListener('ended', onAudioEnded);
            audio.addEventListener('ended', onAudioEnded);
            
            audio.removeEventListener('error', onAudioError);
            audio.addEventListener('error', onAudioError);
        }, 100); // 减少延迟，更快响应
    }

    // 关闭按钮 
    $('.music_info_btn_close').click(function () {
        $(".music_info").slideUp();
        _syncCardPlayState(false);
        _activeCardBtn = null;
        audio.pause();
        currentTime = 0;
        _updateCapsuleTime();
        resumeGlobalIfNeeded();
    });


    const musicInfo = document.querySelector(".music_info");
    let isDragging = false;
    let startX, startY;
    let initialLeft, initialTop;
    let _dragZoneLeft = null, _dragZoneRight = null, _dragOverlay = null;
    let _dragActivated = false;  // 真正开始拖动（超过阈值）
    let _startClientX = 0, _startClientY = 0;  // 记录起始触点
    var DRAG_THRESHOLD = 5;  // 像素

    // 获取 1rem 对应的像素值
    function getRemInPx() {
        return parseFloat(getComputedStyle(document.documentElement).fontSize);
    }

    // 统一处理移动（具名函数，便于 removeEventListener）
    function onMouseMove(e) {
        if (!isDragging) return;
        _handleMove(e, e.clientX, e.clientY);
    }
    function onTouchMove(e) {
        if (!isDragging) return;
        e.preventDefault();
        _handleMove(e, e.touches[0].clientX, e.touches[0].clientY);
    }
    function _handleMove(e, clientX, clientY) {
        // 未超过阈值时，检测是否达到拖拽距离
        if (!_dragActivated) {
            var dx = clientX - _startClientX;
            var dy = clientY - _startClientY;
            if (dx * dx + dy * dy < DRAG_THRESHOLD * DRAG_THRESHOLD) return;
            _dragActivated = true;
            musicInfo.classList.add('music_info_dragging');
            musicInfo.style.transition = 'none';
            document.body.style.userSelect = 'none';
            document.body.style.webkitUserSelect = 'none';
            if (window.getSelection) window.getSelection().removeAllRanges();
            _createDragZones();
            _updateDragZones(musicInfo.offsetLeft + musicInfo.offsetWidth / 2);
        }

        const remPx = getRemInPx();
        
        // 计算新的位置
        let newLeft = clientX - startX;
        let newTop = clientY - startY;

        // 计算边界 (窗口宽高 - 元素宽高 - 1rem)
        const maxLeft = window.innerWidth - musicInfo.offsetWidth - remPx;
        const maxTop = window.innerHeight - musicInfo.offsetHeight - remPx;
        const minLeft = remPx;
        const minTop = remPx;

        newLeft = Math.max(minLeft, Math.min(newLeft, maxLeft));
        newTop = Math.max(minTop, Math.min(newTop, maxTop));

        musicInfo.style.left = newLeft + "px";
        musicInfo.style.top = newTop + "px";

        // 实时更新停靠区高亮
        _updateDragZones(newLeft + musicInfo.offsetWidth / 2);
    }

    function _updateDragZones(centerX) {
        var isLeft = centerX < window.innerWidth / 2;
        if (_dragZoneLeft) {
            _dragZoneLeft.classList.toggle('active', isLeft);
            _dragZoneLeft.classList.toggle('visible', !isLeft);
        }
        if (_dragZoneRight) {
            _dragZoneRight.classList.toggle('active', !isLeft);
            _dragZoneRight.classList.toggle('visible', isLeft);
        }
    }

    function _createDragZones() {
        _removeDragZones();
        // 全屏遮罩
        _dragOverlay = document.createElement('div');
        _dragOverlay.className = 'music-drag-overlay';
        document.body.appendChild(_dragOverlay);
        // 左右停靠区
        _dragZoneLeft = document.createElement('div');
        _dragZoneLeft.className = 'music-drag-zone music-drag-zone--left';
        _dragZoneRight = document.createElement('div');
        _dragZoneRight.className = 'music-drag-zone music-drag-zone--right';
        document.body.appendChild(_dragZoneLeft);
        document.body.appendChild(_dragZoneRight);
        // 下一帧再显示，触发 CSS transition
        requestAnimationFrame(function() {
            if (_dragOverlay) _dragOverlay.classList.add('visible');
            if (_dragZoneLeft) _dragZoneLeft.classList.add('visible');
            if (_dragZoneRight) _dragZoneRight.classList.add('visible');
        });
    }

    function _removeDragZones() {
        [_dragOverlay, _dragZoneLeft, _dragZoneRight].forEach(function(el) {
            if (!el) return;
            el.classList.remove('active', 'visible');
            setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
        });
        _dragOverlay = null;
        _dragZoneLeft = null;
        _dragZoneRight = null;
    }

    function handleEnd() {
        if (!isDragging) return;
        isDragging = false;
        // 拖拽结束，立即移除 document 级监听器
        document.removeEventListener("mousemove", onMouseMove);
        document.removeEventListener("touchmove", onTouchMove);
        document.removeEventListener("mouseup", handleEnd);
        document.removeEventListener("touchend", handleEnd);

        // 未达到拖拽阈值（只是点击），直接返回
        if (!_dragActivated) return;

        // 恢复文本选择
        document.body.style.userSelect = '';
        document.body.style.webkitUserSelect = '';

        musicInfo.classList.remove("music_info_dragging");

        // 移除停靠区指示器
        _removeDragZones();

        const remPx = getRemInPx();
        const windowWidth = window.innerWidth;
        const elementWidth = musicInfo.offsetWidth;
        const currentLeft = musicInfo.offsetLeft;

        // 计算元素中心点
        const elementCenterX = currentLeft + (elementWidth / 2);
        const windowCenterX = windowWidth / 2;

        let targetLeft;

        // 判断是在左半边还是右半边
        if (elementCenterX < windowCenterX) {
            targetLeft = remPx;
        } else {
            targetLeft = windowWidth - elementWidth - remPx;
        }

        musicInfo.style.transition = 'left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.35s cubic-bezier(0.34, 1.56, 0.64, 1)';
        musicInfo.style.left = targetLeft + "px";
    }

    // 统一处理开始拖拽：仅在此时绑定 document 级移动/结束事件
    function handleStart(clientX, clientY) {
        isDragging = true;
        _dragActivated = false;
        _startClientX = clientX;
        _startClientY = clientY;
        startX = clientX - musicInfo.offsetLeft;
        startY = clientY - musicInfo.offsetTop;

        document.addEventListener("mousemove", onMouseMove);
        document.addEventListener("touchmove", onTouchMove, { passive: false });
        document.addEventListener("mouseup", handleEnd);
        document.addEventListener("touchend", handleEnd);
    }

    // 鼠标/触摸事件：仅在元素上绑定 start，右键不触发拖拽
    musicInfo.addEventListener("mousedown", function(e) {
        if (e.button !== 0) return; // 只响应左键
        handleStart(e.clientX, e.clientY);
    });
    musicInfo.addEventListener("touchstart", e => handleStart(e.touches[0].clientX, e.touches[0].clientY), { passive: false });
});
