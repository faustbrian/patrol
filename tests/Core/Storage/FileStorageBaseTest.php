<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Exceptions\StorageVersionNotFoundException;
use Patrol\Core\ValueObjects\FileMode;
use Tests\Fixtures\TestFileStorage;
use Tests\Helpers\FilesystemHelper;

describe('FileStorageBase', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths', function (): void {
        describe('resolveVersion', function (): void {
            test('returns null when versioning is disabled', function (): void {
                // Arrange
                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: false,
                );

                // Act
                $result = $storage->exposedResolveVersion('policies');

                // Assert
                expect($result)->toBeNull();
            });

            test('returns specific version when explicitly set and exists', function (): void {
                // Arrange
                $versionDir = $this->tempDir.'/policies/1.0.0';
                mkdir($versionDir, 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: '1.0.0',
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedResolveVersion('policies');

                // Assert
                expect($result)->toBe('1.0.0');
            });

            test('auto-detects latest version when version is null', function (): void {
                // Arrange
                mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);
                mkdir($this->tempDir.'/policies/1.2.3', 0o755, true);
                mkdir($this->tempDir.'/policies/1.1.5', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedResolveVersion('policies');

                // Assert
                expect($result)->toBe('1.2.3');
            });
        });

        describe('detectLatestVersion', function (): void {
            test('returns highest semantic version from directory', function (): void {
                // Arrange
                $typeDir = $this->tempDir.'/policies';
                mkdir($typeDir.'/1.0.0', 0o755, true);
                mkdir($typeDir.'/2.1.0', 0o755, true);
                mkdir($typeDir.'/1.5.3', 0o755, true);
                mkdir($typeDir.'/2.0.0', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                );

                // Act
                $result = $storage->exposedDetectLatestVersion($typeDir);

                // Assert
                expect($result)->toBe('2.1.0');
            });

            test('handles patch version sorting correctly', function (): void {
                // Arrange
                $typeDir = $this->tempDir.'/delegations';
                mkdir($typeDir.'/1.0.0', 0o755, true);
                mkdir($typeDir.'/1.0.10', 0o755, true);
                mkdir($typeDir.'/1.0.2', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                );

                // Act
                $result = $storage->exposedDetectLatestVersion($typeDir);

                // Assert
                expect($result)->toBe('1.0.10');
            });

            test('throws exception for invalid semver directory names', function (): void {
                // Arrange
                $typeDir = $this->tempDir.'/policies';
                mkdir($typeDir.'/1.0.0', 0o755, true);
                mkdir($typeDir.'/invalid', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                );

                // Act & Assert
                // Note: Semver::satisfies() throws UnexpectedValueException for invalid versions
                // This is expected behavior from the composer/semver library
                expect(fn (): ?string => $storage->exposedDetectLatestVersion($typeDir))
                    ->toThrow(UnexpectedValueException::class, 'Invalid version string "invalid"');
            });

            test('ignores files and only processes directories', function (): void {
                // Arrange
                $typeDir = $this->tempDir.'/policies';
                mkdir($typeDir.'/1.0.0', 0o755, true);
                mkdir($typeDir.'/2.0.0', 0o755, true);
                touch($typeDir.'/3.0.0'); // File, not directory

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                );

                // Act
                $result = $storage->exposedDetectLatestVersion($typeDir);

                // Assert
                expect($result)->toBe('2.0.0');
            });
        });

        describe('buildPath', function (): void {
            test('builds path with single file mode and versioning enabled', function (): void {
                // Arrange
                mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: '1.0.0',
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedBuildPath('policies', 'admin', 'json');

                // Assert
                expect($result)->toBe($this->tempDir.'/policies/1.0.0/policies.json');
            });

            test('builds path with multiple file mode and versioning enabled', function (): void {
                // Arrange
                mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Multiple,
                    version: '1.0.0',
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedBuildPath('policies', 'admin-policy', 'json');

                // Assert
                expect($result)->toBe($this->tempDir.'/policies/1.0.0/admin-policy.json');
            });

            test('builds path with versioning disabled and single file mode', function (): void {
                // Arrange
                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: false,
                );

                // Act
                $result = $storage->exposedBuildPath('policies', 'admin', 'json');

                // Assert
                expect($result)->toBe($this->tempDir.'/policies/policies.json');
            });

            test('builds path with versioning disabled and multiple file mode', function (): void {
                // Arrange
                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Multiple,
                    version: null,
                    versioningEnabled: false,
                );

                // Act
                $result = $storage->exposedBuildPath('policies', 'user-policy', 'json');

                // Assert
                expect($result)->toBe($this->tempDir.'/policies/user-policy.json');
            });

            test('builds path with auto-detected latest version', function (): void {
                // Arrange
                mkdir($this->tempDir.'/delegations/1.0.0', 0o755, true);
                mkdir($this->tempDir.'/delegations/2.0.0', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Multiple,
                    version: null,
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedBuildPath('delegations', 'delegation-1', 'json');

                // Assert
                expect($result)->toBe($this->tempDir.'/delegations/2.0.0/delegation-1.json');
            });
        });

        describe('createNewVersion', function (): void {
            test('creates patch version when no versions exist', function (): void {
                // Arrange
                mkdir($this->tempDir.'/policies', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedCreateNewVersion('policies', 'patch');

                // Assert
                // When no versions exist, starts from 0.0.0, sets to 0.0.1, then bumps to 0.0.2
                expect($result)->toBe('0.0.2');
                expect(is_dir($this->tempDir.'/policies/0.0.2'))->toBeTrue();
            });

            test('creates minor version when no versions exist', function (): void {
                // Arrange
                mkdir($this->tempDir.'/policies', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedCreateNewVersion('policies', 'minor');

                // Assert
                // When no versions exist, starts from 0.0.0, sets to 0.1.0, then bumps to 0.2.0
                expect($result)->toBe('0.2.0');
                expect(is_dir($this->tempDir.'/policies/0.2.0'))->toBeTrue();
            });

            test('creates major version when no versions exist', function (): void {
                // Arrange
                mkdir($this->tempDir.'/policies', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedCreateNewVersion('policies', 'major');

                // Assert
                // When no versions exist, starts from 0.0.0, sets to 1.0.0, then bumps to 2.0.0
                expect($result)->toBe('2.0.0');
                expect(is_dir($this->tempDir.'/policies/2.0.0'))->toBeTrue();
            });

            test('bumps patch version correctly', function (): void {
                // Arrange
                mkdir($this->tempDir.'/policies/1.2.3', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedCreateNewVersion('policies', 'patch');

                // Assert
                expect($result)->toBe('1.2.4');
                expect(is_dir($this->tempDir.'/policies/1.2.4'))->toBeTrue();
            });

            test('bumps minor version correctly and resets patch', function (): void {
                // Arrange
                mkdir($this->tempDir.'/policies/1.2.3', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedCreateNewVersion('policies', 'minor');

                // Assert
                expect($result)->toBe('1.3.0');
                expect(is_dir($this->tempDir.'/policies/1.3.0'))->toBeTrue();
            });

            test('bumps major version correctly and resets minor and patch', function (): void {
                // Arrange
                mkdir($this->tempDir.'/policies/1.2.3', 0o755, true);

                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: true,
                );

                // Act
                $result = $storage->exposedCreateNewVersion('policies', 'major');

                // Assert
                expect($result)->toBe('2.0.0');
                expect(is_dir($this->tempDir.'/policies/2.0.0'))->toBeTrue();
            });

            test('returns null when versioning is disabled', function (): void {
                // Arrange
                $storage = new TestFileStorage(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: false,
                );

                // Act
                $result = $storage->exposedCreateNewVersion('policies', 'patch');

                // Assert
                expect($result)->toBeNull();
            });
        });
    });

    describe('Sad Paths', function (): void {
        test('throws StorageVersionNotFoundException when specified version does not exist', function (): void {
            // Arrange
            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: '9.9.9',
                versioningEnabled: true,
            );

            // Act & Assert
            expect(fn (): ?string => $storage->exposedResolveVersion('policies'))
                ->toThrow(StorageVersionNotFoundException::class);
        });

        test('throws exception with correct version and type information', function (): void {
            // Arrange
            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: '2.0.0',
                versioningEnabled: true,
            );

            // Act & Assert
            try {
                $storage->exposedResolveVersion('delegations');
                expect(false)->toBeTrue('Exception should have been thrown');
            } catch (StorageVersionNotFoundException $storageVersionNotFoundException) {
                expect($storageVersionNotFoundException->getMessage())->toContain('2.0.0');
                expect($storageVersionNotFoundException->getMessage())->toContain('delegations');
            }
        });
    });

    describe('Edge Cases', function (): void {
        test('detectLatestVersion returns null when directory does not exist', function (): void {
            // Arrange
            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
            );

            // Act
            $result = $storage->exposedDetectLatestVersion($this->tempDir.'/nonexistent');

            // Assert
            expect($result)->toBeNull();
        });

        test('detectLatestVersion returns null when directory is empty', function (): void {
            // Arrange
            $emptyDir = $this->tempDir.'/empty';
            mkdir($emptyDir, 0o755, true);

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
            );

            // Act
            $result = $storage->exposedDetectLatestVersion($emptyDir);

            // Assert
            expect($result)->toBeNull();
        });

        test('throws exception when encountering invalid semver directories', function (): void {
            // Arrange
            $typeDir = $this->tempDir.'/policies';
            mkdir($typeDir.'/1.0.0', 0o755, true);
            mkdir($typeDir.'/latest', 0o755, true); // Invalid semver - will throw

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
            );

            // Act & Assert
            // Semver::satisfies() throws UnexpectedValueException for invalid version strings
            expect(fn (): ?string => $storage->exposedDetectLatestVersion($typeDir))
                ->toThrow(UnexpectedValueException::class, 'Invalid version string "latest"');
        });

        test('handles complex semantic versions with major, minor, and patch', function (): void {
            // Arrange
            $typeDir = $this->tempDir.'/policies';
            mkdir($typeDir.'/10.5.3', 0o755, true);
            mkdir($typeDir.'/10.10.1', 0o755, true);
            mkdir($typeDir.'/2.99.99', 0o755, true);

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
            );

            // Act
            $result = $storage->exposedDetectLatestVersion($typeDir);

            // Assert
            expect($result)->toBe('10.10.1');
        });

        test('resolveVersion returns null when no versions exist and versioning enabled', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies', 0o755, true);

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: true,
            );

            // Act
            $result = $storage->exposedResolveVersion('policies');

            // Assert
            expect($result)->toBeNull();
        });

        test('buildPath omits version directory when auto-detection finds no versions', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies', 0o755, true);

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: true,
            );

            // Act
            $result = $storage->exposedBuildPath('policies', 'admin', 'json');

            // Assert
            expect($result)->toBe($this->tempDir.'/policies/policies.json');
        });

        test('handles different file extensions correctly', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: '1.0.0',
                versioningEnabled: true,
            );

            // Act
            $jsonPath = $storage->exposedBuildPath('policies', 'policy1', 'json');
            $yamlPath = $storage->exposedBuildPath('policies', 'policy2', 'yaml');
            $xmlPath = $storage->exposedBuildPath('policies', 'policy3', 'xml');

            // Assert
            expect($jsonPath)->toEndWith('.json');
            expect($yamlPath)->toEndWith('.yaml');
            expect($xmlPath)->toEndWith('.xml');
        });

        test('subsequent calls create incrementing versions', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: true,
            );

            // Act
            $result1 = $storage->exposedCreateNewVersion('policies', 'patch');
            $result2 = $storage->exposedCreateNewVersion('policies', 'patch');

            // Assert
            // Each call detects the latest version and bumps it
            expect($result1)->toBe('1.0.1');
            expect($result2)->toBe('1.0.2'); // Detects 1.0.1 as latest and bumps to 1.0.2
            expect(is_dir($this->tempDir.'/policies/1.0.1'))->toBeTrue();
            expect(is_dir($this->tempDir.'/policies/1.0.2'))->toBeTrue();
        });

        test('handles identifiers with special characters in multiple mode', function (): void {
            // Arrange
            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: null,
                versioningEnabled: false,
            );

            // Act
            $result1 = $storage->exposedBuildPath('policies', 'admin-policy', 'json');
            $result2 = $storage->exposedBuildPath('policies', 'user_policy', 'json');
            $result3 = $storage->exposedBuildPath('policies', 'policy.backup', 'json');

            // Assert
            expect($result1)->toBe($this->tempDir.'/policies/admin-policy.json');
            expect($result2)->toBe($this->tempDir.'/policies/user_policy.json');
            expect($result3)->toBe($this->tempDir.'/policies/policy.backup.json');
        });

        test('handles base path with trailing slash', function (): void {
            // Arrange
            $storage = new TestFileStorage(
                basePath: $this->tempDir.'/',
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act
            $result = $storage->exposedBuildPath('policies', 'admin', 'json');

            // Assert
            expect($result)->toBe($this->tempDir.'//policies/policies.json');
        });

        test('throws exception for hidden dot directories with invalid names', function (): void {
            // Arrange
            $typeDir = $this->tempDir.'/policies';
            mkdir($typeDir.'/1.0.0', 0o755, true);
            mkdir($typeDir.'/.hidden', 0o755, true); // Will throw when validated as semver

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
            );

            // Act & Assert
            // The .hidden directory will cause Semver::satisfies() to throw
            expect(fn (): ?string => $storage->exposedDetectLatestVersion($typeDir))
                ->toThrow(UnexpectedValueException::class);
        });

        test('resolves correct version when multiple types with different versions exist', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);
            mkdir($this->tempDir.'/policies/2.0.0', 0o755, true);
            mkdir($this->tempDir.'/delegations/3.0.0', 0o755, true);

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: true,
            );

            // Act
            $policiesVersion = $storage->exposedResolveVersion('policies');
            $delegationsVersion = $storage->exposedResolveVersion('delegations');

            // Assert
            expect($policiesVersion)->toBe('2.0.0');
            expect($delegationsVersion)->toBe('3.0.0');
        });

        test('bumps version from highest existing version when multiple exist', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);
            mkdir($this->tempDir.'/policies/1.5.0', 0o755, true);
            mkdir($this->tempDir.'/policies/1.2.0', 0o755, true);

            $storage = new TestFileStorage(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: true,
            );

            // Act
            $result = $storage->exposedCreateNewVersion('policies', 'patch');

            // Assert
            expect($result)->toBe('1.5.1');
        });
    });
});
