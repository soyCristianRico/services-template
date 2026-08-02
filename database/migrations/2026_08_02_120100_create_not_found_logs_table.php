<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_found_logs', function (Blueprint $table): void {
            $table->id();

            // One row per address, not per visit. A dead link from a popular page
            // gets hit thousands of times and would bury everything else.
            $table->string('path', 500)->unique();

            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            // Where the visit came from. It is the difference between "someone
            // typed it wrong" and "we have a broken link on the home page".
            $table->string('last_referrer', 500)->nullable();

            // Set when a redirect is created from this row. Kept instead of deleted
            // so the same address does not reappear as pending the next time a
            // cached page still points at it.
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['resolved_at', 'hits']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
    }
};
