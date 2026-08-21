(function () {
    'use strict';

    // ============================================================
    //  配置 & 状态
    // ============================================================
    // 状态 & DOM 引用（在 init 中重置 / 获取）
    var LOAD_ENDPOINT;
    var chatState;
    var isReversed, isPlaying, isFinished, playSpeed, isSkipping, isMuted;
    var _resumeResolver, _currentVoiceAudio, _voiceTimer;
    var menuToggle, dropdownMenu, chatBox, playBtn, speedBtn, skipBtn;
    var _docClickHandler;
    var _xhr = null;           // 跟踪数据请求，destroy 时 abort
    var _beepCtx = null;       // 复用 AudioContext，避免超限
    var _beepPrimed = false;   // AudioContext 是否已由用户手势预热
    var _primeHandler = null;  // 一次性交互监听器引用（destroy 时移除）
    var _activeFlowId = 0;     // 模块级流控计数器（永不重置，解决新旧 chatState 对象切换时旧 flow 不退出的问题）

    function fmtDuration(sec) {
        if (!isFinite(sec) || sec < 0) return '--\"';
        var total = Math.round(sec);
        if (total < 60) return total + '\"';
        var m = Math.floor(total / 60);
        var s = total % 60;
        return m + "'" + (s < 10 ? '0' : '') + s + '\"';
    }

    const VOICE_PLAY_SVG = '<svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg"><path d="M713.896 393.15l-240-158.691c-80.996-54.004-150-17.987-150 80.996v360c0 98.73 65.703 134.707 150 80.703l240-158.711c80.724-60 80.724-150 0-203.984v-0.313z"/></svg>';
    const VOICE_PAUSE_SVG = '<svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg"><path d="M685.237 808.447h-27.988c-33.594 0-56.016-27.988-56.016-55.996V310.049c0-33.594 28.008-55.996 56.016-55.996h27.988c33.594 0 56.016 28.008 56.016 55.996v436.816c5.585 33.594-22.423 61.582-56.016 61.582zM366.037 808.447h-27.988c-33.594 0-56.016-27.988-56.016-55.996V310.049c0-33.594 28.008-55.996 56.016-55.996h27.988c33.594 0 56.016 28.008 56.016 55.996v436.816c0 33.594-22.422 61.582-56.016 61.582z"/></svg>';

    // ============================================================
    //  主题 & 菜单
    // ============================================================

    function switchTheme(theme) {
        var device = document.querySelector('.lgnewui-chat-device');
        var pjax = document.getElementById('pjax-container');
        if (!device) return;
        if (theme === 'theme2') {
            device.setAttribute('data-theme', 'theme2');
            if (pjax) pjax.setAttribute('data-theme', 'theme2');
        } else {
            device.removeAttribute('data-theme');
            if (pjax) pjax.removeAttribute('data-theme');
        }
        dropdownMenu.classList.remove('show');
    }

    function toggleMute() {
        isMuted = !isMuted;
        const icon = document.getElementById('menuMuteIcon');
        const text = document.getElementById('menuMuteText');
        if (isMuted) {
            icon.className = 'ph ph-speaker-slash';
            text.textContent = '开启提示音';
        } else {
            icon.className = 'ph ph-speaker-high';
            text.textContent = '关闭提示音';
        }
        dropdownMenu.classList.remove('show');
    }

    // ============================================================
    //  导出长图
    // ============================================================
    var _exportImgUrl = null;
    var _exportSeqTimer = null;
    var _exportTransTimer = null;

    // 文案序列
    var _exportTextSeq = [
        { t: '正在收集聊天片段...', s: '文字、图片与语音正在归档' },
        { t: '正在生成长图排版...', s: '调整气泡间距与视觉呈现' },
        { t: '即将完成...', s: '正在输出高清长图' }
    ];

    async function exportChat() {
        dropdownMenu.classList.remove('show');

        var stateGen = document.getElementById('stateGenerating');
        var statePrev = document.getElementById('statePreview');
        var genTitle = document.getElementById('genTitle');
        var genSubtitle = document.getElementById('genSubtitle');
        if (!stateGen || !statePrev) return;

        // 初始化面板状态
        stateGen.classList.remove('lgnewui-chat-hidden-state');
        statePrev.classList.add('lgnewui-chat-hidden-state');
        if (genTitle) { genTitle.style.opacity = 1; genTitle.textContent = _exportTextSeq[0].t; }
        if (genSubtitle) { genSubtitle.style.opacity = 1; genSubtitle.textContent = _exportTextSeq[0].s; }

        // 激活遮罩 + 面板（先display:flex再触发opacity过渡）
        var overlay = document.getElementById('exportOverlay');
        if (overlay) overlay.style.display = 'flex';
        requestAnimationFrame(function() {
            document.body.classList.add('lgnewui-chat-is-exporting');
        });
        if (window.lgScrollLock) lgScrollLock();

        // 启动文案序列动画
        var step = 0;
        _exportSeqTimer = setInterval(function() {
            step++;
            if (step < _exportTextSeq.length) {
                if (genTitle) genTitle.style.opacity = 0;
                if (genSubtitle) genSubtitle.style.opacity = 0;
                setTimeout(function() {
                    if (genTitle) { genTitle.textContent = _exportTextSeq[step].t; genTitle.style.opacity = 1; }
                    if (genSubtitle) { genSubtitle.textContent = _exportTextSeq[step].s; genSubtitle.style.opacity = 1; }
                }, 300);
            } else {
                clearInterval(_exportSeqTimer);
                _exportSeqTimer = null;
            }
        }, 1800);

        // 同时后台生成长图
        try {
            var zone = document.getElementById('poster-export-zone');
            var chatHtml = chatBox.innerHTML;
            var now = new Date();
            var dateStr = now.getFullYear() + '年' + (now.getMonth() + 1) + '月' + now.getDate() + '日';

            zone.innerHTML =
                '<div class="lgnewui-chat-poster-header-box">' +
                    '<div class="lgnewui-chat-poster-title">我们的回忆记录</div>' +
                    '<div class="lgnewui-chat-poster-date">' + dateStr + '</div>' +
                '</div>' +
                '<div class="lgnewui-chat-container" style="padding:0; overflow:visible; display:flex; flex-direction:column; gap:20px; background:transparent;">' +
                    chatHtml +
                '</div>' +
                '<div class="lgnewui-chat-poster-footer-box">' +
                    '<div class="lgnewui-chat-footer-quote">「 陪伴是最长情的告白 」</div>' +
                    '<div class="lgnewui-chat-footer-brand">Memory of Us · 永恒珍藏</div>' +
                '</div>';

            var typingNode = zone.querySelector('#typing');
            if (typingNode) typingNode.remove();

            zone.querySelectorAll('.lgnewui-chat-msg-row').forEach(function(r) {
                r.style.animation = 'none';
                r.style.opacity = '1';
                r.style.transform = 'none';
            });

            var images = zone.querySelectorAll('img');
            var imagePromises = Array.from(images).map(function(img) {
                img.crossOrigin = 'anonymous';
                if (img.src && !img.src.startsWith('data:')) {
                    var separator = img.src.includes('?') ? '&' : '?';
                    img.src = img.src + separator + '_cors=' + new Date().getTime();
                }
                if (img.complete) return Promise.resolve();
                return new Promise(function(resolve) {
                    img.onload = resolve;
                    img.onerror = resolve;
                });
            });
            await Promise.all(imagePromises);
            await new Promise(function(resolve) { setTimeout(resolve, 500); });

            var canvas = await html2canvas(zone, {
                scale: 2,
                useCORS: true,
                allowTaint: false,
                backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--chat-bg').trim() || '#f5f5f7'
            });

            _exportImgUrl = canvas.toDataURL('image/png');

            // 停掉文案动画（如果还在转）
            if (_exportSeqTimer) { clearInterval(_exportSeqTimer); _exportSeqTimer = null; }

            // 设置预览图
            var previewImg = document.getElementById('exportPreviewImg');
            if (previewImg) previewImg.src = _exportImgUrl;

            // 无缝过渡到预览状态
            stateGen.classList.add('lgnewui-chat-hidden-state');
            _exportTransTimer = setTimeout(function() {
                statePrev.classList.remove('lgnewui-chat-hidden-state');
            }, 100);

        } catch (e) {
            alert('长图生成失败: ' + e.message);
            _closeExport();
        } finally {
            document.getElementById('poster-export-zone').innerHTML = '';
        }
    }

    function _closeExport() {
        document.body.classList.remove('lgnewui-chat-is-exporting');
        if (window.lgScrollUnlock) lgScrollUnlock();
        // 过渡结束后隐藏overlay，防止pjax闪烁
        var overlay = document.getElementById('exportOverlay');
        if (overlay) setTimeout(function() { overlay.style.display = 'none'; }, 700);

        // 清理定时器
        if (_exportSeqTimer) { clearInterval(_exportSeqTimer); _exportSeqTimer = null; }
        if (_exportTransTimer) { clearTimeout(_exportTransTimer); _exportTransTimer = null; }

        // 延迟重置内部状态，等关闭动画结束后
        setTimeout(function() {
            var stateGen = document.getElementById('stateGenerating');
            var statePrev = document.getElementById('statePreview');
            if (stateGen) stateGen.classList.remove('lgnewui-chat-hidden-state');
            if (statePrev) statePrev.classList.add('lgnewui-chat-hidden-state');
            var previewImg = document.getElementById('exportPreviewImg');
            if (previewImg) previewImg.src = '';
            _exportImgUrl = null;
        }, 600);
    }

    function _downloadExport() {
        if (!_exportImgUrl) return;
        var btn = document.getElementById('exportDownloadBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner-gap lgnewui-chat-icon-spin"></i> 正在保存...';
        }
        setTimeout(function() {
            var link = document.createElement('a');
            link.href = _exportImgUrl;
            link.download = '回忆长图_' + new Date().getTime() + '.png';
            link.click();
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-download-simple"></i> 保存长图';
            }
        }, 1000);
    }

    // ============================================================
    //  提示音
    // ============================================================

    // 在用户手势事件中预热 AudioContext（Chrome autoplay policy 要求）
    function _primeAudioContext() {
        if (_beepPrimed || _primeHandler) return;
        var ACtor = window.AudioContext || window.webkitAudioContext;
        if (!ACtor) return;
        _primeHandler = function () {
            // 只执行一次
            document.removeEventListener('click', _primeHandler, true);
            document.removeEventListener('touchstart', _primeHandler, true);
            document.removeEventListener('keydown', _primeHandler, true);
            _primeHandler = null;
            try {
                if (!_beepCtx) _beepCtx = new ACtor();
                if (_beepCtx.state === 'suspended') {
                    _beepCtx.resume();
                }
                _beepPrimed = true;
            } catch (e) { /* ignore */ }
        };
        document.addEventListener('click', _primeHandler, true);
        document.addEventListener('touchstart', _primeHandler, true);
        document.addEventListener('keydown', _primeHandler, true);
    }

    function _doBeep() {
        var osc = _beepCtx.createOscillator();
        var gain = _beepCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(600, _beepCtx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1200, _beepCtx.currentTime + 0.05);
        gain.gain.setValueAtTime(0, _beepCtx.currentTime);
        gain.gain.linearRampToValueAtTime(0.12, _beepCtx.currentTime + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.001, _beepCtx.currentTime + 0.1);
        osc.connect(gain);
        gain.connect(_beepCtx.destination);
        osc.start();
        osc.stop(_beepCtx.currentTime + 0.1);
    }

    function playBeep() {
        if (isMuted || isSkipping) return;
        // AudioContext 未预热则静默跳过（等用户交互后自动预热）
        if (!_beepPrimed || !_beepCtx || _beepCtx.state !== 'running') return;
        try { _doBeep(); } catch (e) { /* ignore */ }
    }

    function pausePlayback() {
        if (!isPlaying || isFinished) return;
        isPlaying = false;
        if (playBtn) {
            playBtn.innerHTML = '<i class="ph-fill ph-play"></i>';
            playBtn.classList.add('paused');
        }
    }

    function waitIfPaused() {
        if (isPlaying) return Promise.resolve();
        return new Promise(resolve => { _resumeResolver = resolve; });
    }

    async function sleep(ms) {
        if (isSkipping) return;
        let elapsed = 0;
        const step = 50;
        while (elapsed < ms / playSpeed) {
            await waitIfPaused();
            if (isSkipping) return;
            await new Promise(r => setTimeout(r, step));
            elapsed += step;
        }
    }

    // ============================================================
    //  渲染 Header
    // ============================================================
    function renderHeader() {
        const headerAvatar = document.getElementById('headerAvatar');
        const headerName = document.getElementById('headerName');
        if (!headerAvatar || !headerName) return;

        const targetAvatar = chatState.swapViewpoint !== isReversed
            ? chatState.avatars.male : chatState.avatars.female;
        headerAvatar.src = targetAvatar;
        headerName.textContent = chatState.dialogueName || '亲爱的';

        const statusEl = document.querySelector('.lgnewui-chat-user-status');
        if (statusEl) statusEl.innerHTML = '<span class="lgnewui-chat-status-dot"></span> 对话回放中...';
    }

    // ============================================================
    //  气泡颜色 → CSS 变量映射
    // ============================================================
    function applyBubbleColors() {
        const root = document.documentElement;
        // swapViewpoint 决定谁在左谁在右
        const leftRole = chatState.swapViewpoint ? 'male' : 'female';
        const rightRole = chatState.swapViewpoint ? 'female' : 'male';

        root.style.setProperty('--msg-left-bg', chatState.colors[leftRole].bg);
        root.style.setProperty('--msg-left-text', chatState.colors[leftRole].text);
        root.style.setProperty('--msg-right-bg', chatState.colors[rightRole].bg);
        root.style.setProperty('--msg-right-text', chatState.colors[rightRole].text);
    }

    // ============================================================
    //  消息 HTML 构建
    // ============================================================
    function getMessageMeta(msg) {
        const isRight = chatState.swapViewpoint ? (msg.role === 'female') : (msg.role === 'male');
        const actualRole = isReversed ? (isRight ? 'left' : 'right') : (isRight ? 'right' : 'left');
        const avatar = msg.role === 'male' ? chatState.avatars.male : chatState.avatars.female;
        return { actualRole, avatar };
    }

    function createTypingBubble(msg) {
        if (msg.type === 'notice') return '';
        const { actualRole, avatar } = getMessageMeta(msg);
        return `
            <div class="lgnewui-chat-msg-row ${actualRole}" id="typing">
                <img src="${avatar}" class="lgnewui-chat-avatar">
                <div class="lgnewui-chat-bubble">
                    <div class="lgnewui-chat-typing-indicator">
                        <div class="lgnewui-chat-typing-dot"></div>
                        <div class="lgnewui-chat-typing-dot"></div>
                        <div class="lgnewui-chat-typing-dot"></div>
                    </div>
                </div>
            </div>`;
    }

    function buildMessageHTML(msg) {
        const { actualRole, avatar } = getMessageMeta(msg);
        let inner = '';

        if (msg.type === 'text') {
            inner = `<div class="lgnewui-chat-bubble">${escapeHtml(msg.content).replace(/\n/g, '<br>')}</div>`;
        } else if (msg.type === 'image') {
            const imgSrc = msg.thumbnail || msg.content;
            inner = `<div><img src="${imgSrc}" data-original="${msg.content}" class="lgnewui-chat-media-img" onclick="chatOpenImage(this)"></div>`;
        } else if (msg.type === 'video') {
            const coverHtml = msg.thumbnail
                ? `<img src="${msg.thumbnail}" style="width:100%;height:100%;object-fit:cover;display:block;">`
                : `<div style="width:100%;height:100%;background:#1a1a1a;"></div>`;
            inner = `
                <div class="lgnewui-chat-media-video" onclick="chatOpenVideo('${msg.content}')">
                    ${coverHtml}
                    <div class="lgnewui-chat-play-btn-overlay">
                        <i class="ph-fill ph-play lgnewui-chat-media-play-icon"></i>
                    </div>
                </div>`;
        } else if (msg.type === 'audio') {
            inner = `
                <div class="lgnewui-chat-bubble lgnewui-chat-voice-msg" data-audio-src="${msg.content}" onclick="playVoice(this)">
                    <span class="lgnewui-chat-voice-icon">${VOICE_PLAY_SVG}</span>
                    <div class="lgnewui-chat-voice-waves"><span></span><span></span><span></span><span></span><span></span></div>
                    <div class="lgnewui-chat-voice-time">--"</div>
                    <audio preload="metadata"></audio>
                </div>`;
        }

        if (msg.type === 'notice') {
            return `<div class="lgnewui-chat-system-notice"><span>${escapeHtml(msg.content)}</span></div>`;
        }
        return `<div class="lgnewui-chat-msg-row ${actualRole}"><img src="${avatar}" class="lgnewui-chat-avatar">${inner}</div>`;
    }

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ============================================================
    //  对话播放引擎
    // ============================================================
    function scrollToBottom() {
        if (chatBox) chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' });
    }

    async function startConversationFlow(list) {
        const flowId = ++_activeFlowId;
        chatBox.innerHTML = '';
        isSkipping = false;

        for (let i = 0; i < list.length; i++) {
            if (flowId !== _activeFlowId) return;

            if (!isSkipping) {
                await waitIfPaused();
                if (flowId !== _activeFlowId) return;

                chatBox.insertAdjacentHTML('beforeend', createTypingBubble(list[i]));
                scrollToBottom();

                await sleep(1000);
                if (flowId !== _activeFlowId) return;
                await waitIfPaused();
                if (flowId !== _activeFlowId) return;

                const typingNode = document.getElementById('typing');
                if (typingNode) typingNode.remove();
            }

            if (flowId !== _activeFlowId) return;
            chatBox.insertAdjacentHTML('beforeend', buildMessageHTML(list[i]));
            scrollToBottom();
            if (list[i].type !== 'notice') playBeep();

            // 语音消息：自动加载获取时长
            if (list[i].type === 'audio') {
                const rows = chatBox.querySelectorAll('.lgnewui-chat-msg-row');
                const lastRow = rows[rows.length - 1];
                if (lastRow) {
                    const voiceEl = lastRow.querySelector('.lgnewui-chat-voice-msg');
                    if (voiceEl) initVoiceDuration(voiceEl);
                }
            }

            if (i < list.length - 1) {
                await sleep(600);
            }
        }

        if (flowId === _activeFlowId) {
            isFinished = true;
            isPlaying = false;
            if (chatBox && chatState.endingText) {
                const endEl = document.createElement('div');
                endEl.className = 'lgnewui-chat-system-notice';
                endEl.innerHTML = '<span>' + escapeHtml(chatState.endingText) + '</span>';
                chatBox.appendChild(endEl);
                chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' });
            }
            if (playBtn) {
                playBtn.innerHTML = '<i class="ph-bold ph-arrow-counter-clockwise"></i>';
                playBtn.classList.remove('paused');
            }
        }
    }

    // ============================================================
    //  语音播放（真实 audio）
    // ============================================================
    function initVoiceDuration(el) {
        const audio = el.querySelector('audio');
        const src = el.getAttribute('data-audio-src');
        if (!audio || !src) return;

        audio.addEventListener('loadedmetadata', function () {
            if (isFinite(this.duration) && this.duration > 0) {
                el.querySelector('.lgnewui-chat-voice-time').textContent = fmtDuration(this.duration);
            }
        });
        audio.src = src;
    }

    function resetVoice(el) {
        if (!el) return;
        const audio = el.querySelector('audio');
        if (audio) { audio.pause(); audio.currentTime = 0; }
        el.classList.remove('playing');
        el.querySelector('.lgnewui-chat-voice-icon').innerHTML = VOICE_PLAY_SVG;
        if (_voiceTimer) { clearInterval(_voiceTimer); _voiceTimer = null; }
        if (audio && isFinite(audio.duration) && audio.duration > 0) {
            el.querySelector('.lgnewui-chat-voice-time').textContent = fmtDuration(audio.duration);
        }
    }

    function pauseVoice(el) {
        if (!el) return;
        const audio = el.querySelector('audio');
        if (audio) audio.pause();
        el.classList.remove('playing');
        el.querySelector('.lgnewui-chat-voice-icon').innerHTML = VOICE_PLAY_SVG;
        if (_voiceTimer) { clearInterval(_voiceTimer); _voiceTimer = null; }
    }

    function playVoice(el) {
        const audio = el.querySelector('audio');
        if (!audio) return;

        // 设置 src（只设置一次）
        if (!audio.getAttribute('src')) {
            audio.src = el.getAttribute('data-audio-src') || '';
        }

        // 如果正在播放，暂停（保留进度）
        if (el.classList.contains('playing')) {
            pauseVoice(el);
            return;
        }

        // 暂停其他正在播放的语音（保留其进度）
        document.querySelectorAll('.lgnewui-chat-voice-msg.playing').forEach(node => {
            pauseVoice(node);
        });

        pausePlayback();

        // 播放（从当前位置继续）
        el.classList.add('playing');
        el.querySelector('.lgnewui-chat-voice-icon').innerHTML = VOICE_PAUSE_SVG;
        audio.play().catch(() => {});

        // 启动倒计时
        if (_voiceTimer) clearInterval(_voiceTimer);
        _voiceTimer = setInterval(function () {
            var timeEl = el.querySelector('.lgnewui-chat-voice-time');
            if (timeEl && isFinite(audio.duration)) {
                timeEl.textContent = fmtDuration(audio.duration - audio.currentTime);
            }
        }, 120);

        audio.onended = function () {
            resetVoice(el);
        };
    }

    // ============================================================
    //  图片预览（使用全局 ViewImage）
    // ============================================================
    function chatOpenImage(imgEl) {
        pausePlayback();
        if (!window.ViewImage) return;
        var allImgs = chatBox.querySelectorAll('.lgnewui-chat-media-img');
        var urls = Array.from(allImgs).map(function (img) { return img.getAttribute('data-original') || img.src; });
        var currentUrl = imgEl.getAttribute('data-original') || imgEl.src;
        ViewImage.display(urls, currentUrl);
    }

    // ============================================================
    //  视频播放（复用全局 VideoModal）
    // ============================================================
    function chatOpenVideo(url) {
        pausePlayback();
        if (!url) return;
        if (window.VideoModal) {
            window.VideoModal.open(url, '');
        }
    }

    // ============================================================
    //  重置
    // ============================================================
    function resetDemo() {
        dropdownMenu.classList.remove('show');
        isPlaying = true;
        isFinished = false;
        isSkipping = false;
        isReversed = false;
        if (playBtn) {
            playBtn.innerHTML = '<i class="ph-fill ph-pause"></i>';
            playBtn.classList.remove('paused');
        }
        if (_resumeResolver) { _resumeResolver(); _resumeResolver = null; }

        renderHeader();
        applyBubbleColors();

        if (chatState.data && chatState.data.length > 0) {
            startConversationFlow(chatState.data);
        } else {
            loadChatData();
        }
    }

    function clearChat() {
        dropdownMenu.classList.remove('show');
        _activeFlowId++;
        chatBox.innerHTML = '';
    }

    // ============================================================
    //  加载数据（从 services/chat-data.php）
    // ============================================================
    function loadChatData() {
        // 中断上次未完成的请求
        if (_xhr) { try { _xhr.abort(); } catch(e) {} }
        var xhr = new XMLHttpRequest();
        _xhr = xhr;
        xhr.open('GET', LOAD_ENDPOINT, true);
        xhr.responseType = 'json';
        xhr.onload = function () {
            _xhr = null;
            const res = xhr.response;
            if (!res || res.status !== 'success') {
                if (res && res.status === 'empty') {
                    _loadMockConversation();
                    return;
                }
                showLoadError((res && res.message) || '加载失败');
                return;
            }

            // 隐藏 loading
            const loading = document.getElementById('page-loading');
            if (loading) {
                loading.style.opacity = '0';
                setTimeout(() => loading.remove(), 500);
            }

            const data = res.data || {};
            const settings = data.settings || {};
            const dialogues = data.dialogues || [];

            // 缓存
            chatState.data = dialogues;
            chatState.swapViewpoint = !!settings.swapViewpoint;
            chatState.dialogueName = settings.dialogueName || '';
            chatState.endingText = (settings.endingText && settings.endingText.trim())
                ? settings.endingText.trim()
                : '— 故事还在继续 —';
            chatState.avatars = {
                male: (settings.avatars && settings.avatars.male) || '',
                female: (settings.avatars && settings.avatars.female) || ''
            };
            chatState.colors = {
                male: (settings.colors && settings.colors.male) || { bg: '#007aff', text: '#ffffff' },
                female: (settings.colors && settings.colors.female) || { bg: '#ffffff', text: '#1d1d1f' }
            };

            renderHeader();
            applyBubbleColors();
            startConversationFlow(dialogues);
        };
        xhr.onerror = function () {
            _xhr = null;
            showLoadError('网络连接失败');
        };
        xhr.send();
    }

    function showLoadError(msg) {
        const loading = document.getElementById('page-loading');
        if (loading) {
            loading.innerHTML = '<div style="color:#ff3b30;font-size:14px;">' + msg + '</div>';
        }
    }

    function _loadMockConversation() {
        var cfg = window.LG_CONFIG || {};
        var boyName = cfg.maleName || '我';
        var girlName = cfg.femaleName || '你';
        var boyAvatar = cfg.maleAvatar || '';
        var girlAvatar = cfg.femaleAvatar || '';

        var loading = document.getElementById('page-loading');
        if (loading) {
            loading.style.opacity = '0';
            setTimeout(function() { loading.remove(); }, 500);
        }

        chatState.swapViewpoint = false;
        chatState.dialogueName = girlName;
        chatState.avatars = { male: boyAvatar, female: girlAvatar };
        chatState.colors = {
            male: { bg: '#007aff', text: '#ffffff' },
            female: { bg: '#ffffff', text: '#1d1d1f' }
        };

        var mockDialogues = [
            { role: 'male',   type: 'text', content: '今天路过那家书店，顺手帮你看了一眼，没有新货。' },
            { role: 'female', type: 'text', content: '……那家店要绕两条街吧。' },
            { role: 'male',   type: 'text', content: '最近在那片跑步。' },
            { role: 'female', type: 'text', content: '你之前说那片路灯不好，不爱去。' },
            { role: 'male',   type: 'text', content: '换路线了。' },
            { role: 'female', type: 'text', content: '哦。' },
            { role: 'female', type: 'text', content: '谢谢你。' },
            { role: 'male',   type: 'text', content: '今晚下雨，记得带伞。' },
            { role: 'female', type: 'text', content: '你怎么知道今晚下雨。' },
            { role: 'male',   type: 'text', content: '我查了。' },
            { role: 'female', type: 'text', content: '你每天都查天气吗。' },
            { role: 'male',   type: 'text', content: '只查你那边的。' },
            { role: null, type: 'notice', content: '以上为演示对话 · 管理员尚未配置真实聊天记录' }
        ];

        chatState.data = mockDialogues;
        renderHeader();
        applyBubbleColors();
        startConversationFlow(mockDialogues);
    }

    // ============================================================
    //  初始化（首次加载 + PJAX 返回时调用）
    // ============================================================
    function init() {
        var newChatBox = document.getElementById('chatBox');
        if (!newChatBox) return;
        if (newChatBox === chatBox && chatState) return; // 同一 DOM 已初始化，防止重复

        destroy();

        var CFG = window.__CHAT_CONFIG || {};
        LOAD_ENDPOINT = CFG.loadEndpoint || 'services/chat-data.php';

        chatState = {
            swapViewpoint: false,
            avatars: { male: '', female: '' },
            colors: { male: { bg: '#007aff', text: '#ffffff' }, female: { bg: '#ffffff', text: '#1d1d1f' } },
            dialogueName: '',
            data: []
        };
        isReversed = false;
        isPlaying = true;
        isFinished = false;
        playSpeed = 1.0;
        isSkipping = false;
        isMuted = false;
        _resumeResolver = null;
        _currentVoiceAudio = null;
        _voiceTimer = null;

        menuToggle = document.getElementById('menuToggle');
        dropdownMenu = document.getElementById('dropdownMenu');
        chatBox = newChatBox;
        playBtn = document.getElementById('playBtn');
        speedBtn = document.getElementById('speedBtn');
        skipBtn = document.getElementById('skipBtn');

        bindEvents();
        loadChatData();

        // 移除 FOUC 防护内联样式，CSS 已加载完毕
        var wrapper = document.querySelector('.lgnewui-chat-wrapper');
        if (wrapper) {
            requestAnimationFrame(function() { wrapper.style.opacity = '1'; });
        }
    }

    function bindEvents() {
        _primeAudioContext();

        if (menuToggle) {
            menuToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });
        }

        _docClickHandler = function (e) {
            if (menuToggle && !menuToggle.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        };
        document.addEventListener('click', _docClickHandler);

        if (playBtn) {
            playBtn.addEventListener('click', function () {
                if (isFinished) { resetDemo(); return; }
                isPlaying = !isPlaying;
                if (isPlaying) {
                    playBtn.innerHTML = '<i class="ph-fill ph-pause"></i>';
                    playBtn.classList.remove('paused');
                    if (_resumeResolver) { _resumeResolver(); _resumeResolver = null; }
                } else {
                    playBtn.innerHTML = '<i class="ph-fill ph-play"></i>';
                    playBtn.classList.add('paused');
                }
            });
        }

        if (speedBtn) {
            speedBtn.addEventListener('click', function () {
                var speeds = [1.0, 1.5, 2.0];
                var idx = speeds.indexOf(playSpeed);
                playSpeed = speeds[(idx + 1) % speeds.length];
                speedBtn.querySelector('.lgnewui-chat-speed-btn-text').textContent = playSpeed.toFixed(1) + 'x';
            });
        }

        if (skipBtn) {
            skipBtn.addEventListener('click', function () {
                if (isFinished) return;
                isSkipping = true;
                if (_resumeResolver) { _resumeResolver(); _resumeResolver = null; }
            });
        }

        // 导出弹窗：关闭 & 下载
        var exportCloseBtn = document.getElementById('exportCloseBtn');
        if (exportCloseBtn) exportCloseBtn.addEventListener('click', _closeExport);
        var exportDownloadBtn = document.getElementById('exportDownloadBtn');
        if (exportDownloadBtn) exportDownloadBtn.addEventListener('click', _downloadExport);
        // 点击遮罩背景关闭
        var exportOverlay = document.getElementById('exportOverlay');
        if (exportOverlay) {
            exportOverlay.addEventListener('click', function(e) {
                if (e.target === exportOverlay) _closeExport();
            });
        }

        var perspectiveBtn = document.getElementById('perspectiveBtn');
        if (perspectiveBtn) {
            perspectiveBtn.addEventListener('click', function () {
                isReversed = !isReversed;
                renderHeader();
                document.querySelectorAll('.lgnewui-chat-msg-row').forEach(function (row) {
                    row.style.animation = 'none';
                    row.offsetHeight;
                    if (row.classList.contains('left')) {
                        row.classList.remove('left'); row.classList.add('right');
                    } else if (row.classList.contains('right')) {
                        row.classList.remove('right'); row.classList.add('left');
                    }
                    row.style.animation = '';
                });
            });
        }
    }

    // ============================================================
    //  销毁（PJAX 切走时调用）
    // ============================================================
    function destroy() {
        _activeFlowId++;  // 立即失效所有运行中的 flow
        isPlaying = false;
        isFinished = true;
        isSkipping = true;  // 让 sleep() 立即退出，不再创建新的 pending Promise
        if (_resumeResolver) { _resumeResolver(); _resumeResolver = null; }

        if (_voiceTimer) { clearInterval(_voiceTimer); _voiceTimer = null; }

        // 中断未完成的数据请求
        if (_xhr) { try { _xhr.abort(); } catch(e) {} _xhr = null; }

        document.querySelectorAll('.lgnewui-chat-voice-msg.playing').forEach(function (node) {
            var a = node.querySelector('audio');
            if (a) { a.pause(); a.currentTime = 0; }
            node.classList.remove('playing');
        });

        if (_docClickHandler) {
            document.removeEventListener('click', _docClickHandler);
            _docClickHandler = null;
        }

        // 清理 AudioContext 预热监听器
        if (_primeHandler) {
            document.removeEventListener('click', _primeHandler, true);
            document.removeEventListener('touchstart', _primeHandler, true);
            document.removeEventListener('keydown', _primeHandler, true);
            _primeHandler = null;
        }

        // 清除 data-theme 残留，防止影响其他页面
        var pjax = document.getElementById('pjax-container');
        if (pjax) pjax.removeAttribute('data-theme');

        // 清空 DOM 引用，防止 PJAX 回来时误判为同一实例
        chatBox = null;
    }

    // ============================================================
    //  暴露全局
    //  生命周期由 lg-pjax.js 统一管理：
    //  - pjax:beforeReplace → LGChatModule.destroy()
    //  - pjax:complete      → LGChatModule.init()
    // ============================================================
    window.switchTheme = switchTheme;
    window.toggleMute = toggleMute;
    window.exportChat = exportChat;
    window.resetDemo = resetDemo;
    window.clearChat = clearChat;
    window.chatOpenImage = chatOpenImage;
    window.chatOpenVideo = chatOpenVideo;
    window.playVoice = playVoice;

    window.LGChatModule = { init: init, destroy: destroy };

    // 直接加载 about.php 时（非 PJAX），footer 中脚本执行时 DOM 已就绪
    if (document.getElementById('chatBox')) {
        init();
    }

})();
