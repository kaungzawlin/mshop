<?php

namespace App\Services\Messaging;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class SpreadsheetAudienceParser
{
    /**
     * @return array<int, array<string, string>>
     */
    public function parse(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'csv', 'txt' => $this->parseCsv($file->getRealPath()),
            'xlsx' => $this->parseXlsx($file->getRealPath()),
            default => throw new InvalidArgumentException('Only .csv and .xlsx uploads are supported.'),
        };
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read upload.');
        }

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $data);

                continue;
            }

            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = trim((string) ($data[$i] ?? ''));
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Minimal XLSX reader (first sheet) via ZipArchive.
     *
     * @return array<int, array<string, string>>
     */
    private function parseXlsx(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new InvalidArgumentException('ZipArchive is required for xlsx. Upload CSV instead.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Invalid xlsx file.');
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = simplexml_load_string($sharedXml);
            if ($sx !== false) {
                foreach ($sx->si as $si) {
                    $shared[] = (string) ($si->t ?? $si->r->t ?? '');
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new InvalidArgumentException('xlsx sheet1 missing.');
        }

        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new InvalidArgumentException('Unable to parse xlsx sheet.');
        }

        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $rIndex = ((int) $row['r']) - 1;
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                preg_match('/([A-Z]+)/', $ref, $m);
                $col = $this->columnIndex($m[1] ?? 'A');
                $type = (string) $c['t'];
                $value = (string) ($c->v ?? '');
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $matrix[$rIndex][$col] = $value;
            }
        }

        if ($matrix === []) {
            return [];
        }

        ksort($matrix);
        $headerRow = array_shift($matrix);
        ksort($headerRow);
        $headers = [];
        foreach ($headerRow as $col => $name) {
            $headers[$col] = strtolower(trim((string) $name));
        }

        $rows = [];
        foreach ($matrix as $row) {
            $item = [];
            foreach ($headers as $col => $name) {
                $item[$name] = trim((string) ($row[$col] ?? ''));
            }
            if (count(array_filter($item)) === 0) {
                continue;
            }
            $rows[] = $item;
        }

        return $rows;
    }

    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }

        return $n - 1;
    }
}
