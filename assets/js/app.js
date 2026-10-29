/* CampusFind client-side helpers (vanilla JavaScript).
   These only improve the experience. All validation and permissions are enforced again on the server. */
(function () {
    'use strict';

    var body = document.body;

    /* ---- mobile navigation ---- */
    var toggle = document.querySelector('[data-nav-toggle]');
    var overlay = document.querySelector('[data-nav-overlay]');
    function setNav(open) {
        body.classList.toggle('nav-open', open);
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        }
    }
    if (toggle) {
        toggle.addEventListener('click', function () { setNav(!body.classList.contains('nav-open')); });
    }
    if (overlay) { overlay.addEventListener('click', function () { setNav(false); }); }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { setNav(false); }
    });

    /* ---- dismissible flash messages ---- */
    document.querySelectorAll('[data-dismiss]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = btn.closest('.flash');
            if (box) { box.remove(); }
        });
    });

    /* ---- confirmation prompts: <form data-confirm="..."> or <button data-confirm="..."> ---- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-confirm]');
        if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    /* ---- stop double submits and show a "working" label ---- */
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) { return; }
            // Wait one tick so the clicked button's name/value is still sent with the form.
            window.setTimeout(function () {
                form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                    var label = btn.getAttribute('data-loading-text');
                    btn.disabled = true;
                    btn.classList.add('is-loading');
                    if (label) {
                        btn.setAttribute('data-original-text', btn.textContent);
                        btn.textContent = label;
                    }
                });
            }, 0);
        });
    });
    // Re-enable buttons if the user comes back with the browser's Back button.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) { return; }
        document.querySelectorAll('button.is-loading').forEach(function (btn) {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            var original = btn.getAttribute('data-original-text');
            if (original) { btn.textContent = original; }
        });
    });

    /* ---- show / hide password ---- */
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-password'));
            if (!input) { return; }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    /* ---- live character counter for textareas ---- */
    document.querySelectorAll('textarea[data-counter]').forEach(function (area) {
        var out = document.getElementById(area.getAttribute('data-counter'));
        if (!out) { return; }
        var max = parseInt(area.getAttribute('maxlength'), 10) || 0;
        function update() { out.textContent = area.value.length + (max ? ' / ' + max : ''); }
        area.addEventListener('input', update);
        update();
    });

    /* ---- photo preview and size check ---- */
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        var preview = document.getElementById(input.getAttribute('data-preview'));
        var maxBytes = parseInt(input.getAttribute('data-max-bytes'), 10) || 0;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) {
                if (preview) { preview.hidden = true; }
                return;
            }
            if (maxBytes && file.size > maxBytes) {
                window.alert('That photo is larger than ' + Math.round(maxBytes / 1048576) + ' MB. Please choose a smaller one.');
                input.value = '';
                if (preview) { preview.hidden = true; }
                return;
            }
            if (preview && window.URL && file.type.indexOf('image/') === 0) {
                preview.src = URL.createObjectURL(file);
                preview.hidden = false;
            }
        });
    });
})();
