<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Exceptions\InvalidPrimaryKeyTypeException;

describe('InvalidPrimaryKeyTypeException', function (): void {
    test('can be created with invalid type and supported types', function (): void {
        $exception = InvalidPrimaryKeyTypeException::create('invalid', ['autoincrement', 'uuid', 'ulid']);

        expect($exception)->toBeInstanceOf(InvalidPrimaryKeyTypeException::class)
            ->and($exception)->toBeInstanceOf(InvalidArgumentException::class)
            ->and($exception->getMessage())->toContain('invalid')
            ->and($exception->getMessage())->toContain('autoincrement, uuid, ulid');
    });

    test('message includes both invalid value and supported types', function (): void {
        $exception = InvalidPrimaryKeyTypeException::create('wrong', ['a', 'b', 'c']);

        expect($exception->getMessage())
            ->toContain('wrong')
            ->toContain('a, b, c')
            ->toContain('Invalid primary key type')
            ->toContain('configured for Patrol');
    });

    test('is throwable', function (): void {
        throw InvalidPrimaryKeyTypeException::create('bad', ['good']);
    })->throws(InvalidPrimaryKeyTypeException::class, 'Invalid primary key type "bad" configured for Patrol. Supported types: good');
});
