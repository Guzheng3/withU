/**
 * withU 首页课表模块（与轻屿课表 App 周视图一致）
 * 数据源：/api/timetable.php?action=bootstrap（返回双方课表）
 * 布局参数对照 mikcb：表头 40 / 节次行 68 / 时间列 34 / 卡片圆角 8
 */

;(function(window, $) {
    'use strict';

    const TT_ACCENT = '#3482FF';
    const DAY_LABELS = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];

    /** 周一为一周起始：把 date 归到所在周的周一 00:00 */
    function startOfWeek(date) {
        const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
        return d;
    }

    /** 计算 date 在学期中的周次（1 起）；早于学期开始返回 null */
    function weekIndex(date, semesterStart) {
        const alignedStart = startOfWeek(semesterStart);
        const alignedTarget = startOfWeek(date);
        const diffDays = Math.round(
            (alignedTarget - alignedStart) / 86400000
        );
        if (diffDays < 0) return null;
        return Math.floor(diffDays / 7) + 1;
    }

    /** 展示用教学周：未配置开学日→fallback；开学前→1；超出→最后一周 */
    function weekForDate(date, semesterStart, semesterWeekCount, fallback) {
        if (!semesterStart) return fallback;
        const week = weekIndex(date, semesterStart);
        if (week == null) return 1;
        return Math.min(week, Math.max(1, semesterWeekCount || 30));
    }

    /** 课程在某周是否上课（对照 Course.isActiveInWeek） */
    function courseActiveInWeek(course, week) {
        if (Array.isArray(course.suspendedWeeks) && course.suspendedWeeks.includes(week)) {
            return false;
        }
        if (Array.isArray(course.customWeeks) && course.customWeeks.length > 0) {
            return course.customWeeks.includes(week);
        }
        if (week < (course.startWeek || 1) || week > (course.endWeek || 16)) {
            return false;
        }
        if (course.isOddWeek && week % 2 === 0) return false;
        if (course.isEvenWeek && week % 2 !== 0) return false;
        return true;
    }

    /** 课程候选周集合：customWeeks 优先，否则 startWeek–endWeek（对照 weekCandidates） */
    function weekCandidates(course) {
        if (Array.isArray(course.customWeeks) && course.customWeeks.length > 0) {
            return course.customWeeks.slice();
        }
        const start = Math.max(1, course.startWeek || 1);
        const end = Math.max(start, course.endWeek || start);
        const weeks = [];
        for (let w = start; w <= end; w++) weeks.push(w);
        return weeks;
    }

    /** 冲突索引：同一天、节次重叠、且在某个候选周内同时上课（对照 buildConflictMap） */
    function buildConflictMap(courses) {
        const map = new Map();
        for (let i = 0; i < courses.length; i++) {
            for (let j = i + 1; j < courses.length; j++) {
                const a = courses[i];
                const b = courses[j];
                if (!a || !b || a.dayOfWeek !== b.dayOfWeek) continue;
                if (a.endSection < b.startSection || b.endSection < a.startSection) continue;
                const candidates = new Set([...weekCandidates(a), ...weekCandidates(b)]);
                let clashes = false;
                for (const w of candidates) {
                    if (courseActiveInWeek(a, w) && courseActiveInWeek(b, w)) {
                        clashes = true;
                        break;
                    }
                }
                if (!clashes) continue;
                if (!map.has(String(a.id))) map.set(String(a.id), new Set());
                if (!map.has(String(b.id))) map.set(String(b.id), new Set());
                map.get(String(a.id)).add(String(b.id));
                map.get(String(b.id)).add(String(a.id));
            }
        }
        return map;
    }

    /** 与 App 一致的节次时间解析；无法解析时返回 null */
    function parseSections(raw, fallback) {
        if (Array.isArray(raw) && raw.length > 0) {
            const sections = [];
            for (const item of raw) {
                const start = item && typeof item.startTime === 'string' ? item.startTime : '';
                const end = item && typeof item.endTime === 'string' ? item.endTime : '';
                if (start) {
                    sections.push({ startTime: start, endTime: end });
                }
            }
            if (sections.length > 0) return sections;
        }
        return fallback || null;
    }

    /** 旧版 withU 课表（{week, courses:[{title,day,start,end}]}）转 App 格式 */
    function normalizeContent(raw) {
        if (!raw || typeof raw !== 'object') return null;
        if (raw.app !== 'mikcb') {
            // 兼容旧部署：只有课程列表的紧凑格式
            const legacyCourses = Array.isArray(raw.courses) ? raw.courses : [];
            if (legacyCourses.length === 0) return null;
            const courses = [];
            let maxSection = 0;
            const bySection = new Map();
            for (let i = 0; i < legacyCourses.length; i++) {
                const c = legacyCourses[i];
                if (!c || typeof c !== 'object') continue;
                const name = String(c.title ?? c.name ?? '').trim();
                const startTime = String(c.start ?? c.startTime ?? '').trim();
                const endTime = String(c.end ?? c.endTime ?? '').trim();
                const day = clampInt(c.day ?? c.dayOfWeek, 1, 7, 1);
                let startSection = clampInt(c.startSection, 1, 30, 1);
                let endSection = clampInt(c.endSection, 1, 30, startSection);
                // 旧格式没有节次号时按时间匹配（对照 _sectionForLegacyTime）
                if (c.startSection == null && startTime) {
                    const hit = [...bySection.entries()].find(([, t]) => t === startTime);
                    if (hit) startSection = hit[0];
                }
                if (c.endSection == null && endTime) {
                    const hit = [...bySection.entries()].find(([, t]) => t === endTime);
                    if (hit) endSection = hit[0];
                }
                bySection.set(startSection, startTime);
                if (endTime) bySection.set(endSection, endTime);
                maxSection = Math.max(maxSection, endSection);
                if (!name) continue;
                courses.push({
                    id: String(c.id ?? 'legacy-' + i),
                    name,
                    shortName: c.shortName != null ? String(c.shortName) : null,
                    teacher: String(c.teacher ?? ''),
                    location: String(c.location ?? ''),
                    dayOfWeek: day,
                    startSection,
                    endSection,
                    startTime: startTime || '08:00',
                    endTime: endTime || '09:40',
                    color: typeof c.color === 'string' && c.color ? c.color : '#2196F3',
                    startWeek: clampInt(c.startWeek, 1, 30, 1),
                    endWeek: clampInt(c.endWeek, 1, 30, 16),
                });
            }
            if (courses.length === 0) return null;
            // 按节次号补全时间列：缺失的用空串（App 默认 12 节模板）
            const sections = [];
            for (let s = 1; s <= maxSection; s++) {
                const t = bySection.get(s) || '';
                sections.push({ startTime: t, endTime: t });
            }
            return {
                profileName: raw.profileName ? String(raw.profileName) : null,
                courses,
                sections,
                currentWeek: clampInt(raw.week ?? raw.currentWeek, 1, 30, 1),
                semesterStartDate: null,
                semesterWeekCount: 12,
                timeSchemes: [],
            };
        }

        const settings = raw.settings && typeof raw.settings === 'object' ? raw.settings : {};
        const courses = Array.isArray(raw.courses)
            ? raw.courses.filter((c) => c && typeof c === 'object')
            : [];

        // 时间模板解析：activeTimeSchemeId 命中时用它（对照 App 的 scheme 解析）
        const schemes = Array.isArray(raw.timeSchemes) ? raw.timeSchemes : [];
        const activeScheme = settings.activeTimeSchemeId
            ? schemes.find((s) => s && s.id === settings.activeTimeSchemeId)
            : null;
        const sections =
            parseSections(activeScheme && activeScheme.sections ? activeScheme.sections : null, null) ||
            parseSections(settings.sections, null) ||
            buildFallbackSections(courses);

        let semesterStart = null;
        if (settings.semesterStartDate != null) {
            // App 序列化为毫秒时间戳；兜底兼容 ISO 字符串
            if (typeof settings.semesterStartDate === 'number') {
                semesterStart = new Date(settings.semesterStartDate);
            } else {
                const parsed = new Date(String(settings.semesterStartDate).replace(' ', 'T'));
                if (!isNaN(parsed.getTime())) semesterStart = parsed;
            }
            if (semesterStart && isNaN(semesterStart.getTime())) semesterStart = null;
        }

        return {
            profileName: raw.profileName ? String(raw.profileName) : null,
            courses,
            sections,
            currentWeek: clampInt(raw.currentWeek, 1, 30, 1),
            semesterStartDate: semesterStart,
            semesterWeekCount: clampInt(settings.semesterWeekCount, 1, 60, 30),
            timeSchemes: schemes,
        };
    }

    /** 无 settings 时按课程节次号生成时间列，缺失时间用占位 */
    function buildFallbackSections(courses) {
        let maxSection = 12;
        for (const c of courses) {
            maxSection = Math.max(maxSection, clampInt(c.endSection, 1, 30, 1));
        }
        const sections = [];
        for (let s = 1; s <= maxSection; s++) {
            sections.push({ startTime: '', endTime: '' });
        }
        for (const c of courses) {
            const s = clampInt(c.startSection, 1, maxSection, 1);
            if (typeof c.startTime === 'string' && c.startTime) {
                sections[s - 1] = { startTime: c.startTime, endTime: sections[s - 1].endTime };
            }
            const e = clampInt(c.endSection, 1, maxSection, 1);
            if (typeof c.endTime === 'string' && c.endTime) {
                sections[e - 1] = { startTime: sections[e - 1].startTime, endTime: c.endTime };
            }
        }
        return sections;
    }

    function clampInt(value, min, max, fallback) {
        const n = typeof value === 'number' ? value : parseInt(String(value ?? ''), 10);
        if (!isFinite(n)) return fallback;
        return Math.min(max, Math.max(min, n));
    }

    /** 时间列宽与 App 默认窄档一致 */
    const TIME_COL_WIDTH = 34;
    const SECTION_HEIGHT = 68;
    const CARD_GAP = 2;

    const TimetableModule = {
        _inited: false,
        _payload: null,
        _side: 'mine',            // mine | partner
        _visibleWeek: 1,
        _currentWeek: 1,
        _els: null,
        _timetables: { mine: null, partner: null },
        _pointer: null,

        init() {
            if (this._inited) return;
            const card = document.getElementById('withu-tt-card');
            if (!card) return;

            this._els = {
                section: document.getElementById('timetable-section'),
                card,
                loading: document.getElementById('withu-tt-loading'),
                body: document.getElementById('withu-tt-body'),
                tabs: document.getElementById('withu-tt-tabs'),
            };
            if (!this._els.body) return;

            this._inited = true;
            this._fetchAndRender();
        },

        destroy() {
            this._inited = false;
            this._payload = null;
            this._els = null;
            this._timetables = { mine: null, partner: null };
            if (this._popEl) {
                this._popEl.remove();
                this._popEl = null;
            }
        },

        async _fetchAndRender() {
            const base = (window.WITHU_CONFIG && window.WITHU_CONFIG.siteBase) || '';
            let payload = null;
            try {
                const res = await fetch(base + 'api/timetable.php?action=bootstrap', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('http ' + res.status);
                const data = await res.json();
                if (data && data.success === true) payload = data;
            } catch (_) {
                // 网络/接口异常：静默隐藏课表区，不影响首页其他模块
            }

            if (!payload || payload.logged_in !== true) {
                if (this._els.section) this._els.section.style.display = 'none';
                return;
            }

            this._payload = payload;
            this._timetables.mine = normalizeContent(payload.timetable ? payload.timetable.content : null);
            this._timetables.partner = normalizeContent(
                payload.partner_timetable ? payload.partner_timetable.content : null
            );

            const user = payload.user && typeof payload.user === 'object' ? payload.user : null;
            const partner = payload.partner && typeof payload.partner === 'object' ? payload.partner : null;
            this._names = {
                mine: displayName(user, '我的课表'),
                partner: displayName(partner, 'TA的课表'),
            };

            const active = this._timetables[this._side];
            this._currentWeek = this._resolveCurrentWeek(active);
            this._visibleWeek = this._currentWeek;

            this._buildTabs();
            this._render();
            if (this._els.section) this._els.section.style.display = '';
        },

        _resolveCurrentWeek(active) {
            if (!active) return 1;
            if (active.semesterStartDate) {
                return weekForDate(
                    new Date(),
                    active.semesterStartDate,
                    active.semesterWeekCount,
                    active.currentWeek || 1
                );
            }
            return active.currentWeek || 1;
        },

        // ---------- 双方切换 ----------
        _buildTabs() {
            const tabsEl = this._els.tabs;
            if (!tabsEl) return;
            tabsEl.innerHTML = '';
            const sides = [
                { side: 'mine', label: this._names.mine, available: !!this._timetables.mine },
                { side: 'partner', label: this._names.partner, available: !!this._timetables.partner },
            ];
            if (!this._payload.partner) {
                sides.length = 1; // 没有绑定对象时只显示自己的课表
            }
            for (const item of sides) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'withu-tt-tab' +
                    (item.side === this._side ? ' is-active' : '') +
                    (item.available ? '' : ' is-disabled');
                btn.textContent = item.label;
                btn.title = item.label;
                if (item.available) {
                    btn.addEventListener('click', () => this._switchSide(item.side));
                }
                tabsEl.appendChild(btn);
            }
        },

        _switchSide(side) {
            if (side === this._side || !this._timetables[side]) return;
            this._side = side;
            this._buildTabs();
            this._render();
        },

        // ---------- 渲染 ----------
        _render() {
            const body = this._els.body;
            if (!body) return;
            const data = this._timetables[this._side];
            if (!data) {
                this._renderEmpty();
                return;
            }
            this._renderGrid(data);
        },

        _renderEmpty() {
            const body = this._els.body;
            const name = this._names[this._side];
            body.innerHTML =
                '<div class="withu-tt-empty">' +
                '  <i class="ph-fill ph-calendar-blank withu-tt-empty-icon"></i>' +
                '  <div><b>' + escapeHtml(name) + '</b> 还没有同步课表<br>在轻屿课表 App 中登录 withU 后会自动同步</div>' +
                '</div>';
            if (this._els.loading) this._els.loading.style.display = 'none';
        },

        _renderGrid(data) {
            const body = this._els.body;
            const week = this._visibleWeek;
            const sectionCount = data.sections.length;
            const gridHeight = sectionCount * SECTION_HEIGHT;
            const monday = data.semesterStartDate
                ? new Date(startOfWeek(data.semesterStartDate).getTime() + (week - 1) * 7 * 86400000)
                : null;

            // 表头
            let headerHtml =
                '<div class="withu-tt-header">' +
                '  <div class="withu-tt-header-week" title="回到本周">' + week + '周</div>' +
                '  <div class="withu-tt-header-days">';
            for (let day = 1; day <= 7; day++) {
                const date = monday ? new Date(monday.getTime() + (day - 1) * 86400000) : null;
                const isToday = date && isSameDate(date, new Date());
                headerHtml +=
                    '<div class="withu-tt-header-day' + (isToday ? ' is-today' : '') + '">' +
                    '  <span class="withu-tt-header-day__label">' + DAY_LABELS[day - 1] + '</span>' +
                    '  <span class="withu-tt-header-day__date">' +
                    (date ? pad2(date.getMonth() + 1) + '/' + pad2(date.getDate()) : '') +
                    '</span>' +
                    '</div>';
            }
            headerHtml += '</div></div>';

            // 时间列 + 日列
            let timeCol = '<div class="withu-tt-time-col">';
            for (let i = 0; i < sectionCount; i++) {
                const s = data.sections[i];
                timeCol +=
                    '<div class="withu-tt-time-cell">' +
                    '<span class="withu-tt-time-cell__num">' + (i + 1) + '</span>' +
                    (s && s.startTime
                        ? '<span class="withu-tt-time-cell__time">' + s.startTime + '</span>'
                        : '') +
                    (s && s.endTime && s.endTime !== s.startTime
                        ? '<span class="withu-tt-time-cell__time">' + s.endTime + '</span>'
                        : '') +
                    '</div>';
            }
            timeCol += '</div>';

            let daysHtml = '';
            const conflictMap = buildConflictMap(data.courses || []);
            for (let day = 1; day <= 7; day++) {
                daysHtml += '<div class="withu-tt-day-col">' + this._renderDayCourses(data, week, day, conflictMap) + '</div>';
            }

            body.innerHTML =
                '<div class="withu-tt-grid">' +
                '  <div class="withu-tt-nav is-prev"><button type="button" aria-label="上一周">‹</button></div>' +
                '  <div class="withu-tt-nav is-next"><button type="button" aria-label="下一周">›</button></div>' +
                (week !== this._currentWeek
                    ? '<button type="button" class="withu-tt-back-week">回到本周</button>'
                    : '') +
                headerHtml +
                '<div class="withu-tt-body-row" style="min-height:' + gridHeight + 'px">' +
                timeCol + daysHtml +
                '</div>' +
                '</div>';

            if (this._els.loading) this._els.loading.style.display = 'none';

            const grid = body.querySelector('.withu-tt-grid');
            const dayCols = body.querySelectorAll('.withu-tt-day-col');
            dayCols.forEach((col) => { col.style.height = gridHeight + 'px'; });

            // 事件绑定
            grid.querySelector('.withu-tt-header-week').addEventListener('click', () => this._goCurrentWeek());
            grid.querySelector('.withu-tt-nav.is-prev button').addEventListener('click', (e) => {
                e.stopPropagation();
                this._goWeek(week - 1);
            });
            grid.querySelector('.withu-tt-nav.is-next button').addEventListener('click', (e) => {
                e.stopPropagation();
                this._goWeek(week + 1);
            });
            const backWeek = grid.querySelector('.withu-tt-back-week');
            if (backWeek) backWeek.addEventListener('click', () => this._goCurrentWeek());

            grid.querySelectorAll('.withu-tt-course').forEach((el) => {
                el.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this._showPopover(el, data);
                });
            });

            this._bindSwipe(grid);
        },

        /** 单日课程卡：按节次绝对定位铺满整列；重叠课程按 App 顺序叠加（后绘者在上） */
        _renderDayCourses(data, week, day, conflictMap) {
            const courses = data.courses
                .filter((c) => c.dayOfWeek === day && courseActiveInWeek(c, week))
                .map((c) => ({
                    ...c,
                    startSection: clampInt(c.startSection, 1, data.sections.length, 1),
                    endSection: Math.min(
                        clampInt(c.endSection, 1, data.sections.length, 1),
                        data.sections.length
                    ),
                }))
                .sort((a, b) =>
                    a.startSection - b.startSection ||
                    a.endSection - b.endSection ||
                    String(a.id).localeCompare(String(b.id))
                );

            if (courses.length === 0) return '';

            let html = '';
            for (const c of courses) {
                const top = (c.startSection - 1) * SECTION_HEIGHT + CARD_GAP;
                const height = (c.endSection - c.startSection + 1) * SECTION_HEIGHT - CARD_GAP * 2;
                const isConflict = conflictMap.has(String(c.id));
                html += this._courseCardHtml(c, top, height, isConflict);
            }
            return html;
        },

        _courseCardHtml(c, top, height, isConflict) {
            const color = typeof c.color === 'string' && /^#([0-9a-f]{6})$/i.test(c.color) ? c.color : '#2196F3';
            // 与 App 实心卡一致：色值 → 提亮 8%，冲突时整体压暗到 0.7
            const gradient = 'linear-gradient(135deg, ' +
                hexWithAlpha(color, isConflict ? 0.7 : 1) + ', ' +
                hexWithAlpha(lightenHex(color, 0.08), isConflict ? 0.7 : 1) + ')';
            const textColor = typeof c.textColor === 'string' && /^#([0-9a-f]{6})$/i.test(c.textColor) ? c.textColor : '#ffffff';

            let lines = '';
            if (isConflict) lines += '<div class="withu-tt-course__over">冲突</div>';
            lines += '<div class="withu-tt-course__name">' + escapeHtml(c.name || '课程') + '</div>';
            const subs = [];
            if (c.teacher && c.teacher.trim()) subs.push(['withu-tt-course__sub is-teacher', c.teacher]);
            if (c.location && c.location.trim()) subs.push(['withu-tt-course__sub', c.location]);
            for (const [cls, text] of subs.slice(0, 2)) {
                lines += '<div class="' + cls + '">' + escapeHtml(text) + '</div>';
            }

            return (
                '<div class="withu-tt-course' + (isConflict ? ' is-conflict' : '') + '"' +
                ' data-course-id="' + escapeAttr(c.id) + '"' +
                ' style="top:' + top + 'px;height:' + Math.max(height, 12) + 'px;' +
                'background:' + gradient + ';color:' + textColor + '">' +
                '  <div class="withu-tt-course__inner">' + lines + '</div>' +
                (isConflict ? '<span class="withu-tt-course__badge">冲突</span>' : '') +
                '</div>'
            );
        },

        // ---------- 课程详情弹层 ----------
        _showPopover(el, data) {
            const id = el.getAttribute('data-course-id');
            const course = data.courses.find((c) => String(c.id) === id);
            if (!course) return;
            if (this._popEl) this._popEl.remove();

            const weeksText = weekDescription(course);
            const pop = document.createElement('div');
            pop.className = 'withu-tt-pop';
            pop.innerHTML =
                '<div class="withu-tt-pop__title" style="color:' + popTextColor(course.color) + '">' +
                escapeHtml(course.name || '课程') + '</div>' +
                popRow('ph-fill ph-user', course.teacher) +
                popRow('ph-fill ph-map-pin', course.location) +
                popRow('ph-fill ph-clock', course.startTime + ' – ' + course.endTime + '（第' + course.startSection + '-' + course.endSection + '节）') +
                popRow('ph-fill ph-calendar-blank', weeksText);

            document.body.appendChild(pop);
            this._popEl = pop;

            const rect = el.getBoundingClientRect();
            const vw = window.innerWidth;
            const w = 260;
            let left = rect.left + rect.width / 2 - w / 2;
            left = Math.max(8, Math.min(vw - w - 8, left));
            let top = rect.bottom + 10;
            if (top + 180 > window.innerHeight) {
                top = Math.max(8, rect.top - 10 - pop.offsetHeight);
            }
            pop.style.left = left + 'px';
            pop.style.top = top + 'px';

            setTimeout(() => {
                const dismiss = (e) => {
                    if (!pop.contains(e.target)) {
                        pop.remove();
                        this._popEl = null;
                        document.removeEventListener('pointerdown', dismiss, true);
                    }
                };
                document.addEventListener('pointerdown', dismiss, true);
            }, 0);
        },

        // ---------- 切周 ----------
        _goWeek(week, data) {
            const active = this._timetables[this._side];
            const maxWeek = Math.max(1, active ? active.semesterWeekCount || 30 : 30);
            week = Math.min(maxWeek, Math.max(1, week));
            if (week === this._visibleWeek) return;
            this._visibleWeek = week;
            this._render();
        },

        _goCurrentWeek() {
            if (this._visibleWeek === this._currentWeek) return;
            this._visibleWeek = this._currentWeek;
            this._render();
        },

        _bindSwipe(grid) {
            let startX = null;
            let startY = null;
            const onDown = (e) => {
                if (e.target.closest('.withu-tt-course')) return;
                startX = e.clientX;
                startY = e.clientY;
            };
            const onUp = (e) => {
                if (startX == null) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                startX = null;
                startY = null;
                if (Math.abs(dx) > 48 && Math.abs(dy) < 40) {
                    this._goWeek(this._visibleWeek + (dx < 0 ? 1 : -1));
                }
            };
            grid.addEventListener('pointerdown', onDown);
            grid.addEventListener('pointerup', onUp);
            grid.addEventListener('pointerleave', () => { startX = null; startY = null; });
        },
    };

    // ---------- 工具 ----------
    function displayName(user, fallback) {
        if (!user) return fallback;
        const nickname = String(user.nickname || '').trim();
        return nickname || String(user.username || '').trim() || fallback;
    }

    function isSameDate(a, b) {
        return a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate();
    }

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function escapeHtml(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function escapeAttr(text) {
        return escapeHtml(text).replace(/\n/g, ' ');
    }

    /** #RRGGBB + alpha → rgba() */
    function hexWithAlpha(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    /** #RRGGBB 向白色混合 amount（对照 App secondaryFillColor 的 lerp(color, white, 0.08)） */
    function lightenHex(hex, amount) {
        const mix = (v) => Math.round(v + (255 - v) * amount);
        const r = mix(parseInt(hex.slice(1, 3), 16));
        const g = mix(parseInt(hex.slice(3, 5), 16));
        const b = mix(parseInt(hex.slice(5, 7), 16));
        return '#' + [r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('');
    }

    /** 弹层标题墨色：按课程色亮度自动黑白（与 App 的可读性兜底一致） */
    function popTextColor(color) {
        const r = parseInt(color.slice(1, 3), 16);
        const g = parseInt(color.slice(3, 5), 16);
        const b = parseInt(color.slice(5, 7), 16);
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.62 ? 'rgba(0,0,0,0.85)' : '#ffffff';
    }

    function popRow(icon, text) {
        const value = String(text ?? '').trim();
        if (!value) return '';
        return (
            '<div class="withu-tt-pop__row">' +
            '  <i class="' + icon + '"></i><span>' + escapeHtml(value) + '</span>' +
            '</div>'
        );
    }

    /** 周次描述（对照 App 的 courseWeekDescription） */
    function weekDescription(course) {
        const parts = [];
        if (Array.isArray(course.customWeeks) && course.customWeeks.length > 0) {
            parts.push('第' + course.customWeeks.join('、') + '周');
        } else {
            const start = course.startWeek || 1;
            const end = course.endWeek || 16;
            if (course.isOddWeek) parts.push('第' + start + '-' + end + '周（单周）');
            else if (course.isEvenWeek) parts.push('第' + start + '-' + end + '周（双周）');
            else if (start === end) parts.push('第' + start + '周');
            else parts.push('第' + start + '-' + end + '周');
        }
        if (Array.isArray(course.suspendedWeeks) && course.suspendedWeeks.length > 0) {
            parts.push('停课：' + course.suspendedWeeks.join('、') + '周');
        }
        return parts.join(' ');
    }

    // ---------- 注册与生命周期 ----------
    if (window.WithUApp) {
        window.WithUApp.register('indexTimetable', TimetableModule);
    }

    function boot() {
        if (document.getElementById('withu-tt-card')) {
            TimetableModule.init();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    if ($) {
        $(document)
            .off('pjax:start.withuTimetable')
            .on('pjax:start.withuTimetable', () => TimetableModule.destroy());
        $(document)
            .off('pjax:complete.withuTimetable')
            .on('pjax:complete.withuTimetable', boot);
    }

    window.WithUTimetableModule = TimetableModule;
})(window, window.jQuery);
