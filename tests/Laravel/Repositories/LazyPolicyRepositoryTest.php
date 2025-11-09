<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Repositories\LazyPolicyRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create the patrol_policies table for testing
    DB::statement('
        CREATE TABLE patrol_policies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject VARCHAR(255) NOT NULL,
            resource VARCHAR(255),
            action VARCHAR(255) NOT NULL,
            effect VARCHAR(10) NOT NULL,
            priority INTEGER NOT NULL DEFAULT 1,
            domain VARCHAR(255),
            deleted_at TIMESTAMP
        )
    ');
});

afterEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS patrol_policies');
});

describe('LazyPolicyRepository', function (): void {
    describe('Happy Paths', function (): void {
        test('loads policies with default chunk size', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[0]->resource)->toBe('document:456');
        });

        test('loads policies with custom chunk size of 10', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 10);

            // Insert 25 policies
            $policies = [];

            for ($i = 1; $i <= 25; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(25);
        });

        test('loads policies with custom chunk size of 50', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 50);

            // Insert 75 policies
            $policies = [];

            for ($i = 1; $i <= 75; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(75);
        });

        test('filters by subject type successfully', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 100,
                ],
                [
                    'subject' => '*',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
                [
                    'subject' => 'admin:789',
                    'resource' => 'document:456',
                    'action' => 'delete',
                    'effect' => 'Allow',
                    'priority' => 25,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForSubjectType('user', $subject, $resource);

            // Assert - Should get user:123 and * (wildcard matches all types)
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[1]->subject)->toBe('*');
        });

        test('filters by subject type with chunking', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 5);

            // Insert 15 policies with user: subject type matching user:1 or *
            $policies = [];

            for ($i = 1; $i <= 15; ++$i) {
                $policies[] = [
                    'subject' => $i === 1 ? 'user:1' : '*',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:1');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForSubjectType('user', $subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(15);
        });

        test('filters by resource type successfully', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 100,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => '*',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'file:999',
                    'action' => 'delete',
                    'effect' => 'Allow',
                    'priority' => 25,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForResourceType('document', $subject, $resource);

            // Assert - Should get document:456 and * (wildcard matches all types)
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->resource)->toBe('document:456');
            expect($policy->rules[1]->resource)->toBe('*');
        });

        test('filters by resource type with chunking', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 5);

            // Insert 20 policies with document resource type matching document:1 or *
            $policies = [];

            for ($i = 1; $i <= 20; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => $i === 1 ? 'document:1' : '*',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:1', 'document');

            // Act
            $policy = $repository->getPoliciesForResourceType('document', $subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(20);
        });

        test('filters by specific domain', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                    'domain' => 'tenant:1',
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 1,
                    'domain' => 'tenant:2',
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'delete',
                    'effect' => 'Allow',
                    'priority' => 1,
                    'domain' => null,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForDomain('tenant:1', $subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->domain?->id)->toBe('tenant:1');
            expect($policy->rules[1]->domain)->toBeNull();
        });

        test('filters for null domain returns only domain-agnostic policies', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                    'domain' => 'tenant:1',
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 1,
                    'domain' => null,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForDomain(null, $subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->domain)->toBeNull();
        });

        test('filters by domain with chunking', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 5);

            // Insert 15 policies for tenant:1 domain
            $policies = [];

            for ($i = 1; $i <= 15; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                    'domain' => 'tenant:1',
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForDomain('tenant:1', $subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(15);
            expect($policy->rules[0]->domain?->id)->toBe('tenant:1');
        });

        test('handles chunking with large datasets over 200 records', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 50);

            // Insert 250 policies
            $policies = [];

            for ($i = 1; $i <= 250; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => $i % 2 === 0 ? 'Allow' : 'Deny',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(250);
        });

        test('preserves priority ordering across chunks', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 10);

            // Insert 30 policies with varying priorities
            $policies = [];

            for ($i = 1; $i <= 30; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => 100 - $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(30);
            expect($policy->rules[0]->priority->value)->toBe(99);
            expect($policy->rules[29]->priority->value)->toBe(70);

            // Verify ordering is maintained
            for ($i = 0; $i < 29; ++$i) {
                expect($policy->rules[$i]->priority->value)->toBeGreaterThanOrEqual($policy->rules[$i + 1]->priority->value);
            }
        });

        test('handles wildcard subjects with subject type filtering', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 100,
                ],
                [
                    'subject' => '*',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForSubjectType('user', $subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[1]->subject)->toBe('*');
        });

        test('handles wildcard resources with resource type filtering', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 100,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => '*',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => null,
                    'action' => 'delete',
                    'effect' => 'Allow',
                    'priority' => 25,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForResourceType('document', $subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(3);
            expect($policy->rules[0]->resource)->toBe('document:456');
            expect($policy->rules[1]->resource)->toBe('*');
            expect($policy->rules[2]->resource)->toBeNull();
        });

        test('handles mixed domain and non-domain policies', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                    'domain' => 'tenant:1',
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 1,
                    'domain' => null,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->domain)->toBeInstanceOf(Domain::class);
            expect($policy->rules[1]->domain)->toBeNull();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns empty policy when no matching policies exist', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                'subject' => 'user:999',
                'resource' => 'document:999',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('returns empty policy when invalid subject type provided', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForSubjectType('admin', $subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('returns empty policy when invalid resource type provided', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForResourceType('file', $subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('returns empty policy when non-existent domain provided', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
                'domain' => 'tenant:1',
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForDomain('tenant:999', $subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles chunk size of 1 for extreme pagination', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 1);

            // Insert 10 policies
            $policies = [];

            for ($i = 1; $i <= 10; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(10);
        });

        test('handles exact chunk boundary with 100 records and chunk size 100', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            // Insert exactly 100 policies
            $policies = [];

            for ($i = 1; $i <= 100; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(100);
        });

        test('handles policies spanning multiple chunks with priority ordering', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 25);

            // Insert 80 policies with random priorities
            $policies = [];

            for ($i = 1; $i <= 80; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => ($i * 13) % 100,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(80);

            // Verify ordering is maintained across chunks
            for ($i = 0; $i < 79; ++$i) {
                expect($policy->rules[$i]->priority->value)->toBeGreaterThanOrEqual($policy->rules[$i + 1]->priority->value);
            }
        });

        test('handles very large chunk size of 1000', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 1_000);

            // Insert 50 policies
            $policies = [];

            for ($i = 1; $i <= 50; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(50);
        });

        test('handles subject type with special characters', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user-admin:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user_guest:456',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            $subject = new Subject('user-admin:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesForSubjectType('user-admin', $subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('user-admin:123');
        });

        test('handles resource type with special characters', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document-type:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'file_type:789',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document-type:456', 'document-type');

            // Act
            $policy = $repository->getPoliciesForResourceType('document-type', $subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->resource)->toBe('document-type:456');
        });

        test('handles empty table', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('handles multiple chunks with wildcard matches', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 10);

            // Insert mix of exact and wildcard matches
            $policies = [];

            for ($i = 1; $i <= 30; ++$i) {
                $policies[] = [
                    'subject' => $i % 3 === 0 ? '*' : 'user:123',
                    'resource' => $i % 2 === 0 ? '*' : 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(30);
        });
    });

    describe('Save Operations', function (): void {
        test('saves policy with single rule to database', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(10),
                    domain: new Domain('tenant:1'),
                ),
            ]);

            // Act
            $repository->save($policy);

            // Assert
            $records = DB::table('patrol_policies')->get();
            expect($records)->toHaveCount(1);
            expect($records[0]->subject)->toBe('user:123');
            expect($records[0]->resource)->toBe('document:456');
            expect($records[0]->action)->toBe('read');
            expect($records[0]->effect)->toBe('Allow');
            expect($records[0]->priority)->toBe(10);
            expect($records[0]->domain)->toBe('tenant:1');
        });

        test('saves policy with multiple rules to database', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(10),
                ),
                new PolicyRule(
                    subject: 'user:456',
                    resource: 'file:789',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(5),
                ),
            ]);

            // Act
            $repository->save($policy);

            // Assert
            $records = DB::table('patrol_policies')->get();
            expect($records)->toHaveCount(2);
            expect($records[0]->subject)->toBe('user:123');
            expect($records[1]->subject)->toBe('user:456');
        });

        test('saves policy with null domain correctly', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(10),
                ),
            ]);

            // Act
            $repository->save($policy);

            // Assert
            $records = DB::table('patrol_policies')->get();
            expect($records)->toHaveCount(1);
            expect($records[0]->domain)->toBeNull();
        });
    });

    describe('Regressions', function (): void {
        test('chunking does not skip records between boundaries', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 7);

            // Insert 50 policies with sequential actions to detect skips
            $policies = [];

            for ($i = 1; $i <= 50; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action_'.$i,
                    'effect' => 'Allow',
                    'priority' => 100,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(50);

            // Verify all actions are present
            $actions = array_map(fn (PolicyRule $rule): string => $rule->action, $policy->rules);

            for ($i = 1; $i <= 50; ++$i) {
                expect($actions)->toContain('action_'.$i);
            }
        });

        test('priority order maintained across all chunks', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 15);

            // Insert 100 policies with varying priorities
            $policies = [];

            for ($i = 1; $i <= 100; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => 200 - $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(100);

            // Verify strict descending order
            expect($policy->rules[0]->priority->value)->toBe(199);
            expect($policy->rules[99]->priority->value)->toBe(100);

            for ($i = 0; $i < 99; ++$i) {
                expect($policy->rules[$i]->priority->value)->toBeGreaterThan($policy->rules[$i + 1]->priority->value);
            }
        });

        test('produces same results as non-lazy loading for small datasets', function (): void {
            // Arrange
            $lazyRepository = new LazyPolicyRepository(connection: 'testing', chunkSize: 10);
            $nonLazyRepository = new LazyPolicyRepository(connection: 'testing', chunkSize: 1_000);

            // Insert 20 policies
            $policies = [];

            for ($i = 1; $i <= 20; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => $i % 2 === 0 ? 'Allow' : 'Deny',
                    'priority' => ($i * 7) % 50,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $lazyPolicy = $lazyRepository->getPoliciesFor($subject, $resource);
            $nonLazyPolicy = $nonLazyRepository->getPoliciesFor($subject, $resource);

            // Assert
            expect($lazyPolicy->rules)->toHaveCount(20);
            expect($nonLazyPolicy->rules)->toHaveCount(20);

            // Verify same order and content
            for ($i = 0; $i < 20; ++$i) {
                expect($lazyPolicy->rules[$i]->subject)->toBe($nonLazyPolicy->rules[$i]->subject);
                expect($lazyPolicy->rules[$i]->resource)->toBe($nonLazyPolicy->rules[$i]->resource);
                expect($lazyPolicy->rules[$i]->action)->toBe($nonLazyPolicy->rules[$i]->action);
                expect($lazyPolicy->rules[$i]->effect)->toBe($nonLazyPolicy->rules[$i]->effect);
                expect($lazyPolicy->rules[$i]->priority->value)->toBe($nonLazyPolicy->rules[$i]->priority->value);
            }
        });

        test('chunk size does not affect final result count or order', function (): void {
            // Arrange
            $repo10 = new LazyPolicyRepository(connection: 'testing', chunkSize: 10);
            $repo25 = new LazyPolicyRepository(connection: 'testing', chunkSize: 25);
            $repo50 = new LazyPolicyRepository(connection: 'testing', chunkSize: 50);

            // Insert 75 policies
            $policies = [];

            for ($i = 1; $i <= 75; ++$i) {
                $policies[] = [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => 150 - $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy10 = $repo10->getPoliciesFor($subject, $resource);
            $policy25 = $repo25->getPoliciesFor($subject, $resource);
            $policy50 = $repo50->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy10->rules)->toHaveCount(75);
            expect($policy25->rules)->toHaveCount(75);
            expect($policy50->rules)->toHaveCount(75);

            // Verify all produce identical results
            for ($i = 0; $i < 75; ++$i) {
                expect($policy10->rules[$i]->action)->toBe($policy25->rules[$i]->action);
                expect($policy10->rules[$i]->action)->toBe($policy50->rules[$i]->action);
                expect($policy10->rules[$i]->priority->value)->toBe($policy25->rules[$i]->priority->value);
                expect($policy10->rules[$i]->priority->value)->toBe($policy50->rules[$i]->priority->value);
            }
        });

        test('saveMany inserts multiple policies atomically', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            $policy1 = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $policy2 = new Policy([
                new PolicyRule(
                    subject: 'user:456',
                    resource: 'document:789',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(10),
                    domain: new Domain('tenant:1'),
                ),
                new PolicyRule(
                    subject: 'user:789',
                    resource: 'document:999',
                    action: 'delete',
                    effect: Effect::Allow,
                    priority: new Priority(5),
                ),
            ]);

            // Act
            $repository->saveMany([$policy1, $policy2]);

            // Assert
            $savedPolicies = DB::table('patrol_policies')->get();
            expect($savedPolicies)->toHaveCount(3);
            expect($savedPolicies[0]->subject)->toBe('user:123');
            expect($savedPolicies[0]->action)->toBe('read');
            expect($savedPolicies[1]->subject)->toBe('user:456');
            expect($savedPolicies[1]->action)->toBe('write');
            expect($savedPolicies[1]->domain)->toBe('tenant:1');
            expect($savedPolicies[2]->subject)->toBe('user:789');
            expect($savedPolicies[2]->action)->toBe('delete');
        });

        test('saveMany handles empty array', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            // Act
            $repository->saveMany([]);

            // Assert
            $savedPolicies = DB::table('patrol_policies')->get();
            expect($savedPolicies)->toBeEmpty();
        });

        test('deleteMany removes multiple policies by IDs', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            DB::table('patrol_policies')->insert([
                ['id' => 1, 'subject' => 'user:123', 'resource' => 'doc:1', 'action' => 'read', 'effect' => 'Allow', 'priority' => 1],
                ['id' => 2, 'subject' => 'user:456', 'resource' => 'doc:2', 'action' => 'write', 'effect' => 'Deny', 'priority' => 1],
                ['id' => 3, 'subject' => 'user:789', 'resource' => 'doc:3', 'action' => 'delete', 'effect' => 'Allow', 'priority' => 1],
            ]);

            // Act
            $repository->deleteMany([1, 3]);

            // Assert
            $remainingPolicies = DB::table('patrol_policies')->get();
            expect($remainingPolicies)->toHaveCount(1);
            expect($remainingPolicies[0]->id)->toBe(2);
            expect($remainingPolicies[0]->subject)->toBe('user:456');
        });

        test('deleteMany handles empty array', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            DB::table('patrol_policies')->insert([
                ['subject' => 'user:123', 'resource' => 'doc:1', 'action' => 'read', 'effect' => 'Allow', 'priority' => 1],
            ]);

            // Act
            $repository->deleteMany([]);

            // Assert
            $remainingPolicies = DB::table('patrol_policies')->get();
            expect($remainingPolicies)->toHaveCount(1);
        });

        test('soft deletes a policy rule', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            $id = DB::table('patrol_policies')->insertGetId([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act
            $repository->softDelete((string) $id);

            // Assert
            $policy = DB::table('patrol_policies')->find($id);
            expect($policy->deleted_at)->not->toBeNull();
        });

        test('restores a soft deleted policy rule', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            $id = DB::table('patrol_policies')->insertGetId([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
                'deleted_at' => now(),
            ]);

            // Act
            $repository->restore((string) $id);

            // Assert
            $policy = DB::table('patrol_policies')->find($id);
            expect($policy->deleted_at)->toBeNull();
        });

        test('force deletes permanently remove policy rule', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            $id = DB::table('patrol_policies')->insertGetId([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
                'deleted_at' => now(),
            ]);

            // Act
            $repository->forceDelete((string) $id);

            // Assert
            $policy = DB::table('patrol_policies')->find($id);
            expect($policy)->toBeNull();
        });

        test('getTrashed returns only soft deleted policies', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                    'deleted_at' => null,
                ],
                [
                    'subject' => 'user:456',
                    'resource' => 'document:789',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 2,
                    'deleted_at' => now(),
                ],
            ]);

            // Act
            $trashed = $repository->getTrashed();

            // Assert
            expect($trashed->rules)->toHaveCount(1);
            expect($trashed->rules[0]->subject)->toBe('user:456');
        });

        test('getTrashed handles chunking with multiple soft deleted policies', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 5);

            // Insert 20 soft deleted policies
            $policies = [];

            for ($i = 1; $i <= 20; ++$i) {
                $policies[] = [
                    'subject' => 'user:'.$i,
                    'resource' => 'document:'.$i,
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                    'deleted_at' => now(),
                ];
            }

            // Insert 5 active policies
            for ($i = 21; $i <= 25; ++$i) {
                $policies[] = [
                    'subject' => 'user:'.$i,
                    'resource' => 'document:'.$i,
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                    'deleted_at' => null,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            // Act
            $trashed = $repository->getTrashed();

            // Assert
            expect($trashed->rules)->toHaveCount(20);
        });

        test('getWithTrashed returns both active and soft deleted policies', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                    'deleted_at' => null,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 2,
                    'deleted_at' => now(),
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $all = $repository->getWithTrashed($subject, $resource);

            // Assert
            expect($all->rules)->toHaveCount(2);
        });
    });

    describe('Batch Operations', function (): void {
        test('retrieves policies for multiple resources', function (): void {
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:1',
                    'resource' => 'doc:1',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:1',
                    'resource' => 'doc:2',
                    'action' => 'read',
                    'effect' => 'Deny',
                    'priority' => 1,
                ],
            ]);

            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);
            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
            ];

            $policies = $repository->getPoliciesForBatch($subject, $resources);

            expect($policies)->toHaveCount(2);
            expect($policies['doc:1']->rules)->toHaveCount(1);
            expect($policies['doc:2']->rules)->toHaveCount(1);
        });

        test('processes batch with chunking for large datasets', function (): void {
            // Arrange
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 10);

            // Insert 30 policies across 3 resources
            $policies = [];

            for ($i = 1; $i <= 30; ++$i) {
                $policies[] = [
                    'subject' => 'user:1',
                    'resource' => 'doc:'.((($i - 1) % 3) + 1),
                    'action' => 'action'.$i,
                    'effect' => 'Allow',
                    'priority' => $i,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
                new Resource('doc:3', 'document'),
            ];

            // Act
            $result = $repository->getPoliciesForBatch($subject, $resources);

            // Assert
            expect($result)->toHaveCount(3);
            expect($result['doc:1']->rules)->toHaveCount(10);
            expect($result['doc:2']->rules)->toHaveCount(10);
            expect($result['doc:3']->rules)->toHaveCount(10);
        });

        test('applies wildcard resources to all', function (): void {
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:1',
                    'resource' => '*',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);
            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
            ];

            $policies = $repository->getPoliciesForBatch($subject, $resources);

            expect($policies['doc:1']->rules)->toHaveCount(1);
            expect($policies['doc:2']->rules)->toHaveCount(1);
        });

        test('handles empty resources array', function (): void {
            $repository = new LazyPolicyRepository(connection: 'testing', chunkSize: 100);

            $policies = $repository->getPoliciesForBatch(
                new Subject('user:1'),
                [],
            );

            expect($policies)->toBe([]);
        });
    });
});
