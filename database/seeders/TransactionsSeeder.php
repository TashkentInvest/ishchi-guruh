<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionsSeeder extends Seeder
{
    public function run(): void
    {
        // Raise memory limit for large CSV import
        ini_set('memory_limit', '512M');

        $csvPath = storage_path('app/public/detalization/DATASET.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found at: {$csvPath}");
            return;
        }

        $this->command->info('Starting to import transactions from CSV...');

        // Disable query log to avoid memory accumulation
        DB::connection()->disableQueryLog();

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->command->error('Failed to open CSV file');
            return;
        }

        // Read header
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            $this->command->error('Failed to read CSV header');
            fclose($handle);
            return;
        }

        // Pre-compute timestamps once (avoid Carbon overhead per row)
        $now = date('Y-m-d H:i:s');

        $count   = 0;
        $batch   = [];
        $batchSize = 500; // Smaller batches = less peak memory per flush

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 10) {
                continue;
            }

            $batch[] = [
                'date'            => $this->parseDate($row[0] ?? ''),
                'debit_amount'    => $this->parseAmount($row[1] ?? '0'),
                'credit_amount'   => $this->parseAmount($row[2] ?? '0'),
                'payment_purpose' => mb_substr($row[3] ?? '', 0, 500),
                'flow'            => $row[4] ?? '',
                'month'           => $row[5] ?? '',
                'amount'          => $this->parseAmount($row[6] ?? '0'),
                'district'        => $row[7] ?? '',
                'type'            => $row[8] ?? '',
                'year'            => (int) ($row[9] ?? 0),
                'day_date'        => $this->parseDate($row[10] ?? $row[0] ?? ''),
                'created_at'      => $now,
                'updated_at'      => $now,
            ];

            $count++;

            if (count($batch) >= $batchSize) {
                DB::table('transactions')->insert($batch);
                $batch = [];
                gc_collect_cycles(); // Free memory after each batch

                if ($count % 10000 === 0) {
                    $this->command->info("Imported {$count} records...");
                }
            }
        }

        // Insert remaining
        if (!empty($batch)) {
            DB::table('transactions')->insert($batch);
            $batch = [];
        }

        fclose($handle);
        gc_collect_cycles();

        $this->command->info("Successfully imported {$count} transactions!");
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

        $amountStr = str_replace([' ', '\xc2\xa0'], '', trim($amountStr));
        $amountStr = str_replace(',', '.', $amountStr);

        return (float) $amountStr;
    }
}
