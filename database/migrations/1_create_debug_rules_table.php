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
        Schema::create('debug_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('sql_query')->nullable();
            $table->integer('frequency_minutes')->default(15);
            $table->enum('importance_level', ['low', 'medium', 'high'])->default('medium');
            $table->enum('notification_level', ['none', 'default', 'urgent'])->default('default');
            $table->string('expected_rows_operator', 5)->default('=');
            $table->integer('expected_rows')->nullable();

            if (config('database.default') === 'sqlite') {
                $table->text('expected_json')->nullable();
                $table->text('last_debug_log')->nullable();
            } else {
                $table->json('expected_json')->nullable();
                $table->json('last_debug_log')->nullable();
            }

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('suppress')->default(false);
            $table->text('suppress_notes')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debug_rules');
    }
};
