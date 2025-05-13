<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Relacionamento entre Motoristas e Instituições
        Schema::create('driver_institution', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_profile_id');
            $table->unsignedBigInteger('institution_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'temporary'])->default('active');
            $table->string('contract_type')->nullable();
            $table->json('schedule')->nullable(); // Horários de trabalho
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('driver_profile_id')->references('id')->on('driver_profiles')->onDelete('cascade');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');

            $table->unique(['driver_profile_id', 'institution_id']);
        });

        // Relacionamento entre Gerentes e Instituições
        Schema::create('manager_institution', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manager_profile_id');
            $table->unsignedBigInteger('institution_id');
            $table->boolean('is_primary')->default(false);
            $table->json('permissions')->nullable(); // Permissões específicas
            $table->date('assignment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('manager_profile_id')->references('id')->on('manager_profiles')->onDelete('cascade');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');

            $table->unique(['manager_profile_id', 'institution_id']);
        });


        // Relacionamento entre Passageiros e Instituições (opcional)
        Schema::create('passenger_institution', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('passenger_profile_id');
            $table->unsignedBigInteger('institution_id');
            $table->string('enrollment_code')->nullable();
            $table->date('enrollment_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'pending', 'graduated'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('passenger_profile_id')->references('id')->on('passenger_profiles')->onDelete('cascade');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');

            $table->unique(['passenger_profile_id', 'institution_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('passenger_institution');
        Schema::dropIfExists('manager_institution');
        Schema::dropIfExists('driver_institution');
    }
};
