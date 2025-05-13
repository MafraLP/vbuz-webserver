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
//        Schema::create('points', function (Blueprint $table) {
//            $table->id();
//            $table->string('name');
//            $table->enum('type', ['stop', 'terminal', 'landmark', 'connection'])->default('stop');
//            $table->text('description')->nullable();
//            $table->decimal('latitude', 10, 7);
//            $table->decimal('longitude', 10, 7);
//            $table->unsignedBigInteger('institution_id');
//            $table->boolean('is_active')->default(true);
//            $table->text('notes')->nullable();
//            $table->timestamps();
//
//            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points');
    }
};
