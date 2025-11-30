<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Override;
use Patrol\Core\ValueObjects\PrimaryKeyType;
use Patrol\Laravel\Support\DatabaseConfiguration;
use RuntimeException;

/**
 * Base Eloquent model for all Patrol models.
 *
 * Provides automatic configuration of primary key types (UUID, ULID, or auto-increment)
 * based on the patrol.database.primary_key_type configuration value. Extends Laravel's
 * base Model with soft deletes enabled by default.
 *
 * Primary key types:
 * - uuid: Generates UUID v4 identifiers using Laravel's HasUuids concern
 * - ulid: Generates ULID identifiers using Laravel's HasUlids concern
 * - autoincrement: Uses traditional auto-incrementing integer primary keys
 *
 * All child models inherit the configured primary key behavior without additional setup.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class PatrolModel extends Model
{
    /** @phpstan-ignore missingType.generics (generic type specified in child classes) */
    use HasFactory;
    use SoftDeletes;

    /**
     * Create a new Eloquent model instance.
     *
     * Configures the primary key type based on application configuration.
     *
     * @param array<string, mixed> $attributes Initial attribute values
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->configurePrimaryKey();
    }

    /**
     * Generate a new UUID or ULID for the model.
     *
     * Required by HasUuids and HasUlids traits for automatic ID generation.
     * Called when creating new models with UUID or ULID primary keys.
     *
     * @throws RuntimeException When called with AutoIncrement primary key type
     *
     * @return string Generated unique identifier
     */
    #[Override()]
    public function newUniqueId(): string
    {
        $pkType = DatabaseConfiguration::primaryKeyType();

        return match ($pkType) {
            PrimaryKeyType::UUID => (string) Str::uuid(),
            PrimaryKeyType::ULID => (string) Str::ulid(),
            default => throw new RuntimeException('UniqueId generation not supported for AutoIncrement'),
        };
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * Required by HasUuids and HasUlids traits to identify which columns
     * should be automatically populated with generated unique IDs.
     *
     * @return array<int, string> Column names that should receive unique IDs
     */
    #[Override()]
    public function uniqueIds(): array
    {
        $pkType = DatabaseConfiguration::primaryKeyType();

        return match ($pkType) {
            PrimaryKeyType::UUID, PrimaryKeyType::ULID => [$this->getKeyName()],
            default => [],
        };
    }

    /**
     * Configure the primary key behavior based on application settings.
     *
     * Sets $incrementing and $keyType properties to match the configured
     * primary key type from patrol.database.primary_key_type config.
     */
    protected function configurePrimaryKey(): void
    {
        $pkType = DatabaseConfiguration::primaryKeyType();

        match ($pkType) {
            PrimaryKeyType::UUID, PrimaryKeyType::ULID => [
                $this->incrementing = false,
                $this->keyType = 'string',
            ],
            PrimaryKeyType::AutoIncrement => [
                $this->incrementing = true,
                $this->keyType = 'int',
            ],
        };
    }
}
