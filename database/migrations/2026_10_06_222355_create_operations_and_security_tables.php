<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('payload_hash', 64);
            $table->string('title', 150);
            $table->string('severity', 20)->default('info');
            $table->text('message');
            $table->string('target', 255);
            $table->unsignedInteger('recipient_count');
            $table->boolean('is_test');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('sms_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->text('phone_number');
            $table->string('status', 20)->default('queued');
            $table->string('error', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['broadcast_id', 'contact_id']);
            $table->index(['broadcast_id', 'status']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('hotlines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone_number', 30);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
        Schema::dropIfExists('hotlines');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sms_recipients');
        Schema::dropIfExists('broadcasts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'is_active']));
    }
};
