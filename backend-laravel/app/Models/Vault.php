<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vault extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'current_cash_paise',
    ];

    protected function casts(): array
    {
        return [
            'current_cash_paise' => 'integer',
        ];
    }
}
