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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable();
            $table->unsignedBigInteger('status_changed_by')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('profile_status_changed_at')->nullable();
            $table->unsignedBigInteger('profile_status_changed_by')->nullable();
            $table->text('profile_admin_notes')->nullable();
            
            $table->foreign('status_changed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('profile_status_changed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['status_changed_by']);
            $table->dropForeign(['profile_status_changed_by']);
            $table->dropColumn([
                'status_changed_at',
                'status_changed_by',
                'admin_notes',
                'profile_status_changed_at',
                'profile_status_changed_by',
                'profile_admin_notes'
            ]);
        });
    }
};
