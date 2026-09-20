<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One subscription: a meter, a phone line, a connection.
 *
 * This is what lets a household hold several phone lines, or two flats'
 * electricity, without retyping the provider every month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_accounts', function (Blueprint $table): void {
            $table->id();

            // A plain string, not an enum column: App\Enums\UtilityType is the
            // only list, so adding a type never needs a migration.
            $table->string('type')->index();
            $table->string('provider');
            $table->string('subscriber_no')->nullable();
            $table->string('label');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_accounts');
    }
};
