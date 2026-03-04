<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');                           // Дата
            $table->decimal('debit_amount', 15, 2)->default(0);   // Сумма дебет
            $table->decimal('credit_amount', 15, 2)->default(0);  // Сумма кредит
            $table->text('payment_purpose');                // Назначение платежа
            $table->string('flow');                         // Поток (Приход/Расход)
            $table->string('month');                        // Месяц
            $table->decimal('amount', 15, 2);               // Сумма
            $table->string('district');                     // Район
            $table->string('type');                         // Тип
            $table->integer('year');                        // ГОД
            $table->date('day_date');                       // День (as date)
            $table->timestamps();

            // Indexes for common queries
            $table->index('date');
            $table->index('district');
            $table->index('year');
            $table->index('month');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
