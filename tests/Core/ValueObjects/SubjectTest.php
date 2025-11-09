<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Subject;

describe('Subject', function (): void {
    test('creates subject with id', function (): void {
        $subject = new Subject('user-1');

        expect($subject->id)->toBe('user-1');
    });

    test('creates subject with attributes', function (): void {
        $subject = new Subject('user-1', ['role' => 'admin']);

        expect($subject->attributes)->toBe(['role' => 'admin']);
    });

    test('is immutable', function (): void {
        $subject = new Subject('user-1');

        expect(Subject::class)->toBeReadonly();
    });
});
