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
//        Schema::create('routes', function (Blueprint $table) {
//            $table->id();
//            $table->string('name');
//            $table->text('description')->nullable();
//            $table->unsignedBigInteger('institution_id');
//            $table->boolean('is_active')->default(true);
//            $table->json('schedule')->nullable(); // Horários regulares
//            $table->decimal('total_distance', 10, 2)->nullable(); // Distância em km
//            $table->integer('total_duration')->nullable(); // Duração em minutos
//            $table->text('notes')->nullable();
//            $table->timestamps();
//
//            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
//        });
//
//        // Tabela pivot para pontos de rota
//        Schema::create('route_points', function (Blueprint $table) {
//            $table->id();
//            $table->unsignedBigInteger('route_id');
//            $table->unsignedBigInteger('point_id');
//            $table->integer('order')->default(0); // Ordem dos pontos na rota
//            $table->integer('waiting_time')->default(0); // Tempo de espera em minutos
//            $table->text('notes')->nullable();
//            $table->timestamps();
//
//            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
//            $table->foreign('point_id')->references('id')->on('points')->onDelete('cascade');
//
//            // Um ponto pode aparecer várias vezes na mesma rota, então não usamos unique
//            $table->index(['route_id', 'point_id', 'order']);
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_points');
        Schema::dropIfExists('routes');
    }
};
