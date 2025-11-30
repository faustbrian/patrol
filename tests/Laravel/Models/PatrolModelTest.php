<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Patrol\Laravel\Models\PatrolModel;

describe('PatrolModel', function (): void {
    beforeEach(function (): void {
        // Reset configuration before each test
        Config::set('patrol.database.primary_key_type', 'uuid');
    });

    describe('constructor', function (): void {
        test('configures primary key type on instantiation', function (): void {
            Config::set('patrol.database.primary_key_type', 'uuid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            expect($model->getIncrementing())->toBeFalse()
                ->and($model->getKeyType())->toBe('string');
        });
    });

    describe('newUniqueId()', function (): void {
        test('generates UUID when configured', function (): void {
            Config::set('patrol.database.primary_key_type', 'uuid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $uniqueId = $model->newUniqueId();

            expect($uniqueId)->toBeString()
                ->and(Str::isUuid($uniqueId))->toBeTrue();
        });

        test('generates ULID when configured', function (): void {
            Config::set('patrol.database.primary_key_type', 'ulid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $uniqueId = $model->newUniqueId();

            expect($uniqueId)->toBeString()
                ->and(Str::isUlid($uniqueId))->toBeTrue();
        });

        test('throws RuntimeException for AutoIncrement type', function (): void {
            Config::set('patrol.database.primary_key_type', 'autoincrement');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $model->newUniqueId();
        })->throws(RuntimeException::class, 'UniqueId generation not supported for AutoIncrement');

        test('generated UUIDs are unique', function (): void {
            Config::set('patrol.database.primary_key_type', 'uuid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $id1 = $model->newUniqueId();
            $id2 = $model->newUniqueId();

            expect($id1)->not->toBe($id2);
        });

        test('generated ULIDs are unique', function (): void {
            Config::set('patrol.database.primary_key_type', 'ulid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $id1 = $model->newUniqueId();
            $id2 = $model->newUniqueId();

            expect($id1)->not->toBe($id2);
        });
    });

    describe('uniqueIds()', function (): void {
        test('returns primary key column for UUID type', function (): void {
            Config::set('patrol.database.primary_key_type', 'uuid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $uniqueIds = $model->uniqueIds();

            expect($uniqueIds)->toBe(['id']);
        });

        test('returns primary key column for ULID type', function (): void {
            Config::set('patrol.database.primary_key_type', 'ulid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $uniqueIds = $model->uniqueIds();

            expect($uniqueIds)->toBe(['id']);
        });

        test('returns empty array for AutoIncrement type', function (): void {
            Config::set('patrol.database.primary_key_type', 'autoincrement');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $uniqueIds = $model->uniqueIds();

            expect($uniqueIds)->toBe([]);
        });

        test('uses custom key name when defined', function (): void {
            Config::set('patrol.database.primary_key_type', 'uuid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';

                protected $primaryKey = 'custom_id';
            };

            $uniqueIds = $model->uniqueIds();

            expect($uniqueIds)->toBe(['custom_id']);
        });
    });

    describe('configurePrimaryKey()', function (): void {
        test('configures for UUID type', function (): void {
            Config::set('patrol.database.primary_key_type', 'uuid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            expect($model->getIncrementing())->toBeFalse()
                ->and($model->getKeyType())->toBe('string');
        });

        test('configures for ULID type', function (): void {
            Config::set('patrol.database.primary_key_type', 'ulid');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            expect($model->getIncrementing())->toBeFalse()
                ->and($model->getKeyType())->toBe('string');
        });

        test('configures for AutoIncrement type', function (): void {
            Config::set('patrol.database.primary_key_type', 'autoincrement');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            expect($model->getIncrementing())->toBeTrue()
                ->and($model->getKeyType())->toBe('int');
        });
    });

    describe('traits and features', function (): void {
        test('has SoftDeletes trait', function (): void {
            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $traits = class_uses_recursive($model);

            expect($traits)->toHaveKey(SoftDeletes::class);
        });

        test('has HasFactory trait', function (): void {
            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            $traits = class_uses_recursive($model);

            expect($traits)->toHaveKey(HasFactory::class);
        });
    });

    describe('integration scenarios', function (): void {
        test('different models can use different primary key types', function (): void {
            // First model with UUID
            Config::set('patrol.database.primary_key_type', 'uuid');
            $uuidModel = new class() extends PatrolModel
            {
                protected $table = 'uuid_models';
            };

            // Change config to ULID
            Config::set('patrol.database.primary_key_type', 'ulid');
            $ulidModel = new class() extends PatrolModel
            {
                protected $table = 'ulid_models';
            };

            // Verify each model has the correct configuration
            expect($uuidModel->getKeyType())->toBe('string')
                ->and($ulidModel->getKeyType())->toBe('string');
        });

        test('model respects configuration at instantiation time', function (): void {
            Config::set('patrol.database.primary_key_type', 'autoincrement');

            $model = new class() extends PatrolModel
            {
                protected $table = 'test_models';
            };

            // Changing config after instantiation should not affect existing model
            Config::set('patrol.database.primary_key_type', 'uuid');

            expect($model->getIncrementing())->toBeTrue()
                ->and($model->getKeyType())->toBe('int');
        });
    });
});
