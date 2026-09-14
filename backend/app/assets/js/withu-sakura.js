/* WithU · withU-inspired frontend motion layer */
(function(){'use strict';
function init(){
    var body=document.body;
    if(!body||!body.classList.contains('withu-front-modern')||body.classList.contains('withu-no-front-effects'))return;
    var reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function initWithuHeroParticles(){var layer=document.getElementById('withu-hero-particles');if(!layer||layer.childElementCount)return;for(var p=0;p<34;p++){var dot=document.createElement('i');dot.className='withu-hero-particle';dot.style.setProperty('--px',(Math.random()*100)+'%');dot.style.setProperty('--py',(Math.random()*100)+'%');dot.style.setProperty('--ps',(1+Math.random()*3)+'px');dot.style.setProperty('--po',(0.22+Math.random()*.65).toFixed(2));dot.style.setProperty('--pd',(3+Math.random()*6)+'s');dot.style.setProperty('--pdelay',(Math.random()*-8)+'s');dot.style.setProperty('--dx',((Math.random()-.5)*36)+'px');dot.style.setProperty('--dy',((Math.random()-.5)*28)+'px');layer.appendChild(dot);}}
    initWithuHeroParticles();
    var groups=['.overview-grid .stat-card','.events-list .event-pill','.article-list-large > *','.albums-section .album-card','.home-messages-masonry .message-card'],nodes=[];
    groups.forEach(function(q){document.querySelectorAll(q).forEach(function(el){if(nodes.indexOf(el)<0)nodes.push(el);});});
    nodes.forEach(function(el){el.classList.add('withu-reveal');});
    if('IntersectionObserver'in window&&!reduced){
        var ob=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting){entry.target.classList.add('is-visible');ob.unobserve(entry.target);}});},{rootMargin:'0px 0px -8% 0px',threshold:.08});
        nodes.forEach(function(el){ob.observe(el);});
    }else nodes.forEach(function(el){el.classList.add('is-visible');});

    /* 图片懒加载 + 进入视口时柔和显现 */
    var images=[].slice.call(document.querySelectorAll('main img:not(.avatar)'));
    images.forEach(function(img){
        img.loading='lazy';
        img.decoding='async';
        img.classList.add('withu-lazy-image');
        var reveal=function(){img.classList.add('is-loaded');};
        if(img.complete&&img.naturalWidth>0) window.requestAnimationFrame(reveal);
        else img.addEventListener('load',reveal,{once:true});
        img.addEventListener('error',function(){img.classList.add('is-loaded','is-load-error');},{once:true});
    });

    var bar=document.createElement('div');bar.id='withu-scroll-progress';document.body.appendChild(bar);
    var top=document.createElement('button');top.id='withu-back-top';top.type='button';top.setAttribute('aria-label','回到顶部');top.innerHTML='<i class="fas fa-arrow-up"></i>';document.body.appendChild(top);
    function scroll(){var max=document.documentElement.scrollHeight-window.innerHeight;bar.style.width=(max>0?Math.min(100,window.scrollY/max*100):0)+'%';top.classList.toggle('is-visible',window.scrollY>480);}window.addEventListener('scroll',scroll,{passive:true});scroll();top.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'});});

    var sakura=document.createElement('div');sakura.id='withu-sakura';sakura.setAttribute('aria-hidden','true');
    for(var i=0;i<16;i++){var petal=document.createElement('span');petal.className='withu-sakura-petal';petal.style.setProperty('--left',(Math.random()*108-4)+'%');petal.style.setProperty('--delay',(Math.random()*-14)+'s');petal.style.setProperty('--duration',(10+Math.random()*10)+'s');petal.style.setProperty('--size',(7+Math.random()*8)+'px');petal.style.setProperty('--drift',((Math.random()-.5)*180)+'px');petal.style.setProperty('--rotate',(Math.random()*360)+'deg');sakura.appendChild(petal);}document.body.appendChild(sakura);

    var hearts=document.createElement('div');hearts.id='withu-heart-layer';hearts.setAttribute('aria-hidden','true');document.body.appendChild(hearts);
    function spawnHeart(x,y,small){
        var h=document.createElement('i');h.className='fas fa-heart withu-floating-heart';
        if(small)h.classList.add('is-small');
        if(typeof x==='number'){h.style.left=x+'px';h.style.top=y+'px';h.style.position='fixed';}
        else {h.style.left=(8+Math.random()*84)+'%';h.style.bottom='-20px';}
        h.style.setProperty('--heart-drift',((Math.random()-.5)*90)+'px');h.style.setProperty('--heart-rotate',((Math.random()-.5)*28)+'deg');
        hearts.appendChild(h);setTimeout(function(){h.remove();},small?1100:3600);
    }
    if(!reduced){
        document.addEventListener('pointerdown',function(e){if(e.button!==0||e.target.closest('input,textarea,select,button,a'))return;spawnHeart(e.clientX,e.clientY,true);},{passive:true});
        window.setInterval(function(){spawnHeart();},2800);
    }

    var path=location.pathname;document.querySelectorAll('.main-nav .nav-button').forEach(function(a){if(a.pathname===path||((path==='/'||path==='/index.php')&&a.pathname==='/'))a.classList.add('withu-nav-active');});
    document.querySelectorAll('.main-nav .nav-button').forEach(function(a){a.addEventListener('click',function(){var r=a.getBoundingClientRect(),h=document.createElement('i');h.className='fas fa-heart withu-heart-pop';h.style.left=(r.left+r.width/2)+'px';h.style.top=(r.top+r.height/2)+'px';h.style.setProperty('--dx',((Math.random()-.5)*34)+'px');document.body.appendChild(h);setTimeout(function(){h.remove();},850);});});
    var hero=document.querySelector('.withu-front-modern .header-content');
    if(hero&&!reduced){var raf=0;document.addEventListener('mousemove',function(e){if(innerWidth<900)return;if(raf)cancelAnimationFrame(raf);raf=requestAnimationFrame(function(){hero.style.transform='translate3d('+((e.clientX/innerWidth-.5)*5)+'px,'+((e.clientY/innerHeight-.5)*3)+'px,0)';});},{passive:true});}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
