<?php
/**
 * Pager bersama + switch "Tampilkan semua".
 * Query filter diambil dari $_GET (tanpa page/all) supaya kolom, lanjutan, sort ikut terbawa.
 * @var array $paginator {items,total,page,per_page,last_page,from,to}
 */
$paginator = $paginator ?? ['page' => 1, 'last_page' => 1, 'total' => 0, 'from' => 0, 'to' => 0];
$pgShowAll = list_show_all();
$pgBase    = list_query();
$pgPage    = (int) ($paginator['page'] ?? 1);
$pgLast    = max(1, (int) ($paginator['last_page'] ?? 1));
$pgTotal   = (int) ($paginator['total'] ?? 0);
$pgPath    = url(\App\Core\Request::path());

$pgLink = static function (int $target) use ($pgBase, $pgPath): string {
    return $pgPath . '?' . http_build_query(array_merge($pgBase, ['page' => $target]));
};
$pgAllHref = $pgPath . '?' . http_build_query($pgShowAll ? $pgBase : array_merge($pgBase, ['all' => '1']));
$pgStart = max(1, $pgPage - 2);
$pgEnd   = min($pgLast, $pgPage + 2);
?>
<div class="pg-bar">
    <div class="pg-mode">
        <span class="pg-mode-info">
            <?php if ($pgShowAll): ?>
                Menampilkan semua <?= $pgTotal ?> data hasil filter
            <?php else: ?>
                Menampilkan <?= (int) ($paginator['from'] ?? 0) ?>&ndash;<?= (int) ($paginator['to'] ?? 0) ?> dari <?= $pgTotal ?> data
            <?php endif; ?>
        </span>
        <a class="pg-switch<?= $pgShowAll ? ' is-on' : '' ?>" href="<?= e($pgAllHref) ?>" role="switch" aria-checked="<?= $pgShowAll ? 'true' : 'false' ?>">
            <span class="pg-switch-track" aria-hidden="true"><span class="pg-switch-thumb"></span></span>
            <span><?= __('Tampilkan semua') ?></span>
        </a>
    </div>

    <?php if (! $pgShowAll && $pgLast > 1): ?>
        <nav class="pagination" aria-label="<?= __('Navigasi halaman') ?>">
            <?php if ($pgPage > 1): ?>
                <a href="<?= e($pgLink($pgPage - 1)) ?>"><?= icon('chev-left') ?></a>
            <?php else: ?>
                <span class="is-disabled"><?= icon('chev-left') ?></span>
            <?php endif; ?>

            <?php if ($pgStart > 1): ?>
                <a href="<?= e($pgLink(1)) ?>">1</a>
                <?php if ($pgStart > 2): ?><span class="is-disabled">&hellip;</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $pgStart; $i <= $pgEnd; $i++): ?>
                <?php if ($i === $pgPage): ?>
                    <span class="is-active"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e($pgLink($i)) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pgEnd < $pgLast): ?>
                <?php if ($pgEnd < $pgLast - 1): ?><span class="is-disabled">&hellip;</span><?php endif; ?>
                <a href="<?= e($pgLink($pgLast)) ?>"><?= $pgLast ?></a>
            <?php endif; ?>

            <?php if ($pgPage < $pgLast): ?>
                <a href="<?= e($pgLink($pgPage + 1)) ?>"><?= icon('chev-right') ?></a>
            <?php else: ?>
                <span class="is-disabled"><?= icon('chev-right') ?></span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
