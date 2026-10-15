<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Native CSV / XLSX reader (no external dependencies).
 *
 * Usage:
 *   $rows = (new SpreadsheetReader())->read($tmpPath, 'xlsx');
 *   // => [ ['code' => 'A-001', 'name' => 'Laptop'], ... ] keyed by header row
 */
class SpreadsheetReader
{
    /**
     * @return array{headers:list<string>,rows:list<array<string,string>>}
     */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        $matrix = match ($extension) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx'       => $this->readXlsx($path),
            default      => throw new RuntimeException("Format file .{$extension} belum didukung. Gunakan .xlsx atau .csv."),
        };

        return $this->toAssoc($matrix);
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibuka.');
        }

        // Detect and strip a UTF-8 BOM.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Sniff the delimiter from the first line.
        $firstLine = (string) fgets($handle);
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") {
            fread($handle, 3);
        }

        $delimiter = ',';
        foreach ([';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t"), '|' => substr_count($firstLine, '|')] as $candidate => $count) {
            if ($count > substr_count($firstLine, ',')) {
                $delimiter = $candidate;
                break;
            }
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = array_map(static fn ($value): string => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Read the first worksheet of an XLSX archive.
     *
     * @return list<list<string>>
     */
    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP "zip" dibutuhkan untuk membaca file .xlsx.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File .xlsx tidak dapat dibaca (arsip rusak).');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetPath = $this->firstWorksheetPath($zip);
        $xmlContent = $zip->getFromName($sheetPath);
        $zip->close();

        if ($xmlContent === false) {
            throw new RuntimeException('Worksheet tidak ditemukan di dalam file .xlsx.');
        }

        return $this->parseSheet($xmlContent, $sharedStrings);
    }

    /** @return list<string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = $this->loadXml($content);
        if ($xml === null) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $si) {
            $strings[] = $this->richText($si);
        }

        return $strings;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        // Prefer the workbook relationship mapping when available.
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $wb = $this->loadXml($workbook);
            $relXml = $this->loadXml($rels);

            if ($wb !== null && $relXml !== null && isset($wb->sheets->sheet[0])) {
                $rid = (string) $wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                foreach ($relXml->Relationship as $relationship) {
                    if ((string) $relationship['Id'] === $rid) {
                        $target = ltrim((string) $relationship['Target'], '/');
                        if (! str_starts_with($target, 'xl/')) {
                            $target = 'xl/' . $target;
                        }

                        return $target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param list<string> $sharedStrings
     * @return list<list<string>>
     */
    private function parseSheet(string $content, array $sharedStrings): array
    {
        $xml = $this->loadXml($content);
        if ($xml === null) {
            return [];
        }

        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndex($reference);
                $type = (string) $cell['t'];

                $value = match ($type) {
                    's' => $sharedStrings[(int) $cell->v] ?? '',
                    'inlineStr' => $this->richText($cell->is),
                    'str' => (string) $cell->v,
                    default => (string) $cell->v,
                };

                $cells[$columnIndex] = trim($value);
            }

            if ($cells === []) {
                $rows[] = [];

                continue;
            }

            // Fill gaps so column positions stay aligned.
            $maxIndex = max(array_keys($cells));
            $ordered = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $ordered[] = $cells[$i] ?? '';
            }

            $rows[] = $ordered;
        }

        return $rows;
    }

    private function richText(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }

        if (isset($node->t)) {
            return (string) $node->t;
        }

        // <si><r><t>..</t></r><r><t>..</t></r></si>
        $text = '';
        if (isset($node->r)) {
            foreach ($node->r as $run) {
                $text .= (string) $run->t;
            }
        }

        return $text;
    }

    /**
     * Convert "BC12" / "A1" into a zero-based column index.
     */
    private function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function loadXml(string $content): ?SimpleXMLElement
    {
        if (! function_exists('simplexml_load_string')) {
            throw new RuntimeException('Ekstensi PHP "simplexml" dibutuhkan untuk membaca file .xlsx.');
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml === false ? null : $xml;
    }

    /**
     * Turn a raw matrix into header-keyed associative rows.
     *
     * @param list<list<string>> $matrix
     * @return array{headers:list<string>,rows:list<array<string,string>>}
     */
    private function toAssoc(array $matrix): array
    {
        // Drop fully-empty leading rows.
        while ($matrix !== [] && $this->isEmptyRow($matrix[0])) {
            array_shift($matrix);
        }

        if ($matrix === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headerRow = array_shift($matrix);
        $headers = array_map(static fn ($h): string => strtolower(trim((string) $h)), $headerRow);

        $rows = [];
        foreach ($matrix as $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            $rows[] = $assoc;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
