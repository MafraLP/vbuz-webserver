<?php

use Clickbar\Magellan\Schema\MagellanSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        MagellanSchema::enablePostgisIfNotExists($this->connection);
    }

    public function down(): void
    {
        MagellanSchema::disablePostgisIfExists($this->connection);
    }
};
