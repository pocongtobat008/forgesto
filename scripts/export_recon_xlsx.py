#!/usr/bin/env python3
"""Export rekonsiliasi ke xlsx untuk impor SAP.

Input : JSON via stdin  {"out": "/path/file.xlsx", "rows": [...], "meta": {...}}
Output: JSON ke stdout  {"ok": true, "rows": N, "bytes": B}

Susunan kolom ada di COLUMNS (satu sumber kebenaran). Ganti/urutkan di sini
bila format impor SAP sudah dipastikan.
"""
import json
import os
import sys

import openpyxl
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

# (kunci_di_row, header_kolom, format_angka)

# Pemetaan kolom layar (data-col) -> (kunci_di_row, header Excel). Dipakai bila payload 'cols' terisi.
SCREEN_COLS = {
    'kode':     ('item_code',       'Kode Material'),
    'nama':     ('item_name',       'Nama Material'),
    'grp':      ('group_name',      'Nama Group'),
    'loc':      ('location_name',   'Lokasi'),
    'kat':      ('category',        'Kategori'),
    'sat':      ('unit',            'Satuan'),
    'obs':      ('group_observers', 'Observer'),
    'aud':      ('group_auditors',  'Auditor'),
    'diajukan': ('submitted_by_name','Dikirim Oleh'),
    'tgl_ajuan':('submitted_at',    'Waktu Kirim'),
    'verif':    ('verified_by_name','Diverifikasi Oleh'),
    'tgl_verif':('verified_at',     'Waktu Verifikasi'),
    'catatan':  ('verify_note',     'Catatan Verifikasi'),
    'harga':    ('unit_price',      'Harga Satuan'),
    'stok':     ('stock_qty',       'Stok Buku'),
    'hitung':   ('counted_qty',     'Hasil Hitung'),
    'selqty':   ('difference',      'Selisih Qty'),
    'selnil':   ('diff_amount',     'Selisih Nilai'),
    'status':   ('status',          'Status'),
}
COLUMNS = [
    # (kunci_di_row, header_kolom, format_angka)  -- semua field reconciliation + relasi
    ('id',                 'ID',                    '0'),
    ('period',             'Periode',               None),
    ('direction',          'Arah Selisih',          None),
    ('group_code',         'Kode Group',            None),
    ('group_name',         'Nama Group',            None),
    ('location_name',      'Lokasi',                None),
    ('item_code',          'Kode Material',         None),
    ('item_name',          'Nama Material',         None),
    ('category',           'Kategori',              None),
    ('unit',               'Satuan',                None),
    ('stock_qty',          'Qty Buku',              '#,##0'),
    ('counted_qty',        'Qty Hitung',            '#,##0'),
    ('difference',         'Selisih Qty',           '#,##0;-#,##0;0'),
    ('unit_price',         'Harga Satuan',          '#,##0.00'),
    ('stock_amount',       'Nilai Buku',            '#,##0.00'),
    ('count_amount',       'Nilai Hitung',          '#,##0.00'),
    ('diff_amount',        'Selisih Nilai',         '#,##0.00;-#,##0.00;0.00'),
    ('status',             'Status',                None),
    ('recheck_requested',  'Minta Cek Ulang',       '0'),
    ('submitted_by_name',  'Dikirim Oleh',          None),
    ('submitted_at',       'Waktu Kirim',           None),
    ('verified_by_name',   'Diverifikasi Oleh',     None),
    ('verified_at',        'Waktu Verifikasi',      None),
    ('verify_note',        'Catatan Verifikasi',    None),
    ('group_observers',    'Observer',              None),
    ('group_auditors',     'Auditor',               None),
]


def main():
    payload = json.load(sys.stdin)
    out = payload['out']
    rows = payload.get('rows', [])

    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = 'Rekonsiliasi'

    # Pilih kolom sesuai layar (urutan ikut layar). Kosong = semua 26 kolom.
    sel = payload.get('cols') or []
    if sel:
        cols_use = [(SCREEN_COLS[k][0], SCREEN_COLS[k][1], None) for k in sel if k in SCREEN_COLS]
        # format angka: ikuti COLUMNS bila kunci sama
        fmt_by_key = {k: f for k, _, f in COLUMNS}
        cols_use = [(k, h, fmt_by_key.get(k)) for k, h, _ in cols_use]
    else:
        cols_use = COLUMNS
    headers = [h for _, h, _ in cols_use]
    ws.append(headers)
    head_fill = PatternFill('solid', fgColor='1F2937')
    for col in range(1, len(headers) + 1):
        c = ws.cell(row=1, column=col)
        c.font = Font(bold=True, color='FFFFFF')
        c.fill = head_fill
        c.alignment = Alignment(horizontal='center', vertical='center', wrap_text=True)
    ws.row_dimensions[1].height = 30

    for r in rows:
        vals = []
        for key, _, fmt in cols_use:
            v = r.get(key)
            if v is None:
                vals.append('')
            elif fmt and isinstance(v, str):
                # DECIMAL/INT dari PDO datang sebagai string -> jadikan angka agar bisa dihitung di Excel
                try:
                    f = float(v)
                    vals.append(int(f) if f.is_integer() and '.' not in v else f)
                except ValueError:
                    vals.append(v)
            else:
                vals.append(v)
        ws.append(vals)

    # Format angka per kolom
    for idx, (_, _, fmt) in enumerate(cols_use, start=1):
        if fmt:
            for row in range(2, len(rows) + 2):
                ws.cell(row=row, column=idx).number_format = fmt
        width = max(12, min(40, len(headers[idx - 1]) + 4))
        ws.column_dimensions[get_column_letter(idx)].width = width

    ws.freeze_panes = 'A2'
    ws.auto_filter.ref = f"A1:{get_column_letter(len(headers))}{max(1, len(rows) + 1)}"

    # Sheet meta: periode, waktu export, jumlah baris
    meta = wb.create_sheet('Info')
    meta.append(['Sumber', 'StockForge - Rekonsiliasi'])
    meta.append(['Jumlah baris', len(rows)])
    for k, v in (payload.get('meta') or {}).items():
        meta.append([k, v])
    meta.column_dimensions['A'].width = 22
    meta.column_dimensions['B'].width = 40

    os.makedirs(os.path.dirname(out), exist_ok=True)
    wb.save(out)
    print(json.dumps({'ok': True, 'rows': len(rows), 'bytes': os.path.getsize(out)}))


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:  # jangan diam-diam: laporkan error ke PHP
        print(json.dumps({'ok': False, 'error': str(exc)}))
        sys.exit(1)
