<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Clickbar\Magellan\Database\PostgisFunctions\ST;

return new class extends Migration
{
    public function up()
    {
        // Tabela de permissões/carteirinhas
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // Código único da carteirinha
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#007bff'); // Cor hexadecimal
            $table->string('icon')->nullable(); // Nome do ícone
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->timestamps();

            // Índices
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
            $table->index('code');
            $table->index('is_active');
            $table->index(['institution_id', 'is_active']);
        });

        // Tabela de rotas
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('institution_id')->nullable();

            // Campos de agendamento
            $table->enum('schedule_type', ['daily', 'weekly', 'monthly', 'custom'])->default('daily');
            $table->json('schedule_data')->nullable(); // Dados do agendamento em JSON

            // Campos de distância e duração
            $table->float('total_distance')->default(0);
            $table->float('total_duration')->default(0);

            // Campos de publicação
            $table->boolean('is_published')->default(false);
            $table->boolean('is_public')->default(false); // Se é pública (acessível a todos)

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
            $table->index('is_public');
            $table->index('schedule_type');
            $table->index(['institution_id', 'is_published']);
            $table->index(['institution_id', 'is_public']);
        });

        // Tabela de pontos de rota - USANDO REFERÊNCIA PARA POINTS
        Schema::create('route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->unsignedBigInteger('point_id'); // REFERÊNCIA PARA A TABELA POINTS
            $table->integer('sequence');
            $table->enum('type', ['start', 'intermediate', 'end'])->default('intermediate');

            // Campos opcionais específicos da rota
            $table->integer('stop_duration')->nullable(); // Tempo de parada em minutos
            $table->boolean('is_optional')->default(false); // Se a parada é opcional
            $table->text('route_specific_notes')->nullable(); // Observações específicas desta parada nesta rota
            $table->time('arrival_time')->nullable(); // Horário previsto de chegada
            $table->time('departure_time')->nullable(); // Horário previsto de saída

            $table->timestamps();

            // Índices e chaves estrangeiras
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
            $table->foreign('point_id')->references('id')->on('points')->onDelete('cascade');

            // Índices
            $table->index(['route_id', 'sequence']);
            $table->index('point_id');
            $table->index('type');
            $table->index(['route_id', 'point_id']); // Para evitar duplicatas e busca rápida

            // Garantir que não haja pontos duplicados na mesma rota
            $table->unique(['route_id', 'point_id', 'sequence']);
        });

        // Tabela de segmentos de rota (entre pontos)
        Schema::create('route_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->integer('sequence');
            $table->unsignedBigInteger('start_point_id'); // Referência para route_points
            $table->unsignedBigInteger('end_point_id');   // Referência para route_points
            $table->float('distance')->default(0); // em metros
            $table->float('duration')->default(0); // em segundos
            $table->json('geometry')->nullable(); // Geometria da rota em JSON
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

        // Tabela pivot para rotas e permissões
        Schema::create('route_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            // Chaves estrangeiras
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');

            // Índices únicos para evitar duplicatas
            $table->unique(['route_id', 'permission_id']);
            $table->index('route_id');
            $table->index('permission_id');
        });

        // Tabela pivot para usuários e permissões
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamp('granted_at')->useCurrent();
            $table->unsignedBigInteger('granted_by')->nullable(); // ID do usuário que concedeu
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Chaves estrangeiras
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');

            // Índices únicos para evitar duplicatas
            $table->unique(['user_id', 'permission_id']);
            $table->index('user_id');
            $table->index('permission_id');
            $table->index('is_active');
            $table->index(['expires_at', 'is_active']);
        });
    }

    public function down()
    {
        // Remover tabelas na ordem correta (respeitando foreign keys)
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('route_permissions');
        Schema::dropIfExists('route_segments');
        Schema::dropIfExists('route_points');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('permissions');
    }
};
