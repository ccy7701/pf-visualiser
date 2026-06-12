<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transportation_parking_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('parking_type', 32)->default('casual');
            $table->string('location');
            $table->date('parking_date');
            $table->date('billing_month')->nullable();
            $table->unsignedTinyInteger('start_hour')->nullable();
            $table->unsignedTinyInteger('end_hour')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('parking_date');
            $table->index('billing_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportation_parking_logs');
    }
};
