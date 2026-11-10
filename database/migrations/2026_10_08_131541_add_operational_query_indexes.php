<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['is_active', 'category_id', 'barangay_id'], 'contacts_audience_index');
        });
        Schema::table('broadcasts', fn (Blueprint $table) => $table->index('created_at', 'broadcasts_activity_index'));
        Schema::table('sms_recipients', fn (Blueprint $table) => $table->index(['status', 'id'], 'sms_result_history_index'));
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['action', 'id'], 'audit_action_history_index');
            $table->index(['subject_type', 'id'], 'audit_subject_history_index');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', fn (Blueprint $table) => $table->dropIndex('contacts_audience_index'));
        Schema::table('broadcasts', fn (Blueprint $table) => $table->dropIndex('broadcasts_activity_index'));
        Schema::table('sms_recipients', fn (Blueprint $table) => $table->dropIndex('sms_result_history_index'));
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_action_history_index');
            $table->dropIndex('audit_subject_history_index');
        });
    }
};
