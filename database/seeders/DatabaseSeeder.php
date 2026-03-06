<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding transactions from DATASET_JAMGARMA.csv and DATASET_GAZNA.csv...');
        $this->call(TransactionsSeeder::class);

        $this->command->info('Seeding formulas from FORMULAS_JAMGARMA.csv and FORMULAS_GAZNA.csv...');
        $this->call(FormulasSeeder::class);

        $this->command->info('Database seeding completed!');
    }
}
