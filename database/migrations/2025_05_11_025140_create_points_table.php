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
        $this->addPostGISLocationToPoints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover índices adicionados
        Schema::table('points', function (Blueprint $table) {
            try {
                $table->dropIndex(['points_latitude_longitude_index']);
                $table->dropIndex(['points_institution_id_index']);
                $table->dropIndex(['points_type_index']);
                $table->dropIndex(['points_is_active_index']);
                $table->dropIndex(['points_institution_id_is_active_type_index']);
            } catch (Exception $e) {
                // Índices não existem, ignorar
            }
        });

        // Remover recursos PostGIS
        $this->removePostGISLocationFromPoints();
    }

    /**
     * Adiciona coluna location com PostGIS na tabela points
     */
    private function addPostGISLocationToPoints(): void
    {
        try {
            // Verificar se PostGIS está disponível
            $postgisCheck = DB::select("SELECT EXISTS(SELECT 1 FROM pg_extension WHERE extname = 'postgis') as has_postgis");

            if (!$postgisCheck[0]->has_postgis) {
                                return;
            }

            // Verificar se a coluna location já existe
            $hasLocationColumn = DB::select("
                SELECT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'points' AND column_name = 'location'
                ) as has_location
            ");

            if ($hasLocationColumn[0]->has_location) {
                                return;
            }

            // Adicionar coluna location
            DB::statement('ALTER TABLE points ADD COLUMN location GEOGRAPHY(POINT, 4326)');

            // Criar função para atualizar location automaticamente
            DB::statement('
                CREATE OR REPLACE FUNCTION update_point_location()
                RETURNS TRIGGER AS $$
                BEGIN
                    NEW.location = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::geography;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ');

            // Criar trigger
            DB::statement('
                CREATE TRIGGER trigger_update_point_location
                BEFORE INSERT OR UPDATE ON points
                FOR EACH ROW
                EXECUTE FUNCTION update_point_location();
            ');

            // Atualizar registros existentes
            DB::statement('
                UPDATE points
                SET location = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)::geography
                WHERE location IS NULL
            ');

            // Criar índice espacial
            DB::statement('CREATE INDEX idx_points_location ON points USING GIST (location)');

                    } catch (Exception $e) {
                    }
    }

    /**
     * Remove coluna location e recursos PostGIS da tabela points
     */
    private function removePostGISLocationFromPoints(): void
    {
        try {
            // Remover trigger
            DB::statement('DROP TRIGGER IF EXISTS trigger_update_point_location ON points');

            // Remover função
            DB::statement('DROP FUNCTION IF EXISTS update_point_location()');

            // Remover índice
            DB::statement('DROP INDEX IF EXISTS idx_points_location');

            // Remover coluna (comentado para segurança)
            // DB::statement('ALTER TABLE points DROP COLUMN IF EXISTS location');

        } catch (Exception $e) {
                    }
    }
};
