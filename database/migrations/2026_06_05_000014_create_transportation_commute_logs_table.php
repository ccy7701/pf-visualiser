<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transportation_commute_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('transportation_vehicles')->cascadeOnDelete();
            $table->string('commute_type', 32)->default('personal_drive');
            $table->string('origin');
            $table->string('destination');
            $table->decimal('distance_km', 10, 2);
            $table->decimal('consumption_value', 10, 4);
            $table->string('consumption_unit', 16)->default('L_PER_100KM');
            $table->dateTime('driven_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('driven_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportation_commute_logs');
    }
};
