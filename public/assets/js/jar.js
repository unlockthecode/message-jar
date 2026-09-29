(() => {
    'use strict';

    // Apply jar colors from data-jar-color attributes on any page.
    // This runs BEFORE the .jar-page check below, so it also fires
    // on the dashboard where there is no .jar-page element.
    //
    // Why JS instead of an inline style="--jar-color: ..." attribute?
    // Some browsers resolve custom properties in the initial HTML
    // against a stylesheet that isn't fully parsed yet, decide the
    // property has no valid value, and cache that. Setting the same
    // value later via setProperty() reliably re-resolves it.
    document.querySelectorAll('[data-jar-color]').forEach(function (el) {
        var c = el.dataset.jarColor;
        if (c) el.style.setProperty('--jar-color', c);
    });

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

    const OPEN_MS    = reduceMotion ? 0 : 600;  // must match CSS .is-opening duration
    const PRE_DELAY  = reduceMotion ? 0 : 180;  // pause between shake and open

    let drawing   = false;
    let firstDraw = true;

    /* ── State helpers ───────────────────────────────── */

    function showJar() {
        // Put the jar back. Called at the start of every draw after the first.
        stage.hidden = false;
        stage.classList.remove('is-shaking', 'is-opening');
        slot.hidden = true;
        slot.innerHTML = '';
    }

    function hideJar() {
        // Jar is fully gone; only the message card should be visible.
        stage.hidden = true;
        stage.classList.remove('is-shaking', 'is-opening');
    }

    /* ── Animation helpers ───────────────────────────── */

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

    /* ── Main draw routine ───────────────────────────── */

    async function draw() {
        if (drawing) return;
        drawing = true;

        // Disable both triggers while the request is in flight.
        jarBtn.disabled  = true;
        drawBtn.disabled = true;

        // If jar is hidden (2nd+ draw), bring it back first.
        if (stage.hidden) {
            showJar();
            await new Promise(requestAnimationFrame);
        }

        // Shake, then a small pause, then pop the lid.
        shake();
        await new Promise((r) => setTimeout(r, PRE_DELAY));

        const openPromise = openJar();

        // Fire the request in parallel so it doesn't add latency.
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

            // Render the returned content into the slot.
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

            // Jar is now gone; slot becomes the only visible element.
            hideJar();
            slot.hidden = false;

            if (!reduceMotion) {
                slot.classList.remove('is-revealed');
                void slot.offsetWidth;
                slot.classList.add('is-revealed');
            }

            // After first successful draw, reveal the actions container
            // and enable the button.
            if (firstDraw) {
                actions.hidden = false;
                firstDraw = false;
            }
            drawBtn.disabled = false;
        } catch (err) {
            console.error(err);
            // On failure, put the jar back so she can try again.
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