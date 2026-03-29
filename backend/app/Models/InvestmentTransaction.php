<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Investment;

class InvestmentTransaction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'investment_id',
        'type',
        'amount',
        'date',
        'description',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'date'       => 'date',
        'created_at' => 'datetime',
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
