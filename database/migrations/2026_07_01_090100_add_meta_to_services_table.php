<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every other content table (categories, pages, blog_posts, locations) already
     * carries its own SEO meta. Services were the exception, which only held while
     * they were catalog items rendered inside a landing. As soon as a project gives
     * a service its own page, it needs its own title and description.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('description');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
