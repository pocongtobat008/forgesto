<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Test 6 Memo per Sheet</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; padding: 2rem; }

.memo-sheet {
    width: min(210mm, 100%);
    min-height: 297mm;
    margin: 0 auto 1.25rem;
    background: #fff;
    padding: 6mm;
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr 1fr;
    gap: 4mm;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 4px;
}

.memo {
    border: 1.2px solid #111;
    border-radius: 3mm;
    padding: 3.5mm;
    display: flex;
    flex-direction: column;
    gap: 2.5mm;
    color: #111;
    background: #fff;
    overflow: hidden;
}

.memo-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 2mm;
    border-bottom: 2px solid #111;
    padding-bottom: 2.2mm;
}

.memo-kicker {
    font-size: 7.5pt;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #444;
}

.memo-code {
    font-family: 'Courier New', monospace;
    font-size: 12pt;
    font-weight: 700;
    letter-spacing: 0.02em;
    overflow-wrap: break-word;
    text-align: right;
    line-height: 1.2;
}

.memo-body {
    display: flex;
    gap: 3mm;
    flex: 1;
}

.memo-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2mm;
    font-size: 9pt;
}

.memo-info > div {
    display: flex;
    flex-direction: column;
    gap: 0.5mm;
}

.memo-k {
    font-size: 7pt;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #666;
}

.memo-v {
    font-weight: 600;
    color: #111;
}

.memo-qr {
    width: 32mm;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.memo-qr svg {
    width: 28mm;
    height: 28mm;
}
</style>
</head>
<body>

<div style="max-width: 210mm; margin: 0 auto 2rem; background: #fff; padding: 1rem; border-radius: 4px;">
    <h1 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Test Layout: 6 Memo per Sheet A4</h1>
    <p style="color: #666;">Grid: 2 kolom × 3 baris. Kode maksimal 15 karakter tidak wrap.</p>
</div>

<!-- Sheet 1: 6 memo -->
<main class="memo-sheet">
    <?php for ($i = 1; $i <= 6; $i++): ?>
    <article class="memo">
        <header class="memo-head">
            <span class="memo-kicker">Marking memo</span>
            <span class="memo-code">KODE-PANJANG-<?= $i ?></span>
        </header>
        <div class="memo-body">
            <div class="memo-info">
                <div>
                    <span class="memo-k">Nama barang</span>
                    <span class="memo-v">Item Test Nomor <?= $i ?></span>
                </div>
                <div>
                    <span class="memo-k">Lokasi</span>
                    <span class="memo-v">Gudang A-<?= $i ?></span>
                </div>
                <div>
                    <span class="memo-k">Jumlah</span>
                    <span class="memo-v">_____ unit</span>
                </div>
            </div>
            <div class="memo-qr">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" fill="#fff"/>
                    <text x="50" y="50" text-anchor="middle" dominant-baseline="middle" font-size="12" fill="#999">QR <?= $i ?></text>
                </svg>
            </div>
        </div>
    </article>
    <?php endfor; ?>
</main>

<!-- Sheet 2: 6 memo lagi -->
<main class="memo-sheet">
    <?php for ($i = 7; $i <= 12; $i++): ?>
    <article class="memo">
        <header class="memo-head">
            <span class="memo-kicker">Marking memo</span>
            <span class="memo-code">KODE-123456789<?= $i ?></span>
        </header>
        <div class="memo-body">
            <div class="memo-info">
                <div>
                    <span class="memo-k">Nama barang</span>
                    <span class="memo-v">Item Test Nomor <?= $i ?></span>
                </div>
                <div>
                    <span class="memo-k">Lokasi</span>
                    <span class="memo-v">Gudang B-<?= $i ?></span>
                </div>
                <div>
                    <span class="memo-k">Jumlah</span>
                    <span class="memo-v">_____ unit</span>
                </div>
            </div>
            <div class="memo-qr">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" fill="#fff"/>
                    <text x="50" y="50" text-anchor="middle" dominant-baseline="middle" font-size="12" fill="#999">QR <?= $i ?></text>
                </svg>
            </div>
        </div>
    </article>
    <?php endfor; ?>
</main>

</body>
</html>
