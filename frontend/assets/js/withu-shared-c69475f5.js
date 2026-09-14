
        // 滚动按钮和回到顶部功能已迁移到 components.js 的 ScrollButtons 模块
        // 以下代码由 WithUApp.init() 统一初始化，保留最小必要代码

        $(document).ready(function() {
            $('body').addClass('loaded');

            // 初始化 WithUApp 核心框架
            if (window.WithUApp && typeof window.WithUApp.init === 'function') {
                window.WithUApp.setConfig(window.WITHU_CONFIG || {});
                window.WithUApp.init();
            }

            // 初始化组件（礼花、轮播、导航等）
            if (window.WithUApp && window.WithUApp.Components) {
                const {
                    ConfettiEffect,
                    Carousel,
                    AvatarInteraction,
                    Navigation,
                    ScrollButtons,
                    HeaderVisitorWeather
                } = window.WithUApp.Components;

                // 初始化礼花效果
                if (ConfettiEffect) {
                    ConfettiEffect.init();
                    // 页面加载完成后延迟触发如影随形效果
                    setTimeout(() => {
                        ConfettiEffect.loveWingEffect();
                    }, 800);
                }

                // 初始化轮播图
                if (Carousel) Carousel.init();

                // 初始化头像交互
                if (AvatarInteraction) AvatarInteraction.init();

                // 初始化导航栏
                if (Navigation) Navigation.init();

                // 初始化滚动按钮
                if (ScrollButtons) ScrollButtons.init();

                // 初始化天气胶囊
                if (HeaderVisitorWeather) HeaderVisitorWeather.init();

            }

            // GetEm 函数调用（如果存在）
            if (typeof GetEm === 'function') GetEm();
        });
    