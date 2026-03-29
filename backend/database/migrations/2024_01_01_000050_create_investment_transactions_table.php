<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('investments')->cascadeOnDelete();
            $table->enum('type', ['buy', 'sell', 'dividend']);
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['investment_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_transactions');
    }
};
