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
        Schema::create('patrol_policies', function (Blueprint $table): void {
            DatabaseConfiguration::addPrimaryKey($table);

            $table->string('subject');
            $table->string('resource')->nullable();
            $table->string('action');
            $table->string('effect');
            $table->integer('priority');
            $table->string('domain')->nullable();
            $table->timestamps();
            DatabaseConfiguration::addSoftDeletes($table);

            $table->index(['subject', 'resource', 'action']);
            $table->index('domain');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_policies');
    }
};
