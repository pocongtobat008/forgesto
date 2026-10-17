<?php
/**
 * Notifikasi: ringkasan, filter tipe/baru, dikelompokkan per hari.
 * @var array  $notifications  daftar (sudah difilter)
 * @var bool   $unreadOnly
 * @var string $typeFilter     '' | info | success | warning | danger
 * @var array  $counts         total, unread, info, success, warning, danger (100 terbaru)
 * @var int    $unread         total belum dibaca (semua)
 */
$notifications = $notifications ?? [];
$unreadOnly    = (bool) ($unreadOnly ?? false);
$typeFilter    = (string) ($typeFilter ?? '');
$counts        = $counts ?? ['total' => 0, 'unread' => 0, 'info' => 0, 'success' => 0, 'warning' => 0, 'danger' => 0];
$unread        = (int) ($unread ?? 0);

$typeMeta = [
    'info'    => ['Info', 'info'],
    'success' => ['Sukses', 'check'],
    'warning' => ['Peringatan', 'alert'],
    'danger'  => ['Bahaya', 'alert'],
];
$typeColor = [
    'info'    => 'var(--info-text)',
    'success' => 'var(--success-text)',
    'warning' => 'var(--warning-text)',
    'danger'  => 'var(--danger-text)',
];

$link = static function (string $type, bool $unreadOnly): string {
    $p = [];
    if ($type !== '') {
        $p['type'] = $type;
    }
    if ($unreadOnly) {
        $p['unread'] = '1';
    }

    return url('notifications') . ($p === [] ? '' : '?' . http_build_query($p));
};

$dayLabel = static function (string $dt): string {
    $t = strtotime($dt);
    if ($t === false) {
        return '-';
    }
    $d = date('Y-m-d', $t);
    if ($d === date('Y-m-d')) {
        return 'Hari ini';
    }
    if ($d === date('Y-m-d', strtotime('-1 day'))) {
        return 'Kemarin';
    }

    return format_date($dt, 'd M Y');
};

$groups = [];
foreach ($notifications as $n) {
    $groups[$dayLabel((string) $n['created_at'])][] = $n;
}

$chips = [
    ''        => 'Semua',
    'warning' => 'Peringatan',
    'info'    => 'Info',
    'success' => 'Sukses',
];
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Notifikasi') ?></h1>
        <p>
            <?= (int) $unread ?> belum dibaca
            · <?= (int) $counts['total'] ?> notifikasi terbaru ditampilkan
        </p>
    </div>
    <div class="spacer"></div>
    <?php if ($unread > 0): ?>
        <form method="post" action="<?= url('notifications/read-all') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn" style="min-height:44px;"><?= icon('check') ?> Tandai semua dibaca</button>
        </form>
    <?php endif; ?>
</div>

<div class="grid grid-4" style="margin-bottom:1rem;">
    <div class="stat" style="--i:0">
        <span class="stat-label"><?= __('Belum dibaca') ?></span>
        <span class="stat-value"><?= (int) $unread ?></span>
    </div>
    <div class="stat is-warning" style="--i:1">
        <span class="stat-label"><?= __('Peringatan') ?></span>
        <span class="stat-value"><?= (int) $counts['warning'] ?></span>
    </div>
    <div class="stat" style="--i:2">
        <span class="stat-label"><?= __('Info') ?></span>
        <span class="stat-value"><?= (int) $counts['info'] ?></span>
    </div>
    <div class="stat" style="--i:3">
        <span class="stat-label"><?= __('Sukses') ?></span>
        <span class="stat-value"><?= (int) $counts['success'] ?></span>
    </div>
</div>

