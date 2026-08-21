(function () {
  'use strict';
  var SAKURA_ID = 'withu-sakura';

  function injectCss() {
    if (document.getElementById('withu-sakura-css')) return;
    var css = [
      '#withu-sakura{position:fixed;inset:0;z-index:60;overflow:hidden;pointer-events:none;contain:strict}',
      '.withu-sakura-petal{position:absolute;top:-24px;left:var(--left);width:var(--size);height:calc(var(--size)*.72);border-radius:70% 20% 70% 20%;background:linear-gradient(135deg,rgba(255,255,255,.96),rgba(251,113,153,.78));box-shadow:0 2px 7px rgba(236,72,153,.16);opacity:0;transform:rotate(var(--rotate));animation:withuSakuraFall var(--duration) linear var(--delay) infinite}',
      '@keyframes withuSakuraFall{0%{opacity:0;transform:translate3d(0,-4vh,0) rotate(0deg)}10%{opacity:.78}50%{opacity:.62;transform:translate3d(var(--drift),52vh,0) rotate(180deg)}90%{opacity:.5}100%{opacity:0;transform:translate3d(calc(var(--drift)*-.7),110vh,0) rotate(360deg)}}',
      '@media(max-width:640px){#withu-sakura{opacity:.72}.withu-sakura-petal{animation-duration:13s}}',
      '@media(prefers-reduced-motion:reduce){#withu-sakura{display:none!important}}'
    ].join('\n');
    var style = document.createElement('style');
    style.id = 'withu-sakura-css';
    style.textContent = css;
    document.head.appendChild(style);
  }

  function mount() {
    if (document.getElementById(SAKURA_ID)) return;
    var sakura = document.createElement('div');
    sakura.id = SAKURA_ID;
    sakura.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < 16; i++) {
      var petal = document.createElement('span');
      petal.className = 'withu-sakura-petal';
      petal.style.setProperty('--left', (Math.random() * 108 - 4) + '%');
      petal.style.setProperty('--delay', (Math.random() * -14) + 's');
      petal.style.setProperty('--duration', (10 + Math.random() * 10) + 's');
      petal.style.setProperty('--size', (7 + Math.random() * 8) + 'px');
      petal.style.setProperty('--drift', ((Math.random() - .5) * 180) + 'px');
      petal.style.setProperty('--rotate', (Math.random() * 360) + 'deg');
      sakura.appendChild(petal);
    }
    document.body.appendChild(sakura);
  }

  function init() {
    injectCss();
    mount();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  document.addEventListener('pjax:end.lgPjax', mount);
})();
