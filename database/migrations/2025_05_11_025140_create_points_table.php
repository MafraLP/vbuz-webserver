<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('points', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['stop', 'terminal', 'landmark', 'connection'])->default('stop');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedBigInteger('institution_id');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->magellanPoint('location'); // Este método adiciona uma coluna point
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('points');
    }
};
