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
        Schema::create('debug_rule_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('rule_file')->unique();
            $table->string('name');
            $table->foreignId('debug_rule_id')
                ->nullable()
                ->constrained('debug_rules')
                ->nullOnDelete();
            $table->enum('status', ['applied', 'skipped'])->default('applied');
            $table->unsignedInteger('batch');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debug_rule_syncs');
    }
};
