<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixRoutePointsTableStructure extends Migration
{
    public function up()
    {
        // Primeiro, verificar se a tabela existe
        if (!Schema::hasTable('route_points')) {
            throw new Exception('Tabela route_points não existe');
        }

        // Adicionar point_id se não existir
        if (!Schema::hasColumn('route_points', 'point_id')) {
            Schema::table('route_points', function (Blueprint $table) {
                $table->unsignedBigInteger('point_id')->after('route_id');
            });

            // Adicionar foreign key separadamente
            Schema::table('route_points', function (Blueprint $table) {
                $table->foreign('point_id')->references('id')->on('points')->onDelete('cascade');
            });
        }

        // Adicionar campos da estrutura intermediária
        Schema::table('route_points', function (Blueprint $table) {
            if (!Schema::hasColumn('route_points', 'type')) {
                $table->enum('type', ['start', 'intermediate', 'end'])->default('intermediate')->after('sequence');
            }

            if (!Schema::hasColumn('route_points', 'stop_duration')) {
                $table->integer('stop_duration')->nullable()->after('type');
            }

            if (!Schema::hasColumn('route_points', 'is_optional')) {
                $table->boolean('is_optional')->default(false)->after('stop_duration');
            }

            if (!Schema::hasColumn('route_points', 'route_specific_notes')) {
                $table->text('route_specific_notes')->nullable()->after('is_optional');
            }

            if (!Schema::hasColumn('route_points', 'arrival_time')) {
                $table->time('arrival_time')->nullable()->after('route_specific_notes');
            }

            if (!Schema::hasColumn('route_points', 'departure_time')) {
                $table->time('departure_time')->nullable()->after('arrival_time');
            }
        });

        // Remover colunas que não devem estar na tabela intermediária
        $columnsToRemove = ['name', 'description', 'latitude', 'longitude', 'location'];

        foreach ($columnsToRemove as $column) {
            if (Schema::hasColumn('route_points', $column)) {
                Schema::table('route_points', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        // Adicionar índices usando SQL direto para evitar conflitos
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS route_points_route_id_sequence_idx ON route_points (route_id, sequence)');
            DB::statement('CREATE INDEX IF NOT EXISTS route_points_point_id_idx ON route_points (point_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS route_points_type_idx ON route_points (type)');
        } catch (\Exception $e) {
            // Índices podem já existir, ignorar erro
        }

        // Adicionar constraint único
        try {
            DB::statement('ALTER TABLE route_points ADD CONSTRAINT route_points_unique UNIQUE (route_id, point_id, sequence)');
        } catch (\Exception $e) {
            // Constraint pode já existir, ignorar erro
        }
    }

    public function down()
    {
        // Remover constraint único
        try {
            DB::statement('ALTER TABLE route_points DROP CONSTRAINT IF EXISTS route_points_unique');
        } catch (\Exception $e) {
            // Ignorar se não existir
        }

        // Remover índices
        try {
            DB::statement('DROP INDEX IF EXISTS route_points_route_id_sequence_idx');
            DB::statement('DROP INDEX IF EXISTS route_points_point_id_idx');
            DB::statement('DROP INDEX IF EXISTS route_points_type_idx');
        } catch (\Exception $e) {
            // Ignorar se não existirem
        }

        Schema::table('route_points', function (Blueprint $table) {
            // Remover foreign key
            $table->dropForeign(['point_id']);

            // Remover colunas adicionadas
            $table->dropColumn([
                'point_id',
                'type',
                'stop_duration',
                'is_optional',
                'route_specific_notes',
                'arrival_time',
                'departure_time'
            ]);
        });

        // Recriar colunas originais se necessário
        Schema::table('route_points', function (Blueprint $table) {
            $table->string('name')->after('sequence');
            $table->text('description')->nullable()->after('name');
            $table->decimal('latitude', 10, 7)->after('description');
            $table->decimal('longitude', 10, 7)->after('latitude');
        });
    }
}
