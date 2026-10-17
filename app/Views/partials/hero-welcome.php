<?php
/**
 * Dashboard welcome hero — "Aurora Glass" signature panel.
 * Menyatukan: sapaan kontekstual (waktu), identitas, status periode, dan
 * pintasan aksi. Bukan duplikat kartu statistik: angka di sini ringkas,
 * aksi di sini nyata (tautan).
 *
 * @var array|null $user
 * @var array      $summary
 * @var array      $progress
 * @var int        $discrepancyCount
 */
$user = $user ?? auth();

$hTotal    = (int) ($summary['total'] ?? 0);
$hCounted  = (int) ($summary['counted'] ?? 0);
$hAudited  = (int) ($summary['audited'] ?? 0);
$hCountPct = percent($hCounted, $hTotal);
$hGroupN   = is_array($progress) ? count($progress) : 0;
$hDisc     = (int) ($discrepancyCount ?? 0);
$hName     = (string) ($user['name'] ?? 'Pengguna');
$hInitial  = strtoupper(mb_substr($hName, 0, 1));

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
$hDate = date('Y-m-d');
$hDateLabel = ($hDowMap[date('l')] ?? date('l')) . ', ' . (int) date('j') . ' ' . ($hMonMap[date('m')] ?? date('m')) . ' ' . date('Y');

// ---- Gudang aktif ----
$hWhName = '—';
try {
    $hWh = \App\Core\Auth::activeWarehouse();
    $hWhName = (string) ($hWh['name'] ?? 'Semua Gudang');
    if (! \App\Core\Auth::hasWarehouse()) { $hWhName = 'Semua Gudang'; }
} catch (\Throwable $e) {
    // Diamkan: hero tetap tampil tanpa nama gudang.
}

$hCanAudit = function_exists('can') ? can('audit_data') : false;
?>
<section class="sf-hero" id="sf-hero" aria-labelledby="sf-hero-name" data-sf-hero>
    <!-- Wave: satu mesin terpadu di CSS (.sf-hero::after) — tile sinusoidal
         seamless + overshoot tepi, jadi mustahil ada gap di ujung kartu. -->

    <div class="sf-hero-top">
        <div class="sf-hero-id">
            <span class="sf-hero-avatar" aria-hidden="true"><?= e($hInitial) ?></span>
            <div class="sf-hero-copy">
                <span class="sf-hero-eyebrow"><?= icon($hGreetIco) ?> <?= e($hGreet) ?></span>
                <h2 class="sf-hero-name" id="sf-hero-name"><?= e($hName) ?></h2>
                <p class="sf-hero-sub"><?= e($hDateLabel) ?> &middot; <?= e(role_label($user['role'] ?? null)) ?> &middot; <?= e($hWhName) ?></p>
            </div>
        </div>

        <div class="sf-hero-chips">
            <span class="sf-hero-chip">
                <span class="sf-hero-chip-ico" aria-hidden="true"><?= icon('layers') ?></span>
                <?= (int) $hGroupN ?> group
            </span>
            <span class="sf-hero-chip" title="<?= e(__('Persentase item sudah dihitung')) ?>">
                <span class="sf-hero-chip-ico" aria-hidden="true"><?= icon('trending-up') ?></span>
                <?= (int) $hCountPct ?>% dihitung
            </span>
            <?php if ($hDisc > 0): ?>
                <span class="sf-hero-chip">
                    <span class="sf-hero-chip-ico" aria-hidden="true"><?= icon('alert-circle') ?></span>
                    <?= (int) $hDisc ?> selisih terbuka
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="sf-hero-actions">
        <a class="sf-hero-action" href="<?= url('items') ?>">
            <?= icon('package') ?> <span><?= __('Buka Data STO') ?></span>
        </a>
        <?php if ($hCanAudit): ?>
            <a class="sf-hero-action" href="<?= url('audit') ?>">
                <?= icon('clipboard-check') ?> <span><?= __('Mulai Audit') ?></span>
            </a>
        <?php endif; ?>
        <a class="sf-hero-action" href="<?= url('discrepancies') ?>">
            <?= icon('trending-up') ?> <span><?= __('Lihat Selisih') ?></span>
        </a>
        <span class="sf-hero-progress" role="progressbar" aria-valuenow="<?= (int) $hCountPct ?>" aria-valuemin="0" aria-valuemax="100"
              aria-label="<?= e(__('Progres penghitungan')) ?>">
            <span class="sf-hero-progress-bar"><i style="width: <?= (int) $hCountPct ?>%"></i></span>
            <span class="sf-hero-progress-txt"><?= (int) $hCounted ?>/<?= (int) $hTotal ?> item</span>
        </span>
    </div>
</section>
