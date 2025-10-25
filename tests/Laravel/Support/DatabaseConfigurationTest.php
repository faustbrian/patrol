<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Patrol\Core\ValueObjects\PrimaryKeyType;
use Patrol\Laravel\Support\DatabaseConfiguration;

describe('DatabaseConfiguration', function (): void {
    beforeEach(function (): void {
        Config::set('patrol.database.primary_key_type', 'uuid');
        Config::set('patrol.database.soft_deletes', false);
    });

    describe('primaryKeyType()', function (): void {
        test('returns uuid by default', function (): void {
            expect(DatabaseConfiguration::primaryKeyType())->toBe(PrimaryKeyType::Uuid);
        });

        test('returns configured type', function (): void {
            Config::set('patrol.database.primary_key_type', 'ulid');

            expect(DatabaseConfiguration::primaryKeyType())->toBe(PrimaryKeyType::Ulid);
        });

        test('returns autoincrement when configured', function (): void {
            Config::set('patrol.database.primary_key_type', 'autoincrement');

            expect(DatabaseConfiguration::primaryKeyType())->toBe(PrimaryKeyType::AutoIncrement);
        });

        test('throws ValueError for invalid type', function (): void {
            Config::set('patrol.database.primary_key_type', 'invalid');

            DatabaseConfiguration::primaryKeyType();
        })->throws(ValueError::class);
    });

    describe('softDeletesEnabled()', function (): void {
        test('returns false by default', function (): void {
            expect(DatabaseConfiguration::softDeletesEnabled())->toBeFalse();
        });

        test('returns true when enabled', function (): void {
            Config::set('patrol.database.soft_deletes', true);

            expect(DatabaseConfiguration::softDeletesEnabled())->toBeTrue();
        });

        test('returns false for non-boolean values', function (): void {
            Config::set('patrol.database.soft_deletes', 'yes');

            expect(DatabaseConfiguration::softDeletesEnabled())->toBeFalse();
        });
    });

    describe('addPrimaryKey()', function (): void {
        test('adds id column for autoincrement', function (): void {
            Config::set('patrol.database.primary_key_type', 'autoincrement');

            $blueprint = Mockery::mock(Blueprint::class);
            $blueprint->shouldReceive('id')->once()->andReturnSelf();

            DatabaseConfiguration::addPrimaryKey($blueprint);
        });

        test('adds uuid column for uuid', function (): void {
            Config::set('patrol.database.primary_key_type', 'uuid');

            $blueprint = Mockery::mock(Blueprint::class);
            $column = Mockery::mock();
            $column->shouldReceive('primary')->once()->andReturnSelf();
            $blueprint->shouldReceive('uuid')->once()->with('id')->andReturn($column);

            DatabaseConfiguration::addPrimaryKey($blueprint);
        });

        test('adds ulid column for ulid', function (): void {
            Config::set('patrol.database.primary_key_type', 'ulid');

            $blueprint = Mockery::mock(Blueprint::class);
            $column = Mockery::mock();
            $column->shouldReceive('primary')->once()->andReturnSelf();
            $blueprint->shouldReceive('ulid')->once()->with('id')->andReturn($column);

            DatabaseConfiguration::addPrimaryKey($blueprint);
        });
    });

    describe('addForeignKey()', function (): void {
        test('adds indexed foreign key for uuid', function (): void {
            Config::set('patrol.database.primary_key_type', 'uuid');

            $blueprint = Mockery::mock(Blueprint::class);
            $column = Mockery::mock();
            $column->shouldReceive('index')->once()->andReturnSelf();
            $blueprint->shouldReceive('foreignUuid')->once()->with('user_id')->andReturn($column);

            DatabaseConfiguration::addForeignKey($blueprint, 'user_id');
        });

        test('adds nullable foreign key when specified', function (): void {
            Config::set('patrol.database.primary_key_type', 'uuid');

            $blueprint = Mockery::mock(Blueprint::class);
            $column = Mockery::mock();
            $column->shouldReceive('nullable')->once()->andReturnSelf();
            $column->shouldReceive('index')->once()->andReturnSelf();
            $blueprint->shouldReceive('foreignUuid')->once()->with('parent_id')->andReturn($column);

            DatabaseConfiguration::addForeignKey($blueprint, 'parent_id', nullable: true);
        });

        test('adds unsignedBigInteger for autoincrement', function (): void {
            Config::set('patrol.database.primary_key_type', 'autoincrement');

            $blueprint = Mockery::mock(Blueprint::class);
            $column = Mockery::mock();
            $column->shouldReceive('index')->once()->andReturnSelf();
            $blueprint->shouldReceive('unsignedBigInteger')->once()->with('user_id')->andReturn($column);

            DatabaseConfiguration::addForeignKey($blueprint, 'user_id');
        });

        test('adds foreignUlid for ulid', function (): void {
            Config::set('patrol.database.primary_key_type', 'ulid');

            $blueprint = Mockery::mock(Blueprint::class);
            $column = Mockery::mock();
            $column->shouldReceive('index')->once()->andReturnSelf();
            $blueprint->shouldReceive('foreignUlid')->once()->with('user_id')->andReturn($column);

            DatabaseConfiguration::addForeignKey($blueprint, 'user_id');
        });
    });

    describe('addSoftDeletes()', function (): void {
        test('adds soft deletes when enabled', function (): void {
            Config::set('patrol.database.soft_deletes', true);

            $blueprint = Mockery::mock(Blueprint::class);
            $blueprint->shouldReceive('softDeletes')->once()->andReturnSelf();

            DatabaseConfiguration::addSoftDeletes($blueprint);
        });

        test('does not add soft deletes when disabled', function (): void {
            Config::set('patrol.database.soft_deletes', false);

            $blueprint = Mockery::mock(Blueprint::class);
            $blueprint->shouldNotReceive('softDeletes');

            DatabaseConfiguration::addSoftDeletes($blueprint);
        });
    });
});
