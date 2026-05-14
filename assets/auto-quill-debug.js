/**
 * AutoQuill Debug Console Streamer
 *
 * Streamt Server-seitige Logs auf jeder AutoQuill-Admin-Seite live in die
 * Browser-Konsole. Nutzer-API:
 *   window.AutoQuill.debug.tail(limit?)   - letzte N Eintraege ausgeben
 *   window.AutoQuill.debug.clear()        - serverseitig leeren + console.clear
 *   window.AutoQuill.debug.download()     - aktuelle Logs als JSON downloaden
 *   window.AutoQuill.debug.startPolling() - Live-Stream manuell starten
 *   window.AutoQuill.debug.stopPolling()  - Live-Stream stoppen
 */
(function () {
    'use strict';

    var cfg = window.autoQuillDebug || {};
    if (!cfg.restUrl) {
        return;
    }

    window.AutoQuill = window.AutoQuill || {};

    var lastSeenId = 0;
    var pollTimer  = null;
    var pollIdleTimer = null;

    var STYLES = {
        error:   'background:#b32d2e;color:#fff;padding:2px 6px;border-radius:3px;font-weight:600;',
        warning: 'background:#dba617;color:#000;padding:2px 6px;border-radius:3px;font-weight:600;',
        info:    'background:#2271b1;color:#fff;padding:2px 6px;border-radius:3px;font-weight:600;',
        debug:   'background:#646970;color:#fff;padding:2px 6px;border-radius:3px;font-weight:600;'
    };
    var BANNER_STYLE = 'background:#1d2327;color:#72aee6;padding:4px 10px;border-radius:3px;font-weight:600;';

    function buildUrl(params) {
        var url = cfg.restUrl;
        var qs  = [];
        if (lastSeenId && (!params || !params.ignoreSince)) {
            qs.push('since_id=' + encodeURIComponent(lastSeenId));
        }
        if (params) {
            for (var k in params) {
                if (k === 'ignoreSince' || !Object.prototype.hasOwnProperty.call(params, k)) continue;
                if (params[k] === undefined || params[k] === null || params[k] === '') continue;
                qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
            }
        }
        return url + (qs.length ? (url.indexOf('?') >= 0 ? '&' : '?') + qs.join('&') : '');
    }

    function fetchLogs(params) {
        return fetch(buildUrl(params), {
            headers: { 'X-WP-Nonce': cfg.restNonce, 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('logs request failed: ' + res.status);
            }
            return res.json();
        });
    }

    function print(log) {
        var style = STYLES[log.level] || STYLES.info;
        var fn = log.level === 'error'   ? console.error
               : log.level === 'warning' ? console.warn
               : console.log;
        var label = (log.level || 'info').toUpperCase();
        fn('%c' + label + '%c [%s] %s %c%s',
            style, '', log.source, log.message,
            'color:#8c8f94;font-size:11px;', '@' + log.created_at);
        if (log.context && typeof log.context === 'object' && Object.keys(log.context).length) {
            try {
                console.groupCollapsed('  context');
                console.dir(log.context);
                console.groupEnd();
            } catch (e) { /* noop */ }
        }
    }

    function emit(logs) {
        if (!logs || !logs.length) return 0;
        for (var i = 0; i < logs.length; i++) {
            print(logs[i]);
            if (logs[i].id > lastSeenId) {
                lastSeenId = logs[i].id;
            }
        }
        return logs.length;
    }

    function tail(limit) {
        lastSeenId = 0;
        return fetchLogs({ limit: limit || 50, ignoreSince: true })
            .then(function (data) {
                var n = emit(data.logs || []);
                return n + ' Eintraege';
            })
            .catch(function (e) {
                console.error('[AutoQuill] tail() fehlgeschlagen:', e);
            });
    }

    function pollOnce() {
        return fetchLogs({ limit: 100 })
            .then(function (data) { emit(data.logs || []); })
            .catch(function () { /* swallow polling errors */ });
    }

    function startPolling(ms) {
        stopPolling();
        var interval = ms || cfg.pollInterval || 2000;
        pollTimer = setInterval(pollOnce, interval);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function clearLogs() {
        return fetch(cfg.restUrl, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': cfg.restNonce, 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) throw new Error('clear failed: ' + res.status);
            lastSeenId = 0;
            try { console.clear(); } catch (e) {}
            console.log('%c[AutoQuill]%c Logs geleert.', BANNER_STYLE, '');
        }).catch(function (e) {
            console.error('[AutoQuill] clear() fehlgeschlagen:', e);
        });
    }

    function download() {
        return fetchLogs({ limit: 500, ignoreSince: true }).then(function (data) {
            var blob = new Blob(
                [JSON.stringify(data.logs || [], null, 2)],
                { type: 'application/json' }
            );
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'auto-quill-logs-' + new Date().toISOString().replace(/[:.]/g, '-') + '.json';
            document.body.appendChild(a);
            a.click();
            setTimeout(function () {
                document.body.removeChild(a);
                URL.revokeObjectURL(a.href);
            }, 0);
        }).catch(function (e) {
            console.error('[AutoQuill] download() fehlgeschlagen:', e);
        });
    }

    window.AutoQuill.debug = {
        tail: tail,
        clear: clearLogs,
        download: download,
        startPolling: startPolling,
        stopPolling: stopPolling
    };

    function init() {
        var modeMsg = cfg.debugEnabled
            ? 'Debug-Logging aktiv (info + debug werden gestreamt)'
            : 'Debug-Logging AUS (nur warning/error werden gespeichert) - aktivierbar in den Einstellungen';

        console.log('%c[AutoQuill]%c %s', BANNER_STYLE, '', modeMsg);
        console.log('%c[AutoQuill]%c Befehle: window.AutoQuill.debug.tail() / .clear() / .download() / .startPolling() / .stopPolling()',
            BANNER_STYLE, 'color:#646970;');

        tail(20);

        if (window.jQuery) {
            jQuery(document).on('ajaxSend', function () {
                startPolling();
                if (pollIdleTimer) clearTimeout(pollIdleTimer);
            });
            jQuery(document).on('ajaxComplete', function () {
                if (pollIdleTimer) clearTimeout(pollIdleTimer);
                pollIdleTimer = setTimeout(stopPolling, 6000);
            });
        }

        var origFetch = window.fetch;
        if (typeof origFetch === 'function') {
            window.fetch = function (input, init) {
                var url = typeof input === 'string' ? input : (input && input.url) || '';
                var isOurs = url.indexOf('auto-quill/v1/logs') !== -1;
                var isPlugin = !isOurs && url.indexOf('auto-quill/v1/') !== -1;
                if (isPlugin) {
                    startPolling();
                    if (pollIdleTimer) clearTimeout(pollIdleTimer);
                }
                var p = origFetch.apply(this, arguments);
                if (isPlugin) {
                    p.finally(function () {
                        if (pollIdleTimer) clearTimeout(pollIdleTimer);
                        pollIdleTimer = setTimeout(stopPolling, 6000);
                    });
                }
                return p;
            };
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
