(() => {
    'use strict';

    // ── Apply jar colors from data-jar-color attributes ──────────
    // Runs BEFORE the .jar-page check so it also fires on the
    // dashboard where there is no .jar-page element.
    document.querySelectorAll('[data-jar-color]').forEach(function (el) {
        var c = el.dataset.jarColor;
        if (c) el.style.setProperty('--jar-color', c);
    });

    // ── Confirm modal ────────────────────────────────────────────
    // Any form with data-confirm="..." shows a themed modal
    // before submitting. Replaces native window.confirm().
    (function () {
        var modal = document.getElementById('confirm-modal');
        if (!modal) return;

        var messageEl   = modal.querySelector('#confirm-message');
        var okBtn       = modal.querySelector('[data-confirm-ok]');
        var pendingForm = null;
        var lastFocus   = null;

        function open(form) {
            pendingForm = form;
            messageEl.textContent = form.dataset.confirm || 'Are you sure?';
            lastFocus = document.activeElement;
            modal.hidden = false;
            okBtn.focus();
        }

        function close() {
            modal.hidden = true;
            pendingForm = null;
            if (lastFocus && lastFocus.focus) lastFocus.focus();
        }

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (!form.dataset.confirm) return;
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            open(form);
        });

        okBtn.addEventListener('click', function () {
            if (!pendingForm) return close();
            pendingForm.dataset.confirmed = '1';
            pendingForm.submit();
        });

        modal.querySelectorAll('[data-confirm-cancel]').forEach(function (el) {
            el.addEventListener('click', close);
        });

        document.addEventListener('keydown', function (e) {
            if (!modal.hidden && e.key === 'Escape') close();
        });
    })();

    // ── Emoji picker ─────────────────────────────────────────────
    // Curated set — no external library. Click the trigger
    // button to open, click a cell to insert, click outside
    // or press Escape to close.
    (function () {
        var input   = document.getElementById('emoji-input');
        var trigger = document.getElementById('emoji-trigger');
        var picker  = document.getElementById('emoji-picker');
        if (!input || !trigger || !picker) return;

        var groups = {
            'love':     ['❤️','💖','💗','💕','💞','💓','💘','💝','💟','🩷','💌','💋','😘','🥰','😍','🤍','🖤','💜','💙','💚','💛','🧡'],
            'feelings': ['🥹','🥺','😊','😄','🥲','😢','😭','😔','😞','😡','😤','🙁','🤗','🫂','🙏','🫶','😴','🥱','😪'],
            'cute':     ['🐻','🐰','🐱','🐶','🦊','🐹','🐼','🐨','🐸','🐣','🦋','🌸','🌷','🌹','🌻','🌼','💐','🍓','🍰','🧸'],
            'cosmic':   ['🌙','⭐','✨','🌟','💫','🌠','☀️','🌈','⛅','🌤️','🪐','🌌'],
            'moments':  ['🌅','🌄','🍵','☕','🎂','🎁','🎉','🎈','📸','🎵','🎶','📚','🍽️','🍕','🍦','🥂'],
            'symbols':  ['♡','♥','❀','✿','✦','✧','∞','☾','☽','♪','♫','❣️','💯'],
        };

        function render() {
            var html = '';
            Object.keys(groups).forEach(function (name) {
                html += '<div class="emoji-group">';
                html += '<div class="emoji-group-title">' + name + '</div>';
                html += '<div class="emoji-group-grid">';
                groups[name].forEach(function (ch) {
                    html += '<button type="button" class="emoji-cell" data-emoji="' + ch + '">' + ch + '</button>';
                });
                html += '</div></div>';
            });
            picker.innerHTML = html;
        }

        function open()  { picker.hidden = false; }
        function close() { picker.hidden = true; }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            picker.hidden ? open() : close();
        });

        picker.addEventListener('click', function (e) {
            var btn = e.target.closest('.emoji-cell');
            if (!btn) return;
            input.value = btn.dataset.emoji;
            close();
            input.focus();
        });

        document.addEventListener('click', function (e) {
            if (picker.hidden) return;
            if (picker.contains(e.target)) return;
            if (trigger.contains(e.target)) return;
            close();
        });

        document.addEventListener('keydown', function (e) {
            if (!picker.hidden && e.key === 'Escape') close();
        });

        render();
    })();

    // ── Jar page draw logic ──────────────────────────────────────
    const page = document.querySelector('.jar-page');
    if (!page) return;

    const jarId    = page.dataset.jarId;
    const csrf     = page.dataset.csrf;
    const stage    = document.getElementById('jar-stage');
    const jarBtn   = document.getElementById('jar-btn');
    const slot     = document.getElementById('msg-slot');
    const drawBtn  = document.getElementById('draw-btn');
    const actions  = document.getElementById('jar-actions');
    if (!jarId || !stage || !jarBtn || !slot || !drawBtn || !actions) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const OPEN_MS   = reduceMotion ? 0 : 600;
    const PRE_DELAY = reduceMotion ? 0 : 180;

    let drawing   = false;
    let firstDraw = true;

    function showJar() {
        stage.hidden = false;
        stage.classList.remove('is-shaking', 'is-opening');
        slot.hidden = true;
        slot.innerHTML = '';
    }

    function hideJar() {
        stage.hidden = true;
        stage.classList.remove('is-shaking', 'is-opening');
    }

    function shake() {
        if (reduceMotion) return;
        stage.classList.remove('is-shaking');
        void stage.offsetWidth;
        stage.classList.add('is-shaking');
    }

    function openJar() {
        if (reduceMotion) return Promise.resolve();
        stage.classList.add('is-opening');
        return new Promise((resolve) => setTimeout(resolve, OPEN_MS));
    }

    async function draw() {
        if (drawing) return;
        drawing = true;

        jarBtn.disabled  = true;
        drawBtn.disabled = true;

        if (stage.hidden) {
            showJar();
            await new Promise(requestAnimationFrame);
        }

        shake();
        await new Promise((r) => setTimeout(r, PRE_DELAY));

        const openPromise = openJar();

        const reqPromise = (async () => {
            const body = new URLSearchParams();
            body.set('jar_id', jarId);
            body.set('_csrf', csrf);

            const res = await fetch('/jar-draw.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
                body,
                credentials: 'same-origin',
            });

            if (res.status === 401) {
                window.location.href = '/login.php';
                throw new Error('unauthenticated');
            }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })();

        try {
            const [data] = await Promise.all([reqPromise, openPromise]);

            if (!data.ok) {
                slot.innerHTML = '<article class="msg-card">'
                    + '<p class="muted" style="text-align:center;">'
                    + 'Something went wrong. Please try again.</p></article>';
            } else if (data.empty) {
                slot.innerHTML = '<article class="msg-card msg-empty"><p>'
                    + escapeHtml(data.message) + '</p></article>';
            } else {
                slot.innerHTML = data.html;
            }

            hideJar();
            slot.hidden = false;

            if (!reduceMotion) {
                slot.classList.remove('is-revealed');
                void slot.offsetWidth;
                slot.classList.add('is-revealed');
            }

            if (firstDraw) {
                actions.hidden = false;
                firstDraw = false;
            }
            drawBtn.disabled = false;
        } catch (err) {
            console.error(err);
            showJar();
            drawBtn.disabled = false;
            jarBtn.disabled  = false;
        } finally {
            drawing = false;
            jarBtn.disabled = false;
        }
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    jarBtn.addEventListener('click', draw);
    drawBtn.addEventListener('click', draw);
})();