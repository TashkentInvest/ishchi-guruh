<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('formulas', function (Blueprint $table) {
            $table->id();
            $table->text('description')->nullable();
            $table->string('amount_1', 100)->nullable();
            $table->string('amount_2', 100)->nullable();
            $table->string('account', 50)->nullable();
            $table->string('payment_code', 50)->nullable();
            $table->string('payment_name', 255)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('district_code', 20)->nullable();
            $table->timestamps();

            $table->index('district');
            $table->index('payment_code');
            $table->index('account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formulas');
    }
};
