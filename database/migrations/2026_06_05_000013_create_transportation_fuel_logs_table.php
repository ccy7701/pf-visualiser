<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transportation_fuel_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('transportation_vehicles')->cascadeOnDelete();
            $table->decimal('odometer_km', 10, 2);
            $table->decimal('fuel_litres', 10, 3);
            $table->string('fuel_price_mode', 32)->default('budi95');
            $table->decimal('price_per_litre', 10, 3);
            $table->decimal('total_amount', 10, 2);
            $table->dateTime('fuelled_at');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('fuelled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportation_fuel_logs');
    }
};
