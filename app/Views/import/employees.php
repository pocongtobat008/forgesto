<?php
/** @var array $out */
$rows = $out['rows'] ?? [];
$errors = $out['errors'] ?? [];
$ok = (int) ($out['created'] ?? 0) + (int) ($out['updated'] ?? 0);
$bad = (int) ($out['failed'] ?? 0);
?>
<div class="page-head">
    <div>
        <h1>Hasil Import Karyawan</h1>
        <p>Master data karyawan + akun login. Baris dengan ID sama = group tambahan.</p>
    </div>
    <div class="spacer"></div>
    <a class="btn" href="<?= url('import') ?>"><?= icon('back') ?> Kembali</a>
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin-bottom:1rem;">
    <div class="card" style="padding:1rem;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:#10b981;"><?= (int) ($out['created'] ?? 0) ?></div>
        <div class="faint">Baru</div>
    </div>
    <div class="card" style="padding:1rem;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:#2563eb;"><?= (int) ($out['updated'] ?? 0) ?></div>
        <div class="faint">Diperbarui</div>
    </div>
    <div class="card" style="padding:1rem;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:<?= $bad > 0 ? '#dc2626' : '#6b7280' ?>;"><?= $bad ?></div>
        <div class="faint">Gagal</div>
    </div>
</div>

<?php if ($bad === 0): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;">
        <?= icon('check') ?> Semua baris berhasil disimpan. Akun login aktif dengan password dari kolom <code>password</code> (default <code>Stockforge#2026</code> bila kosong).
    </div>
<?php endif; ?>

<?php if (! empty($errors)): ?>
    <div class="card" style="padding:1rem;margin-bottom:1rem;border-left:3px solid #dc2626;">
        <h3 style="margin:0 0 .5rem;">Kesalahan (<?= count($errors) ?>)</h3>
        <ul style="margin:0;padding-left:1.25rem;">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars((string) $e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (! empty($rows)): ?>
    <div class="card" style="padding:0;overflow:auto;">
        <table class="table" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:48px;">#</th>
                    <th>ID Karyawan</th>
                    <th>Nama</th>
                    <th>Role</th>
                    <th>Group</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <?php $st = $r['status'] ?? ($r['result'] ?? ''); ?>
                    <tr>
                        <td class="faint"><?= (int) ($r['line'] ?? $i + 2) ?></td>
                        <td><code><?= htmlspecialchars((string) ($r['employee_id'] ?? '')) ?></code></td>
                        <td><?= htmlspecialchars((string) ($r['name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($r['role'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($r['group_code'] ?? '')) ?></td>
                        <td>
                            <?php if ($st === 'created'): ?>
                                <span class="badge" style="background:#10b981;">baru</span>
                            <?php elseif ($st === 'updated'): ?>
                                <span class="badge" style="background:#2563eb;">update</span>
                            <?php elseif ($st === 'failed' || $st === 'skipped'): ?>
                                <span class="badge" style="background:#dc2626;"><?= htmlspecialchars((string) ($r['message'] ?? 'gagal')) ?></span>
                            <?php else: ?>
                                <span class="faint"><?= htmlspecialchars((string) $st) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
