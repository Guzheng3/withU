// withU 全站 UI 动效脚本 — 滚动揭示 (Motion.Lab scroll-reveal)
// 监听 .withu-motion-scroll 元素进入视口，添加 .withu-motion-in
// 支持 PJAX：容器切换后自动重新扫描
(function () {
  'use strict';
  var io = null;

  function ensureObserver() {
    if (io) return io;
    if (typeof IntersectionObserver === 'undefined') return null;
    io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('withu-motion-in');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.12 });
    return io;
  }

  function scan(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var obs = ensureObserver();
    var els = scope.querySelectorAll('.withu-motion-scroll:not(.withu-motion-in)');
    if (!obs) {
      // 无 IntersectionObserver 时直接显示，避免内容不可见
      els.forEach ? els.forEach(function (el) { el.classList.add('withu-motion-in'); })
        : Array.prototype.forEach.call(els, function (el) { el.classList.add('withu-motion-in'); });
      return;
    }
    Array.prototype.forEach.call(els, function (el) { obs.observe(el); });
  }

  function init() { scan(document); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // PJAX 进入新页面后重新扫描（jQuery pjax 事件 + 自定义兜底）
  if (window.jQuery) {
    jQuery(document).on('pjax:end.withuMotion pjax:complete.withuMotion', function () { scan(document); });
  }
  document.addEventListener('withu:pjax-after', function (e) { scan(e.detail && e.detail.container); });
  document.addEventListener('pjax:complete', function () { scan(document); });
  window.withuMotionScan = scan;
})();
