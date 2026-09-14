
    $(function() {
        if (typeof GeetestHelper !== 'undefined') {
            var siteTitle = (window.WITHU_CONFIG && window.WITHU_CONFIG.title) || '';

            GeetestHelper.init({
                toast: {
                    success: function(msg) { Toastify.showScenario('success', { text: msg }); },
                    error: function(msg) { Toastify.showScenario('error', { text: msg }); },
                    warning: function(msg) { Toastify.showScenario('warning', { text: msg }); }
                },
                onClose: function() {
                    $('#leavingPost').removeAttr('disabled').text('提交留言');
                },
                onSuccess: function(result) {
                    if (typeof submitMessage === 'function') {
                        submitMessage(result);
                    }
                }
            });

            $('#leavingPost').off('click.withuGeetest').on('click.withuGeetest', function() {
                var qq = $("input[name='qq']").val();
                var name = $("input[name='name']").val();
                var text = $("textarea[name='text']").val();

                if (!qq || !name || !text) {
                    Toastify.showScenario('warning', { text: '留言提交失败 表单输入不完整！' });
                    return false;
                }

                if (typeof containsBannedChar === 'function' && containsBannedChar((name || '') + ' ' + (text || ''))) {
                    Toastify.showScenario('warning', { text: '留言包含违禁内容，请修改后重试' });
                    return false;
                }

                $('#leavingPost').text('请完成验证...').attr('disabled', 'disabled');
                GeetestHelper.show();
            });
        } else {
            $('#leavingPost').off('click.withuGeetest').on('click.withuGeetest', function() {
                var qq = $("input[name='qq']").val();
                var name = $("input[name='name']").val();
                var text = $("textarea[name='text']").val();

                if (!qq || !name || !text) {
                    Toastify.showScenario('warning', { text: '留言提交失败 表单输入不完整！' });
                    return false;
                }

                if (typeof containsBannedChar === 'function' && containsBannedChar((name || '') + ' ' + (text || ''))) {
                    Toastify.showScenario('warning', { text: '留言包含违禁内容，请修改后重试' });
                    return false;
                }

                if (typeof submitMessage === 'function') {
                    submitMessage({});
                }
            });
        }
    });
    