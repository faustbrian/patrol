<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Patrol\Laravel\Support\DatabaseConfiguration;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_delegations', function (Blueprint $table): void {
            DatabaseConfiguration::addPrimaryKey($table);
            DatabaseConfiguration::addForeignKey($table, 'delegator_id');
            DatabaseConfiguration::addForeignKey($table, 'delegate_id');

            $table->json('scope');
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_transitive');
            $table->string('state')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('revoked_at')->nullable();

            DatabaseConfiguration::addForeignKey($table, 'revoked_by', nullable: true);

            $table->timestamps();
            DatabaseConfiguration::addSoftDeletes($table);

            // Composite index for efficient active delegation queries
            $table->index(['delegate_id', 'state', 'expires_at'], 'delegations_active_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_delegations');
    }
};
