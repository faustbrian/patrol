<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Laravel\PolicyVersioning\PolicyVersionRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create patrol_policy_versions table for testing
    DB::statement('
        CREATE TABLE patrol_policy_versions (
            version INTEGER PRIMARY KEY,
            policy TEXT NOT NULL,
            created_at VARCHAR(255) NOT NULL,
            description TEXT,
            metadata TEXT
        )
    ');

    $this->repository = new PolicyVersionRepository(
        table: 'patrol_policy_versions',
        connection: 'testing',
    );
});

afterEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS patrol_policy_versions');
});

describe('PolicyVersionRepository', function (): void {
    describe('Happy Paths', function (): void {
        test('saves policy version with auto-incremented version number', function (): void {
            $policy1 = new Policy([
                new PolicyRule('user:123', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);
            $policy2 = new Policy([
                new PolicyRule('user:456', 'doc:2', 'write', Effect::Deny, new Priority(2)),
            ]);

            $version1 = $this->repository->save($policy1);
            $version2 = $this->repository->save($policy2);

            expect($version1->version)->toBe(1);
            expect($version2->version)->toBe(2);
        });

        test('saves policy version with description and metadata', function (): void {
            $policy = new Policy([
                new PolicyRule('admin', 'secrets', 'read', Effect::Deny, new Priority(1)),
            ]);
            $description = 'Added security restrictions';
            $metadata = ['author' => 'john.doe', 'ticket' => 'SEC-123'];

            $version = $this->repository->save($policy, $description, $metadata);

            expect($version->description)->toBe($description);
            expect($version->metadata)->toBe($metadata);
        });

        test('retrieves specific policy version by version number', function (): void {
            $policy1 = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);
            $policy2 = new Policy([
                new PolicyRule('user:2', 'doc:2', 'write', Effect::Allow, new Priority(1)),
            ]);
            $policy3 = new Policy([
                new PolicyRule('user:3', 'doc:3', 'delete', Effect::Deny, new Priority(1)),
            ]);

            $this->repository->save($policy1);
            $this->repository->save($policy2);
            $this->repository->save($policy3);

            $retrieved = $this->repository->get(2);

            expect($retrieved)->not->toBeNull();
            expect($retrieved->version)->toBe(2);
            expect($retrieved->policy->rules)->toHaveCount(1);
            expect($retrieved->policy->rules[0]->subject)->toBe('user:2');
        });

        test('retrieves latest policy version', function (): void {
            $policy1 = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);
            $policy2 = new Policy([
                new PolicyRule('user:2', 'doc:2', 'write', Effect::Allow, new Priority(1)),
            ]);
            $policy3 = new Policy([
                new PolicyRule('user:3', 'doc:3', 'delete', Effect::Deny, new Priority(1)),
            ]);

            $this->repository->save($policy1);
            $this->repository->save($policy2);

            $version3 = $this->repository->save($policy3);

            $latest = $this->repository->getLatest();

            expect($latest)->not->toBeNull();
            expect($latest->version)->toBe(3);
            expect($latest->policy->rules[0]->subject)->toBe('user:3');
        });

        test('retrieves all policy versions ordered newest first', function (): void {
            $policy1 = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);
            $policy2 = new Policy([
                new PolicyRule('user:2', 'doc:2', 'write', Effect::Allow, new Priority(1)),
            ]);
            $policy3 = new Policy([
                new PolicyRule('user:3', 'doc:3', 'delete', Effect::Deny, new Priority(1)),
            ]);

            $this->repository->save($policy1);
            $this->repository->save($policy2);
            $this->repository->save($policy3);

            $all = $this->repository->getAll();

            expect($all)->toHaveCount(3);
            expect($all[0]->version)->toBe(3);
            expect($all[1]->version)->toBe(2);
            expect($all[2]->version)->toBe(1);
        });

        test('rollback returns requested policy version', function (): void {
            $policy1 = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);
            $policy2 = new Policy([
                new PolicyRule('user:2', 'doc:2', 'write', Effect::Deny, new Priority(1)),
            ]);

            $this->repository->save($policy1);
            $this->repository->save($policy2);

            $rollback = $this->repository->rollback(1);

            expect($rollback)->not->toBeNull();
            expect($rollback->version)->toBe(1);
            expect($rollback->policy->rules[0]->subject)->toBe('user:1');
        });

        test('correctly serializes and deserializes policy object', function (): void {
            $originalPolicy = new Policy([
                new PolicyRule('admin', 'secrets', 'read', Effect::Deny, new Priority(100)),
                new PolicyRule('user:123', 'docs', 'write', Effect::Allow, new Priority(50)),
            ]);

            $version = $this->repository->save($originalPolicy);
            $retrieved = $this->repository->get($version->version);

            expect($retrieved->policy->rules)->toHaveCount(2);
            expect($retrieved->policy->rules[0]->subject)->toBe('admin');
            expect($retrieved->policy->rules[0]->resource)->toBe('secrets');
            expect($retrieved->policy->rules[0]->action)->toBe('read');
            expect($retrieved->policy->rules[0]->effect)->toBe(Effect::Deny);
            expect($retrieved->policy->rules[0]->priority->value)->toBe(100);
            expect($retrieved->policy->rules[1]->subject)->toBe('user:123');
        });
    });

    describe('Sad Paths', function (): void {
        test('returns null when retrieving non-existent version', function (): void {
            $result = $this->repository->get(999);

            expect($result)->toBeNull();
        });

        test('returns null when getting latest from empty table', function (): void {
            $result = $this->repository->getLatest();

            expect($result)->toBeNull();
        });

        test('returns empty array when getting all from empty table', function (): void {
            $result = $this->repository->getAll();

            expect($result)->toBeArray();
            expect($result)->toBeEmpty();
        });

        test('rollback returns null for non-existent version', function (): void {
            $result = $this->repository->rollback(999);

            expect($result)->toBeNull();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty policy with no rules', function (): void {
            $emptyPolicy = new Policy([]);

            $version = $this->repository->save($emptyPolicy);
            $retrieved = $this->repository->get($version->version);

            expect($retrieved)->not->toBeNull();
            expect($retrieved->policy->rules)->toBeEmpty();
        });

        test('handles policy with complex metadata', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);
            $complexMetadata = [
                'author' => 'john.doe',
                'ticket' => 'SEC-123',
                'reviewers' => ['alice', 'bob'],
                'changes' => [
                    'added' => ['rule1', 'rule2'],
                    'removed' => ['rule3'],
                ],
                'unicode' => '文档',
                'nested' => [
                    'deep' => [
                        'value' => 123,
                    ],
                ],
            ];

            $version = $this->repository->save($policy, 'Complex change', $complexMetadata);
            $retrieved = $this->repository->get($version->version);

            expect($retrieved->metadata)->toBe($complexMetadata);
            expect($retrieved->metadata['unicode'])->toBe('文档');
            expect($retrieved->metadata['nested']['deep']['value'])->toBe(123);
        });

        test('handles null description gracefully', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $version = $this->repository->save($policy, null);
            $retrieved = $this->repository->get($version->version);

            expect($retrieved->description)->toBeNull();
        });

        test('handles empty metadata array', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $version = $this->repository->save($policy, 'Test', []);
            $retrieved = $this->repository->get($version->version);

            expect($retrieved->metadata)->toBeArray();
            expect($retrieved->metadata)->toBeEmpty();
        });

        test('uses custom table name correctly', function (): void {
            // Create custom table
            DB::statement('
                CREATE TABLE custom_versions (
                    version INTEGER PRIMARY KEY,
                    policy TEXT NOT NULL,
                    created_at VARCHAR(255) NOT NULL,
                    description TEXT,
                    metadata TEXT
                )
            ');

            $customRepo = new PolicyVersionRepository(
                table: 'custom_versions',
                connection: 'testing',
            );

            $policy = new Policy([
                new PolicyRule('user:123', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $version = $customRepo->save($policy);
            $retrieved = $customRepo->get($version->version);

            expect($retrieved)->not->toBeNull();
            expect($retrieved->version)->toBe(1);

            // Cleanup
            DB::statement('DROP TABLE custom_versions');
        });

        test('handles large policy objects with many rules', function (): void {
            $rules = [];

            for ($i = 1; $i <= 100; ++$i) {
                $rules[] = new PolicyRule(
                    'user:'.$i,
                    'doc:'.$i,
                    'read',
                    Effect::Allow,
                    new Priority($i),
                );
            }

            $largePolicy = new Policy($rules);

            $version = $this->repository->save($largePolicy);
            $retrieved = $this->repository->get($version->version);

            expect($retrieved->policy->rules)->toHaveCount(100);
            expect($retrieved->policy->rules[0]->subject)->toBe('user:1');
            expect($retrieved->policy->rules[99]->subject)->toBe('user:100');
        });

        test('version sequence continues after retrieval', function (): void {
            $policy1 = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);
            $policy2 = new Policy([
                new PolicyRule('user:2', 'doc:2', 'write', Effect::Allow, new Priority(1)),
            ]);

            $version1 = $this->repository->save($policy1);
            $this->repository->get($version1->version); // Retrieve should not affect sequence
            $version2 = $this->repository->save($policy2);

            expect($version1->version)->toBe(1);
            expect($version2->version)->toBe(2);
        });

        test('handles policy with null resources', function (): void {
            $policy = new Policy([
                new PolicyRule('admin', null, 'manage', Effect::Allow, new Priority(1)),
            ]);

            $version = $this->repository->save($policy);
            $retrieved = $this->repository->get($version->version);

            expect($retrieved->policy->rules[0]->resource)->toBeNull();
        });

        test('preserves timestamps across save and retrieve', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $version = $this->repository->save($policy);
            $retrieved = $this->repository->get($version->version);

            expect($retrieved->createdAt)->toBe($version->createdAt);
            expect($retrieved->createdAt)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
        });
    });
});
