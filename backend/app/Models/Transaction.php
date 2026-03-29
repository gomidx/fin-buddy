<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Category;
use App\Models\RecurringTransaction;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'category_id',
        'type',
        'amount',
        'description',
        'date',
        'is_recurring',
        'recurring_id',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'date'         => 'date',
        'is_recurring' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeInMonth($query, int $year, int $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
