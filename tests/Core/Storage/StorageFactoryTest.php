<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Storage\CsvDelegationRepository;
use Patrol\Core\Storage\CsvPolicyRepository;
use Patrol\Core\Storage\IniDelegationRepository;
use Patrol\Core\Storage\IniPolicyRepository;
use Patrol\Core\Storage\Json5DelegationRepository;
use Patrol\Core\Storage\Json5PolicyRepository;
use Patrol\Core\Storage\JsonDelegationRepository;
use Patrol\Core\Storage\JsonPolicyRepository;
use Patrol\Core\Storage\SerializedPolicyRepository;
use Patrol\Core\Storage\StorageFactory;
use Patrol\Core\Storage\TomlPolicyRepository;
use Patrol\Core\Storage\XmlPolicyRepository;
use Patrol\Core\Storage\YamlPolicyRepository;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\StorageDriver;
use Patrol\Laravel\Repositories\DatabaseDelegationRepository;
use Patrol\Laravel\Repositories\DatabasePolicyRepository;
use Tests\Helpers\FilesystemHelper;

// Load function_exists override for testing the fallback path
require_once __DIR__.'/function_exists_override.php';

describe('StorageFactory', function (): void {
    beforeEach(function (): void {
        $this->factory = new StorageFactory();
        $this->tempDir = sys_get_temp_dir().'/patrol_factory_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths', function (): void {
        describe('createPolicyRepository', function (): void {
            test('creates eloquent policy repository with custom table', function (): void {
                // Arrange
                $config = [
                    'table' => 'custom_policies',
                    'connection' => 'mysql',
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Eloquent,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(DatabasePolicyRepository::class)
                    ->and($repository)->toBeInstanceOf(PolicyRepositoryInterface::class);
            });

            test('creates json policy repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Multiple,
                    'version' => '1.0.0',
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Json,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(JsonPolicyRepository::class)
                    ->and($repository)->toBeInstanceOf(PolicyRepositoryInterface::class);
            });

            test('creates yaml policy repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Single,
                    'version' => '2.0.0',
                    'versioning' => ['enabled' => false],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Yaml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(YamlPolicyRepository::class)
                    ->and($repository)->toBeInstanceOf(PolicyRepositoryInterface::class);
            });

            test('creates xml policy repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Multiple,
                    'version' => 'v3.0',
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Xml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(XmlPolicyRepository::class)
                    ->and($repository)->toBeInstanceOf(PolicyRepositoryInterface::class);
            });

            test('creates toml policy repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Single,
                    'version' => null,
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Toml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(TomlPolicyRepository::class)
                    ->and($repository)->toBeInstanceOf(PolicyRepositoryInterface::class);
            });

            test('creates serialized policy repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Multiple,
                    'version' => '1.2.3',
                    'versioning' => ['enabled' => false],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Serialized,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(SerializedPolicyRepository::class)
                    ->and($repository)->toBeInstanceOf(PolicyRepositoryInterface::class);
            });

            test('creates csv policy repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Multiple,
                    'version' => '1.0.0',
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Csv,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(CsvPolicyRepository::class)
                    ->and($repository)->toBeInstanceOf(PolicyRepositoryInterface::class);
            });

            test('creates ini policy repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Single,
                    'version' => '2.0.0',
                    'versioning' => ['enabled' => false],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Ini,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(IniPolicyRepository::class)
                    ->and($repository)->toBeInstanceOf(PolicyRepositoryInterface::class);
            });

            test('creates json5 policy repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Multiple,
                    'version' => 'v1.5',
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Json5,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(Json5PolicyRepository::class)
                    ->and($repository)->toBeInstanceOf(PolicyRepositoryInterface::class);
            });
        });

        describe('createDelegationRepository', function (): void {
            test('creates eloquent delegation repository with custom table', function (): void {
                // Arrange
                $config = [
                    'table' => 'custom_delegations',
                    'connection' => 'pgsql',
                ];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Eloquent,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(DatabaseDelegationRepository::class)
                    ->and($repository)->toBeInstanceOf(DelegationRepositoryInterface::class);
            });

            test('creates json delegation repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Multiple,
                    'version' => '1.0.0',
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Json,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(JsonDelegationRepository::class)
                    ->and($repository)->toBeInstanceOf(DelegationRepositoryInterface::class);
            });

            test('creates csv delegation repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Single,
                    'version' => '1.5.0',
                    'versioning' => ['enabled' => false],
                ];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Csv,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(CsvDelegationRepository::class)
                    ->and($repository)->toBeInstanceOf(DelegationRepositoryInterface::class);
            });

            test('creates ini delegation repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Multiple,
                    'version' => '2.0.0',
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Ini,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(IniDelegationRepository::class)
                    ->and($repository)->toBeInstanceOf(DelegationRepositoryInterface::class);
            });

            test('creates json5 delegation repository with custom path', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Multiple,
                    'version' => 'v3.1',
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Json5,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(Json5DelegationRepository::class)
                    ->and($repository)->toBeInstanceOf(DelegationRepositoryInterface::class);
            });
        });

        describe('Default Configuration', function (): void {
            test('creates eloquent policy repository with default table', function (): void {
                // Arrange
                $config = [];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Eloquent,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(DatabasePolicyRepository::class);
            });

            test('creates eloquent delegation repository with default table', function (): void {
                // Arrange
                $config = [];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Eloquent,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(DatabaseDelegationRepository::class);
            });

            test('creates json policy repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Json,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
            });

            test('creates yaml policy repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Yaml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(YamlPolicyRepository::class);
            });

            test('creates xml policy repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Xml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(XmlPolicyRepository::class);
            });

            test('creates toml policy repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Toml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(TomlPolicyRepository::class);
            });

            test('creates serialized policy repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Serialized,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(SerializedPolicyRepository::class);
            });

            test('creates json delegation repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Json,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(JsonDelegationRepository::class);
            });

            test('creates csv policy repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Csv,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(CsvPolicyRepository::class);
            });

            test('creates ini policy repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Ini,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(IniPolicyRepository::class);
            });

            test('creates json5 policy repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Json5,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(Json5PolicyRepository::class);
            });

            test('creates csv delegation repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Csv,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(CsvDelegationRepository::class);
            });

            test('creates ini delegation repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Ini,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(IniDelegationRepository::class);
            });

            test('creates json5 delegation repository with default file mode and versioning', function (): void {
                // Arrange - provide path to avoid storage_path() issues in unit tests
                $config = ['path' => $this->tempDir];

                // Act
                $repository = $this->factory->createDelegationRepository(
                    StorageDriver::Json5,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(Json5DelegationRepository::class);
            });
        });

        describe('File Mode Configuration', function (): void {
            test('creates json policy repository with single file mode', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Single,
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Json,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
            });

            test('creates yaml policy repository with multiple file mode', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'file_mode' => FileMode::Multiple,
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Yaml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(YamlPolicyRepository::class);
            });
        });

        describe('Versioning Configuration', function (): void {
            test('creates json policy repository with versioning enabled', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'version' => '1.0.0',
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Json,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
            });

            test('creates yaml policy repository with versioning disabled', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'version' => null,
                    'versioning' => ['enabled' => false],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Yaml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(YamlPolicyRepository::class);
            });

            test('creates xml policy repository with semantic version', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'version' => '2.5.3',
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Xml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(XmlPolicyRepository::class);
            });

            test('creates toml policy repository without version', function (): void {
                // Arrange
                $config = [
                    'path' => $this->tempDir,
                    'version' => null,
                    'versioning' => ['enabled' => true],
                ];

                // Act
                $repository = $this->factory->createPolicyRepository(
                    StorageDriver::Toml,
                    $config,
                );

                // Assert
                expect($repository)->toBeInstanceOf(TomlPolicyRepository::class);
            });
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception when creating delegation repository for yaml driver', function (): void {
            // Arrange
            $config = ['path' => $this->tempDir];

            // Act & Assert
            expect(fn () => $this->factory->createDelegationRepository(
                StorageDriver::Yaml,
                $config,
            ))->toThrow(
                InvalidArgumentException::class,
                'Delegation storage not yet implemented for driver: yaml',
            );
        });

        test('throws exception when creating delegation repository for xml driver', function (): void {
            // Arrange
            $config = ['path' => $this->tempDir];

            // Act & Assert
            expect(fn () => $this->factory->createDelegationRepository(
                StorageDriver::Xml,
                $config,
            ))->toThrow(
                InvalidArgumentException::class,
                'Delegation storage not yet implemented for driver: xml',
            );
        });

        test('throws exception when creating delegation repository for toml driver', function (): void {
            // Arrange
            $config = ['path' => $this->tempDir];

            // Act & Assert
            expect(fn () => $this->factory->createDelegationRepository(
                StorageDriver::Toml,
                $config,
            ))->toThrow(
                InvalidArgumentException::class,
                'Delegation storage not yet implemented for driver: toml',
            );
        });

        test('throws exception when creating delegation repository for serialized driver', function (): void {
            // Arrange
            $config = ['path' => $this->tempDir];

            // Act & Assert
            expect(fn () => $this->factory->createDelegationRepository(
                StorageDriver::Serialized,
                $config,
            ))->toThrow(
                InvalidArgumentException::class,
                'Delegation storage not yet implemented for driver: serialized',
            );
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty config array for eloquent policy repository', function (): void {
            // Arrange
            $config = [];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Eloquent,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(DatabasePolicyRepository::class);
        });

        test('handles empty config array for eloquent delegation repository', function (): void {
            // Arrange
            $config = [];

            // Act
            $repository = $this->factory->createDelegationRepository(
                StorageDriver::Eloquent,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(DatabaseDelegationRepository::class);
        });

        test('handles partial config with only path for json policy repository', function (): void {
            // Arrange
            $config = ['path' => $this->tempDir];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Json,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('handles partial config with only file mode for yaml policy repository', function (): void {
            // Arrange - must provide path to avoid storage_path() issues
            $config = [
                'path' => $this->tempDir,
                'file_mode' => FileMode::Single,
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Yaml,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('handles partial config with only version for xml policy repository', function (): void {
            // Arrange - must provide path to avoid storage_path() issues
            $config = [
                'path' => $this->tempDir,
                'version' => '1.0.0',
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Xml,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(XmlPolicyRepository::class);
        });

        test('handles partial config with only versioning for toml policy repository', function (): void {
            // Arrange - must provide path to avoid storage_path() issues
            $config = [
                'path' => $this->tempDir,
                'versioning' => ['enabled' => false],
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Toml,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(TomlPolicyRepository::class);
        });

        test('handles config with only table for eloquent policy repository', function (): void {
            // Arrange
            $config = ['table' => 'custom_policies'];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Eloquent,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(DatabasePolicyRepository::class);
        });

        test('handles config with only connection for eloquent delegation repository', function (): void {
            // Arrange
            $config = ['connection' => 'sqlite'];

            // Act
            $repository = $this->factory->createDelegationRepository(
                StorageDriver::Eloquent,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(DatabaseDelegationRepository::class);
        });

        test('handles null version in config for json policy repository', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'version' => null,
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Json,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('handles versioning config without enabled key for yaml policy repository', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'versioning' => [],
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Yaml,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('handles deeply nested path for serialized policy repository', function (): void {
            // Arrange
            $nestedPath = $this->tempDir.'/deeply/nested/path/structure';
            mkdir($nestedPath, 0o755, true);
            $config = ['path' => $nestedPath];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Serialized,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(SerializedPolicyRepository::class);
        });

        test('handles path with trailing slash for xml policy repository', function (): void {
            // Arrange
            $config = ['path' => $this->tempDir.'/'];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Xml,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(XmlPolicyRepository::class);
        });

        test('handles config with all optional parameters for json delegation repository', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'file_mode' => FileMode::Multiple,
                'version' => '1.0.0',
                'versioning' => ['enabled' => true],
            ];

            // Act
            $repository = $this->factory->createDelegationRepository(
                StorageDriver::Json,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(JsonDelegationRepository::class);
        });

        test('handles config with extra unrecognized keys for json policy repository', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'unrecognized_key' => 'value',
                'another_key' => 123,
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Json,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('handles unicode characters in custom path for yaml policy repository', function (): void {
            // Arrange
            $unicodePath = $this->tempDir.'/テスト/路径';
            mkdir($unicodePath, 0o755, true);
            $config = ['path' => $unicodePath];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Yaml,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('handles special characters in table name for eloquent policy repository', function (): void {
            // Arrange
            $config = [
                'table' => 'patrol_policies_v2_beta',
                'connection' => 'default',
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Eloquent,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(DatabasePolicyRepository::class);
        });

        test('handles long version string for toml policy repository', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'version' => '1.0.0-alpha.1+build.20250104.sha.abc123def456',
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Toml,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(TomlPolicyRepository::class);
        });

        test('handles invalid config types gracefully for json policy repository', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir, // Valid path needed for test environment
                'file_mode' => 'invalid', // Invalid type - not FileMode enum
                'version' => ['not', 'a', 'string'], // Invalid type - not a string
                'versioning' => 'not_an_array', // Invalid type - not an array
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Json,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('handles invalid config types for eloquent policy repository', function (): void {
            // Arrange
            $config = [
                'connection' => 12_345, // Invalid type - not a string
                'table' => ['not', 'string'], // Invalid type - not a string
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Eloquent,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(DatabasePolicyRepository::class);
        });

        test('handles invalid versioning enabled type for yaml policy repository', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'versioning' => [
                    'enabled' => 'not_a_bool', // Invalid type - not a bool
                ],
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Yaml,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('handles mixed valid and invalid config values for xml policy repository', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir, // Valid
                'file_mode' => 'invalid', // Invalid - will use default
                'version' => '1.0.0', // Valid
                'versioning' => 'not_array', // Invalid - will use default
            ];

            // Act
            $repository = $this->factory->createPolicyRepository(
                StorageDriver::Xml,
                $config,
            );

            // Assert
            expect($repository)->toBeInstanceOf(XmlPolicyRepository::class);
        });
    });

    describe('Default Path Handling', function (): void {
        test('getDefaultPath uses storage_path when function exists', function (): void {
            // Arrange
            $factory = new StorageFactory();
            $reflection = new ReflectionClass($factory);
            $method = $reflection->getMethod('getDefaultPath');

            // Act
            $result = $method->invoke($factory);

            // Assert
            // storage_path() exists in Laravel and app is bootstrapped via CoreTestCase
            // This proves line 181 (the if check) is executed and returns successfully
            expect($result)->toBeString();
            expect($result)->toContain('patrol');
        });
    });
});
