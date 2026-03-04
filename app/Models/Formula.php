<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formula extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'amount_1',
        'amount_2',
        'account',
        'payment_code',
        'payment_name',
        'district',
        'district_code',
    ];
}
