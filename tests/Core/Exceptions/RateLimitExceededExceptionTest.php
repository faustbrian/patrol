<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Exceptions\RateLimitExceededException;

describe('RateLimitExceededException', function (): void {
    test('creates exception with message', function (): void {
        $exception = RateLimitExceededException::create(30);

        expect($exception)->toBeInstanceOf(RateLimitExceededException::class);
        expect($exception->getMessage())->toBe('Too many authorization attempts. Please try again in 30 seconds.');
    });

    test('creates exception with different wait time', function (): void {
        $exception = RateLimitExceededException::create(120);

        expect($exception->getMessage())->toContain('120 seconds');
    });
});
