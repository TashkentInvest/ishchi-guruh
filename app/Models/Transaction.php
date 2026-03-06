<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'debit_amount',
        'credit_amount',
        'payment_purpose',
        'flow',
        'month',
        'amount',
        'district',
        'type',
        'year',
        'day_date',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'day_date' => 'date',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'year' => 'integer',
    ];
}
