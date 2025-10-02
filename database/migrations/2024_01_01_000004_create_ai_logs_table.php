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
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('milestone_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('run_id')->index(); // UUID for tracing related interactions
            $table->enum('agent_type', ['pm', 'ba', 'ux', 'arch', 'dev', 'qa', 'doc']);
            $table->json('prompt'); // The prompt sent to AI
            $table->json('response'); // The AI response
            $table->string('model', 100); // AI model used
            $table->integer('tokens_used')->default(0);
            $table->integer('execution_time_ms')->default(0);
            $table->enum('status', ['success', 'error', 'timeout', 'cache_hit'])->default('success');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['run_id', 'created_at']);
            $table->index(['agent_type', 'status']);
            $table->index(['task_id', 'created_at']);
            $table->index('created_at'); // For cleanup queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};