<nav class="flex gap wrap items-center" aria-label="<?= __('Filter notifikasi') ?>" style="margin-bottom:1rem;">
    <?php foreach ($chips as $key => $label): ?>
        <?php $active = $typeFilter === $key; ?>
        <a class="btn btn-xs <?= $active ? 'btn-brand' : 'btn-outline' ?>"
           href="<?= e($link($key, $unreadOnly)) ?>"
           aria-current="<?= $active ? 'page' : 'false' ?>"
           style="min-height:44px;padding-inline:.9rem;">
            <?= e($label) ?>
            <span class="faint" style="margin-left:.35rem;">
                <?= $key === '' ? (int) $counts['total'] : (int) ($counts[$key] ?? 0) ?>
            </span>
        </a>
    <?php endforeach; ?>
    <a class="btn btn-xs <?= $unreadOnly ? 'btn-brand' : 'btn-outline' ?>"
       href="<?= e($link($typeFilter, ! $unreadOnly)) ?>"
       aria-pressed="<?= $unreadOnly ? 'true' : 'false' ?>"
       style="min-height:44px;padding-inline:.9rem;">
        <?= icon('filter') ?> Hanya belum dibaca
    </a>
</nav>

<div class="card">
    <div class="card-body" style="padding-top:.4rem;">
        <?php if ($groups === []): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('bell') ?></div>
                <h3><?= __('Tidak ada notifikasi') ?></h3>
                <p>
                    <?php if ($typeFilter !== '' || $unreadOnly): ?>
                        Tidak ada yang cocok dengan filter ini. <a href="<?= e(url('notifications')) ?>">Tampilkan semua</a>.
                    <?php else: ?>
                        Notifikasi akan muncul saat ada data diposting, diaudit, atau ditemukan selisih.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($groups as $day => $items): ?>
                <p class="li-meta faint" style="margin:.9rem 0 .2rem;font-weight:600;letter-spacing:.02em;text-transform:uppercase;font-size:.72rem;">
                    <?= e($day) ?> · <?= count($items) ?>
                </p>
                <ul class="list-reset">
                    <?php foreach ($items as $n): ?>
                        <?php
                        $t = (string) $n['type'];
                        $isUnread = (int) $n['is_read'] === 0;
                        $tm = $typeMeta[$t] ?? ['Info', 'info'];
                        $color = $typeColor[$t] ?? 'var(--info-text)';
                        ?>
                        <li class="list-item" style="<?= $isUnread ? '' : 'opacity:.72;' ?>">
                            <span class="li-icon" style="color: <?= e($color) ?>;" title="<?= e($tm[0]) ?>">
                                <?= icon($tm[1]) ?>
                            </span>
                            <span class="li-body">
                                <span class="li-title">
                                    <?= e($n['title']) ?>
                                    <?php if ($isUnread): ?>
                                        <span class="badge badge-brand"><?= __('baru') ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if (! empty($n['message'])): ?>
                                    <span class="li-meta"><?= e($n['message']) ?></span>
                                <?php endif; ?>
                                <span class="li-meta faint">
                                    <span style="color:<?= e($color) ?>;font-weight:600;"><?= e($tm[0]) ?></span>
                                    &middot; <?= e(format_date($n['created_at'], 'H:i')) ?>
                                    <?php if (! empty($n['group_code'])): ?> &middot; Group <?= e($n['group_code']) ?><?php endif; ?>
                                </span>
                            </span>
                            <span class="flex gap-sm items-center" style="flex-shrink:0;">
                                <?php if (! empty($n['link'])): ?>
                                    <a class="btn btn-outline btn-xs" href="<?= e(url(ltrim((string) $n['link'], '/'))) ?>" style="min-height:44px;">Buka</a>
                                <?php endif; ?>
                                <?php if ($isUnread): ?>
                                    <form method="post" action="<?= url('notifications/' . (int) $n['id'] . '/read') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-ghost btn-xs" title="<?= __('Tandai dibaca') ?>" aria-label="<?= __('Tandai dibaca') ?>" style="min-height:44px;min-width:44px;"><?= icon('check') ?></button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        <?php endif; ?>
    <?= \App\Core\View::partial('partials/pagination', ['paginator' => $paginator, 'query' => array_diff_key($_GET, ['page' => 1])]) ?>
    </div>
</div>
