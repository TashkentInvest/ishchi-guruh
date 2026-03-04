<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionsSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = storage_path('app/public/detalization/DATASET.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found at: {$csvPath}");
            return;
        }

        $this->command->info('Starting to import transactions from CSV...');

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

        $count = 0;
        $batch = [];
        $batchSize = 1000;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 10) {
                continue; // Skip invalid rows
            }

            $data = [
                'date' => $this->parseDate($row[0] ?? ''),
                'debit_amount' => $this->parseAmount($row[1] ?? '0'),
                'credit_amount' => $this->parseAmount($row[2] ?? '0'),
                'payment_purpose' => $row[3] ?? '',
                'flow' => $row[4] ?? '',
                'month' => $row[5] ?? '',
                'amount' => $this->parseAmount($row[6] ?? '0'),
                'district' => $row[7] ?? '',
                'type' => $row[8] ?? '',
                'year' => (int) ($row[9] ?? 0),
                'day_date' => $this->parseDate($row[10] ?? $row[0] ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $batch[] = $data;
            $count++;

            if (count($batch) >= $batchSize) {
                DB::table('transactions')->insert($batch);
                $batch = [];
                $this->command->info("Imported {$count} records...");
            }
        }

        // Insert remaining records
        if (!empty($batch)) {
            DB::table('transactions')->insert($batch);
        }

        fclose($handle);

        $this->command->info("Successfully imported {$count} transactions!");
    }

    private function parseDate(string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        // Expected format: DD.MM.YYYY
        $dateStr = trim($dateStr);
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateStr, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            return "{$year}-{$month}-{$day}";
        }

        // Try to parse with strtotime as fallback
        $timestamp = strtotime(str_replace('.', '-', $dateStr));
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    private function parseAmount(string $amountStr): float
    {
        if (empty($amountStr)) {
            return 0.0;
        }

        // Remove spaces and replace comma with dot for decimal
        $amountStr = str_replace(' ', '', trim($amountStr));
        $amountStr = str_replace(',', '.', $amountStr);

        return (float) $amountStr;
    }
}
