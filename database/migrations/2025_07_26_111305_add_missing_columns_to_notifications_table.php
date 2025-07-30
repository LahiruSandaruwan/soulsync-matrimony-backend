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
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('action_text')->nullable()->after('action_url');
            $table->string('source_type')->nullable()->after('action_text');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('batch_id')->nullable()->after('expires_at');
            $table->json('metadata')->nullable()->after('batch_id');
            $table->timestamp('sent_at')->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['action_text', 'source_type', 'source_id', 'batch_id', 'metadata', 'sent_at']);
        });
    }
};
