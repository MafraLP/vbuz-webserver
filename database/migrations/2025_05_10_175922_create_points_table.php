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
        Schema::create('points', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', [
                // Pontos básicos
                'stop',                    // Parada comum
                'terminal',                // Terminal de ônibus
                'landmark',                // Ponto de referência
                'connection',              // Conexão entre linhas

                // Pontos educacionais
                'school_stop',             // Parada escolar
                'university_stop',         // Parada universitária
                'campus_entrance',         // Entrada de campus
                'dormitory',               // Residência estudantil

                // Pontos de saúde
                'hospital',                // Hospital
                'health_center',           // Centro de saúde
                'emergency',               // Pronto socorro

                // Pontos comerciais e serviços
                'shopping_center',         // Centro comercial
                'market',                  // Mercado/feira
                'bank',                    // Banco
                'government_office',       // Repartição pública

                // Pontos de transporte integração
                'metro_station',           // Estação de metrô
                'train_station',           // Estação de trem
                'airport',                 // Aeroporto
                'bus_depot',               // Garagem de ônibus

                // Pontos residenciais
                'residential_complex',     // Conjunto habitacional
                'neighborhood_center',     // Centro de bairro

                // Pontos de lazer e cultura
                'park',                    // Parque
                'sports_center',           // Centro esportivo
                'cultural_center',         // Centro cultural
                'museum',                  // Museu
                'library',                 // Biblioteca

                // Pontos especiais
                'express_stop',            // Parada expressa
                'request_stop',            // Parada sob demanda
                'accessible_stop',         // Parada acessível
                'temporary_stop'           // Parada temporária
            ])->default('stop');

            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedBigInteger('institution_id');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            // Campos adicionais sugeridos
            $table->boolean('has_shelter')->default(false);          // Tem abrigo
            $table->boolean('is_accessible')->default(false);       // Acessível para PcD
            $table->boolean('has_lighting')->default(false);        // Tem iluminação
            $table->boolean('has_security')->default(false);        // Tem segurança
            $table->integer('capacity')->nullable();                // Capacidade de pessoas
            $table->json('operating_hours')->nullable();            // Horários de funcionamento
            $table->json('route_codes')->nullable();                // Códigos das rotas que passam

            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');

            // Índices para melhor performance
            $table->index(['type', 'is_active']);
            $table->index(['latitude', 'longitude']);
            $table->index('institution_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points');
    }
};
