/*!
 *      _      ____ _   _ _____ _    _ _   _ ___
 *     | |    / ___| \ | | ____| |  | | | | |_ _|
 *     | |   | |  _|  \| |  _| | |  | | | | || |
 *     | |___| |_| | |\  | |___| |__| | |_| || |
 *     |_____|\____|_| \_|_____|____/ \___/|___|
 *
 *      ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 *
 * Project      : LGNewUi
 * Author       : Ki
 * Last Updated : 2026-03-31
 * Description  : A private place to preserve love, memory, and the
 *                years shared between two people.
 *
 * Copyright (c) 2022-2026 by Ki. All Rights Reserved.
 *
 * Type         : Native PHP web application built with PHP, HTML,
 *                CSS, JavaScript, and selected third-party libraries.
 * License      : This project is licensed for personal couple-record
 *                use only.
 * Restriction  : Commercial, corporate, organizational, platform,
 *                or enterprise use is strictly prohibited.
 * Restriction  : Redistribution, modification, repackaging, resale,
 *                or unauthorized public deployment without explicit
 *                written permission is strictly prohibited.
 *
 * Official Authorization Site:
 * https://auth-love.kikiw.cn/
 *
 * Warning      : LGNewUi is an original work. Any unauthorized
 *                copying, scraping, resale, redistribution, or
 *                commercial use is forbidden.
 * Notice       : Development and long-term maintenance require
 *                substantial time and effort. Please respect the
 *                work involved and the applicable authorization terms.
 *
 */
function insertEmoticon(tag) {
    var commentBox = document.getElementById('wenben');
    if (!commentBox) return;
    var startPos = commentBox.selectionStart;
    var endPos = commentBox.selectionEnd;
    var oldValue = commentBox.value;

    commentBox.value = oldValue.substring(0, startPos) + tag + oldValue.substring(endPos, oldValue.length);

    commentBox.selectionStart = commentBox.selectionEnd = startPos + tag.length;

    commentBox.focus();
}

// HTML 转义，防止 XSS
function _owoEscapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function GetEm() {
    var _owoBase = (window.LG_CONFIG && window.LG_CONFIG.owoBase) || '/OwO';
    $.ajax({
        url: _owoBase + "/emoji.json",
        type: "GET",
        dataType: "json",
        success: function (res) {

            const tabMenu = $(".OwO_Tab_Menu");
            const tabContents = $(".OwO_Tab_Contents");

            tabMenu.empty();
            tabContents.empty();

            let keys = Object.keys(res).reverse();

            keys.forEach((key, index) => {

                tabMenu.append(`
    <li class="${index === 0 ? 'active' : ''}" data-tab="OwO_${index + 1}">${_owoEscapeHtml(key)}</li>
`);

                tabContents.append(`
    <div id="OwO_${index + 1}" class="OwO_Content ${index === 0 ? 'active' : ''}">
        <ul class="OwO_items"></ul>
    </div>
`);

                let tabData = res[key].container;

                tabData.forEach(element => {
                    $(`#OwO_${index + 1} ul`).append(`
        <li class="Item_OwO" title="${_owoEscapeHtml(element.text)}" data-emoticon="${_owoEscapeHtml(element.data)}">
            <img class="lazy" data-src="${_owoBase}/images/${_owoEscapeHtml(element.icon)}">
        </li>
    `);
                });
            });

            // 先 off 再 on，防止 PJAX 切换后事件委托累积
            tabMenu.off("click.owo").on("click.owo", "li", function () {
                let tabId = $(this).data("tab");

                $(this).addClass("active").siblings().removeClass("active");
                $("#" + tabId).addClass("active").siblings().removeClass("active");
            });

            // 表情点击：事件委托替代 onclick 内联，防止 XSS
            tabContents.off("click.owo").on("click.owo", ".Item_OwO", function () {
                var emoticon = $(this).attr("data-emoticon");
                if (emoticon) insertEmoticon(emoticon);
            });

            // 复用全局 LazyLoad 实例，避免创建重复实例
            if (window.lazyLoadInstance) {
                window.lazyLoadInstance.update();
            }
        },
        error: function (err) {
            console.error("无法加载表情包数据:", err);
        }
    })
}

function Charu(data) {
    $("#wenben").text(data)
}

GetEm();
