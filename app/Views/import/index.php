<?php
/**
 * @var array $groups
 * @var array $logs
 * @var array|null $result
 * @var array $columns
 */
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Import Data') ?></h1>
        <p>Unggah data dari Excel (.xlsx) atau CSV. Data baru ditambahkan, data lama diperbarui.</p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-brand" href="<?= url('templates/stockforge-master-v3.xlsx') ?>?v=<?= time() ?>"><?= icon('download') ?> Unduh Template Excel (.xlsx)</a>
</div>

<?php if ($result !== null): ?>
    <div class="alert alert-<?= $result['failed'] === 0 ? 'success' : ($result['success'] > 0 ? 'warning' : 'error') ?>">
        <?= icon($result['failed'] === 0 ? 'check' : 'alert') ?>
        <div>
            <strong><?= __('Hasil import:') ?></strong>
            <?= (int) $result['success'] ?> berhasil (<?= (int) $result['created'] ?> baru, <?= (int) $result['updated'] ?> diperbarui),
            <?= (int) $result['failed'] ?> gagal dari <?= (int) $result['total'] ?> baris.
            <?php if (! empty($result['errors'])): ?>
                <details class="disclosure">
                    <summary>Lihat <?= count($result['errors']) ?> pesan kesalahan</summary>
                    <ul style="margin:.3rem 0 0;padding-left:1.1rem;font-size:.82rem;">
                        <?php foreach ($result['errors'] as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-2">
    <div class="card-header"><h2><?= icon('upload') ?> Import Master 1 File (semua data)</h2></div>
    <div class="card-body">
        <p class="text-sm muted" style="margin:0 0 .6rem;">Satu file .xlsx untuk gudang, group, petugas (observer &amp; auditor), lokasi, tablet, dan item. Sheet diproses berurutan. Jalankan <strong><?= __('Cek (dry-run)') ?></strong> dulu.</p>
        
        <!-- Progress Bar Container -->
        <div id="master-import-progress-container" style="display:none; margin-bottom:1rem; padding:1rem; background:#f3f4f6; border-radius:8px;">
            <div style="margin-bottom:0.5rem;">
                <span id="master-import-progress-text" style="font-size:0.875rem; font-weight:600; color:#374151;">Memulai import...</span>
            </div>
            <div style="width:100%; height:8px; background:#e5e7eb; border-radius:4px; overflow:hidden;">
                <div id="master-import-progress" style="width:0%; height:100%; background:#3b82f6; transition:width 0.3s ease;"></div>
            </div>
        </div>
        
        <form id="master-import-form" method="post" action="<?= url('import/master') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="master_file">File master (.xlsx) <span class="faint">*</span></label>
                <input type="file" id="master_file" name="file" accept=".xlsx" required>
                <span class="hint">Template: <a href="<?= url('templates/stockforge-master-v3.xlsx') ?>?v=<?= time() ?>">stockforge-master-v3.xlsx</a></span><span class="hint"> Berisi 3 sheet: <b>karyawan</b> (master data + rule group), <b>master</b> (1 baris = 1 kombinasi data lengkap), <b>petunjuk</b>. Wajib isi <code>period_month</code> (YYYY-MM), <code>period_start</code>, <code>period_end</code>. Kolom petugas cukup <code>auditor_employee_id</code> &amp; <code>observer_employee_id</code> — <b>nama di-lookup otomatis</b> dari sheet karyawan.</span>
            </div>
            <div class="form-actions" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem;align-items:center;">
                <button type="submit" name="mode" value="dry" class="btn" style="min-height:44px;"><?= icon('check') ?> Cek (dry-run)</button>
                <button type="submit" name="mode" value="commit" class="btn btn-brand" style="min-height:44px;"><?= icon('upload') ?> Simpan Master</button>
                <div style="flex:1;"></div>
                <button type="button" id="bulk-delete-master-btn" class="btn" style="min-height:44px; background:#dc2626; color:white; border:none;">
                    <?= icon('trash') ?> Hapus Semua Data
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header"><h2><?= icon('users') ?> Import Master Karyawan</h2></div>
    <div class="card-body">
        <p class="text-sm muted" style="margin:0 0 .6rem;">
            File .xlsx / .csv berisi daftar karyawan (ID Karyawan, Nama, Role, Group). Import ini menjadi
            <strong>acuan lookup ID Karyawan &rarr; Nama</strong> dan sekaligus membuat akun login.
        </p>
        <form method="post" action="<?= url('import/employees') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="employee_file">File karyawan (.xlsx / .csv) <span class="faint">*</span></label>
                <input type="file" id="employee_file" name="file" accept=".xlsx,.csv" required>
                <span class="hint">
                    Kolom wajib: <code>employee_id</code> (atau <code>id_karyawan</code>) dan <code>name</code> (atau <code>nama</code>).
                    Kolom opsional: <code>role</code>, <code>group_code</code>, <code>password</code>, <code>is_active</code>.
                </span>
                <span class="hint">
                    Template:
                    <a href="<?= url('import/template/employees.xlsx') ?>?v=<?= time() ?>">stockforge-employees-template.xlsx</a>
                    &middot; password default: <code><?= e(\App\Services\EmployeeImportService::DEFAULT_PASSWORD) ?></code>
                </span>
            </div>
            <div class="form-actions" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem;align-items:center;">
                <button type="submit" class="btn btn-brand" style="min-height:44px;"><?= icon('upload') ?> Import Karyawan</button>
            </div>
        </form>
    </div>
</div>

<div class="grid grid-main">
    <div class="card">
        <div class="card-header"><h2><?= icon('upload') ?> Unggah File</h2></div>
        <div class="card-body">
            <form method="post" action="<?= url('import') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="group_id"><?= __('Group default (opsional)') ?></label>
                    <select id="group_id" name="group_id">
                        <option value="">— Ikuti kolom group_code di file —</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= (int) $group['id'] ?>"><?= e($group['name']) ?> (<?= e($group['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Bila file tidak memuat kolom group_code, seluruh baris akan masuk ke group ini.</span>
                </div>

                <div class="form-group mt-1">
                    <label for="file">File (.xlsx / .csv) <span class="faint">*</span></label>
                    <input type="file" id="file" name="file" accept=".xlsx,.csv" required>
                    <span class="hint">Maksimal <?= round((int) config('app.upload.max_size', 8388608) / 1048576, 1) ?> MB.</span>
                </div>

                <button type="submit" class="btn btn-block mt-2"><?= icon('upload') ?> Mulai Import</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= __('Panduan Kolom') ?></h2></div>
        <div class="card-body">
            <?php if ($columns === []): ?>
                <p class="text-sm muted mb-0">Nama kolom bersifat fleksibel: <span class="kbd"><?= __('kode') ?></span> = <span class="kbd"><?= __('code') ?></span>, <span class="kbd"><?= __('nama') ?></span> = <span class="kbd"><?= __('name') ?></span>, dst.</p>
            <?php else: ?>
                <ul class="list-reset text-sm">
                    <?php foreach ($columns as $key => $description): ?>
                        <li class="list-item" style="padding:.45rem 0;">
                            <span class="li-body">
                                <span class="li-title mono"><?= e($key) ?></span>
                                <span class="li-meta"><?= e($description) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= __('Riwayat Import') ?></h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th><?= __('File') ?></th>
                    <th><?= __('Group') ?></th>
                    <th class="t-center"><?= __('Total') ?></th>
                    <th class="t-center"><?= __('Berhasil') ?></th>
                    <th class="t-center"><?= __('Gagal') ?></th>
                    <th class="t-center"><?= __('Status') ?></th>
                    <th><?= __('Progres') ?></th>
                    <th><?= __('Oleh') ?></th>
                    <th><?= __('Waktu') ?></th>
                    <th class="t-center"><?= __('File') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs === []): ?>
                    <tr><td colspan="8" class="text-center faint" style="padding:1.5rem;"><?= __('Belum ada riwayat import.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php $badge = match ($log['status']) {
                            'success' => 'badge-success', 'partial' => 'badge-warning', 'failed' => 'badge-danger', default => 'badge-info',
                        }; ?>
                        <tr>
                            <td class="cell-title"><?= e($log['filename'] ?: '-') ?></td>
                            <td><?= e($log['group_code'] ?: '—') ?></td>
                            <td class="t-center"><?= (int) $log['total_rows'] ?></td>
                            <td class="t-center"><?= (int) $log['success_rows'] ?></td>
                            <td class="t-center"><?= (int) $log['failed_rows'] ?></td>
                            <td class="t-center"><span class="badge <?= $badge ?>"><?= e(ucfirst((string) $log['status'])) ?></span></td>
                            <td class="t-center" style="min-width:110px;">
                                <div class="progress-mini" role="progressbar" aria-valuenow="<?= (int) $log['progress'] ?>" aria-valuemin="0" aria-valuemax="100" title="<?= (int) $log['progress'] ?>%">
                                    <span style="width:<?= max(0, min(100, (int) $log['progress'])) ?>%"></span>
                                </div>
                                <span class="text-sm faint"><?= (int) $log['progress'] ?>%</span>
                            </td>
                            <td class="text-sm"><?= e($log['user_name'] ?: '-') ?></td>
                            <td class="text-sm faint nowrap"><?= e(format_date($log['created_at'])) ?></td>
                            <td class="t-center">
                                <?php if (! empty($log['stored_path'])): ?>
                                    <a class="btn btn-sm" href="<?= url('import/logs/' . (int) $log['id'] . '/download') ?>"><?= icon('download') ?> Unduh</a>
                                <?php else: ?>
                                    <span class="faint text-sm">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="<?= url('assets/js/master-import-ui.js') ?>"></script>
