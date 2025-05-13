<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Adicionar o trigger para preencher o campo location automaticamente na tabela route_points
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_route_point_location()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.location = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER update_route_point_location_trigger
            BEFORE INSERT OR UPDATE OF latitude, longitude ON route_points
            FOR EACH ROW
            EXECUTE FUNCTION update_route_point_location();
        ');

        // Adicionar índice espacial para o campo location
        DB::statement('CREATE INDEX idx_route_points_location ON route_points USING GIST (location)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('DROP INDEX IF EXISTS idx_route_points_location');
        DB::unprepared('DROP TRIGGER IF EXISTS update_route_point_location_trigger ON route_points');
        DB::unprepared('DROP FUNCTION IF EXISTS update_route_point_location()');
    }
};
