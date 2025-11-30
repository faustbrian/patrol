<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Effect;

describe('Effect', function (): void {
    test('has allow case', function (): void {
        expect(Effect::Allow)->toBeInstanceOf(Effect::class);
    });

    test('has deny case', function (): void {
        expect(Effect::Deny)->toBeInstanceOf(Effect::class);
    });

    test('allow and deny are different', function (): void {
        expect(Effect::Allow)->not->toBe(Effect::Deny);
    });
});
