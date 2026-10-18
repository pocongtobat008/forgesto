<?php
/**
 * Generator ID karyawan untuk role Observer & Auditor.
 *
 * @var array<string,string> $roles
 * @var string $role
 * @var string $prefix
 * @var int    $qty
 * @var int    $start
 * @var string $base
 * @var array  $groups
 * @var list<string> $codes
 * @var list<array<string,mixed>>|null $created
 * @var list<string> $errors
 * @var string $defaultPassword
 */
use App\Core\Request;

$isResult = is_array($created);

// Angka & lebar digit dari pratinjau pertama, dipakai skrip agar pratinjau
// ikut berubah saat jumlah/prefix diubah (tanpa reload).
$firstCode = (string) ($codes[0] ?? '');
$firstNum  = preg_replace('/\D/', '', $firstCode);
$nextNum   = $firstNum !== '' ? (int) $firstNum : 1;
$codeWidth = $firstNum !== '' ? strlen($firstNum) : 6;
$autoName = static fn (int $i): string => $base . ' ' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
?>
<style>
    .eid-pick { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .eid-opt { position: relative; display: flex; gap: .65rem; align-items: flex-start; padding: .8rem .9rem;
        border: 1.5px solid var(--border, #e3e0ee); border-radius: 12px; cursor: pointer; background: var(--surface, #fff);
        transition: border-color .15s ease, box-shadow .15s ease; }
    .eid-opt:hover { border-color: var(--brand, #6d4aff); }
    .eid-opt input { margin-top: .15rem; accent-color: var(--brand, #6d4aff); }
    .eid-opt strong { display: block; font-size: .92rem; }
    .eid-opt small { display: block; color: var(--muted, #6f6787); font-size: .76rem; line-height: 1.35; margin-top: .15rem; }
    .eid-opt:has(input:checked) { border-color: var(--brand, #6d4aff); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
    .eid-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; letter-spacing: .02em; }
    .eid-note { display: flex; gap: .5rem; align-items: flex-start; padding: .7rem .85rem; border-radius: 10px;
        background: rgba(109, 74, 255, .07); color: var(--text, #2c2540); font-size: .82rem; line-height: 1.45; }
    .eid-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
</style>

<?= page_hero(['title' => 'ID Karyawan']) ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('ID Karyawan') ?></h1>
        <p><?= __('Buat nomor induk login untuk petugas Observer dan Auditor sekaligus.') ?></p>
    </div>
    <div class="spacer"></div>
    <div class="btn-group">
        <a class="btn btn-outline" href="<?= url('users') ?>"><?= icon('users') ?> <?= __('Daftar Pengguna') ?></a>
    </div>
</div>

<?php if ($isResult): ?>
    <div class="card">
        <div class="card-header">
            <h2><?= __('ID berhasil dibuat') ?></h2>
            <span class="badge badge-success"><?= count($created) ?> <?= __('ID') ?></span>
        </div>
        <div class="card-body">
            <?php if ($created !== []): ?>
                <div class="eid-note" style="margin-bottom:1rem;">
                    <?= icon('lock') ?>
                    <div>
                        <?= __('Password semua ID di bawah sama:') ?>
                        <strong class="eid-code"><?= e($defaultPassword) ?></strong>
                        — <?= __('minta petugas menggantinya setelah login pertama.') ?>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="data" id="eid-result">
                        <caption class="sr-only"><?= __('ID karyawan yang baru dibuat') ?></caption>
                        <thead>
                            <tr>
                                <th><?= __('ID Karyawan') ?></th>
                                <th><?= __('Nama') ?></th>
                                <th><?= __('Role') ?></th>
                                <th><?= __('Group') ?></th>
                                <th class="t-center"><?= __('Status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($created as $row): ?>
                                <tr>
                                    <td class="eid-code"><?= e((string) $row['employee_id']) ?></td>
                                    <td><?= e((string) $row['name']) ?></td>
                                    <td><?= e($roles[(string) $row['role']] ?? (string) $row['role']) ?></td>
                                    <td><?= e((string) ($row['group_code'] ?: '—')) ?></td>
                                    <td class="t-center">
                                        <span class="badge badge-success"><?= $row['action'] === 'created' ? __('Baru') : __('Diperbarui') ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="eid-actions" style="margin-top:1rem;">
                    <button type="button" class="btn" id="eid-copy"><?= icon('clipboard') ?> <?= __('Salin daftar ID') ?></button>
                    <a class="btn btn-outline" href="<?= url('users') ?>"><?= icon('users') ?> <?= __('Lihat di Pengguna') ?></a>
                    <a class="btn btn-ghost" href="<?= url('employee-ids') ?>"><?= icon('plus') ?> <?= __('Buat lagi') ?></a>
                </div>
            <?php else: ?>
                <p class="faint"><?= __('Tidak ada ID yang dibuat.') ?></p>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger" style="margin-top:1rem;">
                    <strong><?= __('Sebagian gagal:') ?></strong>
                    <ul style="margin:.4rem 0 0 1rem;">
                        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('eid-copy')?.addEventListener('click', function () {
            const rows = [...document.querySelectorAll('#eid-result tbody tr')].map(tr => {
                const c = tr.querySelectorAll('td');
                return [c[0].textContent.trim(), c[1].textContent.trim(), '<?= e($defaultPassword) ?>'].join('\t');
            });
            navigator.clipboard.writeText(rows.join('\n')).then(() => {
                const b = this; const t = b.innerHTML;
                b.innerHTML = '<?= __('Tersalin') ?>';
                setTimeout(() => { b.innerHTML = t; }, 1600);
            });
        });
    </script>
<?php else: ?>
    <form method="post" action="<?= url('employee-ids') ?>" id="eid-form"
          data-next="<?= (int) $nextNum ?>" data-width="<?= (int) $codeWidth ?>"
          data-roles="<?= e(json_encode($roles, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
        <?= csrf_field() ?>

        <div class="card">
            <div class="card-header"><h2><?= __('Pengaturan ID') ?></h2></div>
            <div class="card-body">
                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger" style="margin-bottom:1rem;">
                        <ul style="margin:0 0 0 1rem;">
                            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label><?= __('Role') ?></label>
                    <div class="eid-pick">
                        <?php foreach ($roles as $key => $label): ?>
                            <label class="eid-opt">
                                <input type="radio" name="role" value="<?= e($key) ?>" <?= $role === $key ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($label) ?></strong>
                                    <small><?= $key === 'observer'
                                        ? __('Input & menghitung data di lokasi.')
                                        : __('Mengecek minimal 10% data per group.') ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="qty"><?= __('Jumlah ID') ?></label>
                        <input type="number" id="qty" name="qty" min="1" max="50" value="<?= (int) $qty ?>" required>
                        <span class="hint"><?= __('Maksimal 50 sekali buat.') ?></span>
                    </div>

                    <div class="form-group">
                        <label for="prefix"><?= __('Prefix') ?></label>
                        <input type="text" id="prefix" name="prefix" maxlength="4" value="<?= e($prefix) ?>"
                               style="text-transform:uppercase;" required>
                        <span class="hint"><?= __('1–4 huruf, contoh WE.') ?></span>
                    </div>

                    <div class="form-group">
                        <label for="start"><?= __('Nomor awal') ?> <span class="faint">(<?= __('opsional') ?>)</span></label>
                        <input type="number" id="start" name="start" min="1" value="<?= $start > 0 ? (int) $start : '' ?>"
                               placeholder="<?= e(ltrim(substr((string) ($codes[0] ?? ''), strlen($prefix)), '0') ?: '1') ?>">
                        <span class="hint"><?= __('Kosongkan untuk lanjut otomatis dari nomor terakhir.') ?></span>
                    </div>

                    <div class="form-group">
                        <label for="base"><?= __('Nama dasar') ?></label>
                        <input type="text" id="base" name="base" maxlength="100" value="<?= e($base) ?>" required>
                        <span class="hint"><?= __('Nama diberi nomor urut, mis. "Observer 01".') ?></span>
                    </div>

                    <div class="form-group">
                        <label for="group_code"><?= __('Group') ?> <span class="faint">(<?= __('opsional') ?>)</span></label>
                        <select id="group_code" name="group_code">
                            <option value=""><?= __('— tanpa group —') ?></option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= e((string) $g['code']) ?>"
                                    <?= (string) Request::string('group_code') === (string) $g['code'] ? 'selected' : '' ?>>
                                    <?= e((string) $g['code'] . ' · ' . (string) $g['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint"><?= __('Langsung daftarkan ke group ini.') ?></span>
                    </div>

                    <div class="form-group">
                        <label for="password"><?= __('Password awal') ?></label>
                        <input type="text" id="password" name="password" value="<?= e($defaultPassword) ?>" maxlength="60">
                        <span class="hint"><?= __('Berlaku untuk semua ID pada batch ini.') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><?= __('Pratinjau') ?></h2>
                <span class="badge badge-brand" id="eid-badge"><?= count($codes) ?> <?= __('ID') ?></span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="data">
                        <caption class="sr-only"><?= __('Pratinjau ID yang akan dibuat') ?></caption>
                        <thead>
                            <tr>
                                <th><?= __('ID Karyawan') ?></th>
                                <th><?= __('Nama') ?></th>
                                <th><?= __('Role') ?></th>
                            </tr>
                        </thead>
                        <tbody id="eid-preview">
                            <?php foreach ($codes as $i => $code): ?>
                                <tr>
                                    <td class="eid-code"><?= e($code) ?></td>
                                    <td><?= e($autoName((int) $i)) ?></td>
                                    <td><?= e($roles[$role]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="eid-actions" style="margin-top:1rem;">
                    <button type="submit" class="btn"><?= icon('plus') ?> <?= __('Buat') ?> <span id="eid-count"><?= count($codes) ?></span> <?= __('ID') ?></button>
                    <button type="submit" class="btn btn-outline" formmethod="get" formnovalidate><?= icon('eye') ?> <?= __('Perbarui pratinjau') ?></button>
                </div>
            </div>
        </div>

                <script>
                // Pratinjau langsung: ID dihitung ulang tiap kali jumlah, prefix,
                // nomor awal, nama dasar, atau role diubah.
                (function () {
                    var form = document.getElementById('eid-form');
                    if (!form) return;

                    var tbody  = document.getElementById('eid-preview');
                    var badge  = document.getElementById('eid-badge');
                    var count  = document.getElementById('eid-count');
                    var qtyEl  = form.querySelector('#qty');
                    var preEl  = form.querySelector('#prefix');
                    var stEl   = form.querySelector('#start');
                    var baseEl = form.querySelector('#base');

                    var nextNum = parseInt(form.dataset.next || '1', 10) || 1;
                    var width   = parseInt(form.dataset.width || '6', 10) || 6;
                    var roles   = {};
                    try { roles = JSON.parse(form.dataset.roles || '{}'); } catch (e) {}

                    function esc(s) {
                        return String(s).replace(/[&<>"]/g, function (c) {
                            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
                        });
                    }

                    function roleNow() {
                        var r = form.querySelector('input[name="role"]:checked');
                        return r ? r.value : 'observer';
                    }

                    function render() {
                        var qty = parseInt(qtyEl.value || '1', 10);
                        if (!qty || qty < 1) qty = 1;
                        if (qty > 50) qty = 50;

                        var prefix = (preEl.value || 'WE').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 4) || 'WE';
                        var st = parseInt(stEl.value, 10);
                        var start = (st && st > 0) ? st : nextNum;
                        var base = (baseEl.value || '').trim();
                        var label = roles[roleNow()] || roleNow();

                        var html = '';
                        for (var i = 0; i < qty; i++) {
                            var num = String(start + i);
                            while (num.length < width) num = '0' + num;
                            var name = base ? base + ' ' + (i + 1 < 10 ? '0' : '') + (i + 1) : '—';
                            html += '<tr><td class="eid-code">' + esc(prefix + num) + '</td><td>' +
                                    esc(name) + '</td><td>' + esc(label) + '</td></tr>';
                        }

                        tbody.innerHTML = html;
                        if (badge) badge.textContent = qty + ' ID';
                        if (count) count.textContent = qty;
                    }

                    form.addEventListener('input', render);
                    form.addEventListener('change', render);
                    render();
                })();
                </script>
    </form>
<?php endif; ?>
