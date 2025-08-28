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
        Schema::table('racks', function (Blueprint $table) {
            $table->longText('how_to_article')->nullable()->after('description');
            $table->timestamp('how_to_updated_at')->nullable()->after('how_to_article');
            
            // Add index for filtering racks with how-to articles
            $table->index(['how_to_article'], 'racks_how_to_article_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropIndex('racks_how_to_article_index');
            $table->dropColumn(['how_to_article', 'how_to_updated_at']);
        });
    }
};