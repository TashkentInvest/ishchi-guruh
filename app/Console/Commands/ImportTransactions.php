<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTransactions extends Command
{
    protected $signature   = 'transactions:import {--fresh : Truncate table before importing}';
    protected $description = 'Import transactions from DATASET_JAMGARMA.csv with low memory footprint';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $csvPath = storage_path('app/public/detalization/DATASET_JAMGARMA.csv');

        if (!file_exists($csvPath)) {
            $this->error("CSV not found: {$csvPath}");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::table('transactions')->truncate();
            $this->info('Table truncated.');
        }

        DB::connection()->disableQueryLog();

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->error('Cannot open CSV file.');
            return self::FAILURE;
        }

        // Skip header
        fgetcsv($handle, 0, ';');

        $now       = date('Y-m-d H:i:s');
        $batch     = [];
        $batchSize = 500;
        $count     = 0;

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% records [%bar%] %elapsed:6s% %memory:6s%');
        $bar->start();

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
            $bar->advance();

            if (count($batch) >= $batchSize) {
                DB::table('transactions')->insert($batch);
                $batch = [];
                gc_collect_cycles();
            }
        }

        if (!empty($batch)) {
            DB::table('transactions')->insert($batch);
        }

        fclose($handle);
        gc_collect_cycles();

        $bar->finish();
        $this->newLine();
        $this->info("✓ Imported {$count} transactions successfully.");

        return self::SUCCESS;
    }

    private function parseDate(string $s): ?string
    {
        $s = trim($s);
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $s, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        $t = strtotime(str_replace('.', '-', $s));
        return $t !== false ? date('Y-m-d', $t) : null;
    }

    private function parseAmount(string $s): float
    {
        $s = str_replace([' ', "\xc2\xa0"], '', trim($s));
        return (float) str_replace(',', '.', $s);
    }
}
