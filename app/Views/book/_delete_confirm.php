<?php
/**
 * Halaman konfirmasi hapus periode (3 langkah).
 *
 * @var array $p  periode
 * @var array $pv hasil preview
 */
$p  = $p ?? [];
$pv = $pv ?? ['snapshots' => 0, 'groups' => 0, 'diff_value' => 0];
$code = (string) ($p['audit_month'] ?? '');
$id   = (int) ($p['id'] ?? 0);
$snap = (int) ($pv['snapshots'] ?? 0);
$grp  = (int) ($pv['groups'] ?? 0);
$diff = (float) ($pv['diff_value'] ?? 0);

$fmt = static function (float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
};
?>
<style>
    .sfdel { max-width: 960px; }
    .sfdel-head { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; justify-content: space-between; margin-bottom: 1.25rem; }
    .sfdel-head h1 { margin: 0 0 .35rem; font-size: 1.5rem; line-height: 1.25; }
    .sfdel-head p { margin: 0; max-width: 62ch; color: var(--muted, #64748b); font-size: .9rem; line-height: 1.55; }
    .sfdel-card { background: #fff; border: 1px solid var(--border, #e5e7eb); border-radius: 14px; padding: 1.35rem 1.5rem; margin-bottom: 1.1rem; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
    .sfdel-step { display: flex; gap: .85rem; align-items: flex-start; margin-bottom: 1.1rem; }
    .sfdel-num { flex: 0 0 32px; width: 32px; height: 32px; border-radius: 50%; background: #eef2ff; color: #3730a3; font-weight: 700; font-size: .9rem; display: flex; align-items: center; justify-content: center; }
    .sfdel-step h2 { margin: 0 0 .2rem; font-size: 1rem; font-weight: 650; }
    .sfdel-step p { margin: 0; font-size: .84rem; color: var(--muted, #64748b); line-height: 1.5; }
    .sfdel-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .9rem; }
    .sfdel-stat { border: 1px solid var(--border, #e5e7eb); border-radius: 12px; padding: 1rem 1.1rem; background: #f8fafc; }
    .sfdel-stat .lbl { display: block; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; color: #64748b; margin-bottom: .45rem; }
    .sfdel-stat .val { display: block; font-size: 1.35rem; font-weight: 700; color: #0f172a; line-height: 1.15; font-variant-numeric: tabular-nums; }
    .sfdel-stat .sub { display: block; margin-top: .3rem; font-size: .76rem; color: #64748b; }
    .sfdel-note { margin: 1rem 0 0; font-size: .82rem; color: #475569; background: #f1f5f9; border-left: 3px solid #cbd5e1; padding: .7rem .9rem; border-radius: 0 8px 8px 0; line-height: 1.5; }
    .sfdel-warn { display: flex; gap: .75rem; align-items: flex-start; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 12px; padding: .9rem 1.1rem; margin-bottom: 1.1rem; font-size: .85rem; line-height: 1.5; }
    .sfdel-field { margin-top: .35rem; }
    .sfdel-field label { display: block; font-size: .88rem; font-weight: 600; margin-bottom: .5rem; }
    .sfdel-field code { background: #eef2ff; color: #3730a3; padding: .1rem .4rem; border-radius: 6px; font-size: .95em; }
    .sfdel-input { width: 100%; max-width: 320px; min-height: 48px; padding: .65rem .85rem; font-size: 1.05rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .06em; border: 1.5px solid #cbd5e1; border-radius: 10px; background: #fff; }
    .sfdel-input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, .15); }
    .sfdel-input.is-ok { border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22, 163, 74, .12); }
    .sfdel-input.is-bad { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, .1); }
    .sfdel-hint { margin: .5rem 0 0; font-size: .8rem; color: #64748b; min-height: 1.1rem; }
    .sfdel-hint.ok { color: #15803d; font-weight: 600; }
    .sfdel-hint.bad { color: #b91c1c; }
    .sfdel-actions { display: flex; flex-wrap: wrap; gap: .7rem; margin-top: 1.15rem; }
    .sfdel-actions .btn { min-height: 46px; padding-inline: 1.25rem; }
    .sfdel-actions .btn-danger[disabled] { opacity: .5; cursor: not-allowed; }
    @media (max-width: 640px) {
        .sfdel-card { padding: 1.1rem 1rem; }
        .sfdel-head h1 { font-size: 1.25rem; }
        .sfdel-input { max-width: 100%; }
    }
</style>

<div class="sfdel">
    <div class="sfdel-head">
        <div>
            <h1>Hapus periode <?= e($code) ?></h1>
            <p>Verifikasi 3 langkah. Data dicadangkan otomatis ke <code>storage/backups</code> sebelum dihapus,
               dan penghapusan dibatalkan bila cadangan gagal dibuat.</p>
        </div>
        <a class="btn btn-outline" style="min-height:44px;" href="<?= url('tahun-buku') ?>">Kembali ke Tahun Buku</a>
    </div>

    <div class="sfdel-warn">
        <span style="flex:0 0 auto;"><?= icon('alert-triangle') ?: '' ?></span>
        <div>
            <strong>Tindakan permanen.</strong>
            Setelah dihapus, periode dan seluruh snapshot-nya tidak bisa dipulihkan dari aplikasi.
            Pulihkan hanya dari file cadangan JSON yang dibuat pada langkah 2.
        </div>
    </div>

    <section class="sfdel-card">
        <div class="sfdel-step">
            <span class="sfdel-num">1</span>
            <div>
                <h2>Pratinjau</h2>
                <p>Inilah isi periode yang akan dihapus. Periksa dulu sebelum melanjutkan.</p>
            </div>
        </div>
        <div class="sfdel-stats">
            <div class="sfdel-stat">
                <span class="lbl">Periode</span>
                <span class="val" style="font-size:1.15rem;"><?= e($code) ?></span>
                <span class="sub">Status: <?= e((string) ($p['status'] ?? '-')) ?></span>
            </div>
            <div class="sfdel-stat">
                <span class="lbl">Snapshot</span>
                <span class="val"><?= number_format($snap, 0, ',', '.') ?></span>
                <span class="sub">baris akan dihapus</span>
            </div>
            <div class="sfdel-stat">
                <span class="lbl">Group terdampak</span>
                <span class="val"><?= number_format($grp, 0, ',', '.') ?></span>
                <span class="sub">group kehilangan snapshot</span>
            </div>
            <div class="sfdel-stat">
                <span class="lbl">Nilai selisih</span>
                <span class="val" style="font-size:1.1rem;"><?= e($fmt($diff)) ?></span>
                <span class="sub">total selisih snapshot</span>
            </div>
        </div>
        <?php if (trim((string) ($p['note'] ?? '')) !== ''): ?>
            <p class="sfdel-note"><strong>Catatan periode:</strong> <?= e((string) $p['note']) ?></p>
        <?php endif; ?>
    </section>

    <section class="sfdel-card">
        <div class="sfdel-step">
            <span class="sfdel-num">2</span>
            <div>
                <h2>Cadangan otomatis</h2>
                <p>Sebelum menghapus, sistem menulis file <code>hapus_periode_<?= $id ?>_&lt;tanggal&gt;.json</code>
                   berisi periode dan semua snapshot-nya ke folder <code>storage/backups</code>.
                   Bila penulisan gagal atau ukurannya tidak cocok, penghapusan dibatalkan.</p>
            </div>
        </div>
    </section>

    <section class="sfdel-card">
        <div class="sfdel-step">
            <span class="sfdel-num">3</span>
            <div>
                <h2>Konfirmasi</h2>
                <p>Ketik kode periode persis sama untuk membuka tombol hapus.</p>
            </div>
        </div>

        <form method="post" action="<?= url('tahun-buku/hapus/' . $id) ?>" id="sfdel-form" novalidate>
            <?= csrf_field() ?>
            <div class="sfdel-field">
                <label for="del_confirm">Ketik <code><?= e($code) ?></code></label>
                <input type="text" id="del_confirm" name="confirm_code" class="sfdel-input"
                       autocomplete="off" autocapitalize="off" spellcheck="false"
                       inputmode="text" placeholder="<?= e($code) ?>" required>
                <p class="sfdel-hint" id="sfdel-hint">Tombol hapus aktif setelah kode cocok.</p>
            </div>
            <div class="sfdel-actions">
                <button type="submit" class="btn btn-danger" id="sfdel-submit" disabled>
                    <?= icon('trash') ?> Hapus periode ini
                </button>
                <a class="btn btn-outline" href="<?= url('tahun-buku') ?>">Batal</a>
            </div>
        </form>
    </section>
</div>

<script>
(function () {
    var code   = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
    var input  = document.getElementById('del_confirm');
    var btn    = document.getElementById('sfdel-submit');
    var hint   = document.getElementById('sfdel-hint');
    var form   = document.getElementById('sfdel-form');
    if (!input || !btn || !form) return;

    var norm = function (v) { return (v || '').trim(); };

    function sync() {
        var v = norm(input.value);
        var ok = v === code;
        btn.disabled = !ok;
        input.classList.toggle('is-ok', ok);
        input.classList.toggle('is-bad', v !== '' && !ok);
        if (v === '') {
            hint.className = 'sfdel-hint';
            hint.textContent = 'Tombol hapus aktif setelah kode cocok.';
        } else if (ok) {
            hint.className = 'sfdel-hint ok';
            hint.textContent = 'Kode cocok. Tombol hapus sudah aktif.';
        } else {
            hint.className = 'sfdel-hint bad';
            hint.textContent = 'Belum cocok — harus persis "' + code + '".';
        }
    }

    input.addEventListener('input', sync);
    input.addEventListener('change', sync);
    input.addEventListener('paste', function () { setTimeout(sync, 0); });
    sync();

    form.addEventListener('submit', function (e) {
        if (norm(input.value) !== code) {
            e.preventDefault();
            input.focus();
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Menghapus…';
    });
})();
</script>
