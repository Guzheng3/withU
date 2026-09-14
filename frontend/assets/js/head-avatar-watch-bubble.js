/**
 * 头像区「对方正在看」邀请气泡
 * 轮询 partner_status：另一半正在看电视（不管开没开一起看）时，
 * 在对方头像上方弹出消息气泡「宝宝，我在看xx，快来和我一起看吧」。
 * 点击气泡可直达播放页加入一起看；关闭后同一影片不再重复弹出。
 */
(function () {
    'use strict';
    if (window.WithUWatchBubble) return;

    var POLL_MS = 10000;
    var FIRST_DELAY = 1500;
    var API_URL = '/api/watch.php?action=partner_status';
    var state = {
        timer: null,
        inFlight: false,
        stopped: false,
        shownKey: null,
        dismissedKey: null
    };

    function mediaKey(media) {
        if (!media) return '';
        return (media.media_id || 0) + ':' + (media.source_episode || 0);
    }

    function composeMessage(media) {
        var name = ((media && (media.series_name || media.file_name)) || '').trim();
        var text = '宝宝，我在看' + (name ? '《<b>' + escapeHtml(name) + '</b>》' : '一部好片');
        var ep = media && parseInt(media.episode_number, 10) || 0;
        if (ep > 0) text += ' 第 ' + ep + ' 集';
        return text + '，快来和我一起看吧';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // gender 对应头像区：male → 他，female → 她（主题可能左右互换，气泡朝向再按实际位置判断）
    function findAvatarArea(gender) {
        var scope = gender === 'female' ? '.img-female' : '.img-male';
        return document.querySelector(scope + ' .avatarArea') || document.querySelector('.avatarArea');
    }

    // 头像在行右半边时气泡尾巴镜像到右下角
    function isRightHalf(area) {
        var mid = document.querySelector('.bg-img .middle');
        if (!mid) return false;
        var a = area.getBoundingClientRect();
        var m = mid.getBoundingClientRect();
        return a.left + a.width / 2 > m.left + m.width / 2;
    }

    function removeBubble() {
        var nodes = document.querySelectorAll('.withu-watch-bubble');
        for (var i = 0; i < nodes.length; i++) nodes[i].parentNode.removeChild(nodes[i]);
        state.shownKey = null;
    }

    function buildBubble(data, mediaKeyStr) {
        var media = data.media || {};
        var bubble = document.createElement('div');
        bubble.className = 'withu-watch-bubble';
        bubble.setAttribute('data-watch-media-key', mediaKeyStr);
        bubble.setAttribute('role', 'status');
        bubble.setAttribute('aria-live', 'polite');

        var card = document.createElement('div');
        card.className = 'withu-watch-bubble-card';

        var head = document.createElement('div');
        head.className = 'withu-watch-bubble-head';
        if (media.cover_url) {
            var cover = document.createElement('img');
            cover.className = 'withu-watch-bubble-cover';
            cover.src = media.cover_url;
            cover.alt = '';
            cover.loading = 'lazy';
            cover.draggable = false;
            head.appendChild(cover);
        }
        var live = document.createElement('span');
        live.className = 'withu-watch-bubble-live';
        live.textContent = '正在看';
        head.appendChild(live);

        var text = document.createElement('p');
        text.className = 'withu-watch-bubble-text';
        text.innerHTML = composeMessage(media);

        var foot = document.createElement('div');
        foot.className = 'withu-watch-bubble-foot';
        var cta = document.createElement('span');
        cta.className = 'withu-watch-bubble-cta';
        cta.textContent = '点击加入一起看 ›';
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'withu-watch-bubble-close';
        close.setAttribute('aria-label', '关闭提示');
        close.title = '关闭';
        close.textContent = '×';
        foot.appendChild(cta);
        foot.appendChild(close);

        card.appendChild(head);
        card.appendChild(text);
        card.appendChild(foot);
        bubble.appendChild(card);

        bubble.addEventListener('click', function () {
            if (data.play_url) window.location.href = data.play_url;
        });
        close.addEventListener('click', function (e) {
            e.stopPropagation();
            state.dismissedKey = mediaKeyStr;
            removeBubble();
        });

        return bubble;
    }

    function render(data) {
        var media = data.media || {};
        var key = mediaKey(media);
        if (state.dismissedKey === key) {
            removeBubble();
            return;
        }
        var area = findAvatarArea((data.partner || {}).gender);
        if (!area) return;
        var existing = area.querySelector('.withu-watch-bubble');
        if (existing && existing.getAttribute('data-watch-media-key') === key) return;
        removeBubble();
        var bubble = buildBubble(data, key);
        if (isRightHalf(area)) bubble.classList.add('is-right');
        area.appendChild(bubble);
        state.shownKey = key;
    }

    function tick() {
        if (state.stopped || state.inFlight || document.hidden) return;
        if (!document.querySelector('.avatarArea')) return;
        state.inFlight = true;
        fetch(API_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                if (!res.ok) throw new Error('http ' + res.status);
                var ct = res.headers.get('content-type') || '';
                if (ct.indexOf('json') === -1) throw new Error('not json');
                return res.json();
            })
            .then(function (json) {
                if (!json || json.success !== true) return;
                if (!json.watching || json.together) {
                    if (state.shownKey !== null) removeBubble();
                    return;
                }
                render(json);
            })
            .catch(function () {})
            .then(function () { state.inFlight = false; });
    }

    function start() {
        if (state.timer || state.stopped) return;
        state.timer = setInterval(tick, POLL_MS);
        setTimeout(tick, FIRST_DELAY);
    }

    window.WithUWatchBubble = { start: start, tick: tick };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
