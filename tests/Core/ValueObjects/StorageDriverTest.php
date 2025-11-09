<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\StorageDriver;

describe('StorageDriver', function (): void {
    describe('Happy Paths', function (): void {
        test('has eloquent case with correct value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::Eloquent;

            // Assert
            expect($driver->value)->toBe('eloquent');
        });

        test('has json case with correct value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::Json;

            // Assert
            expect($driver->value)->toBe('json');
        });

        test('has yaml case with correct value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::Yaml;

            // Assert
            expect($driver->value)->toBe('yaml');
        });

        test('has xml case with correct value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::Xml;

            // Assert
            expect($driver->value)->toBe('xml');
        });

        test('has toml case with correct value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::Toml;

            // Assert
            expect($driver->value)->toBe('toml');
        });

        test('has csv case with correct value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::Csv;

            // Assert
            expect($driver->value)->toBe('csv');
        });

        test('has ini case with correct value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::Ini;

            // Assert
            expect($driver->value)->toBe('ini');
        });

        test('has json5 case with correct value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::Json5;

            // Assert
            expect($driver->value)->toBe('json5');
        });

        test('has serialized case with correct value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::Serialized;

            // Assert
            expect($driver->value)->toBe('serialized');
        });

        test('can be created from string value', function (): void {
            // Arrange & Act
            $driver = StorageDriver::from('json');

            // Assert
            expect($driver)->toBe(StorageDriver::Json);
        });

        test('all cases have unique values', function (): void {
            // Arrange
            $cases = StorageDriver::cases();
            $values = array_map(fn (StorageDriver $case): string => $case->value, $cases);

            // Act & Assert
            expect($values)->toHaveCount(count(array_unique($values)));
        });
    });

    describe('Edge Cases', function (): void {
        test('cases method returns all nine driver types', function (): void {
            // Arrange & Act
            $cases = StorageDriver::cases();

            // Assert
            expect($cases)->toHaveCount(9);
            expect($cases)->toContain(StorageDriver::Eloquent);
            expect($cases)->toContain(StorageDriver::Json);
            expect($cases)->toContain(StorageDriver::Yaml);
            expect($cases)->toContain(StorageDriver::Xml);
            expect($cases)->toContain(StorageDriver::Toml);
            expect($cases)->toContain(StorageDriver::Csv);
            expect($cases)->toContain(StorageDriver::Ini);
            expect($cases)->toContain(StorageDriver::Json5);
            expect($cases)->toContain(StorageDriver::Serialized);
        });

        test('comparison operators work correctly', function (): void {
            // Arrange
            $driver1 = StorageDriver::Json;
            $driver2 = StorageDriver::Json;
            $driver3 = StorageDriver::Yaml;

            // Act & Assert
            expect($driver1 === $driver2)->toBeTrue();
            expect($driver1 === $driver3)->toBeFalse();
        });
    });
});
