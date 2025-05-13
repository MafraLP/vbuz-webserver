<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Clickbar\Magellan\Database\PostgisFunctions\ST;

return new class extends Migration
{
    public function up()
    {
        // Tabela de rotas
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('user_id');
            $table->float('total_distance')->default(0);
            $table->float('total_duration')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Tabela de pontos de rota
        Schema::create('route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->integer('sequence');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->magellanPoint('location'); // Este método adiciona uma coluna point
            $table->timestamps();

            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
        });

        // Tabela de segmentos de rota (entre pontos)
        Schema::create('route_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->integer('sequence');
            $table->unsignedBigInteger('start_point_id');
            $table->unsignedBigInteger('end_point_id');
            $table->float('distance')->default(0); // em metros
            $table->float('duration')->default(0); // em segundos
            $table->text('geometry')->nullable(); // GeoJSON
            $table->text('encoded_polyline')->nullable();
            $table->string('profile')->default('driving-car');
            $table->string('cache_key')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
            $table->foreign('start_point_id')->references('id')->on('route_points')->onDelete('cascade');
            $table->foreign('end_point_id')->references('id')->on('route_points')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('route_segments');
        Schema::dropIfExists('route_points');
        Schema::dropIfExists('routes');
    }
};
