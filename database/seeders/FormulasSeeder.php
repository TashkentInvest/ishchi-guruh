<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormulasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/public/detalization/formulas.csv');

        if (!file_exists($csvPath)) {
            Log::error("Formulas CSV file not found: {$csvPath}");
            return;
        }

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            Log::error("Could not open formulas CSV file: {$csvPath}");
            return;
        }

        $batchSize = 500;
        $batch = [];
        $lineCount = 0;

        $this->command->info('Starting formulas import...');

        while (($line = fgets($handle)) !== false) {
            $lineCount++;

            // Parse CSV line handling quoted fields
            $data = $this->parseCsvLine($line);

            if (empty($data) || count($data) < 11) {
                continue;
            }

            $batch[] = [
                'description'  => $data[0] ?? null,
                'amount_1'     => $data[1] ?? null,
                'amount_2'     => $data[2] ?? null,
                'account'      => $data[3] ?? null,
                'payment_code' => $data[4] ?? null,
                'payment_name' => $data[5] ?? null,
                'district'     => $data[9] ?? null,
                'district_code'=> $data[10] ?? null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            if (count($batch) >= $batchSize) {
                DB::table('formulas')->insert($batch);
                $this->command->info("Imported {$lineCount} formulas...");
                $batch = [];
            }
        }

        // Insert remaining records
        if (!empty($batch)) {
            DB::table('formulas')->insert($batch);
        }

        fclose($handle);

        $this->command->info("Formulas import completed! Total: {$lineCount} lines processed.");
    }

    /**
     * Parse a CSV line handling quoted fields
     */
    private function parseCsvLine(string $line): array
    {
        $data = [];
        $field = '';
        $inQuotes = false;
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if ($char === '"') {
                if ($inQuotes && $i + 1 < $length && $line[$i + 1] === '"') {
                    // Escaped quote
                    $field .= '"';
                    $i++;
                } else {
                    $inQuotes = !$inQuotes;
                }
            } elseif ($char === ';' && !$inQuotes) {
                $data[] = trim($field);
                $field = '';
            } else {
                $field .= $char;
            }
        }

        // Add last field
        $data[] = trim($field);

        return $data;
    }
}
