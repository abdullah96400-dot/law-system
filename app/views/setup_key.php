<?php
/* Setup: asks for setup_key from config.php (shown only while setup_key is set). */
?>
<section class="card">
  <h1>Setup — Al Imtyaz Law</h1>
  <?php if ($wrong): ?><div class="flash err" role="alert"><span>This key does not match setup_key in config.php. | المفتاح لا يطابق قيمة setup_key في config.php.</span></div><?php endif; ?>
  <p class="muted">Paste the value of <code>setup_key</code> from config.php (line 56). | الصق قيمة setup_key من ملف config.php (السطر 56).</p>
  <form method="post" action="/setup" class="form-grid"><?= csrf_field() ?>
    <label>setup_key <input name="key" required autocomplete="off" dir="ltr" spellcheck="false"></label>
    <button class="btn">Continue | متابعة</button>
  </form>
</section>
