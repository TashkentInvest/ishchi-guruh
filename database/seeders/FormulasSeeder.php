<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormulasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::connection()->disableQueryLog();

        $sources = [
            [
                'path' => storage_path('app/public/detalization/FORMULAS_JAMGARMA.csv'),
                'status' => 'jamgarma',
            ],
            [
                'path' => storage_path('app/public/detalization/FORMULAS_GAZNA.csv'),
                'status' => 'gazna',
            ],
        ];

        $totalImported = 0;

        foreach ($sources as $source) {
            $csvPath = $source['path'];
            $status = $source['status'];

            if (!file_exists($csvPath)) {
                $this->command->warn("Formulas CSV file not found (skipped): {$csvPath}");
                continue;
            }

            $fileName = basename($csvPath);
            $this->command->info("Starting formulas import from {$fileName} ({$status})...");

            $imported = $this->importFormulaFile($csvPath, $status);
            $totalImported += $imported;

            $this->command->info("Imported {$imported} formulas from {$fileName}.");
        }

        $this->command->info("Formulas import completed. Total imported: {$totalImported}");
    }

    private function importFormulaFile(string $csvPath, string $status): int
    {
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->command->error("Could not open formulas CSV file: {$csvPath}");
            return 0;
        }

        $batchSize = 500;
        $batch = [];
        $count = 0;
        $now = now();

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $record = $status === 'jamgarma'
                ? $this->buildJamgarmaRecord($row)
                : $this->buildGaznaRecord($row);

            if ($record === null) {
                continue;
            }

            $record['status'] = $status;
            $record['created_at'] = $now;
            $record['updated_at'] = $now;

            $batch[] = $record;
            $count++;

            if (count($batch) >= $batchSize) {
                DB::table('formulas')->insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('formulas')->insert($batch);
        }

        fclose($handle);

        return $count;
    }

    private function buildJamgarmaRecord(array $data): ?array
    {
        if (count($data) < 11) {
            return null;
        }

        $description = $this->clip($data[0] ?? null, 65535);
        $amount1 = $this->clip($data[1] ?? null, 100);
        $amount2 = $this->clip($data[2] ?? null, 100);
        $account = $this->clip($data[3] ?? null, 50);
        $paymentCode = $this->clip($data[4] ?? null, 50);
        $paymentName = $this->clip($data[5] ?? null, 255);
        $district = $this->clip($data[9] ?? null, 100);
        $districtCode = $this->clip($data[10] ?? null, 20);

        if (!$description && !$paymentCode && !$paymentName && !$district) {
            return null;
        }

        return [
            'description'   => $description,
            'amount_1'      => $amount1,
            'amount_2'      => $amount2,
            'account'       => $account,
            'payment_code'  => $paymentCode,
            'payment_name'  => $paymentName,
            'district'      => $district,
            'district_code' => $districtCode,
        ];
    }

    private function buildGaznaRecord(array $data): ?array
    {
        $trimmed = array_map(fn ($value) => trim((string) $value), $data);
        $nonEmpty = array_values(array_filter($trimmed, fn ($value) => $value !== '' && $value !== '-'));

        if (count($nonEmpty) < 2) {
            return null;
        }

        $description = $this->clip($nonEmpty[1] ?? $nonEmpty[0] ?? null, 65535);
        $paymentName = $this->clip($nonEmpty[1] ?? null, 255);

        if (!$description) {
            return null;
        }

        $numericValues = array_values(array_filter($nonEmpty, function ($value) {
            return preg_match('/\d/u', $value) && preg_match('/[\d,\.\s]/u', $value);
        }));

        $district = null;
        foreach ($nonEmpty as $value) {
            if (preg_match('/(туман|шаҳар)/iu', $value)) {
                $district = $value;
                break;
            }
        }

        $districtCode = null;
        foreach ($nonEmpty as $value) {
            if (preg_match('/^\d{5}$/', $value)) {
                $districtCode = $value;
                break;
            }
        }

        return [
            'description'   => $description,
            'amount_1'      => $this->clip($numericValues[0] ?? null, 100),
            'amount_2'      => $this->clip($numericValues[1] ?? null, 100),
            'account'       => null,
            'payment_code'  => null,
            'payment_name'  => $paymentName,
            'district'      => $this->clip($district, 100),
            'district_code' => $this->clip($districtCode, 20),
        ];
    }

    private function clip(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }

        return mb_substr($value, 0, $limit);
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
