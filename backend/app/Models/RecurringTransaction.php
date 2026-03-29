<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Category;
use App\Models\Transaction;

class RecurringTransaction extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'category_id',
        'description',
        'amount',
        'type',
        'frequency',
        'start_date',
        'end_date',
        'last_generated_at',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'last_generated_at' => 'datetime',
        'created_at'        => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'recurring_id');
    }

    public function isActive(): bool
    {
        return is_null($this->end_date) || $this->end_date->isFuture();
    }
}
