<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class EmergencyFund extends Model
{
    protected $fillable = [
        'user_id',
        'target_months',
        'target_amount',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'target_months' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
