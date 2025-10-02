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
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->integer('sequence'); // Order of milestone execution
            $table->string('title');
            $table->text('description');
            $table->enum('agent_type', ['pm', 'ba', 'ux', 'arch', 'dev', 'qa', 'doc']);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'skipped'])->default('pending');
            $table->json('input_data')->nullable(); // Data passed to this milestone
            $table->json('output_data')->nullable(); // Data produced by this milestone
            $table->string('git_branch')->nullable();
            $table->string('git_commit')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['task_id', 'sequence']);
            $table->index(['status', 'agent_type']);
            $table->unique(['task_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};