<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the enum to include 'voice' instead of 'audio'
        DB::statement("ALTER TABLE messages MODIFY COLUMN type ENUM('text', 'image', 'voice', 'video', 'file', 'system', 'gift') DEFAULT 'text'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to 'audio'
        DB::statement("ALTER TABLE messages MODIFY COLUMN type ENUM('text', 'image', 'audio', 'video', 'file', 'system', 'gift') DEFAULT 'text'");
    }
};
