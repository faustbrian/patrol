<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Patrol\Core\ValueObjects\PrimaryKeyType;
use ValueError;

use function assert;
use function is_string;

/**
 * Centralized database configuration helper for Patrol migrations.
 *
 * Provides consistent access to database schema configuration options including
 * primary key type and soft delete behavior. Encapsulates configuration retrieval
 * and type safety assertions to keep migrations clean and DRY.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DatabaseConfiguration
{
    /**
     * Get the configured primary key type.
     *
     * Retrieves the primary key type from configuration and casts it to the
     * PrimaryKeyType enum for type safety. Defaults to 'uuid' if not configured.
     * Supports autoincrement, uuid, and ulid types.
     *
     * @throws ValueError When configuration contains invalid primary key type value
     *
     * @return PrimaryKeyType The configured primary key type (autoincrement, uuid, or ulid)
     */
    public static function primaryKeyType(): PrimaryKeyType
    {
        $configValue = Config::get('patrol.database.primary_key_type', 'uuid');
        assert(is_string($configValue));

        return PrimaryKeyType::from($configValue);
    }

    /**
     * Check if soft deletes are enabled.
     *
     * Reads the soft deletes configuration option from the Patrol config file.
     * Defaults to false if not configured.
     *
     * @return bool True if soft deletes should be applied to Patrol database tables, false otherwise
     */
    public static function softDeletesEnabled(): bool
    {
        return Config::get('patrol.database.soft_deletes', false) === true;
    }

    /**
     * Add primary key column to table based on configuration.
     *
     * Creates the appropriate primary key column type (autoincrement, uuid, or ulid)
     * based on the configured primary key type. Uses Laravel Blueprint methods to
     * ensure proper column definition and primary key constraint.
     *
     * @param Blueprint $table The table blueprint to add the primary key column to
     */
    public static function addPrimaryKey(Blueprint $table): void
    {
        match (self::primaryKeyType()) {
            PrimaryKeyType::AutoIncrement => $table->id(),
            PrimaryKeyType::UUID => $table->uuid('id')->primary(),
            PrimaryKeyType::ULID => $table->ulid('id')->primary(),
        };
    }

    /**
     * Add foreign key column to table based on configuration.
     *
     * Creates the appropriate foreign key column type based on the configured
     * primary key type to ensure foreign keys match their referenced columns.
     * Automatically adds an index for query performance and optionally makes
     * the column nullable.
     *
     * @param Blueprint $table      The table blueprint to add the foreign key column to
     * @param string    $columnName The name of the foreign key column to create
     * @param bool      $nullable   Whether the foreign key column should allow NULL values.
     *                              Defaults to false for required relationships.
     */
    public static function addForeignKey(Blueprint $table, string $columnName, bool $nullable = false): void
    {
        $column = match (self::primaryKeyType()) {
            PrimaryKeyType::AutoIncrement => $table->unsignedBigInteger($columnName),
            PrimaryKeyType::UUID => $table->foreignUuid($columnName),
            PrimaryKeyType::ULID => $table->foreignUlid($columnName),
        };

        if ($nullable) {
            $column->nullable();
        }

        $column->index();
    }

    /**
     * Add soft deletes column if enabled in configuration.
     *
     * Conditionally adds Laravel's soft deletes timestamp column (deleted_at)
     * to the table if soft deletes are enabled in the Patrol configuration.
     * Does nothing if soft deletes are disabled.
     *
     * @param Blueprint $table The table blueprint to add soft deletes column to
     */
    public static function addSoftDeletes(Blueprint $table): void
    {
        if (!self::softDeletesEnabled()) {
            return;
        }

        $table->softDeletes();
    }
}
