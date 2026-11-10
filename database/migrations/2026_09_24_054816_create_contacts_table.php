<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            
            // Name Fields
            $table->string('first_name');
            $table->string('middle_initial', 5)->nullable(); // Short string for MI
            $table->string('last_name');
            
            // Employment / Role Fields (Nullable for ordinary citizens)
            $table->string('office')->nullable();    // e.g., "City Mayor's Office"
            $table->string('position')->nullable();  // e.g., "Barangay Captain", "Volunteer"
            
            // Contact Information
            $table->string('phone_number', 15)->unique(); // e.g., +639123456789
            
            // Relationships / Categorization
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete(); 
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};