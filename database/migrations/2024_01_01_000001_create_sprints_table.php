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
        DB::statement('CREATE SCHEMA IF NOT EXISTS admin;');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

        // 2) Create wrapper function in admin schema (idempotent-ish)
        DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION uuid_generate_uuid_v4()
                RETURNS uuid
                LANGUAGE sql
                STABLE
                AS $$
                    SELECT uuid_generate_v4();
                $$;
                SQL);
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['planning', 'active', 'completed', 'cancelled'])->default('planning');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprints');
    }
};