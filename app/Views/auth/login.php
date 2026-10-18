<?php
/**
 * Halaman login — ID Karyawan saja (tanpa password).
 * Desain: panel ilustrasi ungu + panel form putih, responsif semua ukuran.
 *
 * @var string $title
 * @var array  $errors
 * @var array  $old
 */

$errors = $errors ?? [];
$errId  = (string) ($errors['employee_id'] ?? '');
?>
<div class="pp-page">
    <main class="pp-shell">

        <section class="pp-art">
            <div class="pp-brand">
                <span class="pp-brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 2.6 20.1 7v10L12 21.4 3.9 17V7z"/>
                        <path d="m8.6 12.2 2.4 2.4 4.4-4.6" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="pp-brand-text">
                    <b><?= e(config('app.name')) ?></b>
                    <small><?= e(__('Gudang & Stok')) ?></small>
                </span>
            </div>

            <div class="pp-fig">
                <svg class="pp-illu" viewBox="48 18 424 404" role="img"
                     aria-label="<?= e(__('Ilustrasi kotak barang, daftar periksa, dan label barcode')) ?>">
                    <circle cx="262" cy="226" r="188" fill="#fff" opacity=".07"/>
                    <circle cx="262" cy="226" r="142" fill="#fff" opacity=".06"/>
                    <path d="M104 312C72 208 148 108 258 96" fill="none" stroke="#fff" stroke-opacity=".30"
                          stroke-width="3" stroke-linecap="round" stroke-dasharray="2 13"/>
                    <circle cx="398" cy="330" r="15" fill="#b79bf0" opacity=".85"/>
                    <circle cx="122" cy="196" r="9" fill="#ffc46b" opacity=".9"/>
                    <circle cx="430" cy="176" r="7" fill="#7fd8c4" opacity=".9"/>
                    <path d="M150 168h20M160 158v20" stroke="#fff" stroke-opacity=".45" stroke-width="3.4"
                          stroke-linecap="round"/>
                    <path d="M408 250h16M416 242v16" stroke="#fff" stroke-opacity=".35" stroke-width="3.2"
                          stroke-linecap="round"/>

                    <ellipse cx="262" cy="392" rx="172" ry="15" fill="#3b2179" opacity=".32"/>

                    <g>
                        <rect x="318" y="198" width="110" height="86" rx="10" fill="#efe7ff"/>
                        <rect x="318" y="198" width="110" height="19" rx="9" fill="#dcccfb"/>
                        <rect x="364" y="198" width="15" height="86" fill="#cbb6f6"/>
                    </g>

                    <g>
                        <rect x="110" y="266" width="94" height="78" rx="10" fill="#e6dbff"/>
                        <rect x="110" y="266" width="94" height="17" rx="8" fill="#d3c2f9"/>
                        <rect x="148" y="266" width="13" height="78" fill="#c6b1f4"/>
                    </g>

                    <g>
                        <rect x="174" y="234" width="166" height="114" rx="12" fill="#ffffff"/>
                        <rect x="174" y="234" width="166" height="22" rx="11" fill="#f4efff"/>
                        <rect x="243" y="234" width="27" height="114" fill="#efe7ff"/>
                        <rect x="196" y="276" width="92" height="50" rx="9" fill="#f8f5ff"/>
                        <g fill="#7b52cc">
                            <rect x="205" y="285" width="3" height="32"/>
                            <rect x="211" y="285" width="2" height="32"/>
                            <rect x="217" y="285" width="4" height="32"/>
                            <rect x="225" y="285" width="2" height="32"/>
                            <rect x="231" y="285" width="3" height="32"/>
                            <rect x="238" y="285" width="2" height="32"/>
                            <rect x="244" y="285" width="4" height="32"/>
                            <rect x="252" y="285" width="2" height="32"/>
                            <rect x="258" y="285" width="3" height="32"/>
                            <rect x="265" y="285" width="2" height="32"/>
                        </g>
                    </g>

                    <g transform="translate(298 56)">
                        <rect x="0" y="0" width="120" height="136" rx="15" fill="#ffffff"/>
                        <rect x="39" y="-11" width="42" height="22" rx="9" fill="#b79bf0"/>
                        <g>
                            <circle cx="27" cy="34" r="9" fill="#7fd8c4"/>
                            <path d="m22.6 34 3.2 3.4 5-6" fill="none" stroke="#fff" stroke-width="2.2"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="44" y="29" width="58" height="8" rx="4" fill="#ece5fb"/>

                            <circle cx="27" cy="68" r="9" fill="#7fd8c4"/>
                            <path d="m22.6 68 3.2 3.4 5-6" fill="none" stroke="#fff" stroke-width="2.2"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="44" y="63" width="46" height="8" rx="4" fill="#ece5fb"/>

                            <circle cx="27" cy="102" r="9" fill="#ffc46b"/>
                            <path d="m23 102 3 3.2 4.6-5.6" fill="none" stroke="#fff" stroke-width="2.2"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="44" y="97" width="52" height="8" rx="4" fill="#ece5fb"/>
                        </g>
                    </g>

                    <g transform="translate(96 104) rotate(-7)">
                        <rect x="0" y="0" width="96" height="58" rx="11" fill="#ffffff"/>
                        <g fill="#7b52cc" opacity=".9">
                            <rect x="14" y="16" width="3" height="26"/>
                            <rect x="20" y="16" width="2" height="26"/>
                            <rect x="26" y="16" width="4" height="26"/>
                            <rect x="34" y="16" width="2" height="26"/>
                            <rect x="40" y="16" width="3" height="26"/>
                            <rect x="47" y="16" width="2" height="26"/>
                            <rect x="53" y="16" width="4" height="26"/>
                            <rect x="61" y="16" width="2" height="26"/>
                            <rect x="67" y="16" width="3" height="26"/>
                        </g>
                    </g>
                </svg>
            </div>

            <svg class="pp-art-wave" viewBox="0 0 520 120" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                <path fill="#6a43b8" d="M0 52c104-34 178 34 254 18C356-2 420 12 520 34v86H0z"/>
                <path fill="#7b52cc" d="M0 70c108-30 182 30 258 16 100-18 160-54 262-34v68H0z"/>
            </svg>
        </section>

        <section class="pp-form-panel">
            <div class="pp-body">

                <h1 class="pp-hello"><?= e(__('Halo,')) ?></h1>
                <p class="pp-sub"><?= e(__('Masuk ke akun Anda untuk melanjutkan.')) ?></p>

                <?php foreach (consume_flash() as $flash): ?>
                    <div class="pp-alert pp-alert--<?= e((string) $flash['type']) ?>" role="status">
                        <?= e((string) $flash['message']) ?>
                    </div>
                <?php endforeach; ?>

                <form class="pp-form" method="post" action="<?= url('/login') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="pp-field<?= $errId !== '' ? ' is-error' : '' ?>">
                        <label for="employee_id"><?= e(__('Nomor Karyawan')) ?></label>
                        <input
                            type="text"
                            id="employee_id"
                            name="employee_id"
                            value=""
                            placeholder="<?= e(__('Masukkan nomor karyawan')) ?>"
                            autocomplete="off"
                            inputmode="text"
                            autocapitalize="characters"
                            spellcheck="false"
                            aria-describedby="employee_id_help"
                            <?= $errId !== '' ? 'aria-invalid="true" aria-describedby="employee_id_help employee_id_err"' : '' ?>
                            required>

                        <?php if ($errId !== ''): ?>
                            <p class="pp-error" id="employee_id_err">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/>
                                </svg>
                                <span><?= e($errId) ?></span>
                            </p>
                        <?php endif; ?>

                        <p class="pp-hint" id="employee_id_help">
                            <?= e(__('Contoh: WE100001. ID sama dengan nomor karyawan di absensi.')) ?>
                        </p>
                    </div>

                    <div class="pp-row">
                        <a href="#" class="pp-forgot" id="ppForgot"><?= e(__('Lupa Nomor Karyawan?')) ?></a>
                    </div>

                    <button type="submit" class="pp-btn"><?= e(__('Masuk')) ?></button>
                </form>

                <p class="pp-note" id="ppNote" hidden>
                    <?= e(__('Hubungi admin untuk mendaftarkan Nomor Karyawan Anda. Akun yang dinonaktifkan tidak bisa masuk meskipun ID-nya benar.')) ?>
                </p>

                <p class="pp-reg">
                    <?= e(__('Belum punya akses?')) ?>
                    <a href="#" class="pp-link" id="ppContact"><?= e(__('Hubungi admin')) ?></a>
                </p>

            </div>

            <p class="pp-foot">
                &copy; <?= date('Y') ?> <?= e(config('app.name')) ?> &middot; <?= e(__('Gudang & Stok')) ?>
            </p>
        </section>

    </main>

    <div class="pp-toast" id="ppToast" role="status" aria-live="polite"></div>
</div>

<script>
(function () {
    var input   = document.getElementById('employee_id');
    var forgot  = document.getElementById('ppForgot');
    var contact = document.getElementById('ppContact');
    var note    = document.getElementById('ppNote');
    var toast   = document.getElementById('ppToast');
    var toastTimer;

    function say(msg) {
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.add('is-on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('is-on'); }, 3200);
    }

    if (input) {
        // ID karyawan selalu kapital
        input.addEventListener('input', function () {
            var pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            try { this.setSelectionRange(pos, pos); } catch (e) {}
        });

        // fokus otomatis hanya di perangkat bermouse (di HP keyboard tidak muncul sendiri)
        if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            try { input.focus({ preventScroll: true }); } catch (e) {}
        }
    }

    function showNote() {
        if (!note) return;
        note.hidden = false;
        note.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    if (forgot) forgot.addEventListener('click', function (e) { e.preventDefault(); showNote(); });
    if (contact) contact.addEventListener('click', function (e) { e.preventDefault(); showNote(); });
})();
</script>
