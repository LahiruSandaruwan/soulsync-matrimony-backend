<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('smoking_habits')->nullable()->after('smoking');
            $table->string('drinking_habits')->nullable()->after('drinking');
            $table->string('dietary_preferences')->nullable()->after('diet');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['smoking_habits', 'drinking_habits', 'dietary_preferences']);
        });
    }
}; 