<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('institution_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->nullable(); // Papel do usuário na instituição
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['institution_id', 'user_id']);
        });
        // Perfil de Motorista
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('license_number')->nullable();
            $table->string('license_type')->nullable();
            $table->date('license_expiry')->nullable();
            $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active');
            $table->date('hire_date')->nullable();
            $table->string('employment_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Perfil de Gerente
        Schema::create('manager_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->date('hire_date')->nullable();
            $table->enum('access_level', ['prefecture', 'department', 'institution'])->default('institution');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Perfil de Monitor
        // Adicionar ao arquivo de migração das tabelas pivot
// database/migrations/xxxx_xx_xx_create_role_institution_tables.php
        Schema::create('monitor_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('position')->nullable();
            $table->date('hire_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active');
            $table->string('employment_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
// Relacionamento entre Monitores e Instituições
        Schema::create('monitor_institution', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monitor_profile_id');
            $table->unsignedBigInteger('institution_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'temporary'])->default('active');
            $table->string('contract_type')->nullable();
            $table->json('schedule')->nullable(); // Horários de trabalho
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('monitor_profile_id')->references('id')->on('monitor_profiles')->onDelete('cascade');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');

            $table->unique(['monitor_profile_id', 'institution_id']);
        });

        // Perfil de Passageiro
        Schema::create('passenger_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('default_address')->nullable();
            $table->decimal('default_latitude', 10, 7)->nullable();
            $table->decimal('default_longitude', 10, 7)->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->text('special_needs')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('passenger_profiles');
        Schema::dropIfExists('monitor_profiles');
        Schema::dropIfExists('manager_profiles');
        Schema::dropIfExists('driver_profiles');
    }
};
