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
        Schema::create('uploadables', function (Blueprint $table) {
            $table->foreignUuid('upload_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('uploadable');
            $table->string('type', 32);
            $table->timestamps();

            $table->unique(['uploadable_type', 'uploadable_id', 'type']);
            $table->index(['upload_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploadables');
    }
};
