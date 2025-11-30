<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\PrimaryKeyType;

describe('PrimaryKeyType', function (): void {
    test('has autoincrement case', function (): void {
        expect(PrimaryKeyType::AutoIncrement)->toBeInstanceOf(PrimaryKeyType::class)
            ->and(PrimaryKeyType::AutoIncrement->value)->toBe('autoincrement');
    });

    test('has uuid case', function (): void {
        expect(PrimaryKeyType::UUID)->toBeInstanceOf(PrimaryKeyType::class)
            ->and(PrimaryKeyType::UUID->value)->toBe('uuid');
    });

    test('has ulid case', function (): void {
        expect(PrimaryKeyType::ULID)->toBeInstanceOf(PrimaryKeyType::class)
            ->and(PrimaryKeyType::ULID->value)->toBe('ulid');
    });

    test('all cases are different', function (): void {
        expect(PrimaryKeyType::AutoIncrement)
            ->not->toBe(PrimaryKeyType::UUID)
            ->not->toBe(PrimaryKeyType::ULID);
    });

    test('can create from string value', function (): void {
        expect(PrimaryKeyType::from('autoincrement'))->toBe(PrimaryKeyType::AutoIncrement)
            ->and(PrimaryKeyType::from('uuid'))->toBe(PrimaryKeyType::UUID)
            ->and(PrimaryKeyType::from('ulid'))->toBe(PrimaryKeyType::ULID);
    });

    test('throws ValueError for invalid string', function (): void {
        PrimaryKeyType::from('invalid');
    })->throws(ValueError::class);

    test('values() returns all valid string values', function (): void {
        $values = PrimaryKeyType::values();

        expect($values)->toBeArray()
            ->toHaveCount(3)
            ->toContain('autoincrement')
            ->toContain('uuid')
            ->toContain('ulid');
    });
});
