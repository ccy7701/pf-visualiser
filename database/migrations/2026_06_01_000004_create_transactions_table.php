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
            $table->enum('type', ['income', 'expense']);
            $table->dateTime('datetime');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['type', 'datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
