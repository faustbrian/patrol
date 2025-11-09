<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\FileMode;

describe('FileMode', function (): void {
    describe('Happy Paths', function (): void {
        test('has single case with correct value', function (): void {
            // Arrange & Act
            $mode = FileMode::Single;

            // Assert
            expect($mode->value)->toBe('single');
        });

        test('has multiple case with correct value', function (): void {
            // Arrange & Act
            $mode = FileMode::Multiple;

            // Assert
            expect($mode->value)->toBe('multiple');
        });

        test('can be created from string value', function (): void {
            // Arrange & Act
            $singleMode = FileMode::from('single');
            $multipleMode = FileMode::from('multiple');

            // Assert
            expect($singleMode)->toBe(FileMode::Single);
            expect($multipleMode)->toBe(FileMode::Multiple);
        });
    });

    describe('Edge Cases', function (): void {
        test('cases method returns both file modes', function (): void {
            // Arrange & Act
            $cases = FileMode::cases();

            // Assert
            expect($cases)->toHaveCount(2);
            expect($cases)->toContain(FileMode::Single);
            expect($cases)->toContain(FileMode::Multiple);
        });

        test('comparison operators work correctly', function (): void {
            // Arrange
            $mode1 = FileMode::Single;
            $mode2 = FileMode::Single;
            $mode3 = FileMode::Multiple;

            // Act & Assert
            expect($mode1 === $mode2)->toBeTrue();
            expect($mode1 === $mode3)->toBeFalse();
        });
    });
});
