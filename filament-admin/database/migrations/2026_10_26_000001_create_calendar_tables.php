<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking calendar: admin blocks, imported Google / .ics events and the booking rules.
 * Read by the public website too (unas/calendar.php), so the column names are shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_blocks')) {
            Schema::create('calendar_blocks', function (Blueprint $table) {
                $table->id();
                $table->string('source', 16);               // manual | google | upload
                $table->dateTime('starts_at');              // Europe/Budapest wall-clock time
                $table->dateTime('ends_at');
                $table->boolean('all_day')->default(false);
                $table->boolean('busy')->default(true);     // false: "free" (transparent) event
                $table->string('title', 190)->default('');
                $table->string('uid', 190)->default('');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->index(['starts_at', 'ends_at'], 'calendar_blocks_range');
                $table->index('source', 'calendar_blocks_source');
            });
        }

        if (! Schema::hasTable('calendar_settings')) {
            Schema::create('calendar_settings', function (Blueprint $table) {
                $table->string('setting_key', 40)->primary();
                $table->mediumText('setting_value');
                $table->dateTime('updated_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_settings');
        Schema::dropIfExists('calendar_blocks');
    }
};
