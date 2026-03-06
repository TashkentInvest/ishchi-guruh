<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionsSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('memory_limit', '512M');
        DB::connection()->disableQueryLog();

        $sources = [
            [
                'path' => storage_path('app/public/detalization/DATASET_JAMGARMA.csv'),
                'status' => 'jamgarma',
            ],
            [
                'path' => storage_path('app/public/detalization/DATASET_GAZNA.csv'),
                'status' => 'gazna',
            ],
        ];

        $totalImported = 0;

        foreach ($sources as $source) {
            $csvPath = $source['path'];
            $status = $source['status'];

            if (!file_exists($csvPath)) {
                $this->command->warn("CSV file not found (skipped): {$csvPath}");
                continue;
            }

            $fileName = basename($csvPath);
            $this->command->info("Starting {$status} import from {$fileName}...");

            $imported = $this->importCsvFile($csvPath, $status);
            $totalImported += $imported;

            $this->command->info("Imported {$imported} records from {$fileName}.");
        }

        $this->command->info("Successfully imported {$totalImported} transactions in total!");
    }

    private function importCsvFile(string $csvPath, string $status): int
    {
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->command->error("Failed to open CSV file: {$csvPath}");
            return 0;
        }

        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            $this->command->error("Failed to read CSV header: {$csvPath}");
            fclose($handle);
            return 0;
        }

        $headerMap = $this->buildHeaderMap($header);

        $now = date('Y-m-d H:i:s');
        $count = 0;
        $batch = [];
        $batchSize = 500;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $date = $this->parseDate($this->valueByAliases($row, $headerMap, ['дата']));
            if (!$date) {
                continue;
            }

            $dayDate = $this->parseDate($this->valueByAliases($row, $headerMap, ['день', 'дата'])) ?? $date;

            $batch[] = [
                'date'            => $date,
                'debit_amount'    => $this->parseAmount($this->valueByAliases($row, $headerMap, ['сумма дебет'])),
                'credit_amount'   => $this->parseAmount($this->valueByAliases($row, $headerMap, ['сумма кредит'])),
                'payment_purpose' => mb_substr($this->valueByAliases($row, $headerMap, ['назначение платежа', 'детали платежи']), 0, 500),
                'flow'            => $this->valueByAliases($row, $headerMap, ['поток']),
                'month'           => $this->valueByAliases($row, $headerMap, ['месяц']),
                'amount'          => $this->parseAmount($this->valueByAliases($row, $headerMap, ['сумма'])),
                'district'        => $this->valueByAliases($row, $headerMap, ['район']),
                'type'            => $this->valueByAliases($row, $headerMap, ['тип']),
                'year'            => (int) $this->valueByAliases($row, $headerMap, ['год', 'фин. год']),
                'day_date'        => $dayDate,
                'status'          => $status,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];

            $count++;

            if (count($batch) >= $batchSize) {
                DB::table('transactions')->insert($batch);
                $batch = [];
                gc_collect_cycles();

                if ($count % 10000 === 0) {
                    $this->command->info("Imported {$count} records...");
                }
            }
        }

        if (!empty($batch)) {
            DB::table('transactions')->insert($batch);
        }

        fclose($handle);
        gc_collect_cycles();

        return $count;
    }

    private function buildHeaderMap(array $header): array
    {
        $map = [];

        foreach ($header as $index => $columnName) {
            $normalized = $this->normalizeHeader((string) $columnName);
            if ($normalized !== '' && !array_key_exists($normalized, $map)) {
                $map[$normalized] = $index;
            }
        }

        return $map;
    }

    private function valueByAliases(array $row, array $headerMap, array $aliases): string
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeHeader($alias);
            if (array_key_exists($key, $headerMap)) {
                return trim((string) ($row[$headerMap[$key]] ?? ''));
            }
        }

        return '';
    }

    private function normalizeHeader(string $header): string
    {
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        $header = preg_replace('/\s+/u', ' ', trim($header));

        return mb_strtolower($header);
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

    private function parseDate(string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $dateStr = trim($dateStr);
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateStr, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }

        $timestamp = strtotime(str_replace('.', '-', $dateStr));
        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    private function parseAmount(string $amountStr): float
    {
        if (empty($amountStr)) {
            return 0.0;
        }

        $amountStr = str_replace(["\xC2\xA0", ' '], '', trim($amountStr));
        $amountStr = str_replace(',', '.', $amountStr);

        if ($amountStr === '-' || $amountStr === '—' || $amountStr === '') {
            return 0.0;
        }

        return is_numeric($amountStr) ? (float) $amountStr : 0.0;
    }
}
