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
        // Habilitar a extensão PostGIS apenas se for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Verificar se as extensões já estão instaladas
            $postgisExists = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'postgis'");
            $postgisTopologyExists = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'postgis_topology'");

            // Criar extensões apenas se não existirem
            if (empty($postgisExists)) {
                try {
                    DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
                } catch (\Exception $e) {
                    // Registra erro, mas não interrompe a migração
                    \Log::error('Não foi possível criar a extensão PostGIS: ' . $e->getMessage());
                }
            }

            if (empty($postgisTopologyExists)) {
                try {
                    DB::statement('CREATE EXTENSION IF NOT EXISTS postgis_topology');
                } catch (\Exception $e) {
                    // Registra erro, mas não interrompe a migração
                    \Log::error('Não foi possível criar a extensão postgis_topology: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Em um ambiente de produção, geralmente não removemos extensões do PostgreSQL
        // pois outros bancos de dados podem estar usando
        // Se precisar remover, descomente o código abaixo

        /*
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('DROP EXTENSION IF EXISTS postgis_topology');
                DB::statement('DROP EXTENSION IF EXISTS postgis');
            } catch (\Exception $e) {
                \Log::error('Não foi possível remover as extensões PostGIS: ' . $e->getMessage());
            }
        }
        */
    }
};
