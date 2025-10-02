<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->default(DB::raw('uuid_generate_uuid_v4()'));
            $table->foreignId('sprint_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', ['bug', 'feature', 'upgrade', 'maintenance']);
            $table->string('title');
            $table->text('description');
            $table->json('content'); // Original task content/requirements
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->integer('priority')->default(3); // 1=highest, 5=lowest
            $table->string('assigned_to')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'priority']);
            $table->index(['type', 'status']);
            $table->index(['sprint_id', 'status']);
            $table->index('locked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};