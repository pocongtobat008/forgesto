<?php
/**
 * Page hero — banner header untuk SEMUA menu.
 *
 * Satu komponen, dua mode:
 *  1) Legacy dashboard: kirim $summary + $progress + $discrepancyCount
 *     → chip & aksi dihitung otomatis dari data.
 *  2) Menu lain: kirim $title + $tone + $art (+ $chips / $actions / $progress)
 *     → dipakai lewat helper page_hero().
 *
 * @var array|null $user
 * @var string     $title
 * @var string     $tone    kunci gradient (lihat css/hero-menu.css)
 * @var string     $art     kunci ilustrasi  (lihat css/hero-menu.css)
 * @var array|null $chips   [['icon'=>..,'text'=>..,'title'=>..], ...]
 * @var array|null $actions [['icon'=>..,'label'=>..,'url'=>..], ...]
 * @var array|null $progress ['pct'=>int,'text'=>string]  (opsional)
 * @var array|null $summary (legacy)
 * @var array|null $progressRows (legacy; nama lama $progress)
 * @var int|null   $discrepancyCount (legacy)
 */
$user = $user ?? auth();

$heroTitle = (string) ($title ?? __('Dashboard'));
$hTone     = (string) ($tone ?? 'violet');
$hArt      = (string) ($art ?? 'dashboard');

$hName    = (string) ($user['name'] ?? 'Pengguna');
$hInitial = strtoupper(mb_substr($hName, 0, 1));

// ---- Sapaan sesuai jam setempat (timezone aplikasi bila tersedia) ----
$hHour = (int) date('G');
try {
    $hTz = (string) config('app.timezone');
    if ($hTz !== '') {
        $hHour = (int) (new DateTime('now', new DateTimeZone($hTz)))->format('G');
    }
} catch (\Throwable $e) {
    // Pakai jam server sebagai fallback; sapaan tidak boleh memecah halaman.
}
if ($hHour < 11)      { $hGreet = 'Selamat pagi';  $hGreetIco = 'sun'; }
elseif ($hHour < 15)  { $hGreet = 'Selamat siang'; $hGreetIco = 'sun'; }
elseif ($hHour < 19)  { $hGreet = 'Selamat sore';  $hGreetIco = 'sun'; }
else                  { $hGreet = 'Selamat malam'; $hGreetIco = 'moon'; }

// ---- Tanggal lokal (id) ----
$hDowMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$hMonMap = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
$hDateLabel = ($hDowMap[date('l')] ?? date('l')) . ', ' . (int) date('j') . ' ' . ($hMonMap[date('m')] ?? date('m')) . ' ' . date('Y');

// ---- Gudang aktif ----
$hWhName = '—';
$hHasWh  = false;
try {
    $hWh = \App\Core\Auth::activeWarehouse();
    $hWhName = (string) ($hWh['name'] ?? 'Semua Gudang');
    $hHasWh  = (bool) \App\Core\Auth::hasWarehouse();
    if (! $hHasWh) { $hWhName = 'Semua Gudang'; }
} catch (\Throwable $e) {
    // Diamkan: hero tetap tampil tanpa nama gudang.
}

// ---------------------------------------------------------------------------
// Mode legacy (dashboard): data ringkas → chip + aksi + progress bar.
// ---------------------------------------------------------------------------
$hSummary   = $summary ?? null;
$hIsLegacy  = is_array($hSummary);
$hChips     = [];
$hActions   = [];
$hProgress  = null;

if ($hIsLegacy) {
    $hTotal    = (int) ($hSummary['total'] ?? 0);
    $hCounted  = (int) ($hSummary['counted'] ?? 0);
    $hCountPct = percent($hCounted, $hTotal);
    $hGroupN   = is_array($progress ?? null) ? count($progress) : 0;
    $hDisc     = (int) ($discrepancyCount ?? 0);
    $hCanAudit = function_exists('can') ? can('audit_data') : false;

    $hChips = [
        ['icon' => 'layers', 'text' => (int) $hGroupN . ' group'],
        ['icon' => 'trending-up', 'text' => (int) $hCountPct . '% dihitung', 'title' => __('Persentase item sudah dihitung')],
    ];
    if ($hDisc > 0) {
        $hChips[] = ['icon' => 'alert-circle', 'text' => (int) $hDisc . ' selisih terbuka'];
    }

    $hActions = [['icon' => 'package', 'label' => __('Buka Data STO'), 'url' => 'items']];
    if ($hCanAudit) {
        $hActions[] = ['icon' => 'clipboard-check', 'label' => __('Mulai Audit'), 'url' => 'audit'];
    }
    $hActions[] = ['icon' => 'trending-up', 'label' => __('Lihat Selisih'), 'url' => 'discrepancies'];

    $hProgress = ['pct' => (int) $hCountPct, 'text' => (int) $hCounted . '/' . (int) $hTotal . ' item'];
} else {
    foreach ((array) ($chips ?? []) as $c) {
        if (! empty($c['text'])) { $hChips[] = $c; }
    }
    foreach ((array) ($actions ?? []) as $a) {
        if (empty($a['label']) || empty($a['url'])) { continue; }
        if (isset($a['can']) && function_exists('can') && ! can((string) $a['can'])) { continue; }
        $hActions[] = $a;
    }
    $pg = $progress ?? null;
    if (is_array($pg) && isset($pg['pct'])) {
        $hProgress = $pg;
    }
}

