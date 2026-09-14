(function () {
    'use strict';

    var AUTH_URL = 'https://1314-671f8e88838cb484.monkeycode-ai.online/api/auth-status.php';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function applyState(loggedIn) {
        var loginEl = document.querySelector('#withuHeaderActions a[data-entry="login"]');
        var adminEl = document.querySelector('#withuHeaderActions a[data-entry="admin"]');
        if (loginEl) loginEl.style.display = loggedIn ? 'none' : '';
        if (adminEl) adminEl.style.display = loggedIn ? '' : 'none';
    }

    ready(function () {
        if (!document.querySelector('#withuHeaderActions a[data-entry="login"]') &&
            !document.querySelector('#withuHeaderActions a[data-entry="admin"]')) {
            return;
        }

        try {
            fetch(AUTH_URL, { method: 'GET', credentials: 'include', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) { applyState(!!(d && d.logged_in)); })
                .catch(function () { applyState(false); });
        } catch (e) {
            applyState(false);
        }
    });
})();
