<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('barangay_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('contacts')->whereNull('barangay_id')->exists()) {
            throw new RuntimeException('Assign barangays to contacts marked Not applicable before rolling back.');
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('barangay_id')->nullable(false)->change();
        });
    }
};
