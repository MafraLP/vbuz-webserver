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
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->float('total_distance')->default(0);
            $table->float('total_duration')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('last_calculated_at')->nullable();

            // Colunas para controle de status de cálculo
            $table->enum('calculation_status', ['not_started', 'calculating', 'completed', 'error', 'failed'])
                ->default('not_started');
            $table->timestamp('calculation_started_at')->nullable();
            $table->timestamp('calculation_completed_at')->nullable();
            $table->text('calculation_error')->nullable();

            $table->timestamps();

            // Índices
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
            $table->index('calculation_status');
            $table->index(['calculation_status', 'calculation_started_at']);
            $table->index('is_published');
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
            $table->timestamps();

            // Índices e chaves estrangeiras
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
            $table->index(['route_id', 'sequence']);
            $table->index(['latitude', 'longitude']);
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
            $table->text('geometry')->nullable(); // Polyline encoded string
            $table->text('encoded_polyline')->nullable(); // Para compatibilidade
            $table->string('profile')->default('driving-car');
            $table->string('cache_key', 32)->nullable(); // MD5 hash
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Índices e chaves estrangeiras
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
            $table->foreign('start_point_id')->references('id')->on('route_points')->onDelete('cascade');
            $table->foreign('end_point_id')->references('id')->on('route_points')->onDelete('cascade');

            $table->index(['route_id', 'sequence']);
            $table->index('cache_key');
            $table->index(['cache_key', 'expires_at']);
            $table->index('profile');
        });

        // Adicionar colunas geográficas usando PostGIS se disponível
        if (Schema::hasTable('route_points')) {
            DB::statement('ALTER TABLE route_points ADD COLUMN location GEOGRAPHY(POINT, 4326)');

            // Trigger para atualizar automaticamente a coluna location
            DB::statement('
                CREATE OR REPLACE FUNCTION update_route_point_location()
                RETURNS TRIGGER AS $$
                BEGIN
                    NEW.location = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::geography;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ');

            DB::statement('
                CREATE TRIGGER trigger_update_route_point_location
                BEFORE INSERT OR UPDATE ON route_points
                FOR EACH ROW
                EXECUTE FUNCTION update_route_point_location();
            ');

            // Índice espacial
            DB::statement('CREATE INDEX idx_route_points_location ON route_points USING GIST (location)');
        }
    }

    public function down()
    {
        // Remover triggers e funções
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_route_point_location ON route_points');
        DB::statement('DROP FUNCTION IF EXISTS update_route_point_location()');

        // Remover tabelas
        Schema::dropIfExists('route_segments');
        Schema::dropIfExists('route_points');
        Schema::dropIfExists('routes');
    }
};
