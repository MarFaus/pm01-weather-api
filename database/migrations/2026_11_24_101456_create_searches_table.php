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
        Schema::create('searches', function (Blueprint $table) {
            $table->id();
            // История поиска: какие города смотрели и сколько раз.
            $table->string('city');
            $table->string('country')->nullable();
            $table->decimal('latitude', 8, 5);
            $table->decimal('longitude', 8, 5);
            $table->unsignedInteger('hits')->default(1);
            $table->timestamps();
            $table->unique(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('searches');
    }
};
