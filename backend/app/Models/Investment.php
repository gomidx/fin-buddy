<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\InvestmentTransaction;

class Investment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'institution',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investmentTransactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    public function totalInvested(): float
    {
        // Evita N+1: usa a relação já carregada quando disponível
        if ($this->relationLoaded('investmentTransactions')) {
            return (float) $this->investmentTransactions->reduce(function (float $carry, InvestmentTransaction $t): float {
                if ($t->type === 'buy')  return $carry + (float) $t->amount;
                if ($t->type === 'sell') return $carry - (float) $t->amount;
                return $carry;
            }, 0.0);
        }

        return (float) $this->investmentTransactions()
            ->selectRaw("SUM(CASE WHEN type = 'buy' THEN amount WHEN type = 'sell' THEN -amount ELSE 0 END) as total")
            ->value('total') ?? 0;
    }
}
