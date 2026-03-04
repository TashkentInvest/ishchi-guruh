<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding transactions from DATASET.csv...');
        $this->call(TransactionsSeeder::class);

        $this->command->info('Seeding formulas from formulas.csv...');
        $this->call(FormulasSeeder::class);

        $this->command->info('Database seeding completed!');
    }
}
