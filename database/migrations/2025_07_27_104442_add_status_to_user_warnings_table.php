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
        Schema::table('user_warnings', function (Blueprint $table) {
            $table->string('status')->default('active')->after('issued_at');
            $table->string('appeal_status')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_warnings', function (Blueprint $table) {
            $table->dropColumn(['status', 'appeal_status']);
        });
    }
};
