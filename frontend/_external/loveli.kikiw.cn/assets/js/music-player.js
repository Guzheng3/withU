// ==================== Music Player v3.0.8 ====================
// ==================== 统一设置管理 ====================
const MUSIC_STORAGE_KEY = 'lgnewui-music-set';

// 获取所有设置
function getMusicSettings() {
  try {
    const data = localStorage.getItem(MUSIC_STORAGE_KEY);
    return data ? JSON.parse(data) : {};
  } catch (e) {
    return {};
  }
}

// 保存设置（合并更新）
function saveMusicSetting(key, value) {
  const settings = getMusicSettings();
  settings[key] = value;
  localStorage.setItem(MUSIC_STORAGE_KEY, JSON.stringify(settings));
}

// 获取单个设置
function getMusicSetting(key, defaultValue) {
  const settings = getMusicSettings();
  return settings[key] !== undefined ? settings[key] : defaultValue;
}

// HTML 转义，防止 XSS
function _mpEsc(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// ==================== 全局状态 ====================
let lg_love_musicPlaying = false;
let lg_love_musicFirst = false;
let musicBindInited = false;
let musicTelescopicInited = false;

// 检测是否为移动端
const isMobile = window.matchMedia("(hover: none) and (pointer: coarse)").matches;

// 缓存 DOM 元素
const navMusicEl = document.getElementById("nav-music");
let _cachedMetingEl = null;

// 获取 meting 元素（缓存）
function getMetingEl() {
  if (!_cachedMetingEl) {
    _cachedMetingEl = document.querySelector("#nav-music meting-js");
  }
  return _cachedMetingEl;
}

// 获取 aplayer 实例（带安全检查）
function getAPlayer() {
  const meting = getMetingEl();
  return meting && meting.aplayer ? meting.aplayer : null;
}

// 带重试限制的等待函数
const MAX_RETRY = 50;
function waitForCondition(checkFn, callback, interval = 200, retryCount = 0) {
  if (checkFn()) {
    callback();
  } else if (retryCount < MAX_RETRY) {
    setTimeout(() => waitForCondition(checkFn, callback, interval, retryCount + 1), interval);
  } else {
    console.warn('[Music Player] 等待超时，放弃重试');
  }
}

// 移动端控制按钮是否可交互
let mobileControlsReady = false;

// ==================== 主控制对象 ====================
const lg_love = {
  // 切换音乐播放状态
  musicToggle: function (changePaly = true) {
    if (!lg_love_musicFirst) {
      musicBindEvent();
      lg_love_musicFirst = true;
    }
    const msgPlayIcon =
      '<svg viewBox="0 0 1024 1024" class="lgnewui-nav-music-play-icon" aria-hidden="true"><path d="M324.085 95.787l500.422 300.664c82.373 50.453 79.284 136.946-1.03 186.37v0l-506.6 304.784c-41.187 23.683-87.522 37.068-131.798 9.267-36.037-22.653-46.335-58.691-46.335-97.819v-616.774c0-39.127 13.386-75.166 48.395-97.819 45.305-27.801 94.731-14.416 136.946 11.327v0z" fill="#ffffff"/></svg>';

    // 读取 meting-js 标签的 data-expand 属性，决定是否默认展开
    const metingEl = getMetingEl();
    const shouldExpand = metingEl && metingEl.getAttribute('data-expand') === 'true';

    if (lg_love_musicPlaying) {
      navMusicEl.classList.remove("playing");
      document.getElementById("nav-music-hoverTips").innerHTML = msgPlayIcon;
      lg_love_musicPlaying = false;
      navMusicEl.classList.remove("stretch");
      mobileControlsReady = false;
    } else {
      navMusicEl.classList.add("playing");
      lg_love_musicPlaying = true;
      // 根据 data-expand 属性决定是否展开
      if (shouldExpand) {
        navMusicEl.classList.add("stretch");
        // 移动端展开后，延迟允许控制按钮交互
        if (isMobile) {
          mobileControlsReady = false;
          setTimeout(() => { mobileControlsReady = true; }, 300);
        }
      }
    }
    if (changePaly) {
      // 使用带重试限制的等待函数
      waitForCondition(
        () => getAPlayer() !== null,
        () => getAPlayer().toggle(),
        100
      );
    }
  },

  // 音乐伸缩
  musicTelescopic: function () {
    if (navMusicEl.classList.contains("stretch")) {
      navMusicEl.classList.remove("stretch");
      mobileControlsReady = false;
    } else {
      navMusicEl.classList.add("stretch");
      if (isMobile) {
        mobileControlsReady = false;
        setTimeout(() => { mobileControlsReady = true; }, 300);
      }
    }
  },

  // 音乐上一曲
  musicSkipBack: function () {
    const ap = getAPlayer();
    if (ap) ap.skipBack();
  },

  // 音乐下一曲
  musicSkipForward: function () {
    const ap = getAPlayer();
    if (ap) ap.skipForward();
  },

  // 获取音乐中的名称
  musicGetName: function () {
    const x = document.querySelectorAll(".aplayer-title");
    return x.length > 0 ? x[0].innerText : '';
  },
};

// 挂载到全局
window.lg_love = lg_love;

// ==================== 移动端控制按钮交互逻辑 ====================
if (isMobile && navMusicEl) {
  let controls = null;

  function getControls() {
    if (!controls) {
      controls = navMusicEl.querySelector(".lgnewui-nav-music-controls");
    }
    return controls;
  }

  // 给控制按钮绑定事件拦截
  function bindControlButtons() {
    const ctrl = getControls();
    if (!ctrl) {
      setTimeout(bindControlButtons, 200);
      return;
    }

    const buttons = ctrl.querySelectorAll(".lgnewui-nav-music-btn");
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        // 如果控件隐藏或未就绪，阻止事件并显示控件
        if (ctrl.classList.contains("mobile-hidden") || !mobileControlsReady) {
          e.stopPropagation();
          e.preventDefault();
          e.stopImmediatePropagation();

          if (ctrl.classList.contains("mobile-hidden")) {
            ctrl.classList.remove("mobile-hidden");
            mobileControlsReady = false;
            setTimeout(function () {
              mobileControlsReady = true;
            }, 300);
          }
          return false;
        }
      }, true); // 捕获阶段
    });

    // 点击控件区域（非按钮）时显示控件
    ctrl.addEventListener("click", function (e) {
      if (ctrl.classList.contains("mobile-hidden")) {
        e.stopPropagation();
        e.preventDefault();
        ctrl.classList.remove("mobile-hidden");
        mobileControlsReady = false;
        setTimeout(function () {
          mobileControlsReady = true;
        }, 300);
      }
    }, true);
  }

  // 点击播放器外部，隐藏控制按钮
  document.addEventListener("click", function (e) {
    const ctrl = getControls();
    if (!ctrl) return;

    if (!navMusicEl.contains(e.target) && navMusicEl.classList.contains("stretch")) {
      ctrl.classList.add("mobile-hidden");
      mobileControlsReady = false;
    }
  }, { passive: true });

  // 等待 DOM 就绪后绑定
  if (document.readyState === "complete" || document.readyState === "interactive") {
    setTimeout(bindControlButtons, 500);
  } else {
    document.addEventListener("DOMContentLoaded", function () {
      setTimeout(bindControlButtons, 500);
    });
  }
}

