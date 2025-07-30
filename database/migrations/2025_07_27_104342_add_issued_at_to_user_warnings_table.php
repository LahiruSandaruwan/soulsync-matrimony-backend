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
            $table->timestamp('issued_at')->nullable()->after('evidence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_warnings', function (Blueprint $table) {
            $table->dropColumn('issued_at');
        });
    }
};
