<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate', 20)->unique();
            $table->string('model', 100);
            $table->string('brand', 100);
            $table->integer('year');
            $table->integer('capacity');
            $table->string('type', 50); // 'bus', 'minibus', 'van', etc.
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active');
            $table->unsignedBigInteger('institution_id');
            $table->json('features')->nullable(); // Ar condicionado, acessibilidade, etc.
            $table->date('last_maintenance')->nullable();
            $table->date('next_maintenance')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
