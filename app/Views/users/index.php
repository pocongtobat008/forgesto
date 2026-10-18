<?php
/**
 * @var array $users
 * @var array $filters
 * @var array $roles
 */
use App\Core\View;

$roleBadge = static fn (string $role): string => match ($role) {
    'poweradmin' => 'badge-danger',
    'adminsto'   => 'badge-brand',
    'audit'      => 'badge-info',
    default      => 'badge-success',
};
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('User &amp; Role') ?></h1>
        <p>Power Admin mengelola semua user, role akses, dan delegasi group.</p>
    </div>
    <div class="spacer"></div>
    <div class="btn-group">
        <a class="btn btn-outline" href="<?= url('user-rules') ?>"><?= icon('shield') ?> Rules &amp; Hak Akses</a>
        <a class="btn" href="<?= url('users/create') ?>"><?= icon('plus') ?> Tambah User</a>
    </div>
</div>

<div class="filter-section">
    <form method="get" action="<?= url('users') ?>" class="filters" id="users-filter">
        <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden"><?php endforeach; ?><?php endforeach; ?>
        <div class="form-group is-search">
            <label for="search"><?= __('Cari') ?></label>
            <input type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="<?= __('Nama, username, email') ?>" data-filter-live autocomplete="off" enterkeyhint="search">
            <span class="hint"><?= __('Ketik untuk mencari otomatis.') ?></span>
        </div>
    </form>
    <?php
    /* LF-BAR: satu pencarian lanjutan (pola Data STO) */
    $__adv = $filters['adv'] ?? [];
    $asForm    = 'users-filter';
    $asCols    = $advCols ?? [];
    $asCurrent = $__adv;
    $asNumeric = $advNumeric ?? [];
    $asMax     = $advMax ?? 12;
    require __DIR__ . '/../partials/advanced-search.php';
    ?>

    <?php
    $__userHasFilter = ! empty($filters['search']) || ! empty($filters['col']) || ! empty($filters['adv']) || ! empty($filters['role']);
    $cfCol = $filters['col'] ?? [];
    $xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>';
    ?>
    <div class="table-toolbar" style="display:flex;gap:.55rem;margin-bottom:.75rem;margin-left:auto;justify-content:flex-end;align-items:center;">
        <button type="button" class="btn btn-info btn-icon-only" id="users-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
        <?php if ($__userHasFilter): ?>
            <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url('users') ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
        <?php endif; ?>
        <div class="col-mgr" id="users-colmgr">
            <button type="button" class="btn btn-ghost btn-icon-only" id="users-colmgr-btn" aria-expanded="false" aria-label="<?= __('Sesuaikan kolom') ?>" title="<?= __('Sesuaikan kolom') ?>"><?= icon('layers') ?></button>
            <div class="col-mgr-panel" id="users-colmgr-panel" hidden></div>
        </div>
    </div>
        <div class="card-body" style="padding:1.25rem 1.35rem !important;">
    <div class="table-wrap">
        <table class="data" id="users-table" data-colmgr-key="users-v1" data-colmgr-prefix="users"><caption class="sr-only">Daftar pengguna</caption>
            <thead>
                <tr>
                    <th data-col="name" class="xf-th"><span class="xf-label"><?= __('Nama') ?></span><?= $xfBtn('name', 'Nama') ?></th>
                    <th data-col="username" class="xf-th"><span class="xf-label"><?= __('Username') ?></span><?= $xfBtn('username', 'Username') ?></th>
                    <th data-col="role" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Role') ?></span><?= $xfBtn('role', 'Role') ?></th>
                    <th data-col="group" class="xf-th"><span class="xf-label"><?= __('Group didelegasikan') ?></span><?= $xfBtn('group', 'Group didelegasikan') ?></th>
                    <th data-col="status" class="t-center xf-th"><span class="xf-label"><?= __('Status') ?></span><?= $xfBtn('status', 'Status') ?></th>
                    <th data-col="aksi" class="t-right"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users['items'] === []): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('users') ?></div>
                                <h3><?= __('Tidak ada user') ?></h3>
                                <p>Tambahkan user dan tentukan role serta group yang didelegasikan.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users['items'] as $user): ?>
                        <tr>
                            <td data-label="<?= __('Nama') ?>">
                                <div class="cell-title"><?= e($user['name']) ?></div>
                                <div class="cell-sub"><?= e($user['email'] ?: '-') ?></div>
                            </td>
                            <td data-label="<?= __('Username') ?>"><span class="kbd"><?= e($user['username']) ?></span></td>
                            <td data-label="<?= __('Role') ?>" class="col-hide-tablet"><span class="badge <?= $roleBadge((string) $user['role']) ?>"><?= e(role_label($user['role'])) ?></span></td>
                            <td data-label="<?= __('Group') ?>">
                                <?php if (in_array($user['role'], ['observer', 'audit'], true)): ?>
                                    <span class="text-sm"><?= e($user['group_codes'] ?: '- belum ada -') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-brand"><?= __('Semua group') ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= __('Status') ?>" class="t-center">
                                <span class="badge <?= (int) $user['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                    <?= (int) $user['is_active'] === 1 ? __('Aktif') : __('Nonaktif') ?>
                                </span>
                            </td>
                            <td data-label="" class="t-right">
                                <div class="btn-group" style="justify-content:flex-end;">
                                    <a class="btn btn-outline btn-xs" href="<?= url('user-rules?user_id=' . (int) $user['id']) ?>" title="<?= __('Atur Rules Akses User') ?>"><?= icon('shield') ?></a>
                                    <a class="btn btn-outline btn-xs" href="<?= url('users/edit/' . (int) $user['id']) ?>" title="<?= __('Edit User') ?>"><?= icon('edit') ?></a>
                                    <form method="post" action="<?= url('users/' . (int) $user['id'] . '/delete') ?>"
                                          data-confirm="<?= e(__('Hapus user :name?', ['name' => $user['name']])) ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-danger btn-xs" aria-label="<?= __('Hapus user') ?>"><?= icon('trash') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table></div>
    </div>  <!-- /card-body -->

    <?= View::partial('partials/pagination', [
        'paginator' => $users,
        'query'     => array_filter(['role' => $filters['role'] ?? '', 'search' => $filters['search'] ?? '', 'col' => $filters['col'] ?? []], static fn ($v) => $v !== '' && $v !== null && $v !== []),
    ]) ?>
</div>

<?php
$roleLabels = $roles;
$cfLabels = ['name' => __('Nama'), 'username' => __('Username'), 'role' => __('Role'), 'group' => __('Group didelegasikan'), 'status' => __('Status')];
$cfDisplay = $roles + ['1' => 'Aktif', '0' => 'Nonaktif'];
$cfForm = 'users-filter';
$cfDistinct = url('users/distinct');
require __DIR__ . '/../partials/colfilter.php';

?>
<script src="<?= asset_v('js/table-colmgr.js') ?>" defer></script>
