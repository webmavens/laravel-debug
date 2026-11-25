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
        Schema::create('debug_rule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debug_rule_id')
                ->constrained('debug_rules')
                ->cascadeOnDelete();

            if (config('database.default') === 'sqlite') {
                $table->text('result')->nullable();
            } else {
                $table->json('result')->nullable();
            }

            $table->enum('status', ['ok', 'failed'])->default('ok');
            // How long the run took.
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            // A rule's logs, newest first (rule page, paginated).
            $table->index(['debug_rule_id', 'created_at']);
            // Recent failures across all rules (dashboard).
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debug_rule_logs');
    }
};
