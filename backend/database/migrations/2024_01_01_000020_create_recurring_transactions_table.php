<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['income', 'expense']);
            $table->enum('frequency', ['monthly', 'yearly']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
