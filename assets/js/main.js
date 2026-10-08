(function () {
  'use strict';

  // Mobil menü
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç');
    });
  }

  // Seçim kutusu değişince filtre formunu gönder
  document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () {
      if (select.form) select.form.submit();
    });
  });

  // Yüklenemeyen logo/fotoğrafları baş harf rozetine çevir
  document.querySelectorAll('.badge img, .avatar img').forEach(function (img) {
    img.addEventListener('error', function () {
      var holder = img.parentNode;
      var name = (img.getAttribute('alt') || '').replace(/ logosu$/, '');
      var initials = name.trim().split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toLocaleUpperCase('tr-TR');
      holder.classList.add(holder.classList.contains('badge') ? 'badge-initials' : 'avatar-initials');
      holder.textContent = initials || '?';
    }, { once: true });
  });
  // ---- Manşet slider ----
  document.querySelectorAll('[data-slider]').forEach(function (slider) {
    var slides = slider.querySelectorAll('[data-slide]');
    var tabs = slider.querySelectorAll('[data-slide-to]');
    if (slides.length < 2) return;
    var current = 0;
    var delay = 7000;
    var timer = null;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    slider.style.setProperty('--slide-ms', delay + 'ms');

    function show(i) {
      current = (i + slides.length) % slides.length;
      slides.forEach(function (s, k) {
        var on = k === current;
        s.classList.toggle('active', on);
        s.setAttribute('aria-hidden', on ? 'false' : 'true');
        var link = s.querySelector('.slide-copy');
        if (link) link.setAttribute('tabindex', on ? '0' : '-1');
      });
      tabs.forEach(function (t, k) {
        var on = k === current;
        t.classList.remove('active');
        if (on) { void t.offsetWidth; t.classList.add('active'); }
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }
    function start() {
      if (reduce) return;
      stop();
      timer = setInterval(function () { show(current + 1); }, delay);
    }
    function stop() { if (timer) clearInterval(timer); timer = null; }

    tabs.forEach(function (t) {
      t.addEventListener('click', function () { show(parseInt(t.getAttribute('data-slide-to'), 10)); start(); });
    });
    slider.addEventListener('mouseenter', function () { stop(); slider.classList.add('paused'); });
    slider.addEventListener('mouseleave', function () { slider.classList.remove('paused'); start(); });
    slider.addEventListener('focusin', function () { stop(); slider.classList.add('paused'); });

    // Kaydırma (mobil)
    var x0 = null;
    slider.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    slider.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 50) { show(current + (dx < 0 ? 1 : -1)); start(); }
      x0 = null;
    });
    start();
  });

  // ---- Geri sayım ----
  var countdowns = document.querySelectorAll('[data-countdown]');
  function tickCountdowns() {
    countdowns.forEach(function (el) {
      var target = Date.parse(el.getAttribute('data-countdown'));
      if (isNaN(target)) return;
      var diff = Math.floor((target - Date.now()) / 1000);
      if (diff <= 0) { el.innerHTML = ''; return; }
      var d = Math.floor(diff / 86400), h = Math.floor(diff % 86400 / 3600), m = Math.floor(diff % 3600 / 60), s = diff % 60;
      var parts = d > 0 ? [[d, 'g'], [h, 's'], [m, 'dk']] : [[h, 's'], [m, 'dk'], [s, 'sn']];
      el.innerHTML = parts.map(function (p) { return '<span>' + p[0] + '<small>' + p[1] + '</small></span>'; }).join('');
    });
  }
  if (countdowns.length) { tickCountdowns(); setInterval(tickCountdowns, 1000); }

  // ---- Maç akışı filtresi ----
  document.querySelectorAll('[data-feed]').forEach(function (feed) {
    feed.setAttribute('data-mode', 'key');
    var card = feed.closest('.card');
    if (!card) return;
    card.querySelectorAll('[data-feed-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        feed.setAttribute('data-mode', btn.getAttribute('data-feed-filter'));
        card.querySelectorAll('[data-feed-filter]').forEach(function (b) { b.classList.toggle('active', b === btn); });
      });
    });
  });

  // ---- Devre sekmeleri (istatistikler) ----
  document.querySelectorAll('[data-half]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var card = btn.closest('.card');
      var half = btn.getAttribute('data-half');
      card.querySelectorAll('[data-half]').forEach(function (b) { b.classList.toggle('active', b === btn); });
      card.querySelectorAll('[data-half-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-half-panel') !== half; });
    });
  });

  // ---- Sekmeler (oyuncu performans tabloları) ----
  document.querySelectorAll('[data-tab-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var card = btn.closest('.card');
      card.querySelectorAll('[data-tab-target]').forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        var panel = document.getElementById(b.getAttribute('data-tab-target'));
        if (panel) panel.hidden = !on;
      });
    });
  });

  // ---- Bağlantı kopyala ----
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy');
      var done = function () { var old = btn.textContent; btn.textContent = 'Kopyalandı ✓'; setTimeout(function () { btn.textContent = old; }, 1800); };
      if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () {});
    });
  });

  // ---- Alt menüde aktif bölüm ----
  var subLinks = document.querySelectorAll('.subnav a[href^="#"]');
  if (subLinks.length && 'IntersectionObserver' in window) {
    var map = {};
    subLinks.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && map[en.target.id]) {
          subLinks.forEach(function (a) { a.classList.remove('active'); });
          map[en.target.id].classList.add('active');
        }
      });
    }, { rootMargin: '-40% 0px -55% 0px' });
    Object.keys(map).forEach(function (id) { var el = document.getElementById(id); if (el) io.observe(el); });
  }
  // ---- Kurallarda arama ----
  var ruleSearch = document.querySelector('[data-rule-search]');
  if (ruleSearch) {
    var items = Array.prototype.slice.call(document.querySelectorAll('.rule-item'));
    items.forEach(function (it) { it.setAttribute('data-orig', it.innerHTML); });
    var norm = function (t) { return t.toLocaleLowerCase('tr-TR'); };
    var escapeRe = function (t) { return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); };
    var timer;
    ruleSearch.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        var q = norm(ruleSearch.value.trim());
        var any = false;
        document.querySelectorAll('[data-rule-section]').forEach(function (sec) {
          var secHit = false;
          sec.querySelectorAll('.rule-item').forEach(function (it) {
            it.innerHTML = it.getAttribute('data-orig');
            if (!q) { it.hidden = false; return; }
            var hit = norm(it.textContent).indexOf(q) !== -1 || norm(sec.querySelector('.rule-head').textContent).indexOf(q) !== -1;
            it.hidden = !hit;
            if (hit) {
              secHit = true;
              var re = new RegExp('(' + escapeRe(ruleSearch.value.trim()) + ')', 'gi');
              var walker = document.createTreeWalker(it, NodeFilter.SHOW_TEXT);
              var nodes = []; while (walker.nextNode()) nodes.push(walker.currentNode);
              nodes.forEach(function (n) {
                re.lastIndex = 0;
                if (!re.test(n.nodeValue)) return;
                re.lastIndex = 0;
                var span = document.createElement('span');
                span.innerHTML = n.nodeValue.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(re, '<mark class="rule-hit">$1</mark>');
                n.parentNode.replaceChild(span, n);
              });
              var det = it.querySelector('details'); if (det) det.open = true;
            }
          });
          sec.hidden = q ? !secHit : false;
          if (!q || secHit) any = true;
        });
        var empty = document.querySelector('[data-rule-empty]');
        if (empty) empty.hidden = any;
      }, 150);
    });
  }
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });

  // ---- Kurallar içindekiler: aktif bölüm ----
  var tocLinks = document.querySelectorAll('.rules-toc a[href^="#"]');
  if (tocLinks.length && 'IntersectionObserver' in window) {
    var tmap = {};
    tocLinks.forEach(function (a) { tmap[a.getAttribute('href').slice(1)] = a; });
    var tio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && tmap[en.target.id]) {
          tocLinks.forEach(function (a) { a.classList.remove('active'); });
          tmap[en.target.id].classList.add('active');
        }
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    Object.keys(tmap).forEach(function (id) { var el = document.getElementById(id); if (el) tio.observe(el); });
  }
  // ---- Video önizlemesi: tıklanınca oynatıcıyı yükle ----
  function playFacade(el) {
    var src = el.getAttribute('data-embed');
    if (!src) return;
    var iframe = document.createElement('iframe');
    iframe.src = src + (src.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
    iframe.title = el.getAttribute('data-title') || 'Video';
    iframe.allow = 'autoplay; accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen';
    iframe.setAttribute('allowfullscreen', '');
    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    el.innerHTML = '';
    el.appendChild(iframe);
    el.classList.remove('vfacade');
    el.removeAttribute('role');
    el.removeAttribute('tabindex');
    el.removeAttribute('data-embed');
  }
  document.querySelectorAll('.vfacade').forEach(function (el) {
    el.addEventListener('click', function () { playFacade(el); });
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); playFacade(el); }
    });
  });

  // ---- Fotoğraf galerisi: tümünü göster + tam ekran görüntüleyici ----
  document.querySelectorAll('[data-pgal-more]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var section = btn.closest('section');
      section.querySelectorAll('.pgal-item[hidden]').forEach(function (a) { a.hidden = false; });
      btn.parentNode.remove();
    });
  });
  document.querySelectorAll('[data-lightbox]').forEach(function (gal) {
    var items = Array.prototype.slice.call(gal.querySelectorAll('[data-lb-item]'));
    if (!items.length) return;
    var box, img, counter, current = 0, x0 = null;
    function build() {
      box = document.createElement('div');
      box.className = 'lb';
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');
      box.setAttribute('aria-label', 'Fotoğraf görüntüleyici');
      box.innerHTML = '<button type="button" class="lb-close" aria-label="Kapat">✕</button>'
        + '<button type="button" class="lb-nav lb-prev" aria-label="Önceki">‹</button>'
        + '<figure class="lb-fig"><img alt=""><figcaption class="lb-count"></figcaption></figure>'
        + '<button type="button" class="lb-nav lb-next" aria-label="Sonraki">›</button>';
      document.body.appendChild(box);
      img = box.querySelector('img');
      img.referrerPolicy = 'no-referrer';
      counter = box.querySelector('.lb-count');
      box.querySelector('.lb-close').addEventListener('click', close);
      box.querySelector('.lb-prev').addEventListener('click', function () { show(current - 1); });
      box.querySelector('.lb-next').addEventListener('click', function () { show(current + 1); });
      box.addEventListener('click', function (e) { if (e.target === box) close(); });
      box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
      box.addEventListener('touchend', function (e) {
        if (x0 === null) return;
        var dx = e.changedTouches[0].clientX - x0;
        if (Math.abs(dx) > 40) show(current + (dx < 0 ? 1 : -1));
        x0 = null;
      });
    }
    function onKey(e) {
      if (e.key === 'Escape') close();
      else if (e.key === 'ArrowRight') show(current + 1);
      else if (e.key === 'ArrowLeft') show(current - 1);
    }
    function show(i) {
      current = (i + items.length) % items.length;
      img.src = items[current].getAttribute('href');
      img.alt = items[current].querySelector('img').alt;
      counter.textContent = (current + 1) + ' / ' + items.length;
    }
    function open(i) {
      if (!box) build();
      box.classList.add('open');
      document.body.style.overflow = 'hidden';
      document.addEventListener('keydown', onKey);
      show(i);
      box.querySelector('.lb-close').focus();
    }
    function close() {
      box.classList.remove('open');
      document.body.style.overflow = '';
      document.removeEventListener('keydown', onKey);
      items[current].focus();
    }
    items.forEach(function (a, i) {
      a.addEventListener('click', function (e) { e.preventDefault(); open(i); });
    });
  });
})();
