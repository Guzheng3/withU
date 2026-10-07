/**
 * withu-pet.js — 网页桌宠（一二 & 布布）
 * 玩法移植自 oneno-pet（Tauri 桌面应用，Away6v/oneno-pet）：
 * 进场即随机 GIF 造型、待机随机轮播、拖拽专属造型、点击弹跳换装、
 * 位置/大小/透明度记忆、右键/长按菜单。纯动画展示，无文字气泡。
 *
 * 架构约束：
 * - 本脚本由 inc/footer.php 引入（PJAX 容器之外），整页只加载一次，跨 PJAX 导航存活；
 *   根节点挂 document.body，若被意外移除则借助 pjax:complete 自愈重挂。
 * - 隐私模式（html.withu-private-mode，私密相册解锁前后动态切换）由 CSS 隐藏清单控制
 *   （withu-private.css 与 withu-shared-*.css 中 .withu-pet-root），不在此判断。
 */
(function () {
    'use strict';
    if (window.WithUPet) return;

    var DATA = window.WithUPetData || null;
    if (!DATA) return;

    var STORE_KEY = 'withu-pet.v3'; /* v3：修复尺寸哨兵 bug，弃用旧记忆 */
    var CHAR_KEYS = ['yier', 'bubu'];
    var SIZE_DEFAULT = 160, SIZE_MOBILE = 96, SIZE_MIN = 44, SIZE_MAX = 240, SIZE_STEP = 24;
    var SWITCH_BASE_MS = 60000;          // 随机轮播基准间隔（±20% 抖动）
    var LONGPRESS_MS = 550;
    var DRAG_THRESHOLD = 6;
    var OPACITY_STEPS = [1, .8, .6, .4];

    function isMobile() { return window.innerWidth <= 768; }
    function clamp(v, lo, hi) { return Math.min(hi, Math.max(lo, v)); }

    /* ── 持久化 ─────────────────────────────── */
    function loadState() {
        var def = {};
        CHAR_KEYS.forEach(function (c) {
            def[c] = { pos: null, size: 0, opacity: 1, hidden: false };
        });
        try {
            var raw = JSON.parse(localStorage.getItem(STORE_KEY) || '{}');
            CHAR_KEYS.forEach(function (c) {
                var s = raw[c] || {};
                /* 仅当存过有效尺寸才钳制；否则保持 0（跟随默认），防止哨兵值被钳到 SIZE_MIN */
                var savedSize = Number(s.size);
                def[c].size = savedSize >= SIZE_MIN ? clamp(savedSize, SIZE_MIN, SIZE_MAX) : 0;
                def[c].opacity = clamp(Number(s.opacity), 0.3, 1) || 1;
                def[c].hidden = !!s.hidden;
                if (s.pos && isFinite(s.pos.xPct) && isFinite(s.pos.yPct)) {
                    def[c].pos = { xPct: clamp(+s.pos.xPct, 0, 100), yPct: clamp(+s.pos.yPct, 0, 100) };
                }
            });
        } catch (e) { /* 存储不可用时使用默认值 */ }
        return def;
    }

    var state = loadState();
    function saveState() {
        try { localStorage.setItem(STORE_KEY, JSON.stringify(state)); } catch (e) { /* ignore */ }
    }

    /* ── 运行时 ─────────────────────────────── */
    var root = null, menuEl = null, recallEl = null;
    var pets = {};
    var menuFor = null;
    var badPoses = {};

    function petUrl(char, file) { return '/assets/pet/' + char + '/' + file; }

    function idlePoses(char) {
        return DATA[char].poses.filter(function (p) {
            return p.cat !== 'drag' && !(badPoses[char] && badPoses[char][p.file]);
        });
    }

    function gifPoses(char) {
        var gifs = idlePoses(char).filter(function (p) { return /\.gif$/.test(p.file); });
        return gifs.length ? gifs : idlePoses(char);
    }

    function dragPose(char) {
        return DATA[char].poses.filter(function (p) { return p.cat === 'drag'; })[0] || null;
    }

    function defaultSize() { return isMobile() ? SIZE_MOBILE : SIZE_DEFAULT; }

    /* 默认落位：一二偏左、布布偏右，蹲在底部（移动端抬到底部导航上方） */
    function defaultPos(char) {
        var w = state[char].size || defaultSize();
        var bottomPad = isMobile() ? 96 : 28;
        return {
            xPct: char === 'yier' ? 10 : 76,
            yPct: (window.innerHeight - bottomPad - w * 0.7) / window.innerHeight * 100
        };
    }

    function applyLayout(pet) {
        var size = pet.state.size || defaultSize();
        var pos = pet.state.pos || defaultPos(pet.char);
        pet.el.style.width = size + 'px';
        pet.el.style.opacity = pet.state.opacity;
        var x = clamp(pos.xPct / 100 * window.innerWidth, 4, window.innerWidth - size - 4);
        var y = clamp(pos.yPct / 100 * window.innerHeight, 4, window.innerHeight - size * 0.85 - 4);
        pet.el.style.left = Math.round(x) + 'px';
        pet.el.style.top = Math.round(y) + 'px';
    }

    function savePos(pet) {
        pet.state.pos = {
            xPct: parseFloat(pet.el.style.left) / window.innerWidth * 100,
            yPct: parseFloat(pet.el.style.top) / window.innerHeight * 100
        };
        saveState();
    }

    /* ── 造型切换（按需预载后换图，失败降级跳过） ── */
    function setPose(pet, pose, done) {
        if (!pose) return;
        var url = petUrl(pet.char, pose.file);
        var im = new Image();
        im.onload = function () {
            pet.img.src = url;
            pet.img.alt = DATA[pet.char].name;
            pet.curFile = pose.file;
            if (done) done();
        };
        im.onerror = function () {
            (badPoses[pet.char] = badPoses[pet.char] || {})[pose.file] = true;
            var pool = idlePoses(pet.char);
            if (pool.length) setPose(pet, pool[Math.floor(Math.random() * pool.length)], done);
        };
        im.src = url;
    }

    function pickRandom(poseList, avoidFile) {
        var usable = poseList.filter(function (p) { return p.file !== avoidFile; });
        if (!usable.length) usable = poseList;
        return usable[Math.floor(Math.random() * usable.length)] || null;
    }

    function scheduleSwitch(pet, delay) {
        clearTimeout(pet.switchTimer);
        pet.switchTimer = setTimeout(function () { tickSwitch(pet); }, delay);
    }

    function tickSwitch(pet) {
        if (document.hidden) { scheduleSwitch(pet, 10000); return; }
        if (pet.dragging) { scheduleSwitch(pet, 5000); return; }
        var pool = idlePoses(pet.char);
        if (pool.length) setPose(pet, pickRandom(pool, pet.curFile));
        var jitter = SWITCH_BASE_MS * (0.8 + Math.random() * 0.4);
        scheduleSwitch(pet, jitter);
    }

    /* ── 点击：弹跳一下并立即换一个随机 GIF 造型 ── */
    function pokeAction(pet) {
        pet.el.classList.remove('is-poking');
        void pet.el.offsetWidth;
        pet.el.classList.add('is-poking');
        setTimeout(function () { pet.el.classList.remove('is-poking'); }, 480);
        setPose(pet, pickRandom(gifPoses(pet.char), pet.curFile));
        scheduleSwitch(pet, SWITCH_BASE_MS);
    }

    /* ── 拖拽（Pointer Events 统一鼠标/触摸） ── */
    function bindPointer(pet) {
        var startX = 0, startY = 0, offX = 0, offY = 0, moved = false, pressed = false;

        pet.el.addEventListener('pointerdown', function (e) {
            if (e.button !== 2 && e.button !== 1) {
                pressed = true; moved = false;
                startX = e.clientX; startY = e.clientY;
                var rect = pet.el.getBoundingClientRect();
                offX = e.clientX - rect.left; offY = e.clientY - rect.top;
                try { pet.el.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
                clearTimeout(pet.longPressTimer);
                pet.longPressTimer = setTimeout(function () {
                    if (pressed && !moved) { pressed = false; openMenu(pet, startX, startY); }
                }, LONGPRESS_MS);
            }
            e.preventDefault();
        });

        pet.el.addEventListener('pointermove', function (e) {
            if (!pressed) return;
            var dx = e.clientX - startX, dy = e.clientY - startY;
            if (!moved && Math.hypot(dx, dy) > DRAG_THRESHOLD) {
                moved = true;
                clearTimeout(pet.longPressTimer);
                pet.dragging = true;
                pet.preDragFile = pet.curFile; /* 松手后恢复到拖拽前的造型 */
                pet.el.classList.add('is-dragging');
                var dp = dragPose(pet.char);
                if (dp) setPose(pet, dp);
            }
            if (moved) {
                var size = pet.el.offsetWidth;
                pet.el.style.left = clamp(e.clientX - offX, 4, window.innerWidth - size - 4) + 'px';
                pet.el.style.top = clamp(e.clientY - offY, 4, window.innerHeight - size * 0.85 - 4) + 'px';
            }
        });

        function finish(e, allowClick) {
            if (!pressed) return;
            pressed = false;
            clearTimeout(pet.longPressTimer);
            if (pet.dragging) {
                pet.dragging = false;
                pet.el.classList.remove('is-dragging');
                savePos(pet);
                var pool = idlePoses(pet.char);
                var back = pool.filter(function (p) { return p.file === pet.preDragFile; })[0];
                setPose(pet, back || pickRandom(pool, null));
                scheduleSwitch(pet, SWITCH_BASE_MS);
            } else if (allowClick && !moved) {
                pokeAction(pet);
            }
            if (e && e.pointerId !== undefined) {
                try { pet.el.releasePointerCapture(e.pointerId); } catch (err) { /* ignore */ }
            }
        }

        pet.el.addEventListener('pointerup', function (e) { finish(e, true); });
        pet.el.addEventListener('pointercancel', function (e) { finish(e, false); });
        pet.el.addEventListener('contextmenu', function (e) {
            e.preventDefault();
            openMenu(pet, e.clientX, e.clientY);
        });
    }

    /* ── 菜单 ───────────────────────────────── */
    function buildMenu() {
        menuEl = document.createElement('div');
        menuEl.className = 'withu-pet-menu';
        menuEl.innerHTML =
            '<div class="withu-pet-menu-char"></div>' +
            '<button class="withu-pet-menu-item" data-act="pose"><i class="ico">✨</i>换个造型</button>' +
            '<button class="withu-pet-menu-item" data-act="bigger"><i class="ico">＋</i>大一点</button>' +
            '<button class="withu-pet-menu-item" data-act="smaller"><i class="ico">－</i>小一点</button>' +
            '<button class="withu-pet-menu-item" data-act="opacity"><i class="ico">◐</i>透明一点</button>' +
            '<button class="withu-pet-menu-item" data-act="hide"><i class="ico">🙈</i>躲起来</button>';
        menuEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.withu-pet-menu-item');
            if (!btn || !menuFor) return;
            var pet = menuFor;
            switch (btn.getAttribute('data-act')) {
                case 'pose':
                    var pool = idlePoses(pet.char);
                    if (pool.length) {
                        setPose(pet, pickRandom(pool, pet.curFile));
                        scheduleSwitch(pet, SWITCH_BASE_MS);
                    }
                    break;
                case 'bigger':
                    pet.state.size = clamp((pet.state.size || defaultSize()) + SIZE_STEP, SIZE_MIN, SIZE_MAX);
                    applyLayout(pet); saveState();
                    break;
                case 'smaller':
                    pet.state.size = clamp((pet.state.size || defaultSize()) - SIZE_STEP, SIZE_MIN, SIZE_MAX);
                    applyLayout(pet); saveState();
                    break;
                case 'opacity': {
                    var cur = pet.state.opacity || 1;
                    var idx = OPACITY_STEPS.indexOf(cur);
                    pet.state.opacity = OPACITY_STEPS[(idx + 1) % OPACITY_STEPS.length] || 1;
                    applyLayout(pet); saveState();
                    break;
                }
                case 'hide':
                    pet.state.hidden = true;
                    pet.el.classList.add('is-hidden');
                    saveState(); updateRecall();
                    break;
            }
            closeMenu();
        });
        root.appendChild(menuEl);
    }

    function openMenu(pet, x, y) {
        menuFor = pet;
        menuEl.querySelector('.withu-pet-menu-char').textContent = DATA[pet.char].name;
        menuEl.classList.add('is-open');
        var mw = menuEl.offsetWidth || 140, mh = menuEl.offsetHeight || 180;
        menuEl.style.left = clamp(x, 6, window.innerWidth - mw - 6) + 'px';
        menuEl.style.top = clamp(y, 6, window.innerHeight - mh - 6) + 'px';
    }

    function closeMenu() {
        menuFor = null;
        if (menuEl) menuEl.classList.remove('is-open');
    }

    /* ── 唤回 chip ──────────────────────────── */
    function buildRecall() {
        recallEl = document.createElement('button');
        recallEl.className = 'withu-pet-recall';
        recallEl.innerHTML = '<i class="ico" style="font-style:normal">🐾</i>桌宠躲起来了，点我唤回';
        recallEl.addEventListener('click', function () {
            CHAR_KEYS.forEach(function (c) {
                pets[c].state.hidden = false;
                pets[c].el.classList.remove('is-hidden');
            });
            saveState(); updateRecall();
        });
        root.appendChild(recallEl);
    }

    function updateRecall() {
        var anyHidden = CHAR_KEYS.some(function (c) { return pets[c] && pets[c].state.hidden; });
        if (recallEl) recallEl.classList.toggle('is-visible', anyHidden);
    }

    /* ── 挂载 ───────────────────────────────── */
    function makePet(char) {
        var el = document.createElement('div');
        el.className = 'withu-pet';
        var img = document.createElement('img');
        img.className = 'withu-pet-img';
        img.alt = DATA[char].name;
        img.draggable = false;
        el.appendChild(img);
        root.appendChild(el);

        var pet = { char: char, el: el, img: img, state: state[char], dragging: false, curFile: null, switchTimer: 0, longPressTimer: 0 };
        pets[char] = pet;

        if (pet.state.hidden) el.classList.add('is-hidden');
        applyLayout(pet);
        bindPointer(pet);

        /* 进场即随机 GIF 造型，不等轮播 */
        var first = pickRandom(gifPoses(char), null);
        if (first) setPose(pet, first);
        return pet;
    }

    function warmPreload() {
        var warm = function () {
            CHAR_KEYS.forEach(function (c) {
                var dp = dragPose(c);
                if (dp) new Image().src = petUrl(c, dp.file);
                idlePoses(c).filter(function (p) { return /\.webp$/.test(p.file); })
                    .sort(function (a, b) { return a.bytes - b.bytes; })
                    .slice(0, 2)
                    .forEach(function (p) { new Image().src = petUrl(c, p.file); });
            });
        };
        if ('requestIdleCallback' in window) requestIdleCallback(warm, { timeout: 8000 });
        else setTimeout(warm, 3000);
    }

    function mount() {
        if (root && document.body.contains(root)) return;
        if (!document.body) return;

        root = document.createElement('div');
        root.id = 'withu-pet-root';
        root.className = 'withu-pet-root';
        document.body.appendChild(root);

        CHAR_KEYS.forEach(function (c) {
            if (pets[c]) { root.appendChild(pets[c].el); applyLayout(pets[c]); }
            else makePet(c);
        });
        if (!menuEl) buildMenu(); else root.appendChild(menuEl);
        if (!recallEl) buildRecall(); else root.appendChild(recallEl);
        updateRecall();

        /* 两只错峰开轮，避免同频换装 */
        scheduleSwitch(pets.yier, 20000);
        scheduleSwitch(pets.bubu, 34000);
        warmPreload();
    }

    window.addEventListener('resize', function () {
        CHAR_KEYS.forEach(function (c) { if (pets[c] && !pets[c].state.hidden) applyLayout(pets[c]); });
    });

    document.addEventListener('pointerdown', function (e) {
        if (menuEl && menuEl.classList.contains('is-open') &&
            !menuEl.contains(e.target) && menuFor && !menuFor.el.contains(e.target)) {
            closeMenu();
        }
    }, true);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenu();
    });

    /* PJAX 导航后自愈：根节点若被移出 DOM 则重挂（状态在内存/localStorage，不丢） */
    ['pjax:complete', 'pjax:end'].forEach(function (ev) {
        document.addEventListener(ev, function () { mount(); });
    });

    function boot() { mount(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
