<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();

            // Normalised at save time: leading slash, no trailing slash, no host,
            // lower case. Without that the same address written four different ways
            // makes four rows and only one of them ever fires.
            $table->string('source', 500);

            // Null only for 410, which answers on the spot instead of sending
            // anywhere. Long because it can hold an absolute URL to another domain.
            $table->string('destination', 500)->nullable();

            $table->string('match_type', 20)->default('exact');
            $table->unsignedSmallInteger('status_code')->default(301);

            $table->boolean('is_active')->default(true);

            // Carries `?utm_source=…` from the old address to the new one. Campaign
            // links keep working and attribution survives the migration.
            $table->boolean('preserve_query')->default(true);

            // Why this redirect exists. A year later nobody remembers, and that is
            // when someone deletes the row that holds up half the old positioning.
            $table->text('notes')->nullable();

            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();

            $table->timestamps();

            // Two rows with the same source would make the winner depend on
            // insertion order, which is exactly the bug nobody can reproduce.
            $table->unique('source');
            $table->index(['is_active', 'match_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