// ==================== 进度条同步 ====================
function bindProgressSync() {
  const ap = getAPlayer();
  if (!ap) return;

  const progressWrap = document.getElementById('nav-music-progress');
  const playedBar = progressWrap?.querySelector(".lgnewui-nav-music-progress-played");
  const loadedBar = progressWrap?.querySelector(".lgnewui-nav-music-progress-loaded");
  const thumb = progressWrap?.querySelector(".lgnewui-nav-music-progress-thumb");
  const loadingEl = progressWrap?.querySelector(".lgnewui-nav-music-progress-loading"); // 缓存 loading 元素

  if (!progressWrap || !playedBar) return;

  let rafId = null;
  let lastProgress = 0;
  let lastBuffered = 0;
  let isBuffering = false;

  // 更新播放进度 + 滑块位置 + loading 位置
  function updateProgress() {
    if (!ap.audio.duration) return;
    const p = ap.audio.currentTime / ap.audio.duration;
    if (Math.abs(p - lastProgress) > 0.001) {
      lastProgress = p;
      const percent = (p * 100).toFixed(2) + "%";
      playedBar.style.width = percent;
      // 滑块位置
      if (thumb) {
        thumb.style.left = percent;
      }
      // loading 圆跟随滑块位置
      if (loadingEl) {
        loadingEl.style.left = percent;
      }
    }
  }

  // 更新缓冲进度
  function updateBuffered() {
    if (!ap.audio.duration || !ap.audio.buffered.length) return;

    // 获取当前播放位置对应的缓冲区域
    let bufferedEnd = 0;
    for (let i = 0; i < ap.audio.buffered.length; i++) {
      if (ap.audio.buffered.start(i) <= ap.audio.currentTime &&
        ap.audio.buffered.end(i) >= ap.audio.currentTime) {
        bufferedEnd = ap.audio.buffered.end(i);
        break;
      }
    }

    const b = bufferedEnd / ap.audio.duration;
    if (Math.abs(b - lastBuffered) > 0.005) {
      lastBuffered = b;
      if (loadedBar) {
        loadedBar.style.width = (b * 100).toFixed(2) + "%";
      }
    }
  }

  // 设置加载中状态
  function setLoadingState(loading) {
    if (loading !== isBuffering) {
      isBuffering = loading;
      if (loading) {
        progressWrap.classList.add('is-loading');
      } else {
        progressWrap.classList.remove('is-loading');
      }
    }
  }

  // 综合更新函数
  function updateAll() {
    updateProgress();
    updateBuffered();
  }

  ap.on("timeupdate", function () {
    if (rafId) return;
    rafId = requestAnimationFrame(function () {
      updateAll();
      rafId = null;
    });
  });

  ap.on("seeked", function () {
    updateProgress();
    updateBuffered();
  });

  // 监听切歌，重置进度条
  ap.on("listswitch", function () {
    lastProgress = 0;
    lastBuffered = 0;
    playedBar.style.width = "0%";
    if (loadedBar) loadedBar.style.width = "0%";
    if (thumb) thumb.style.left = "0%";
    if (loadingEl) loadingEl.style.left = "0%";
  });

  // 监听缓冲进度
  ap.audio.addEventListener("progress", updateBuffered);

  // 监听等待/播放事件来控制加载状态
  ap.audio.addEventListener("waiting", function () {
    setLoadingState(true);
  });

  ap.audio.addEventListener("playing", function () {
    setLoadingState(false);
  });

  ap.audio.addEventListener("canplay", function () {
    setLoadingState(false);
  });

  // 监听歌曲播放结束，确保自动播放下一首
  ap.audio.addEventListener("ended", function () {
    // 延迟一点检查，给 APlayer 时间处理
    setTimeout(function () {
      // 如果 APlayer 没有自动播放下一首，手动触发
      if (ap.audio.paused && ap.list && ap.list.audios && ap.list.audios.length > 1) {
        ap.skipForward();
        ap.play();
      }
    }, 100);
  });

  // 监听系统控制（锁屏、通知栏等）的播放/暂停
  ap.audio.addEventListener("pause", function () {
    setLoadingState(false);
    if (lg_love_musicPlaying) {
      navMusicEl.classList.remove("playing");
      navMusicEl.classList.remove("stretch");
      document.getElementById("nav-music-hoverTips").innerHTML = '<svg viewBox="0 0 1024 1024" class="lgnewui-nav-music-play-icon" aria-hidden="true"><path d="M324.085 95.787l500.422 300.664c82.373 50.453 79.284 136.946-1.03 186.37v0l-506.6 304.784c-41.187 23.683-87.522 37.068-131.798 9.267-36.037-22.653-46.335-58.691-46.335-97.819v-616.774c0-39.127 13.386-75.166 48.395-97.819 45.305-27.801 94.731-14.416 136.946 11.327v0z" fill="#ffffff"/></svg>';
      lg_love_musicPlaying = false;
      mobileControlsReady = false;
    }
  });

  ap.audio.addEventListener("play", function () {
    if (!lg_love_musicPlaying) {
      navMusicEl.classList.add("playing");
      navMusicEl.classList.add("stretch");
      lg_love_musicPlaying = true;
      if (isMobile) {
        mobileControlsReady = false;
        setTimeout(function () {
          mobileControlsReady = true;
        }, 300);
      }
    }
  });

  // ==================== 进度条点击 Seek 功能 ====================
  if (progressWrap) {
    function handleSeek(e) {
      // 如果正在加载中，不允许点击
      if (progressWrap.classList.contains('is-loading')) {
        e.stopPropagation();
        e.preventDefault();
        return;
      }

      e.stopPropagation(); // 阻止冒泡，防止触发拖拽
      e.preventDefault();

      if (!ap.audio.duration) return;

      const rect = progressWrap.getBoundingClientRect();
      const clientX = e.touches ? e.touches[0].clientX : e.clientX;
      let percentage = (clientX - rect.left) / rect.width;
      percentage = Math.max(0, Math.min(1, percentage));

      ap.seek(percentage * ap.audio.duration);
      ap.play(); // Seek后自动播放
    }

    // 绑定点击和触摸事件
    progressWrap.addEventListener('click', handleSeek);
    progressWrap.addEventListener('touchstart', handleSeek, { passive: false });

    // 阻止 mousedown 冒泡，确保从按下的那一刻起就不会触发长按拖动
    progressWrap.addEventListener('mousedown', function (e) { e.stopPropagation(); });
  }
}

// 使用带重试限制的等待函数
waitForCondition(
  () => getAPlayer() !== null,
  bindProgressSync,
  200
);

