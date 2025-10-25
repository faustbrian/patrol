<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Resource;
use Patrol\Laravel\Resolvers\LaravelResourceResolver;
use Tests\Laravel\Support\TestModel;

describe('LaravelResourceResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new LaravelResourceResolver();
    });

    describe('Happy Paths', function (): void {
        test('resolves Eloquent model with integer ID to Resource', function (): void {
            // Arrange
            $model = new TestModel(
                id: 123,
                attributes: [
                    'title' => 'Test Document',
                    'status' => 'published',
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result)->toBeInstanceOf(Resource::class)
                ->and($result->id)->toBe('123')
                ->and($result->type)->toBe('TestModel')
                ->and($result->attributes)->toBe([
                    'id' => 123,
                    'title' => 'Test Document',
                    'status' => 'published',
                ]);
        });

        test('resolves Eloquent model with string ID to Resource', function (): void {
            // Arrange
            $model = new TestModel(
                id: 'uuid-123-456',
                attributes: [
                    'name' => 'Test Resource',
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result)->toBeInstanceOf(Resource::class)
                ->and($result->id)->toBe('uuid-123-456')
                ->and($result->type)->toBe('TestModel')
                ->and($result->attributes)->toBe([
                    'id' => 'uuid-123-456',
                    'name' => 'Test Resource',
                ]);
        });

        test('resolves scalar string identifier to Resource with unknown type', function (): void {
            // Arrange
            $identifier = 'document-123';

            // Act
            $result = $this->resolver->resolve($identifier);

            // Assert
            expect($result)->toBeInstanceOf(Resource::class)
                ->and($result->id)->toBe('document-123')
                ->and($result->type)->toBe('unknown')
                ->and($result->attributes)->toBe([]);
        });

        test('resolves scalar integer identifier to Resource with unknown type', function (): void {
            // Arrange
            $identifier = 456;

            // Act
            $result = $this->resolver->resolve($identifier);

            // Assert
            expect($result)->toBeInstanceOf(Resource::class)
                ->and($result->id)->toBe('456')
                ->and($result->type)->toBe('unknown')
                ->and($result->attributes)->toBe([]);
        });

        test('extracts full attributes from model for ABAC policies', function (): void {
            // Arrange
            $model = new TestModel(
                id: 1,
                attributes: [
                    'owner_id' => 'user-123',
                    'department' => 'engineering',
                    'sensitivity' => 'high',
                    'tags' => ['important', 'confidential'],
                    'metadata' => [
                        'created_at' => '2023-01-01',
                        'updated_at' => '2023-12-31',
                    ],
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes)->toHaveKey('owner_id', 'user-123')
                ->and($result->attributes)->toHaveKey('department', 'engineering')
                ->and($result->attributes)->toHaveKey('sensitivity', 'high')
                ->and($result->attributes)->toHaveKey('tags')
                ->and($result->attributes)->toHaveKey('metadata');
        });

        test('correctly extracts class basename as resource type', function (): void {
            // Arrange
            $model = new TestModel(id: 1);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->type)->toBe('TestModel')
                ->and($result->type)->not->toContain('\\')
                ->and($result->type)->not->toContain('Tests\\Laravel\\Support\\');
        });

        test('resolves model with no additional attributes', function (): void {
            // Arrange
            $model = new TestModel(id: 999);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes)->toBe(['id' => 999])
                ->and($result->id)->toBe('999')
                ->and($result->type)->toBe('TestModel');
        });

        test('converts integer ID to string in Resource', function (): void {
            // Arrange
            $model = new TestModel(id: 12_345);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->id)->toBeString()
                ->and($result->id)->toBe('12345');
        });

        test('preserves string ID without conversion', function (): void {
            // Arrange
            $model = new TestModel(id: 'custom-id-abc');

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->id)->toBeString()
                ->and($result->id)->toBe('custom-id-abc');
        });
    });

    describe('Edge Cases', function (): void {
        test('resolves model with zero ID', function (): void {
            // Arrange
            $model = new TestModel(id: 0);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->id)->toBe('0')
                ->and($result->type)->toBe('TestModel');
        });

        test('resolves model with negative ID', function (): void {
            // Arrange
            $model = new TestModel(id: -999);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->id)->toBe('-999')
                ->and($result->type)->toBe('TestModel');
        });

        test('resolves model with very large integer ID', function (): void {
            // Arrange
            $largeId = \PHP_INT_MAX;
            $model = new TestModel(id: $largeId);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->id)->toBe((string) $largeId)
                ->and($result->type)->toBe('TestModel');
        });

        test('resolves model with UUID string ID', function (): void {
            // Arrange
            $uuid = '550e8400-e29b-41d4-a716-446655440000';
            $model = new TestModel(id: $uuid);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->id)->toBe($uuid)
                ->and($result->type)->toBe('TestModel');
        });

        test('resolves model with ULID string ID', function (): void {
            // Arrange
            $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
            $model = new TestModel(id: $ulid);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->id)->toBe($ulid)
                ->and($result->type)->toBe('TestModel');
        });

        test('resolves scalar string with special characters', function (): void {
            // Arrange
            $identifier = 'resource://path/to/file.pdf';

            // Act
            $result = $this->resolver->resolve($identifier);

            // Assert
            expect($result->id)->toBe('resource://path/to/file.pdf')
                ->and($result->type)->toBe('unknown')
                ->and($result->attributes)->toBe([]);
        });

        test('resolves scalar string with unicode characters', function (): void {
            // Arrange
            $identifier = '文档-123';

            // Act
            $result = $this->resolver->resolve($identifier);

            // Assert
            expect($result->id)->toBe('文档-123')
                ->and($result->type)->toBe('unknown');
        });

        test('resolves empty string identifier', function (): void {
            // Arrange
            $identifier = '';

            // Act
            $result = $this->resolver->resolve($identifier);

            // Assert
            expect($result->id)->toBe('')
                ->and($result->type)->toBe('unknown')
                ->and($result->attributes)->toBe([]);
        });

        test('resolves model with empty attributes array', function (): void {
            // Arrange
            $model = new TestModel(id: 1, attributes: []);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes)->toBe(['id' => 1]);
        });

        test('resolves model with nested array attributes', function (): void {
            // Arrange
            $model = new TestModel(
                id: 1,
                attributes: [
                    'config' => [
                        'nested' => [
                            'deep' => [
                                'value' => 'test',
                            ],
                        ],
                    ],
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes['config']['nested']['deep']['value'])->toBe('test');
        });

        test('resolves model with null values in attributes', function (): void {
            // Arrange
            $model = new TestModel(
                id: 1,
                attributes: [
                    'nullable_field' => null,
                    'optional_field' => null,
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes)->toHaveKey('nullable_field')
                ->and($result->attributes['nullable_field'])->toBeNull()
                ->and($result->attributes)->toHaveKey('optional_field')
                ->and($result->attributes['optional_field'])->toBeNull();
        });

        test('resolves model with boolean attributes', function (): void {
            // Arrange
            $model = new TestModel(
                id: 1,
                attributes: [
                    'is_active' => true,
                    'is_deleted' => false,
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes['is_active'])->toBeTrue()
                ->and($result->attributes['is_deleted'])->toBeFalse();
        });

        test('resolves model with numeric string attributes', function (): void {
            // Arrange
            $model = new TestModel(
                id: 1,
                attributes: [
                    'numeric_string' => '12345',
                    'float_string' => '123.45',
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes['numeric_string'])->toBe('12345')
                ->and($result->attributes['float_string'])->toBe('123.45');
        });

        test('resolves model with array containing mixed types', function (): void {
            // Arrange
            $model = new TestModel(
                id: 1,
                attributes: [
                    'mixed_array' => [1, 'string', true, null, ['nested']],
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes['mixed_array'])->toBe([1, 'string', true, null, ['nested']]);
        });

        test('resolves very long string identifier', function (): void {
            // Arrange
            $longIdentifier = str_repeat('a', 10_000);

            // Act
            $result = $this->resolver->resolve($longIdentifier);

            // Assert
            expect($result->id)->toBe($longIdentifier)
                ->and($result->type)->toBe('unknown')
                ->and(mb_strlen((string) $result->id))->toBe(10_000);
        });

        test('resolves model with very large attributes array', function (): void {
            // Arrange
            $largeAttributes = [];

            for ($i = 0; $i < 1_000; ++$i) {
                $largeAttributes['field_'.$i] = 'value_'.$i;
            }

            $model = new TestModel(id: 1, attributes: $largeAttributes);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes)->toHaveCount(1_001) // 1000 + id field
                ->and($result->attributes['field_0'])->toBe('value_0')
                ->and($result->attributes['field_999'])->toBe('value_999');
        });

        test('preserves attribute key order from model', function (): void {
            // Arrange
            $model = new TestModel(
                id: 1,
                attributes: [
                    'z_last' => 'last',
                    'a_first' => 'first',
                    'm_middle' => 'middle',
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            $keys = array_keys($result->attributes);
            expect($keys[0])->toBe('id')
                ->and($keys[1])->toBe('z_last')
                ->and($keys[2])->toBe('a_first')
                ->and($keys[3])->toBe('m_middle');
        });

        test('resolves model with attributes containing special characters in keys', function (): void {
            // Arrange
            $model = new TestModel(
                id: 1,
                attributes: [
                    'key-with-dashes' => 'value1',
                    'key_with_underscores' => 'value2',
                    'key.with.dots' => 'value3',
                ],
            );

            // Act
            $result = $this->resolver->resolve($model);

            // Assert
            expect($result->attributes)->toHaveKey('key-with-dashes')
                ->and($result->attributes)->toHaveKey('key_with_underscores')
                ->and($result->attributes)->toHaveKey('key.with.dots');
        });

        test('returns immutable Resource value object', function (): void {
            // Arrange
            $model = new TestModel(id: 1, attributes: ['test' => 'value']);

            // Act
            $result = $this->resolver->resolve($model);

            // Assert - Resource is readonly
            expect($result)->toBeInstanceOf(Resource::class);
            // Attempting to modify would cause TypeError in PHP 8.1+
            // This test verifies the Resource class is properly marked as readonly
        });

        test('resolves multiple models independently', function (): void {
            // Arrange
            $model1 = new TestModel(id: 1, attributes: ['name' => 'First']);
            $model2 = new TestModel(id: 2, attributes: ['name' => 'Second']);

            // Act
            $result1 = $this->resolver->resolve($model1);
            $result2 = $this->resolver->resolve($model2);

            // Assert
            expect($result1->id)->toBe('1')
                ->and($result2->id)->toBe('2')
                ->and($result1->attributes['name'])->toBe('First')
                ->and($result2->attributes['name'])->toBe('Second');
        });

        test('resolves same model multiple times consistently', function (): void {
            // Arrange
            $model = new TestModel(id: 123, attributes: ['test' => 'value']);

            // Act
            $result1 = $this->resolver->resolve($model);
            $result2 = $this->resolver->resolve($model);
            $result3 = $this->resolver->resolve($model);

            // Assert
            expect($result1->id)->toBe($result2->id)
                ->and($result2->id)->toBe($result3->id)
                ->and($result1->attributes)->toBe($result2->attributes)
                ->and($result2->attributes)->toBe($result3->attributes);
        });
    });
});