// Chip konteks periode + gudang (selalu ada, apa pun menunya)
$hCtxChips = [];
try {
    $hPeriode = (new \App\Models\AuditPeriod())->active();
    if (is_array($hPeriode) && ! empty($hPeriode['audit_month'])) {
        $hCtxChips[] = ['icon' => 'clock', 'text' => (string) $hPeriode['audit_month'], 'title' => __('Periode aktif')];
    }
} catch (\Throwable $e) {
    // Tanpa periode: chip dilewati.
}
if ($hHasWh) {
    $hCtxChips[] = ['icon' => 'building', 'text' => $hWhName, 'title' => __('Gudang aktif')];
}
?>
<section class="sf-hero" id="sf-hero" aria-labelledby="sf-hero-name" data-sf-hero
         data-tone="<?= e($hTone) ?>" data-art="<?= e($hArt) ?>">
    <div class="sf-hero-top">
        <div class="sf-hero-id">
            <span class="sf-hero-avatar" aria-hidden="true"><?= e($hInitial) ?></span>
            <div class="sf-hero-copy">
                <span class="sf-hero-eyebrow"><?= icon($hGreetIco) ?> <?= e($hGreet) ?> &middot; <?= e($hName) ?></span>
                <h1 class="sf-hero-name" id="sf-hero-name"><?= e($heroTitle) ?></h1>
                <p class="sf-hero-sub"><?= e($hDateLabel) ?> &middot; <?= e(role_label($user['role'] ?? null)) ?> &middot; <?= e($hWhName) ?></p>
            </div>
        </div>

        <?php if ($hChips !== [] || $hCtxChips !== []): ?>
        <div class="sf-hero-chips">
            <?php foreach ($hCtxChips as $c): ?>
                <span class="sf-hero-chip"<?= ! empty($c['title']) ? ' title="' . e($c['title']) . '"' : '' ?>>
                    <span class="sf-hero-chip-ico" aria-hidden="true"><?= icon($c['icon'] ?? 'info') ?></span>
                    <?= e($c['text']) ?>
                </span>
            <?php endforeach; ?>
            <?php foreach ($hChips as $c): ?>
                <span class="sf-hero-chip"<?= ! empty($c['title']) ? ' title="' . e($c['title']) . '"' : '' ?>>
                    <span class="sf-hero-chip-ico" aria-hidden="true"><?= icon($c['icon'] ?? 'info') ?></span>
                    <?= e($c['text']) ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php /* Tombol aksi di header DIHAPUS untuk semua menu (permintaan 17 Okt 2026).
             Yang tersisa hanya progress bar (bila ada), karena itu indikator, bukan tombol. */ ?>
    <?php if ($hProgress !== null): ?>
    <div class="sf-hero-actions">
        <?php if ($hProgress !== null): ?>
            <span class="sf-hero-progress" role="progressbar" aria-valuenow="<?= (int) $hProgress['pct'] ?>" aria-valuemin="0" aria-valuemax="100"
                  aria-label="<?= e(__('Progres penghitungan')) ?>">
                <span class="sf-hero-progress-bar"><i style="width: <?= (int) $hProgress['pct'] ?>%"></i></span>
                <span class="sf-hero-progress-txt"><?= e((string) ($hProgress['text'] ?? '')) ?></span>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Wave berlapis: kaki header menyatu dengan kanvas -->
    <svg class="sf-hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <path class="sf-hw1" d="M0,98 C160,72 400,102 620,82 C840,62 1040,96 1240,78 C1330,70 1390,66 1440,72 L1440,120 L0,120 Z"/>
        <path class="sf-hw2" d="M0,70 C200,96 380,44 560,64 C740,84 920,112 1100,86 C1280,60 1360,44 1440,62 L1440,120 L0,120 Z"/>
        <path class="sf-hw3" d="M0,86 C180,52 360,110 540,88 C720,66 900,26 1080,54 C1260,82 1350,104 1440,84 L1440,120 L0,120 Z"/>
    </svg>
</section>
