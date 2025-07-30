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
            $table->string('warning_type')->nullable()->after('template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_warnings', function (Blueprint $table) {
            $table->dropColumn('warning_type');
        });
    }
};
