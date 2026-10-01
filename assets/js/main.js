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
})();
