<?php
/**
 * Jejak Proses (per Group & Petugas) — panel bersama untuk:
 *   - Data STO (/items)
 *   - Audit (/audit)
 *   - Rekonsiliasi (/reconciliation)
 *
 * @var list<array<string,mixed>> $trail
 * @var ?int    $trailGroupId  kalau diisi, hanya group itu (label konteks)
 * @var ?string $trailContext  'items'|'audit'|'recon' (hanya untuk label)
 * @var int     $trailLimit
 */
use App\Models\ActivityLog;

$trail       = $trail ?? [];
$trailGroupId = $trailGroupId ?? null;
$trailLimit  = $trailLimit ?? 40;

$stageIcon = static function (string $action): string {
    $stage = ActivityLog::trailStage($action);
    return icon($stage[2]);
};
$stageBadge = static fn (string $action): string => 'badge ' . ActivityLog::trailStage($action)[1];
$stageLabel = static fn (string $action): string => __(ActivityLog::trailStage($action)[0]);

$__trailUser = static function (array $r): string {
    $name = trim((string) ($r['user_name'] ?? ''));
    $role = role_label($r['user_role'] ?? null);
    if ($name === '') {
        return $role !== '' ? $role : (string) ($r['user_role'] ?? 'Sistem');
    }
    // Hindari duplikasi "Power Admin · Power Admin" saat nama == label role.
    if ($role === '' || strcasecmp($name, (string) $role) === 0) {
        return $name;
    }
    return $name . ' · ' . $role;
};
?>
<section class="card process-trail mt-2" id="process-trail">
    <div class="card-toolbar" style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <h3 style="font-size:1rem;font-weight:700;margin:0;display:flex;align-items:center;gap:.5rem;">
            <?= icon('activity') ?> <?= __('Jejak Proses') ?>
        </h3>
        <span class="badge badge-subtle trail-scope"><?= icon('users') ?> <?= __('per Group & Petugas') ?></span>
        <div class="spacer" style="margin-left:auto;"></div>
        <span class="text-sm faint"><?= (int) count($trail) ?> <?= __('aktivitas terakhir') ?></span>
        <button type="button" class="btn btn-ghost btn-icon-only" id="trail-toggle"
                aria-expanded="false" aria-controls="trail-body" title="<?= __('Ringkas / bentangkan') ?>"
                aria-label="<?= __('Ringkas atau bentangkan jejak proses') ?>"><?= icon('chev-down') ?></button>
        <a class="btn btn-ghost btn-xs" href="<?= e(url('activity' . ($trailGroupId ? '?group_id=' . (int) $trailGroupId : ''))) ?>">
            <?= icon('list') ?> <?= __('Semua aktivitas') ?>
        </a>
    </div>

    <div class="trail-body" id="trail-body" style="padding:.5rem .9rem 1rem;display:none;">
        <?php if ($trail === []): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('activity') ?></div>
                <h3><?= __('Belum ada jejak proses') ?></h3>
                <p><?= __('Aktivitas hitung, posting, audit, dan verifikasi akan tampil di sini.') ?></p>
            </div>
        <?php else: ?>
            <ol class="trail-list">
                <?php foreach ($trail as $t): ?>
                    <?php $__act = (string) $t['action']; ?>
                    <li class="trail-item">
                        <span class="trail-dot is-<?= e($__act) ?>" aria-hidden="true"><?= $stageIcon($__act) ?></span>
                        <div class="trail-main">
                            <div class="trail-top">
                                <span class="<?= $stageBadge($__act) ?>"><?= e($stageLabel($__act)) ?></span>
                                <span class="trail-desc"><?= e($t['description'] ?? '') ?></span>
                            </div>
                            <div class="trail-meta">
                                <span class="trail-user"><?= e($__trailUser($t)) ?></span>
                                <?php if (! empty($t['group_name'])): ?>
                                    <span class="trail-group"><?= e($t['group_name']) ?></span>
                                <?php endif; ?>
                                <span class="trail-time nowrap"><?= e(format_date($t['created_at'])) ?></span>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
<script>
(function () {
  var btn = document.getElementById('trail-toggle');
  var body = document.getElementById('trail-body');
  if (!btn || !body || btn.dataset.trailBound) return;
  btn.dataset.trailBound = '1';
  var KEY = 'sf-trail-collapsed';
  function apply(collapsed) {
    body.style.display = collapsed ? 'none' : '';
    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    btn.style.transform = collapsed ? 'rotate(-90deg)' : '';
  }
  // Default RINGKAS (collapsed) supaya tab & tabel utama tidak terdorong ke
  // bawah fold. Pilihan user disimpan di localStorage.
  var stored = true;
  try { var v = localStorage.getItem(KEY); if (v !== null) stored = v === '1'; } catch (e) {}
  apply(stored);
  btn.addEventListener('click', function () {
    var next = body.style.display !== 'none';
    apply(next);
    try { localStorage.setItem(KEY, next ? '1' : '0'); } catch (e) {}
  });
})();
</script>
