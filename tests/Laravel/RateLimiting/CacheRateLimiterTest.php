<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Date;
use Patrol\Laravel\RateLimiting\CacheRateLimiter;

describe('CacheRateLimiter', function (): void {
    test('attempt succeeds when under limit', function (): void {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('test-key', 0)->andReturn(0);
        $cache->shouldReceive('put')->with('test-key', 1, 60)->once();
        $cache->shouldReceive('put')->with('test-key:timer', Mockery::type('int'), 60)->once();

        $limiter = new CacheRateLimiter($cache);
        $result = $limiter->attempt('test-key', 5, 60);

        expect($result)->toBeTrue();
    });

    test('attempt fails when limit exceeded', function (): void {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('test-key', 0)->andReturn(5);

        $limiter = new CacheRateLimiter($cache);
        $result = $limiter->attempt('test-key', 5, 60);

        expect($result)->toBeFalse();
    });

    test('tooManyAttempts returns false under limit', function (): void {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('test-key', 0)->andReturn(3);

        $limiter = new CacheRateLimiter($cache);

        expect($limiter->tooManyAttempts('test-key', 5))->toBeFalse();
    });

    test('tooManyAttempts returns true at limit', function (): void {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('test-key', 0)->andReturn(5);

        $limiter = new CacheRateLimiter($cache);

        expect($limiter->tooManyAttempts('test-key', 5))->toBeTrue();
    });

    test('hit increments counter', function (): void {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('test-key', 0)->andReturn(2);
        $cache->shouldReceive('put')->with('test-key', 3, 60)->once();

        $limiter = new CacheRateLimiter($cache);
        $attempts = $limiter->hit('test-key', 60);

        expect($attempts)->toBe(3);
    });

    test('hit sets timer on first attempt', function (): void {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('test-key', 0)->andReturn(0);
        $cache->shouldReceive('put')->with('test-key', 1, 60)->once();
        $cache->shouldReceive('put')->with('test-key:timer', Mockery::type('int'), 60)->once();

        $limiter = new CacheRateLimiter($cache);
        $limiter->hit('test-key', 60);
    });

    test('clear removes key and timer', function (): void {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('forget')->with('test-key')->once();
        $cache->shouldReceive('forget')->with('test-key:timer')->once();

        $limiter = new CacheRateLimiter($cache);
        $limiter->clear('test-key');
    });

    test('availableIn returns seconds until reset', function (): void {
        $futureTime = Date::now()->getTimestamp() + 30;
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('test-key:timer', Date::now()->getTimestamp())->andReturn($futureTime);

        $limiter = new CacheRateLimiter($cache);
        $seconds = $limiter->availableIn('test-key');

        expect($seconds)->toBeGreaterThanOrEqual(29);
        expect($seconds)->toBeLessThanOrEqual(30);
    });

    test('availableIn returns zero when timer expired', function (): void {
        $pastTime = Date::now()->subSeconds(10)->getTimestamp();
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('test-key:timer', Date::now()->getTimestamp())->andReturn($pastTime);

        $limiter = new CacheRateLimiter($cache);

        expect($limiter->availableIn('test-key'))->toBe(0);
    });
});
