<?php

declare(strict_types=1);

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
        $tableName = config('laravel-cities.table_name', 'geo');

        Schema::create($tableName, function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('parent_id')->nullable()->index();
            $table->unsignedInteger('left')->nullable()->index();
            $table->unsignedInteger('right')->nullable()->index();
            $table->unsignedSmallInteger('depth')->default(0)->index();
            $table->string('name', 60)->index();
            $table->json('alternames')->nullable();
            $table->char('country', 2)->nullable()->index();
            $table->string('a1code', 25)->nullable()->index();
            $table->string('level', 10)->nullable()->index();
            $table->unsignedBigInteger('population')->default(0);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('long', 10, 7)->nullable();
            $table->string('timezone', 40)->nullable();

            // Composite indexes for common queries
            $table->index(['country', 'level']);
            $table->index(['left', 'right', 'depth']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('laravel-cities.table_name', 'geo');

        Schema::dropIfExists($tableName);
    }
};
