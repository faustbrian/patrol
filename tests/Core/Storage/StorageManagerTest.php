<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Storage\JsonDelegationRepository;
use Patrol\Core\Storage\JsonPolicyRepository;
use Patrol\Core\Storage\StorageFactory;
use Patrol\Core\Storage\StorageManager;
use Patrol\Core\Storage\YamlPolicyRepository;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\StorageDriver;
use Patrol\Laravel\Repositories\DatabasePolicyRepository;
use Tests\Helpers\FilesystemHelper;

describe('StorageManager', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_manager_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);

        $this->factory = new StorageFactory();
        $this->defaultConfig = [
            'path' => $this->tempDir,
            'file_mode' => FileMode::Single,
            'versioning' => ['enabled' => true],
        ];
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths', function (): void {
        test('uses default driver from configuration', function (): void {
            // Arrange
            $defaultDriver = StorageDriver::Json;
            $manager = new StorageManager($defaultDriver, $this->defaultConfig, $this->factory);

            // Act
            $repository = $manager->policy();

            // Assert
            expect($repository)->toBeInstanceOf(PolicyRepositoryInterface::class)
                ->and($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('switches to different storage driver at runtime', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $yamlRepository = $manager->driver(StorageDriver::Yaml)->policy();

            // Assert
            expect($yamlRepository)->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('returns policy repository for active driver', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $repository = $manager->policy();

            // Assert
            expect($repository)->toBeInstanceOf(PolicyRepositoryInterface::class)
                ->and($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('returns delegation repository for active driver', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $repository = $manager->delegation();

            // Assert
            expect($repository)->toBeInstanceOf(DelegationRepositoryInterface::class)
                ->and($repository)->toBeInstanceOf(JsonDelegationRepository::class);
        });

        test('switches to specific version for file-based storage', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);
            $version = '1.2.0';

            // Act
            $versionedManager = $manager->version($version);
            $repository = $versionedManager->policy();

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('switches file mode at runtime', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $multipleFileManager = $manager->fileMode(FileMode::Multiple);
            $repository = $multipleFileManager->policy();

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('chains driver, version, and file mode switches', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $repository = $manager
                ->driver(StorageDriver::Yaml)
                ->version('2.0.0')
                ->fileMode(FileMode::Multiple)
                ->policy();

            // Assert
            expect($repository)->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('creates new instance on driver switch', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $newManager = $manager->driver(StorageDriver::Yaml);

            // Assert
            expect($newManager)->not->toBe($manager)
                ->and($manager->policy())->toBeInstanceOf(JsonPolicyRepository::class)
                ->and($newManager->policy())->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('creates new instance on version switch', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $newManager = $manager->version('1.0.0');

            // Assert
            expect($newManager)->not->toBe($manager);
        });

        test('creates new instance on file mode switch', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $newManager = $manager->fileMode(FileMode::Multiple);

            // Assert
            expect($newManager)->not->toBe($manager);
        });

        test('passes configuration to factory when creating repositories', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'file_mode' => FileMode::Single,
                'versioning' => ['enabled' => true],
            ];
            $manager = new StorageManager(StorageDriver::Json, $config, $this->factory);

            // Act
            $repository = $manager->policy();

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('merges runtime overrides with base configuration', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $repository = $manager
                ->version('3.0.0')
                ->fileMode(FileMode::Multiple)
                ->policy();

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('supports database driver for policies', function (): void {
            // Arrange
            $config = [
                'table' => 'patrol_policies',
                'connection' => 'default',
            ];
            $manager = new StorageManager(StorageDriver::Eloquent, $config, $this->factory);

            // Act
            $repository = $manager->policy();

            // Assert
            expect($repository)->toBeInstanceOf(DatabasePolicyRepository::class);
        });

        test('allows switching between file and database drivers', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $fileRepository = $manager->policy();
            $dbRepository = $manager->driver(StorageDriver::Eloquent)->policy();

            // Assert
            expect($fileRepository)->toBeInstanceOf(JsonPolicyRepository::class)
                ->and($dbRepository)->toBeInstanceOf(DatabasePolicyRepository::class);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception when delegation storage not implemented for driver', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Yaml, $this->defaultConfig, $this->factory);

            // Act & Assert
            expect($manager->delegation(...))
                ->toThrow(InvalidArgumentException::class, 'Delegation storage not yet implemented for driver: yaml');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty configuration array with explicit path', function (): void {
            // Arrange
            $config = ['path' => $this->tempDir];
            $manager = new StorageManager(StorageDriver::Json, $config, $this->factory);

            // Act
            $repository = $manager->policy();

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('handles null version in configuration', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act - No version specified
            $repository = $manager->policy();

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('handles minimal file configuration', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'file_mode' => FileMode::Single,
            ];
            $manager = new StorageManager(StorageDriver::Json, $config, $this->factory);

            // Act
            $repository = $manager->policy();

            // Assert - Factory should provide defaults for versioning
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('handles missing file mode in configuration', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'versioning' => ['enabled' => true],
            ];
            $manager = new StorageManager(StorageDriver::Json, $config, $this->factory);

            // Act
            $repository = $manager->policy();

            // Assert - Factory should provide default file mode
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('handles missing versioning configuration', function (): void {
            // Arrange
            $config = [
                'path' => $this->tempDir,
                'file_mode' => FileMode::Single,
            ];
            $manager = new StorageManager(StorageDriver::Json, $config, $this->factory);

            // Act
            $repository = $manager->policy();

            // Assert - Factory should provide default versioning enabled
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('preserves original manager state after cloning', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $modifiedManager = $manager->driver(StorageDriver::Yaml)->version('1.0.0');

            // Assert - Original manager should still use JSON driver
            expect($manager->policy())->toBeInstanceOf(JsonPolicyRepository::class)
                ->and($modifiedManager->policy())->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('handles multiple sequential driver switches', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $step1 = $manager->driver(StorageDriver::Yaml);
            $step2 = $step1->driver(StorageDriver::Json);
            $step3 = $step2->driver(StorageDriver::Yaml);

            // Assert
            expect($step3->policy())->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('handles version override after driver switch', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $repository = $manager
                ->driver(StorageDriver::Yaml)
                ->version('2.0.0')
                ->policy();

            // Assert
            expect($repository)->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('handles file mode override after version switch', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $repository = $manager
                ->version('1.0.0')
                ->fileMode(FileMode::Multiple)
                ->policy();

            // Assert
            expect($repository)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('handles all storage drivers for policy repositories', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act & Assert - Test JSON driver
            $jsonRepository = $manager->driver(StorageDriver::Json)->policy();
            expect($jsonRepository)->toBeInstanceOf(JsonPolicyRepository::class);

            // Test YAML driver
            $yamlRepository = $manager->driver(StorageDriver::Yaml)->policy();
            expect($yamlRepository)->toBeInstanceOf(YamlPolicyRepository::class);
        });

        test('creates independent instances for parallel configurations', function (): void {
            // Arrange
            $manager = new StorageManager(StorageDriver::Json, $this->defaultConfig, $this->factory);

            // Act
            $config1 = $manager->driver(StorageDriver::Json)->version('1.0.0');
            $config2 = $manager->driver(StorageDriver::Yaml)->version('2.0.0');
            $config3 = $manager->driver(StorageDriver::Json)->fileMode(FileMode::Multiple);

            // Assert - All should be different instances
            expect($config1)->not->toBe($config2)
                ->and($config2)->not->toBe($config3)
                ->and($config1)->not->toBe($config3)
                ->and($config1->policy())->toBeInstanceOf(JsonPolicyRepository::class)
                ->and($config2->policy())->toBeInstanceOf(YamlPolicyRepository::class)
                ->and($config3->policy())->toBeInstanceOf(JsonPolicyRepository::class);
        });
    });
});
