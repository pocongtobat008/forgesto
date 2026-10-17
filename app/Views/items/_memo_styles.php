<?php
/** Gaya Marking Memo bersama: preview, PDF, dan contoh di halaman kelola. */
// $standalone = true HANYA saat render PDF (View::render dengan layout=null,
// sehingga tidak ada <head> dari layouts/app yang memuat CSS).
//
// PENTING: di halaman app biasa (memo_index, memo preview) CSS sudah dimuat oleh
// layouts/app dengan urutan tokens -> app -> sf-ui. Memuat ulang app.css DI SINI
// (setelah sf-ui.css) akan menurunkan kembali aturan !important lama app.css
// (.sidebar{top:0;z-index:100} + .topbar{z-index:50}) sehingga SIDEBAR MENUTUPI
// HEADER. Karena itu link di bawah hanya dirender pada mode standalone/PDF.
$standalone = $standalone ?? false;
?>
<?php if ($standalone): ?>
<link rel="stylesheet" href="<?= asset_v('css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset_v('css/app.css') ?>">
<?php endif; ?>
<style>
    .memo-stage{min-height:100vh;background:var(--bg);padding:1rem;box-sizing:border-box;}
    .memo-stage.is-pdf{background:#fff;padding:0;min-height:0;}
    .memo-bar{max-width:210mm;margin:0 auto 1rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;
        background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:.8rem 1rem;box-sizing:border-box;}
    .memo-bar h1{margin:0;font-family:var(--font-display);font-size:1.1rem;line-height:1.2;}
    .memo-bar p{margin:.15rem 0 0;color:var(--text-2);font-size:.85rem;}
    .memo-bar-actions{display:flex;flex-wrap:wrap;gap:.5rem;}
    .memo-bar-actions .btn{min-height:44px;}
    .memo-pager{max-width:210mm;margin:0 auto 1rem;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:center;}
    .memo-pager .btn{min-height:44px;}
    .memo-pager span{font-size:.9rem;color:var(--text-2);}
    .memo-sheet{width:min(210mm,100%);min-height:297mm;margin:0 auto 1.25rem;background:#fff;padding:5mm;box-sizing:border-box;
        display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr 1fr;gap:3mm;box-shadow:none;border:1px solid var(--sf-flat-border, #dfe3e8);border-radius:var(--sf-radius-card, 8px);}
    .memo-stage.is-pdf .memo-sheet{box-shadow:none;border-radius:0;width:210mm;min-height:297mm;margin:0;padding:6mm;
        break-after:page;page-break-after:always;}
    .memo-stage.is-pdf .memo-sheet:last-child{break-after:auto;page-break-after:auto;}
    .memo{border:1.2px solid #111;border-radius:3mm;padding:2.5mm;display:flex;flex-direction:column;gap:1.5mm;color:#111;
        break-inside:avoid;overflow:hidden;box-sizing:border-box;background:#fff;}
    .memo-head{display:flex;justify-content:space-between;align-items:flex-end;gap:2mm;border-bottom:2px solid #111;padding-bottom:1.8mm;}
    .memo-kicker{font-size:7.5pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#444;}
    .memo-code{font-family:var(--font-mono);font-size:12pt;font-weight:700;letter-spacing:.02em;overflow-wrap:break-word;text-align:right;line-height:1.2;}
    .memo-body{display:flex;gap:3mm;flex:1;min-height:0;}
    .memo-info{flex:1;display:flex;flex-direction:column;gap:1mm;min-width:0;}
    .memo-info > div:first-child{min-height:28pt;}
    .memo-k{display:block;font-size:6.5pt;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#555;}
    .memo-v{display:block;font-size:9pt;font-weight:600;line-height:1.2;overflow-wrap:anywhere;}
    .memo-qty{display:inline-flex;align-items:baseline;gap:1.5mm;border:1.2px solid #111;border-radius:2mm;padding:1mm 2.4mm;align-self:flex-start;}
    .memo-qty b{font-family:var(--font-mono);font-size:15pt;font-weight:800;}
    .memo-qty-blank{min-width:34mm;min-height:9mm;align-items:center;}
    .memo-qty-blank b{display:inline-block;min-width:26mm;}
    .memo-meta{display:flex;flex-direction:column;gap:.6mm;min-width:0;}
    .memo-period{font-size:8.5pt;font-weight:700;color:#111;}
    .memo-wh{font-size:7.5pt;font-weight:600;color:#444;text-transform:uppercase;letter-spacing:.06em;}
    .memo-qr{width:34mm;flex:0 0 34mm;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.5mm;
        border-left:1px dashed #999;padding-left:3mm;}
    .memo-qr svg{width:30mm;height:30mm;}
    .memo-qr small{font-family:var(--font-mono);font-size:6.5pt;color:#333;overflow-wrap:anywhere;text-align:center;}
    .memo-empty{grid-column:1/-1;text-align:center;color:#666;padding:20mm 0;}
    .memo-sample{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;padding:0;background:none;}
    .memo-sample .memo{min-width:0;}
    .memo-stats{margin-bottom:1.25rem;}
    .memo-stats + .card{margin-top:0.5rem;}
    .memo-toolbar{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;padding:.6rem 1.1rem;border-bottom:1px solid var(--border);}
    .memo-toolbar .btn{min-height:44px;}
    .memo-toolbar label{display:inline-flex;gap:.4rem;align-items:center;cursor:pointer;min-height:44px;}
    .memo-toolbar .spacer{flex:1 1 auto;}
    @page{size:A4;margin:0;}
    @media print{
        .no-print{display:none !important;}
        .memo-stage{background:#fff;padding:0;min-height:0;}
        .memo-sheet{box-shadow:none;border-radius:0;margin:0;width:210mm;min-height:297mm;break-after:page;page-break-after:always;}
        .memo-sheet:last-child{break-after:auto;page-break-after:auto;}
    }
    @media screen and (max-width:767px){
        .memo-sheet{min-height:0;padding:4mm;gap:3mm;grid-template-columns:1fr;grid-template-rows:auto;}
        .memo-bar-actions .btn{flex:1 1 auto;justify-content:center;}
        .memo-qr{width:28mm;flex-basis:28mm;}
        .memo-qr svg{width:24mm;height:24mm;}
    }

    .memo-info{flex:1;display:flex;flex-direction:column;min-width:0;height:100% !important;}
    .memo-info .memo-row-name{min-height:24pt !important;max-height:24pt !important;overflow:hidden !important;line-height:1.2 !important;}
    .memo-info .memo-row-loc{margin-top:1.5mm !important;}
    .memo-info .memo-row-qty{margin-top:auto !important;padding-top:1.5mm !important;}
    .memo-info .memo-row-remark{margin-top:1mm !important;}

</style>
