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
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index(); // e.g., 'features', 'payments', 'general'
            $table->string('key', 100)->index(); // Setting key
            $table->text('value')->nullable(); // Setting value (JSON or string)
            $table->string('type', 20)->default('string'); // 'string', 'boolean', 'integer', 'json', 'array'
            $table->text('description')->nullable(); // Description of the setting
            $table->boolean('is_public')->default(false); // Whether this setting is exposed via public config
            $table->boolean('is_encrypted')->default(false); // Whether the value is encrypted
            $table->boolean('is_active')->default(true); // Whether the setting is active
            $table->integer('sort_order')->default(0); // For ordering in admin panel
            $table->json('validation_rules')->nullable(); // Validation rules for the setting
            $table->json('options')->nullable(); // Available options for select/radio inputs
            $table->string('group', 50)->nullable(); // Sub-grouping within category
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->unique(['category', 'key']); // Prevent duplicate settings
            $table->index(['category', 'is_public']);
            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};