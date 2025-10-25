<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Priority;

describe('Priority', function (): void {
    test('compares higher priority correctly', function (): void {
        $high = new Priority(100);
        $low = new Priority(1);

        expect($high->isHigherThan($low))->toBeTrue();
        expect($low->isHigherThan($high))->toBeFalse();
    });

    test('compares lower priority correctly', function (): void {
        $high = new Priority(100);
        $low = new Priority(1);

        expect($low->isLowerThan($high))->toBeTrue();
        expect($high->isLowerThan($low))->toBeFalse();
    });

    test('equal priorities are neither higher nor lower', function (): void {
        $p1 = new Priority(50);
        $p2 = new Priority(50);

        expect($p1->isHigherThan($p2))->toBeFalse();
        expect($p1->isLowerThan($p2))->toBeFalse();
    });
});
