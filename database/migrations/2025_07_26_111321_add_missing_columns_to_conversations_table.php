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
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('type');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('is_group');
            $table->json('metadata')->nullable()->after('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['is_group', 'created_by', 'metadata']);
        });
    }
};
