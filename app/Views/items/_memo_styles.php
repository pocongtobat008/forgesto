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

    /* ---- Kartu memo: padat, 7 blok data + tanda tangan ---- */
    .memo{border:1.2px solid #111;border-radius:2.6mm;padding:2.2mm 2.6mm;display:flex;flex-direction:column;gap:1.2mm;
        color:#111;break-inside:avoid;overflow:hidden;box-sizing:border-box;background:#fff;}

    .memo-head{display:flex;justify-content:space-between;align-items:flex-start;gap:2mm;
        border-bottom:1.6px solid #111;padding-bottom:1.2mm;}
    .memo-head-l{display:flex;flex-direction:column;gap:.3mm;min-width:0;}
    .memo-kicker{font-size:6pt;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#555;line-height:1;}
    .memo-period{font-size:8.4pt;font-weight:800;line-height:1.1;}
    .memo-wh{font-size:6.6pt;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#333;line-height:1.1;}
    .memo-code{font-family:var(--font-mono);font-size:11pt;font-weight:800;letter-spacing:.01em;
        text-align:right;line-height:1.05;overflow-wrap:anywhere;max-width:54%;}

    .memo-body{display:flex;gap:2.2mm;flex:1;min-height:0;}
    .memo-info{flex:1;display:flex;flex-direction:column;gap:1.2mm;min-width:0;}
    .memo-grid{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:1mm 2.4mm;align-content:space-between;}
    .memo-field{min-width:0;}
    .memo-span2{grid-column:1 / -1;}
    .memo-k{display:block;font-size:5.6pt;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#666;line-height:1.1;}
    .memo-v{display:block;font-size:7.6pt;font-weight:600;line-height:1.15;overflow-wrap:anywhere;}
    .memo-v-lg{font-size:9pt;font-weight:800;line-height:1.1;max-height:2.2em;overflow:hidden;}
    .memo-v-mono{font-family:var(--font-mono);font-size:6.2pt;letter-spacing:.01em;}

    .memo-qty{display:inline-flex;align-items:center;justify-content:center;border:1.2px solid #111;border-radius:1.6mm;
        padding:.4mm 1.6mm;width:100%;box-sizing:border-box;}
    .memo-qty b{font-family:var(--font-mono);font-size:13pt;font-weight:800;line-height:1;}
    .memo-field-qty{display:flex;align-items:center;gap:2.6mm;}
    .memo-field-qty .memo-k{margin:0;flex:0 0 auto;}
    .memo-qty-blank{min-height:11mm;margin-top:0;flex:1 1 auto;}
    .memo-line{display:block;border-bottom:1px solid #111;height:6mm;}
    .memo-v-name{font-weight:600;font-size:6.9pt;}
    .memo-sign-name{display:block;margin-top:.6mm;font-size:5.4pt;line-height:1.28;font-weight:600;color:#2b3444;
        text-align:center;letter-spacing:.01em;}
    .memo-sign-name span{display:inline;font-size:5pt;font-weight:800;letter-spacing:.07em;text-transform:uppercase;
        color:#8a94a6;margin-right:.7mm;}
    .memo-field-note{margin-top:1.2mm;}
    .memo-field-note .memo-line{height:5.4mm;border-bottom-style:dotted;}
    .memo-checks{display:flex;gap:3.4mm;font-size:6.6pt;font-weight:600;line-height:1.1;margin-top:.6mm;}
    .memo-check{display:inline-flex;align-items:center;gap:1.1mm;}
    .memo-check i{width:2.6mm;height:2.6mm;border:1px solid #111;border-radius:.5mm;display:inline-block;flex:0 0 auto;}

    .memo-qr{width:31.5mm;flex:0 0 31.5mm;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.9mm;
        border-left:1px dashed #aaa;padding-left:2.4mm;}
    .memo-qr svg{width:28mm;height:28mm;}
    .memo-qr small{font-family:var(--font-mono);font-size:6pt;color:#222;overflow-wrap:anywhere;text-align:center;line-height:1.1;}
    .memo-qr-hint{font-size:5.2pt;text-transform:uppercase;letter-spacing:.08em;color:#777;}

    .memo-foot{display:flex;gap:3mm;border-top:1px dashed #aaa;padding-top:1mm;}
    .memo-sign{flex:1;display:flex;flex-direction:column;gap:.3mm;min-width:0;}
    .memo-sign i{font-style:normal;font-size:5.4pt;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#666;}
    .memo-sign b{display:block;border-bottom:1px solid #111;height:4.4mm;}

    .memo-empty{grid-column:1/-1;text-align:center;color:#666;padding:20mm 0;}
    .memo-sample{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;padding:0;background:none;}
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
        .memo-qr{width:26mm;flex-basis:26mm;}
        .memo-qr svg{width:22mm;height:22mm;}
    }
</style>
