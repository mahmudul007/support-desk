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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->string('external_ref', 100)->nullable()->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('open');

            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            // SLA tracking
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('sla_warning_at')->nullable();
            $table->timestamp('approaching_notified_at')->nullable();
            $table->timestamp('breached_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->unsignedSmallInteger('escalation_count')->default(0);

            // Lifecycle
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Concurrency optimistic versioning
            $table->unsignedInteger('version')->default(0);

            $table->softDeletes();
            $table->timestamps();

            // Composite indexes for fast queries
            $table->index(['status', 'priority']);
            $table->index(['assigned_agent_id', 'status']);
            $table->index(['created_by', 'status']);
            $table->index(['status', 'sla_due_at']);
            $table->index(['status', 'sla_warning_at']);
            $table->index('category_id');
            $table->index('created_at');

            // Fulltext search on subject and description (supported natively on MySQL)
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['subject', 'description']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