// ==================== 音乐绑定事件 ====================
function musicBindEvent() {
  if (musicBindInited) return;
  musicBindInited = true;

  const metingEl = getMetingEl();
  if (!navMusicEl || !metingEl) return;

  function bindThemeWhenReady() {
    const ap = getAPlayer();
    if (!ap) {
      setTimeout(bindThemeWhenReady, 120);
      return;
    }
    const colorThief = window.ColorThief ? new ColorThief() : null;
    let lastCover = null;

    function setThemeFromCover() {
      try {
        if (!ap.list || !ap.list.audios || ap.list.audios.length === 0) return;
        const index = ap.list.index || 0;
        const audio = ap.list.audios[index];
        if (!audio || !audio.cover) return;

        if (audio.cover === lastCover) return;
        lastCover = audio.cover;

        const img = new Image();
        img.crossOrigin = "anonymous";
        img.src = audio.cover;

        img.onload = function () {
          try {
            if (!colorThief) return;

            // 取调色板，从中选最鲜艳好看的颜色
            var palette = colorThief.getPalette(img, 6);
            if (!palette || palette.length === 0) return;

            function rgb2hsl(r, g, b) {
              r /= 255; g /= 255; b /= 255;
              var max = Math.max(r, g, b), min = Math.min(r, g, b);
              var h, s, l = (max + min) / 2;
              if (max === min) { h = s = 0; }
              else {
                var d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                switch (max) {
                  case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                  case g: h = ((b - r) / d + 2) / 6; break;
                  case b: h = ((r - g) / d + 4) / 6; break;
                }
              }
              return { h: h, s: s, l: l };
            }

            function hsl2rgb(h, s, l) {
              var r2, g2, b2;
              if (s === 0) { r2 = g2 = b2 = l; }
              else {
                function hue2rgb(p, q, t) {
                  if (t < 0) t += 1; if (t > 1) t -= 1;
                  if (t < 1/6) return p + (q - p) * 6 * t;
                  if (t < 1/2) return q;
                  if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                  return p;
                }
                var q2 = l < 0.5 ? l * (1 + s) : l + s - l * s;
                var p2 = 2 * l - q2;
                r2 = hue2rgb(p2, q2, h + 1/3);
                g2 = hue2rgb(p2, q2, h);
                b2 = hue2rgb(p2, q2, h - 1/3);
              }
              return [Math.round(r2 * 255), Math.round(g2 * 255), Math.round(b2 * 255)];
            }

            var hslAll = palette.map(function(c) { return rgb2hsl(c[0], c[1], c[2]); });

            // 检测灰度封面：所有颜色饱和度都很低
            var maxSat = Math.max.apply(null, hslAll.map(function(h) { return h.s; }));
            var isGray = maxSat < 0.15;

            if (isGray) {
              // 灰度封面：选亮度最接近 0.35 的颜色，保持中性灰，不加饱和度
              var best = hslAll[0], bestDist = Math.abs(hslAll[0].l - 0.35);
              hslAll.forEach(function(h) {
                var d = Math.abs(h.l - 0.35);
                if (d < bestDist) { bestDist = d; best = h; }
              });
              best.s = Math.min(best.s, 0.08); // 压掉残余色偏
              if (best.l > 0.45) best.l = 0.45;
              if (best.l < 0.25) best.l = 0.25;
            } else {
              // 检测暗色封面：超过半数颜色亮度 < 0.25
              var darkCount = hslAll.filter(function(h) { return h.l < 0.25; }).length;
              var isDark = darkCount >= palette.length * 0.5;

              // 给每个颜色打分，前面的颜色（面积大）有额外权重
              var best = null, bestScore = -1;
              palette.forEach(function(c, idx) {
                var hsl = rgb2hsl(c[0], c[1], c[2]);
                if (hsl.s < 0.12) return; // 跳过灰色/微弱色偏
                var targetL = isDark ? 0.30 : 0.42;
                var lScore = 1 - Math.abs(hsl.l - targetL) / 0.5;
                var score = hsl.s * 0.65 + Math.max(0, lScore) * 0.25;
                if (idx < 3) score += 0.1 * (3 - idx) / 3;
                if (score > bestScore) { bestScore = score; best = hsl; }
              });

              if (!best) best = hslAll[0];

              if (isDark) {
                if (best.l > 0.40) best.l = 0.40;
                if (best.l < 0.18) best.l = 0.18;
                if (best.s < 0.20) best.s = 0.20;
              } else {
                if (best.l > 0.50) best.l = 0.50;
                if (best.l < 0.30) best.l = 0.30;
                if (best.s < 0.35) best.s = 0.35;
              }
            }

            var rgb = hsl2rgb(best.h, best.s, best.l);
            var r = rgb[0], g = rgb[1], b = rgb[2];

            navMusicEl.style.setProperty("--nav-music-theme", "rgb(" + r + "," + g + "," + b + ")");
            navMusicEl.style.setProperty("--nav-music-theme-op-deep", "rgba(" + r + "," + g + "," + b + ",0.85)");

            // 同步主题色到音乐列表面板
            const playlistPanel = document.getElementById('musicPlaylist');
            if (playlistPanel) {
              playlistPanel.style.setProperty("--music-theme-rgb", r + "," + g + "," + b);
              playlistPanel.style.setProperty("--nav-music-theme", "rgb(" + r + "," + g + "," + b + ")");
            }
          } catch (e) {
            console.error("取色失败", e);
            lastCover = null;
          }
        };

        img.onerror = function () {
          console.error("封面加载失败", audio.cover);
          lastCover = null;
        };
      } catch (e) {
        console.error("setThemeFromCover error", e);
        lastCover = null;
      }
    }

    // 首次取色
    setThemeFromCover();

    // 切歌时取色
    ap.on("listswitch", function () {
      setTimeout(setThemeFromCover, 50);
    });

    // 将 .aplayer-pic 的 background-image 替换为 <img> 标签
    (function patchCoverToImg() {
      const picEl = navMusicEl.querySelector('.aplayer-pic');
      if (!picEl) return;

      const coverImg = document.createElement('img');
      coverImg.className = 'aplayer-cover-img lazy';
      coverImg.alt = '';
      coverImg.draggable = false;
      picEl.insertBefore(coverImg, picEl.firstChild);

      let lastPatchedUrl = null;

      function applyCover(url) {
        if (!url || url === lastPatchedUrl) return;
        lastPatchedUrl = url;
        coverImg.setAttribute('data-src', url);
        coverImg.removeAttribute('data-ll-status');
        picEl.style.backgroundImage = 'none';
        if (window.lazyLoadInstance) window.lazyLoadInstance.update();
      }

      // 主驱动：直接从 APlayer 数据源读封面 URL
      function readCoverFromAPlayer() {
        if (ap.list && ap.list.audios && ap.list.audios.length > 0) {
          const audio = ap.list.audios[ap.list.index || 0];
          if (audio && audio.cover) {
            applyCover(audio.cover);
            return;
          }
        }
        // 兜底：从 background-image 提取
        readCoverFromBg();
      }

      // 兜底：从 inline style 提取 background-image URL
      function readCoverFromBg() {
        const bg = picEl.style.backgroundImage;
        if (!bg || bg === 'none') return;
        const m = bg.match(/url\(["']?(.*?)["']?\)/);
        if (m && m[1]) applyCover(m[1]);
      }

      // 首次同步
      readCoverFromAPlayer();

      // 切歌事件驱动
      ap.on('listswitch', function () {
        setTimeout(readCoverFromAPlayer, 60);
      });

      // MutationObserver 纯兜底：防止漏掉其他场景
      new MutationObserver(function () { readCoverFromBg(); })
        .observe(picEl, { attributes: true, attributeFilter: ['style'] });
    })();

    // 注意：头像/标题点击事件已移动到 bindTelescopicEvents 函数统一处理
  }

  bindThemeWhenReady();
}

// 页面加载完成后初始化
if (document.readyState === "complete" || document.readyState === "interactive") {
  musicBindEvent();
} else {
  document.addEventListener("DOMContentLoaded", musicBindEvent);
}

// 切换音乐列表显示
function toggleMusicPlaylist() {
  const panel = document.getElementById('musicPlaylist');
  if (!panel) return;

  // 如果要显示，先计算位置
  if (!panel.classList.contains('show')) {
    updatePlaylistPosition();
  }

  panel.classList.toggle('show');

  // 如果有刷新列表的函数，调用它
  if (window.refreshMusicPlaylist) {
    window.refreshMusicPlaylist();
  }
}

// 更新播放列表位置
function updatePlaylistPosition() {
  const panel = document.getElementById('musicPlaylist');
  if (!panel || !navMusicEl) return;

  const navRect = navMusicEl.getBoundingClientRect();
  const navHeight = navRect.height; // 胶囊高度，约41px
  const defaultMaxHeight = 360; // 对应 CSS 中的 max-height
  const gap = 10; // 与胶囊的间距

  // 获取播放器的 left 位置
  const navLeft = parseInt(window.getComputedStyle(navMusicEl).left) || 20;

  // 设置列表的左边位置与播放器一致
  panel.style.left = navLeft + 'px';

  // 计算上方和下方的可用空间
  const spaceAbove = navRect.top; // 胶囊上边到屏幕顶部的距离
  const spaceBelow = window.innerHeight - navRect.bottom; // 胶囊下边到屏幕底部的距离

  // 重置 maxHeight 到默认值
  panel.style.maxHeight = defaultMaxHeight + 'px';

  // 决定显示在上方还是下方
  if (spaceAbove >= defaultMaxHeight + gap) {
    // 上方空间足够，显示在上方
    panel.classList.remove('position-below');
    panel.style.bottom = (window.innerHeight - navRect.top + gap) + 'px';
    panel.style.top = 'auto';
  } else if (spaceBelow >= defaultMaxHeight + gap) {
    // 下方空间足够，显示在下方
    panel.classList.add('position-below');
    panel.style.top = (navRect.bottom + gap) + 'px';
    panel.style.bottom = 'auto';
  } else {
    // 两边空间都不够，选择空间大的一边，并限制高度
    if (spaceAbove > spaceBelow) {
      panel.classList.remove('position-below');
      panel.style.bottom = (window.innerHeight - navRect.top + gap) + 'px';
      panel.style.top = 'auto';
      panel.style.maxHeight = Math.max(150, spaceAbove - gap) + 'px';
    } else {
      panel.classList.add('position-below');
      panel.style.top = (navRect.bottom + gap) + 'px';
      panel.style.bottom = 'auto';
      panel.style.maxHeight = Math.max(150, spaceBelow - gap) + 'px';
    }
  }
}

// 挂载到全局
window.toggleMusicPlaylist = toggleMusicPlaylist;

// 单独绑定封面/标题点击事件（不依赖 musicBindEvent）
function bindTelescopicEvents() {
  if (!navMusicEl) return;

  const pic = navMusicEl.querySelector(".aplayer-pic");
  const title = navMusicEl.querySelector(".aplayer-title");

  if (!pic || !title) {
    setTimeout(bindTelescopicEvents, 200);
    return;
  }

  // 头像点击：打开音乐列表
  pic.addEventListener("click", function (e) {
    e.stopImmediatePropagation();
    e.stopPropagation();
    e.preventDefault();
    toggleMusicPlaylist();
    return false;
  }, true);

  // 标题点击：收起/展开播放器
  title.addEventListener("click", function (e) {
    e.stopImmediatePropagation();
    e.stopPropagation();
    e.preventDefault();
    lg_love.musicTelescopic();
    return false;
  }, true);
}

setTimeout(bindTelescopicEvents, 800);


// ==================== 长按拖动音乐条 ====================
(function () {
  if (!navMusicEl) return;

  let isDragging = false;
  let longPressTimer = null;
  let startY = 0;
  let startBottom = 20;
  let rafId = null;
  let pendingY = 0;

  const LONG_PRESS_DELAY = 300; // 缩短长按触发时间，更灵敏
  const DRAG_CLASS = 'is-dragging'; // 拖动状态 class
  const TOP_SAFE_GAP = 80; // 顶部安全距离（避免贴住导航栏）
  const BOTTOM_FALLBACK = 20; // 无 tab 时的底部默认间距

  // 检测移动端底部 tab 栏占据的高度，返回安全的最小 bottom 值
  function getMobileTabSafeBottom() {
    var navItems = document.querySelectorAll('.lgnewui-base-nav-item');
    if (!navItems.length) return BOTTOM_FALLBACK;
    var topOfNav = Infinity;
    for (var i = 0; i < navItems.length; i++) {
      var rect = navItems[i].getBoundingClientRect();
      if (rect.height > 0 && rect.width > 0) {
        topOfNav = Math.min(topOfNav, rect.top);
      }
    }
    if (topOfNav < Infinity && topOfNav > window.innerHeight - 160) {
      return window.innerHeight - topOfNav + 14;
    }
    return BOTTOM_FALLBACK;
  }

  // 获取当前 bottom 值
  function getCurrentBottom() {
    const style = window.getComputedStyle(navMusicEl);
    return parseInt(style.bottom) || BOTTOM_FALLBACK;
  }

  // 开始拖动
  function startDrag(clientY) {
    isDragging = true;
    startY = clientY;
    startBottom = getCurrentBottom();

    // 先添加 class，让 CSS 过渡动画生效（放大效果）
    navMusicEl.classList.add(DRAG_CLASS);

    // 拖动时隐藏播放列表面板
    const panel = document.getElementById('musicPlaylist');
    if (panel && panel.classList.contains('show')) {
      panel.classList.remove('show');
    }

    // 200ms 后禁用 bottom 过渡（此时放大动画已完成，开始丝滑拖动）
    setTimeout(function () {
      if (isDragging) {
        navMusicEl.style.willChange = 'bottom';
      }
    }, 200);

    // 禁止选择文字
    document.body.style.userSelect = 'none';
    document.body.style.webkitUserSelect = 'none';

  }

  // 用 RAF 更新位置，保证丝滑
  function updatePosition() {
    if (!isDragging) return;

    const deltaY = startY - pendingY;
    let newBottom = startBottom + deltaY;

    // 底部安全区：感知移动端 tab 栏高度；顶部安全区：保持与顶部导航距离
    const minBottom = getMobileTabSafeBottom();
    const maxBottom = window.innerHeight - navMusicEl.offsetHeight - TOP_SAFE_GAP;
    newBottom = Math.max(minBottom, Math.min(newBottom, maxBottom));

    navMusicEl.style.bottom = newBottom + 'px';
    rafId = null;
  }

  // 拖动中
  function onDrag(e) {
    if (!isDragging) return;
    e.preventDefault();

    // 获取当前 Y 坐标
    pendingY = e.type.includes('mouse') ? e.clientY : e.touches[0].clientY;

    // 用 RAF 批量更新，避免掉帧
    if (!rafId) {
      rafId = requestAnimationFrame(updatePosition);
    }
  }

  // 结束拖动
  function endDrag() {
    clearTimeout(longPressTimer);
    longPressTimer = null;

    if (rafId) {
      cancelAnimationFrame(rafId);
      rafId = null;
    }

    if (isDragging) {
      isDragging = false;

      // 移除拖动状态样式（CSS 过渡会自动平滑恢复）
      navMusicEl.classList.remove(DRAG_CLASS);
      navMusicEl.style.willChange = '';

      // 恢复选择
      document.body.style.userSelect = '';
      document.body.style.webkitUserSelect = '';

      // 保存位置到 localStorage
      saveMusicSetting('bottom', getCurrentBottom());
    }
  }

  // 取消长按（手指移动或抬起时）
  function cancelLongPress() {
    if (longPressTimer) {
      clearTimeout(longPressTimer);
      longPressTimer = null;
    }
  }

  // 鼠标事件 - 只响应左键
  navMusicEl.addEventListener('mousedown', function (e) {
    if (e.button !== 0) return; // 只响应左键
    // 记录按下时的 Y 坐标
    const clientY = e.clientY;
    longPressTimer = setTimeout(function () {
      startDrag(clientY);
    }, LONG_PRESS_DELAY);
  });

  document.addEventListener('mousemove', function (e) {
    if (isDragging) {
      onDrag(e);
    } else if (longPressTimer) {
      // 鼠标移动超过阈值，取消长按
      cancelLongPress();
    }
  });

  document.addEventListener('mouseup', endDrag);

  // 触摸事件
  navMusicEl.addEventListener('touchstart', function (e) {
    // 立即记录触摸点 Y 坐标（避免 setTimeout 中 touches 失效）
    const clientY = e.touches[0].clientY;
    longPressTimer = setTimeout(function () {
      startDrag(clientY);
    }, LONG_PRESS_DELAY);
  }, { passive: true });

  navMusicEl.addEventListener('touchmove', function (e) {
    if (isDragging) {
      onDrag(e);
    } else {
      // 如果开始移动但还没触发长按，取消长按
      cancelLongPress();
    }
  }, { passive: false });

  navMusicEl.addEventListener('touchend', endDrag);
  navMusicEl.addEventListener('touchcancel', endDrag);

  // 恢复保存的位置 (优化版：禁用动画防止跳动 + 边界校验)
  const savedBottom = getMusicSetting('bottom', null);
  if (savedBottom) {
    // 1. 暂时禁用 transition
    navMusicEl.style.transition = 'none';
    // 2. 立即应用位置（夹到安全区间内）
    const minB = getMobileTabSafeBottom();
    const maxB = window.innerHeight - navMusicEl.offsetHeight - TOP_SAFE_GAP;
    const clampedBottom = Math.max(minB, Math.min(Number(savedBottom), maxB));
    navMusicEl.style.bottom = clampedBottom + 'px';

    // 3. 强制重绘 (读取一下 offsetHeight)
    navMusicEl.offsetHeight;

    // 4. 恢复 transition (稍微延迟一点点确保生效)
    setTimeout(function () {
      navMusicEl.style.transition = '';
    }, 50);
  } else {
    // 无保存位置时，延迟检测底部 tab 栏并调整默认 bottom（DOM 可能尚未完全渲染）
    setTimeout(function () {
      var safeMin = getMobileTabSafeBottom();
      if (safeMin > BOTTOM_FALLBACK) {
        navMusicEl.style.transition = 'none';
        navMusicEl.style.bottom = safeMin + 'px';
        navMusicEl.offsetHeight;
        setTimeout(function () { navMusicEl.style.transition = ''; }, 50);
      }
    }, 600);
  }
})();


// ==================== 播放列表功能模块 ====================
(function () {
  const playlistPanel = document.getElementById('musicPlaylist');
  const playlistContent = document.getElementById('playlistContent');
  const playlistCount = document.getElementById('playlistCount');
  let playlistInited = false;
  let currentIndex = -1;
  // 从缓存读取播放模式，默认随机
  const savedMode = getMusicSetting('mode', 'random');
  let currentModeState = savedMode || 'random';

  // 如果没有相关 DOM，不执行
  if (!playlistPanel || !playlistContent) return;

  // 点击外部关闭
  document.addEventListener('click', function (e) {
    if (playlistPanel && !playlistPanel.contains(e.target) && !e.target.closest('.aplayer-pic')) {
      playlistPanel.classList.remove('show');
    }
  });

  // 格式化时长
  function formatDuration(seconds) {
    if (!seconds || isNaN(seconds)) return '--:--';
    const min = Math.floor(seconds / 60);
    const sec = Math.floor(seconds % 60);
    return min + ':' + (sec < 10 ? '0' : '') + sec;
  }

  // 重置播放列表
  function resetPlaylist() {
    const metingEl = getMetingEl();
    const ap = getAPlayer();
    if (!ap || !metingEl) return;

    const wasPaused = ap.audio.paused;
    const currentTime = ap.audio.currentTime;
    const currentSong = ap.list.audios[ap.list.index];

    fetch(metingEl.getAttribute('api'))
      .then(res => res.json())
      .then(data => {
        let newIndex = 0;
        ap.list.clear();
        ap.list.add(data);

        if (currentSong) {
          const foundIndex = data.findIndex(item => item.name === currentSong.name && item.artist === currentSong.artist);
          if (foundIndex !== -1) newIndex = foundIndex;
        }

        if (ap.list.index !== newIndex) {
          ap.list.switch(newIndex);
        }

        try {
          if (currentTime > 0) ap.seek(currentTime);
          if (wasPaused) ap.pause();
          else ap.play();
        } catch (e) {
          console.error('State restore failed', e);
        }

        renderPlaylist(ap.list.audios, ap.list.index);

        if (window.Toastify && Toastify.showScenario) {
          Toastify.showScenario('success', { text: "播放列表已重置", icon: "rotate-ccw" });
        }
      })
      .catch(e => {
        console.error('Reset failed', e);
        if (window.Toastify && Toastify.showScenario) {
          Toastify.showScenario('error', { text: "重置失败" });
        }
      });
  }

  // 定位到当前播放
  function scrollToCurrent() {
    const activeItem = playlistContent.querySelector('.lgnewui-music-playlist-item.active');
    if (activeItem) {
      activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      if (window.Toastify && Toastify.showScenario) {
        Toastify.showScenario('info', { text: "已定位到当前播放", icon: "crosshair", duration: 1500 });
      }
    }
  }

  // 渲染播放列表
  function renderPlaylist(audios, activeIndex) {
    if (!audios || audios.length === 0) {
      playlistContent.innerHTML = `
        <div class="lgnewui-music-playlist-empty">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
          </svg>
          <div>暂无音乐</div>
        </div>
      `;
      if (playlistCount) playlistCount.textContent = '';
      return;
    }

    if (playlistCount) playlistCount.textContent = audios.length + ' 首';
    currentIndex = activeIndex;

    let html = '';
    audios.forEach(function (audio, index) {
      const isActive = index === activeIndex;
      const indexNum = String(index + 1).padStart(2, '0');
      const _plCover = (audio.cover && audio.cover !== 'undefined') ? _mpEsc(audio.cover) : 'https://picsum.photos/200/200?random=' + index;
      const _plName = _mpEsc(audio.name || '未知歌曲');
      const _plArtist = _mpEsc(audio.artist || '未知歌手');
      const _plQuality = audio.quality ? _mpEsc(audio.quality) : '';
      html += `
        <div class="lgnewui-music-playlist-item${isActive ? ' active' : ''}" data-index="${index}">
          <div class="lgnewui-music-playlist-index">${indexNum}</div>
          <div class="lgnewui-music-playlist-cover">
            <img class="lazy" data-src="${_plCover}" alt="" onerror="this.src='https://picsum.photos/200/200?random=err'">
            <div class="lgnewui-music-playlist-playing-indicator">
              <div class="lgnewui-music-playlist-playing-bars">
                <span></span><span></span><span></span>
              </div>
            </div>
          </div>
          <div class="lgnewui-music-playlist-info">
            <div class="lgnewui-music-playlist-song-title">${_plName}</div>
            <div class="lgnewui-music-playlist-meta">
              <span class="lgnewui-music-playlist-artist">${_plArtist}</span>
              ${_plQuality ? `<span class="lgnewui-music-playlist-quality ${_plQuality.toLowerCase()}">${_plQuality}</span>` : ''}
            </div>
          </div>
          ${!isActive ? `
          <div class="lgnewui-music-playlist-action" title="下一首播放">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
          </div>
          ` : ''}
        </div>
      `;
    });
    playlistContent.innerHTML = html;

    // 触发懒加载扫描新封面图
    if (window.lazyLoadInstance) window.lazyLoadInstance.update();

    // 绑定点击事件
    playlistContent.querySelectorAll('.lgnewui-music-playlist-item').forEach(function (item) {
      // 下一首播放逻辑
      item.querySelector('.lgnewui-music-playlist-action')?.addEventListener('click', function (e) {
        e.stopPropagation();
        const parentItem = this.closest('.lgnewui-music-playlist-item');
        const targetIndex = parseInt(parentItem.dataset.index);

        const ap = getAPlayer();
        if (ap) {
          const currentIdx = ap.list.index;

          if (targetIndex === currentIdx) return;

          const audioList = ap.list.audios;
          const song = audioList[targetIndex];

          audioList.splice(targetIndex, 1);

          let insertIndex = currentIdx + 1;
          if (targetIndex < currentIdx) insertIndex = currentIdx;

          audioList.splice(insertIndex, 0, song);

          if (targetIndex < currentIdx) {
            ap.list.index--;
            ap.audio.index = ap.list.index;
          }

          renderPlaylist(audioList, ap.list.index);

          if (window.Toastify && Toastify.showScenario) {
            Toastify.showScenario('info', { text: "已添加到下一首播放", icon: "list-music" });
          }

          // 切换到列表循环模式
          ap.options.order = 'list';
          ap.options.loop = 'all';
          currentModeState = 'list';
          updateModeIcon('list');
        }
      });

      // 切歌逻辑
      item.addEventListener('click', function (e) {
        if (e.target.closest('.lgnewui-music-playlist-action')) return;

        const idx = parseInt(this.dataset.index);
        const ap = getAPlayer();
        if (ap) {
          ap.list.switch(idx);
          ap.play();
          updateActiveItem(idx);
        }
      });
    });

    // 滚动到当前播放项
    if (activeIndex >= 0 && audios.length > 0) {
      const activeItem = playlistContent.querySelector('.lgnewui-music-playlist-item.active');
      if (activeItem) {
        setTimeout(function () {
          activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 150);
      }
    }
  }

  // 更新当前播放状态
  function updateActiveItem(index) {
    if (currentIndex === index) return;

    const oldIndex = currentIndex;
    currentIndex = index;

    const items = playlistContent.querySelectorAll('.lgnewui-music-playlist-item');

    if (!items || items.length === 0) return;

    // 移除旧的 active
    if (oldIndex >= 0 && oldIndex < items.length && items[oldIndex]) {
      items[oldIndex].classList.remove('active');
    }
    // 添加新的 active
    if (index >= 0 && index < items.length && items[index]) {
      items[index].classList.add('active');
    }
  }

  // 初始化播放列表
  let initPlaylistRetry = 0;
  function initPlaylist() {
    const ap = getAPlayer();
    if (!ap) {
      if (initPlaylistRetry++ < MAX_RETRY) {
        setTimeout(initPlaylist, 300);
      }
      return;
    }

    let waitListRetry = 0;
    function waitForList() {
      if (!ap.list || !ap.list.audios || ap.list.audios.length === 0) {
        if (waitListRetry++ < MAX_RETRY) {
          setTimeout(waitForList, 200);
        }
        return;
      }

      playlistInited = true;
      renderPlaylist(ap.list.audios, ap.list.index || 0);

      ap.on('listswitch', () => {
        setTimeout(() => {
          // 切歌只需更新高亮，不重建整个列表
          updateActiveItem(ap.list.index);
        }, 50);
      });
    }

    waitForList();
  }

  // 切换列表显示（forceRender=true 时强制重建 DOM，用于歌曲列表变化后）
  function togglePlaylist(forceRender) {
    if (!playlistInited) {
      // 首次：显示 loading，然后初始化
      if (playlistContent && !playlistContent.querySelector('.lgnewui-music-playlist-item')) {
        playlistContent.innerHTML = '<div style="text-align:center;padding:40px 0;color:rgba(255,255,255,0.5);font-size:13px;"><i class="ph ph-spinner" style="animation:spin 1s linear infinite;display:inline-block;margin-right:6px;"></i>加载中...</div>';
      }
      initPlaylist();
      return;
    }

    const ap = getAPlayer();
    if (!ap || !ap.list || !ap.list.audios) return;

    if (forceRender) {
      // 歌曲列表变化（如加载更多），需要重建 DOM
      renderPlaylist(ap.list.audios, ap.list.index || 0);
    } else {
      // 普通打开面板：只更新当前播放高亮
      updateActiveItem(ap.list.index || 0);
    }
  }

  // ============ 播放模式切换 ============
  const playlistMode = document.getElementById('playlistMode');
  let modeIcons = null;

  if (playlistMode) {
    modeIcons = {
      random: playlistMode.querySelector('.mode-random'),
      loop: playlistMode.querySelector('.mode-loop'),
      single: playlistMode.querySelector('.mode-single')
    };
  }

  function updateModeIcon(mode) {
    if (!modeIcons || !modeIcons.random) return;

    modeIcons.random.style.display = 'none';
    modeIcons.loop.style.display = 'none';
    modeIcons.single.style.display = 'none';

    if (mode === 'random') {
      modeIcons.random.style.display = 'block';
      playlistMode.title = '随机播放';
    } else if (mode === 'list') {
      modeIcons.loop.style.display = 'block';
      playlistMode.title = '列表循环';
    } else if (mode === 'single') {
      modeIcons.single.style.display = 'block';
      playlistMode.title = '单曲循环';
    }
  }

  if (playlistMode) {
    playlistMode.addEventListener('click', function (e) {
      e.stopPropagation();
      const ap = getAPlayer();
      if (!ap) return;

      if (currentModeState === 'random') {
        ap.options.order = 'list';
        ap.options.loop = 'all';
        currentModeState = 'list';
        saveMusicSetting('mode', 'list');
        updateModeIcon('list');
        if (window.Toastify && Toastify.showScenario) {
          Toastify.showScenario('info', { text: "列表循环", icon: "repeat", duration: 1500 });
        }
      } else if (currentModeState === 'list') {
        ap.options.order = 'list';
        ap.options.loop = 'one';
        currentModeState = 'single';
        saveMusicSetting('mode', 'single');
        updateModeIcon('single');
        if (window.Toastify && Toastify.showScenario) {
          Toastify.showScenario('info', { text: "单曲循环", icon: "repeat-1", duration: 1500 });
        }
      } else {
        ap.options.order = 'random';
        ap.options.loop = 'all';
        currentModeState = 'random';
        saveMusicSetting('mode', 'random');
        updateModeIcon('random');
        if (window.Toastify && Toastify.showScenario) {
          Toastify.showScenario('info', { text: "随机播放", icon: "shuffle", duration: 1500 });
        }
      }
    });
  }

  // ============ 音量滑块控制 ============
  function initVolumeSlider() {
    const track = document.getElementById('volumeTrack');
    const btn = document.getElementById('volumeBtn');
    const valDisplay = document.getElementById('volumeValue');

    if (!track || !btn) return;

    let isDragging = false;
    let volume = 100; // 默认 100%
    let prevVolume = 100;
    const segmentCount = 10;
    const step = 10;
    let segments = [];

    function createSegments() {
      track.innerHTML = '';
      segments = [];
      for (let i = 0; i < segmentCount; i++) {
        const el = document.createElement('div');
        el.className = 'lgnewui-music-volume-segment-block';
        track.appendChild(el);
        segments.push(el);
      }
    }
    createSegments();

    // 从缓存读取音量，或等待 aplayer 加载完成后同步
    let syncVolumeRetry = 0;
    function syncVolumeFromPlayer() {
      // 优先使用我们自己的缓存
      const savedVolume = getMusicSetting('volume', null);
      if (savedVolume !== null) {
        volume = savedVolume;
        volume = snapToStep(volume);
        prevVolume = volume > 0 ? volume : 100;
        // 同步到 APlayer
        const ap = getAPlayer();
        if (ap) {
          ap.volume(volume / 100, true); // true = 静默模式，不触发 APlayer 的存储
        }
        updateUI(false);
        return;
      }

      // 如果没有缓存，从 APlayer 读取
      const ap = getAPlayer();
      if (ap && ap.audio) {
        volume = ap.audio.volume * 100;
        volume = snapToStep(volume);
        prevVolume = volume > 0 ? volume : 100;
        updateUI(false);
      } else if (syncVolumeRetry++ < MAX_RETRY) {
        setTimeout(syncVolumeFromPlayer, 200);
      }
    }
    syncVolumeFromPlayer();

    function snapToStep(val) {
      return Math.round(val / step) * step;
    }

    function updateUI(syncToPlayer = true) {
      if (valDisplay) valDisplay.innerText = Math.round(volume) + '%';

      // 修复高亮逻辑：第 i 段在 volume > i*step 时高亮
      // 例如：volume=100 -> 10段都高亮; volume=50 -> 5段高亮
      segments.forEach((seg, index) => {
        if (volume > index * step) {
          seg.classList.add('active');
        } else {
          seg.classList.remove('active');
        }
      });

      if (volume > 0) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }

      btn.classList.remove('lgnewui-icon-state-mute', 'lgnewui-icon-state-low', 'lgnewui-icon-state-med', 'lgnewui-icon-state-high');
      if (volume <= 0) {
        btn.classList.add('lgnewui-icon-state-mute');
      } else if (volume < 33) {
        btn.classList.add('lgnewui-icon-state-low');
      } else if (volume < 66) {
        btn.classList.add('lgnewui-icon-state-med');
      } else {
        btn.classList.add('lgnewui-icon-state-high');
      }

      const ap = getAPlayer();
      if (syncToPlayer && ap) {
        ap.volume(volume / 100, true); // true = 静默模式，不触发 APlayer 的存储
        // 保存到统一设置
        saveMusicSetting('volume', volume);
      }
    }

    function calculateVolume(e) {
      const rect = track.getBoundingClientRect();
      const clientX = e.touches ? e.touches[0].clientX : e.clientX;
      let rawPercentage = ((clientX - rect.left) / rect.width) * 100;
      rawPercentage = Math.max(0, Math.min(100, rawPercentage));
      volume = snapToStep(rawPercentage);
      updateUI(true);
    }

    function handleMove(e) {
      if (!isDragging) return;
      e.preventDefault();
      calculateVolume(e);
    }

    function handleEnd() {
      if (!isDragging) return;
      isDragging = false;
      window.removeEventListener('mousemove', handleMove);
      window.removeEventListener('touchmove', handleMove);
      window.removeEventListener('mouseup', handleEnd);
      window.removeEventListener('touchend', handleEnd);
    }

    function handleStart(e) {
      isDragging = true;
      calculateVolume(e);
      window.addEventListener('mousemove', handleMove);
      window.addEventListener('touchmove', handleMove, { passive: false });
      window.addEventListener('mouseup', handleEnd);
      window.addEventListener('touchend', handleEnd);
    }

    track.addEventListener('mousedown', handleStart);
    track.addEventListener('touchstart', handleStart, { passive: false });

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (volume > 0) {
        prevVolume = volume;
        volume = 0;
      } else {
        volume = snapToStep(prevVolume > 0 ? prevVolume : 50);
      }
      updateUI(true);
    });

    btn.addEventListener('mousedown', (e) => e.stopPropagation());
    btn.addEventListener('touchstart', (e) => e.stopPropagation());
  }

  // 绑定按钮事件
  document.getElementById('playlistReset')?.addEventListener('click', function (e) {
    e.stopPropagation();
    resetPlaylist();
  });

  document.getElementById('playlistLocate')?.addEventListener('click', function (e) {
    e.stopPropagation();
    scrollToCurrent();
  });

  // 挂载到全局
  window.refreshMusicPlaylist = togglePlaylist;

  // 初始化
  setTimeout(function () {
    // 播放列表改为懒初始化：打开面板时才 initPlaylist()，避免预加载所有封面图
    // 使用缓存的播放模式
    updateModeIcon(currentModeState);
    // 同步到 APlayer
    const ap = getAPlayer();
    if (ap) {
      if (currentModeState === 'random') {
        ap.options.order = 'random';
        ap.options.loop = 'all';
      } else if (currentModeState === 'list') {
        ap.options.order = 'list';
        ap.options.loop = 'all';
      } else if (currentModeState === 'single') {
        ap.options.order = 'list';
        ap.options.loop = 'one';
      }
    }
    initVolumeSlider();
  }, 1000);
})();


// ==================== 歌词懒加载（不打断播放）====================
(function () {
  let lyricCache = {};
  let loadingSet = new Set();

  // 加载歌词
  function loadLyric(songId) {
    if (lyricCache[songId]) {
      return Promise.resolve(lyricCache[songId]);
    }

    if (loadingSet.has(songId)) {
      return Promise.reject('loading');
    }

    loadingSet.add(songId);
    const metingEl = document.querySelector('#nav-music meting-js');
    const media = metingEl ? metingEl.getAttribute('server') : 'tencent';
    const url = `music-api.php?media=${media}&type=lyric&id=${songId}`;

    return fetch(url)
      .then(res => res.json())
      .then(data => {
        loadingSet.delete(songId);
        let lrc = '';
        if (data && data.lyric) {
          lrc = data.lyric;
        }
        if (lrc) {
          lyricCache[songId] = lrc;
        }
        return lrc;
      })
      .catch(err => {
        loadingSet.delete(songId);
        return '';
      });
  }

  // 更新当前歌曲的歌词
  function updateCurrentLyric() {
    const ap = getAPlayer();
    if (!ap || !ap.list || !ap.list.audios) return;

    const index = ap.list.index;
    const audio = ap.list.audios[index];

    if (!audio) return;

    const songId = audio.song_id || audio.id;
    if (!songId) return;

    // 检查是否已有歌词
    const hasLyric = audio.lrc && audio.lrc.trim();

    if (hasLyric) {
      const lrcVal = audio.lrc.trim();
      // lrc 是 URL → fetch 内容后再渲染
      if (lrcVal.match(/^https?:\/\//) || lrcVal.startsWith('/')) {
        fetch(lrcVal)
          .then(function (r) { return r.ok ? r.text() : ''; })
          .then(function (txt) {
            if (!txt || ap.list.index !== index) return;
            audio.lrc = txt;
            renderLyric(ap, txt, index);
          })
          .catch(function () { /* 静默 */ });
      } else {
        // lrc 已是歌词文本，直接渲染
        renderLyric(ap, audio.lrc, index);
      }
      return;
    }

    // 否则从远程 API 加载歌词
    loadLyric(songId).then(lrc => {
      if (!lrc || ap.list.index !== index) {
        return;
      }

      // 更新歌曲对象
      audio.lrc = lrc;

      // 渲染歌词
      renderLyric(ap, lrc, index);
    }).catch(err => {
      // 静默处理错误
    });
  }

  // 渲染歌词到 APlayer
  function renderLyric(ap, lrc, expectedIndex) {
    // 如果 APlayer 有歌词对象，直接更新
    if (!ap.lrc) return;

    try {
      // 解析歌词
      const lines = lrc.split('\n');
      const parsed = [];

      lines.forEach(line => {
        const match = line.match(/\[(\d{2}):(\d{2})\.(\d{2,3})\](.*)/);
        if (match) {
          const time = parseInt(match[1]) * 60 + parseInt(match[2]) + parseInt(match[3]) / (match[3].length === 2 ? 100 : 1000);
          const text = match[4].trim();
          // 过滤：1.非空 2.不包含时间标签[xx:xx]
          if (text !== '' && !text.match(/^\[[\d:\.]+\]$/)) {
            parsed.push([time, text]);
          }
        }
      });

      // 按时间排序
      parsed.sort((a, b) => a[0] - b[0]);

      if (parsed.length > 0) {
        // 更新 APlayer 内部数据
        ap.lrc.parsed = parsed;
        ap.lrc.current = 0;

        // 禁用 APlayer 原生的歌词更新方法，避免冲突
        if (ap.lrc.update && !ap.lrc._originalUpdate) {
          ap.lrc._originalUpdate = ap.lrc.update;
          ap.lrc.update = function () {
            // 空函数，阻止 APlayer 原生更新
          };
        }

        // 重建 DOM
        const container = ap.lrc.container;
        if (container) {
          container.innerHTML = '';
          container.style.transform = 'translateY(0px)';
          container.style.webkitTransform = 'translateY(0px)';

          parsed.forEach(item => {
            const p = document.createElement('p');
            p.textContent = item[1] || '';
            container.appendChild(p);
          });

          // 设置第一行为当前行
          if (container.children[0]) {
            container.children[0].classList.add('aplayer-lrc-current');
          }
        }

        // 手动启动歌词滚动（监听 timeupdate）
        if (!ap.lrc._manualUpdateBound) {
          ap.lrc._manualUpdateBound = true;

          const updateLyricScroll = () => {
            try {
              if (!ap.audio || ap.audio.paused) return;

              const currentTime = ap.audio.currentTime;
              const container = ap.lrc.container;
              if (!container || !ap.lrc.parsed || !ap.lrc.parsed.length) return;

              // 找到当前应该显示的歌词行（从上次位置向后搜索，避免每次全扫描）
              let currentIndex = ap.lrc.current || 0;
              const parsed = ap.lrc.parsed;
              // 向后查找：时间前进
              while (currentIndex < parsed.length - 1 && currentTime >= parsed[currentIndex + 1][0]) {
                currentIndex++;
              }
              // 向前查找：拖动进度条回退
              while (currentIndex > 0 && currentTime < parsed[currentIndex][0]) {
                currentIndex--;
              }

              // 如果索引变化，更新高亮和滚动
              if (currentIndex !== ap.lrc.current) {
                ap.lrc.current = currentIndex;

                const items = container.children;
                // 移除所有高亮
                for (let i = 0; i < items.length; i++) {
                  items[i].classList.remove('aplayer-lrc-current');
                }

                // 高亮当前行
                if (items[currentIndex]) {
                  items[currentIndex].classList.add('aplayer-lrc-current');

                  // 滚动到当前行（居中显示）
                  const itemHeight = items[currentIndex].offsetHeight || 16;
                  const offset = currentIndex * itemHeight;
                  container.style.transform = `translateY(-${offset}px)`;
                  container.style.webkitTransform = `translateY(-${offset}px)`;
                }
              }
            } catch (e) {
              // 静默处理错误，避免控制台报错
            }
          };

          // 使用 timeupdate 事件
          ap.audio.addEventListener('timeupdate', updateLyricScroll);
        }
      }
    } catch (e) {
      // 静默处理错误
    }
  }

  // 初始化
  function init() {
    const ap = getAPlayer();
    if (!ap) {
      setTimeout(init, 500);
      return;
    }

    if (!ap.list || !ap.list.audios || ap.list.audios.length === 0) {
      setTimeout(init, 500);
      return;
    }

    // 监听切歌
    ap.on('listswitch', function () {
      setTimeout(updateCurrentLyric, 200);
    });

    // 首次加载
    setTimeout(updateCurrentLyric, 1000);
  }

  setTimeout(init, 2000);
})();

// ==================== 后台预加载剩余歌曲 ====================
(function () {
  // 刷新播放列表显示
  function refreshPlaylistDisplay() {
    if (window.refreshMusicPlaylist && typeof window.refreshMusicPlaylist === 'function') {
      window.refreshMusicPlaylist(true); // forceRender: 歌曲列表变化后需要重建 DOM
    }
  }

  function loadMoreSongs() {
    const ap = getAPlayer();
    if (!ap || !ap.list) return;

    const metingEl = document.querySelector('#nav-music meting-js');
    if (!metingEl) return;

    const server = metingEl.getAttribute('server');
    const type = metingEl.getAttribute('type');
    const id = metingEl.getAttribute('id');

    // 如果是 local 模式，跳过后台加载（因为 music.php 一次性返回了所有数据）
    if (server === 'local') {
      return;
    }

    const initialCount = ap.list.audios.length;

    // 获取完整歌单
    const url = `music-api.php?media=${server}&type=${type}&id=${id}&includeLyric=0&includeUrl=1&limit=100`;

    fetch(url)
      .then(res => res.json())
      .then(data => {
        if (!Array.isArray(data)) {
          return;
        }


        if (data.length <= initialCount) {
          return;
        }

        // 获取已有歌曲的 ID，避免重复
        const existingIds = new Set(ap.list.audios.map(a => a.song_id || a.id));

        // 添加新歌曲（跳过已有的）
        const newSongs = data.filter(song => {
          const songId = song.song_id || song.id;
          return !existingIds.has(songId);
        });


        if (newSongs.length > 0) {
          // 转换为 APlayer 格式
          const formatted = newSongs.map(item => ({
            name: item.name || 'Unknown',
            artist: item.artist || item.author || 'Unknown',
            url: item.url,
            cover: item.cover || item.pic || '',
            lrc: item.lrc || '',
            song_id: item.song_id || item.id,
            url_id: item.url_id,
            lyric_id: item.lyric_id
          }));

          // 添加到播放列表
          ap.list.add(formatted);

          const finalCount = ap.list.audios.length;

          // 刷新播放列表显示
          refreshPlaylistDisplay();

          // 显示提示
          if (window.Toastify && Toastify.showScenario) {
            Toastify.showScenario('success', {
              text: `已加载 ${finalCount} 首歌曲`,
              icon: "music",
              duration: 2000
            });
          }
        }
      })
      .catch(err => {
        console.error('[后台加载] 失败:', err);
      });
  }

  // 等待 APlayer 初始化完成
  function waitForAPlayerReady() {
    const ap = getAPlayer();

    if (!ap) {
      setTimeout(waitForAPlayerReady, 500);
      return;
    }

    // 监听 APlayer 的 listswitch 事件，确保歌单已加载
    if (!ap.list || !ap.list.audios || ap.list.audios.length === 0) {
      setTimeout(waitForAPlayerReady, 500);
      return;
    }


    // 延迟 5 秒后开始后台加载，让用户先听歌
    setTimeout(() => {
      loadMoreSongs();
    }, 5000);
  }

  // 启动
  waitForAPlayerReady();
})();
