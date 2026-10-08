<?php
$cfg = ccl_config();
$contact = $cfg['contact'];
?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <a class="brand brand-footer" href="index.php">
        <img class="brand-logo" src="assets/img/logo-white.png" alt="Natura Dünyası CCL CUP" width="270" height="56" loading="lazy">
      </a>
      <p class="muted">Maç sonuçları, puan durumu ve oyuncu istatistikleri maç günü anlık olarak güncellenir.</p>
    </div>
    <div>
      <h4>Turnuva</h4>
      <ul>
        <li><a href="haberler.php">Haberler</a></li>
        <li><a href="fikstur.php">Fikstür &amp; Sonuçlar</a></li>
        <li><a href="puan-durumu.php">Puan Durumu</a></li>
        <li><a href="takimlar.php">Takımlar</a></li>
        <li><a href="istatistikler.php">İstatistikler</a></li>
        <li><a href="kurallar">Kurallar</a></li>
        <li><a href="<?= e($cfg['archive_url']) ?>" target="_blank" rel="noopener">Arşiv (geçmiş sezonlar) ↗</a></li>
      </ul>
    </div>
    <div>
      <h4>İletişim</h4>
      <ul>
        <?php if ($contact['address']): ?><li><?= e($contact['address']) ?></li><?php endif; ?>
        <?php if ($contact['phone']): ?><li><a href="tel:<?= e(preg_replace('/\s+/', '', $contact['phone'])) ?>"><?= e($contact['phone']) ?></a></li><?php endif; ?>
        <?php if ($contact['email']): ?><li><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></li><?php endif; ?>
        <?php if ($contact['instagram']): ?><li><a href="https://instagram.com/<?= e(ltrim($contact['instagram'], '@')) ?>" rel="noopener" target="_blank">Instagram</a></li><?php endif; ?>
        <?php if ($contact['whatsapp']): ?><li><a href="https://wa.me/<?= e(preg_replace('/\D+/', '', $contact['whatsapp'])) ?>" rel="noopener" target="_blank">WhatsApp</a></li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> <?= e($cfg['site_name']) ?></span>
    <span>Natura Dünyası CCL CUP · Kurumlar Arası Futbol Turnuvası</span>
  </div>
</footer>
<script src="<?= e(asset('assets/js/main.js')) ?>" defer></script>
</body>
</html>
