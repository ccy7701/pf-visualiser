<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transportation_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('tank_capacity_l', 8, 2)->default(0);
            $table->string('consumption_unit_default', 16)->default('L_PER_100KM');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportation_vehicles');
    }
};
