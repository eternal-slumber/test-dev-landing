<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('ai_summary', 300)->nullable()->after('ai_category');
            $table->text('ai_suggested_reply')->nullable()->after('ai_summary');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['ai_summary', 'ai_suggested_reply']);
        });
    }
};
