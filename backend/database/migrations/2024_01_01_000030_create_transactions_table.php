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
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->enum('type', ['income', 'expense', 'investment', 'emergency_fund']);
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->date('date');
            $table->boolean('is_recurring')->default(false);
            $table->foreignId('recurring_id')
                ->nullable()
                ->constrained('recurring_transactions')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
