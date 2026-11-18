/* Al Imtyaz Law — small, dependency-free interactions. Everything works without
   JavaScript too: forms submit, links open, all tab panels show. */
(function () {
  'use strict';
  var d = document, html = d.documentElement;
  html.classList.add('js');
  var ar = html.lang === 'ar';

  /* ---------- sidebar (phone and tablet) ---------- */
  function side(open) {
    d.body.classList.toggle('side-open', open);
    d.querySelectorAll('[data-side-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
    if (open) { var a = d.querySelector('.side-nav a'); if (a) a.focus(); }
  }
  d.addEventListener('click', function (e) {
    if (e.target.closest('[data-side-toggle]')) side(!d.body.classList.contains('side-open'));
    else if (e.target.closest('[data-side-close]')) side(false);
    /* close open pop-up menus when clicking elsewhere */
    d.querySelectorAll('details[data-pop][open]').forEach(function (p) { if (!p.contains(e.target)) p.removeAttribute('open'); });
    var x = e.target.closest('[data-dismiss]'); if (x) { var fl = x.closest('.flash'); if (fl) fl.remove(); }
    /* theme: switch at once and remember it on this device (the form posts only without JavaScript) */
    var th = e.target.closest('[data-theme-toggle]');
    if (th) { e.preventDefault(); var to = th.value; d.documentElement.setAttribute('data-theme', to); document.cookie = 'theme=' + to + ';path=/;max-age=31536000;samesite=lax' + (location.protocol === 'https:' ? ';secure' : ''); th.value = to === 'dark' ? 'light' : 'dark';
      var mc = d.querySelector('meta[name=theme-color]'); if (mc) mc.content = to === 'dark' ? '#0B1626' : '#152A45'; return; }
    /* a link that opens a folded section (details) further down */
    var o = e.target.closest('[data-open]'); if (o) { var dt = d.getElementById(o.getAttribute('data-open')); if (dt && dt.tagName === 'DETAILS') { e.preventDefault(); dt.open = true; dt.scrollIntoView({ block: 'start' }); var f = dt.querySelector('input,select,textarea'); if (f) f.focus({ preventScroll: true }); } }
  });
  d.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (d.body.classList.contains('side-open')) side(false);
    d.querySelectorAll('details[data-pop][open]').forEach(function (p) { p.removeAttribute('open'); var s = p.querySelector('summary'); if (s) s.focus(); });
  });

  /* ---------- confirm before actions that cannot be undone quickly ---------- */
  var dlg = d.getElementById('confirm-dlg');
  d.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.dataset.confirm && f.dataset.confirmed !== '1') {
      e.preventDefault();
      var sub = e.submitter || null;
      if (!dlg || typeof dlg.showModal !== 'function') { if (window.confirm(f.dataset.confirm)) { f.dataset.confirmed = '1'; f.requestSubmit ? f.requestSubmit(sub) : f.submit(); } return; }
      d.getElementById('dlg-m').textContent = f.dataset.confirm;
      dlg.returnValue = '';
      dlg.showModal();
      dlg.addEventListener('close', function once() {
        dlg.removeEventListener('close', once);
        if (dlg.returnValue === 'yes') { f.dataset.confirmed = '1'; if (f.requestSubmit) f.requestSubmit(sub); else f.submit(); }
      });
      return;
    }
    /* busy state on the button that sent the form (not for downloads or dialogs) */
    if (e.defaultPrevented || f.method === 'dialog' || f.hasAttribute('data-nobusy') || f.target) return;
    var btn = e.submitter || f.querySelector('button:not([type=button]), input[type=submit]');
    if (btn && btn.classList.contains('btn')) {
      setTimeout(function () { btn.setAttribute('aria-busy', 'true'); }, 0);
      setTimeout(function () { btn.removeAttribute('aria-busy'); }, 9000);   // a download keeps the page: free the button again
    }
  });
  window.addEventListener('pageshow', function () { d.querySelectorAll('[aria-busy=true]').forEach(function (b) { b.removeAttribute('aria-busy'); }); });

  /* ---------- in-page tabs: sections marked data-panel become tabs ---------- */
  d.querySelectorAll('[data-tabs]').forEach(function (bar) {
    var panels = Array.prototype.slice.call(d.querySelectorAll('[data-panel]'));
    if (!panels.length) return;
    var ids = []; panels.forEach(function (p) { if (ids.indexOf(p.dataset.panel) < 0) ids.push(p.dataset.panel); });
    var btns = Array.prototype.slice.call(bar.querySelectorAll('button[data-for]')).filter(function (b) { return ids.indexOf(b.dataset.for) >= 0; });
    bar.querySelectorAll('button[data-for]').forEach(function (b) { if (btns.indexOf(b) < 0) b.remove(); });
    function show(id, focus) {
      if (ids.indexOf(id) < 0) id = ids[0];
      panels.forEach(function (p) { var on = p.dataset.panel === id; p.hidden = !on; p.classList.toggle('shown', on); });
      btns.forEach(function (b) { var on = b.dataset.for === id; b.setAttribute('aria-selected', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1; if (on && focus) b.focus(); });
      try { history.replaceState(null, '', '#' + id); } catch (err) {}
    }
    bar.setAttribute('role', 'tablist');
    btns.forEach(function (b, i) {
      b.setAttribute('role', 'tab');
      b.addEventListener('click', function () { show(b.dataset.for); });
      b.addEventListener('keydown', function (e) {
        var k = e.key, n = btns.length;
        if (k === 'ArrowLeft' || k === 'ArrowRight') {   // arrows follow the reading direction
          e.preventDefault(); var fwd = (k === 'ArrowRight') !== ar; show(btns[(i + (fwd ? 1 : -1) + n) % n].dataset.for, true);
        }
        if (k === 'Home') { e.preventDefault(); show(btns[0].dataset.for, true); }
        if (k === 'End') { e.preventDefault(); show(btns[n - 1].dataset.for, true); }
      });
    });
    bar.classList.add('ready');
    /* a link to an anchor inside a panel (e.g. #portal, #timeline) opens that panel */
    var h = location.hash.slice(1), target = h && d.getElementById(h);
    var start = ids.indexOf(h) >= 0 ? h : (target && target.closest('[data-panel]') ? target.closest('[data-panel]').dataset.panel : ids[0]);
    show(start);
    if (target && ids.indexOf(h) < 0) setTimeout(function () { target.scrollIntoView({ block: 'start' }); }, 0);
    /* a link to #panel (or to an anchor inside one) on the same page switches the tab */
    window.addEventListener('hashchange', function () { var hh = location.hash.slice(1), tg = hh && d.getElementById(hh); if (ids.indexOf(hh) >= 0) show(hh); else if (tg && tg.closest('[data-panel]')) { show(tg.closest('[data-panel]').dataset.panel); tg.scrollIntoView({ block: 'start' }); } });
    /* a form error inside a hidden panel: show that panel */
    var errEl = d.querySelector('[data-panel] .flash.err, [data-panel] [aria-invalid=true]');
    if (errEl) show(errEl.closest('[data-panel]').dataset.panel);
  });

  /* ---------- sortable tables (rows on this page) ---------- */
  d.querySelectorAll('table[data-sortable]').forEach(function (t) {
    var ths = t.querySelectorAll('th[data-sort]');
    ths.forEach(function (th) {
      th.tabIndex = 0; th.setAttribute('role', 'columnheader');
      function sort() {
        var idx = Array.prototype.indexOf.call(th.parentNode.children, th), asc = th.getAttribute('aria-sort') !== 'ascending';
        ths.forEach(function (o) { o.removeAttribute('aria-sort'); });
        th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
        var tb = t.tBodies[0], rows = Array.prototype.slice.call(tb.rows).filter(function (r) { return r.cells.length > idx; });
        var num = th.dataset.sort === 'num';
        rows.sort(function (a, b) {
          var x = a.cells[idx].dataset.v != null ? a.cells[idx].dataset.v : a.cells[idx].textContent.trim();
          var y = b.cells[idx].dataset.v != null ? b.cells[idx].dataset.v : b.cells[idx].textContent.trim();
          if (num) { x = parseFloat(x) || 0; y = parseFloat(y) || 0; return asc ? x - y : y - x; }
          return asc ? x.localeCompare(y, html.lang) : y.localeCompare(x, html.lang);
        });
        rows.forEach(function (r) { tb.appendChild(r); });
      }
      th.addEventListener('click', sort);
      th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sort(); } });
    });
  });

  /* ---------- filters: change a select → apply at once (the button stays for keyboards) ---------- */
  d.querySelectorAll('form.filters[data-auto] select').forEach(function (s) { s.addEventListener('change', function () { s.form.requestSubmit ? s.form.requestSubmit() : s.form.submit(); }); });

  /* ---------- required fields: a star on the label (screen readers already hear "required") ---------- */
  d.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (el) {
    var l = el.id && d.querySelector('label[for="' + el.id + '"]');
    if (l && !l.querySelector('.req')) { var s = d.createElement('span'); s.className = 'req'; s.setAttribute('aria-hidden', 'true'); s.textContent = '*'; l.appendChild(s); }
  });

  /* ---------- success messages fade after a while; errors stay ---------- */
  var ok = d.querySelector('.flash.ok');
  if (ok && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) setTimeout(function () { ok.style.transition = 'opacity .4s'; ok.style.opacity = '0'; setTimeout(function () { ok.remove(); }, 450); }, 7000);
})();
