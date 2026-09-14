
/* 图片查看器增强：鼠标滚轮缩放（仅作用于 ViewImage 弹层，不影响页面其它区域） */
(function () {
    if (window.WithuViewImageWheelZoom) return;
    window.WithuViewImageWheelZoom = true;

    var MIN_SCALE = 0.5, MAX_SCALE = 8;

    function activeImg() {
        var viewer = document.querySelector('.view-image');
        return viewer ? viewer.querySelector('.view-image-lead img') : null;
    }

    function apply(img) {
        img.style.transform = 'translate(' + (img._vx || 0) + 'px,' + (img._vy || 0) + 'px) scale(' + img._vs + ')';
    }

    document.addEventListener('wheel', function (e) {
        var img = activeImg();
        if (!img) return;
        e.preventDefault();
        if (img._vs === undefined) { img._vs = 1; img._vx = 0; img._vy = 0; }

        var delta = e.deltaY * (e.deltaMode === 1 ? 16 : (e.deltaMode === 2 ? 100 : 1));
        var next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, img._vs * Math.exp(-delta * 0.0022)));
        if (next === img._vs) return;

        img._vs = next;
        if (next <= 1) { img._vx = 0; img._vy = 0; }
        img.style.transition = 'transform .12s ease-out';
        img.style.cursor = next > 1 ? 'grab' : '';
        apply(img);
    }, { passive: false });

    /* 放大后可按住图片拖动查看局部；图片本身点击无动作，不会误触关闭 */
    document.addEventListener('pointerdown', function (e) {
        var img = activeImg();
        if (!img || e.target !== img || (img._vs || 1) <= 1) return;
        e.preventDefault();
        var ox = e.clientX - (img._vx || 0), oy = e.clientY - (img._vy || 0);
        img.style.transition = 'none';
        img.style.cursor = 'grabbing';

        function onMove(ev) {
            img._vx = ev.clientX - ox;
            img._vy = ev.clientY - oy;
            apply(img);
        }
        function onUp() {
            img.style.cursor = 'grab';
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
        }
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
    });
})();
