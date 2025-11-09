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
use Patrol\Laravel\Repositories\DatabasePolicyRepository;

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
            priority INTEGER NOT NULL,
            domain VARCHAR(255),
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            deleted_at TIMESTAMP
        )
    ');

    $this->repository = new DatabasePolicyRepository(connection: 'testing');
});

afterEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS patrol_policies');
});

describe('DatabasePolicyRepository', function (): void {
    describe('Happy Paths', function (): void {
        test('retrieves policies matching subject and resource', function (): void {
            // Arrange
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
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[0]->resource)->toBe('document:456');
            expect($policy->rules[0]->action)->toBe('read');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('handles wildcard subjects', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => '*',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('*');
        });

        test('handles wildcard resources', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => '*',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->resource)->toBe('*');
        });

        test('handles null resources', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => null,
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->resource)->toBeNull();
        });

        test('orders rules by priority descending', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 100,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'delete',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(3);
            expect($policy->rules[0]->priority->value)->toBe(100);
            expect($policy->rules[1]->priority->value)->toBe(50);
            expect($policy->rules[2]->priority->value)->toBe(1);
        });

        test('retrieves domain-scoped policies', function (): void {
            // Arrange
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
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->domain)->toBeInstanceOf(Domain::class);
            expect($policy->rules[0]->domain->id)->toBe('tenant:1');
        });

        test('retrieves policies without domain when domain is null', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
                'domain' => null,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->domain)->toBeNull();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns empty policy when no rules match', function (): void {
            // Arrange
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
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('returns empty policy when subject does not match', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:999',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('returns empty policy when resource does not match', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:999',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('handles edge case where database returns non-string subject due to type coercion', function (): void {
            // Arrange - Force SQLite type coercion by using INTEGER column type
            // This tests the defensive is_string($subject) guard on line 110-111
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject,
                    resource VARCHAR(255),
                    action VARCHAR(255) NOT NULL,
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');

            // Insert an integer that will match wildcard '*' in WHERE but be returned as int by Laravel
            // SQLite with no type affinity will preserve the integer type
            DB::getPdo()->exec("INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES (999, '*', 'read', 'Allow', 1)");

            // Insert a valid string subject that will definitely match
            DB::getPdo()->exec("INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES ('user:123', 'document:456', 'write', 'Deny', 2)");

            // Also insert an integer matching the exact user ID for comprehensive coverage
            DB::getPdo()->exec("INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES (123, 'document:456', 'delete', 'Allow', 3)");

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert - only string subject should pass the is_string() check
            // Integer subjects should be filtered out by the defensive guard on line 110-111
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[0]->action)->toBe('write');

            // Cleanup - restore original schema
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255) NOT NULL,
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');
        });

        test('filters out multiple non-string subjects while preserving valid rules', function (): void {
            // Arrange - Test that continue statement works correctly in loop with multiple records
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject,
                    resource VARCHAR(255),
                    action VARCHAR(255) NOT NULL,
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');

            // Insert mix of valid and invalid (non-string) subjects
            DB::getPdo()->exec("INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES (111, 'document:456', 'read', 'Allow', 5)");
            DB::getPdo()->exec("INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES ('user:123', 'document:456', 'write', 'Deny', 4)");
            DB::getPdo()->exec("INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES (222, '*', 'delete', 'Allow', 3)");
            DB::getPdo()->exec("INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES ('*', 'document:456', 'execute', 'Allow', 2)");
            DB::getPdo()->exec("INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES (333, 'document:456', 'admin', 'Deny', 1)");

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert - should only return the 2 rules with string subjects, filtering out 3 integer subjects
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[0]->action)->toBe('write');
            expect($policy->rules[1]->subject)->toBe('*');
            expect($policy->rules[1]->action)->toBe('execute');

            // Cleanup
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255) NOT NULL,
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');
        });

        test('skips policy rules with non-string action attribute', function (): void {
            // Arrange - temporarily allow NULL for testing invalid data
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255),
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');

            DB::statement('INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES ("user:123", "document:456", NULL, "Allow", 1)');

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();

            // Cleanup - restore original schema
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255) NOT NULL,
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles multiple rules with same priority', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 50,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'delete',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(3);
            expect($policy->rules[0]->priority->value)->toBe(50);
            expect($policy->rules[1]->priority->value)->toBe(50);
            expect($policy->rules[2]->priority->value)->toBe(50);
        });

        test('combines exact match with wildcard matches', function (): void {
            // Arrange
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
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => '*',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(3);
        });

        test('handles empty table', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('handles both allow and deny effects', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 1,
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
            expect($policy->rules[1]->effect)->toBe(Effect::Deny);
        });

        test('handles multiple domains for same subject and resource', function (): void {
            // Arrange
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
                    'action' => 'read',
                    'effect' => 'Deny',
                    'priority' => 1,
                    'domain' => 'tenant:2',
                ],
            ]);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->domain->id)->toBe('tenant:1');
            expect($policy->rules[1]->domain->id)->toBe('tenant:2');
        });
    });

    describe('Persistence', function (): void {
        test('saves policy rules to database', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
                new PolicyRule(
                    subject: 'user:456',
                    resource: 'document:789',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(10),
                ),
            ]);

            // Act
            $this->repository->save($policy);

            // Assert
            $savedPolicies = DB::table('patrol_policies')->get();
            expect($savedPolicies)->toHaveCount(2);
            expect($savedPolicies[0]->subject)->toBe('user:123');
            expect($savedPolicies[0]->resource)->toBe('document:456');
            expect($savedPolicies[0]->action)->toBe('read');
            expect($savedPolicies[0]->effect)->toBe('Allow');
            expect($savedPolicies[0]->priority)->toBe(1);
            expect($savedPolicies[1]->subject)->toBe('user:456');
            expect($savedPolicies[1]->resource)->toBe('document:789');
            expect($savedPolicies[1]->action)->toBe('write');
            expect($savedPolicies[1]->effect)->toBe('Deny');
            expect($savedPolicies[1]->priority)->toBe(10);
        });

        test('saves policy rules with null resource', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: null,
                    action: 'global',
                    effect: Effect::Allow,
                    priority: new Priority(5),
                ),
            ]);

            // Act
            $this->repository->save($policy);

            // Assert
            $savedPolicy = DB::table('patrol_policies')->first();
            expect($savedPolicy->subject)->toBe('user:123');
            expect($savedPolicy->resource)->toBeNull();
            expect($savedPolicy->action)->toBe('global');
            expect($savedPolicy->effect)->toBe('Allow');
            expect($savedPolicy->priority)->toBe(5);
        });

        test('saves policy rules with domain', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                    domain: new Domain('tenant:1'),
                ),
            ]);

            // Act
            $this->repository->save($policy);

            // Assert
            $savedPolicy = DB::table('patrol_policies')->first();
            expect($savedPolicy->subject)->toBe('user:123');
            expect($savedPolicy->resource)->toBe('document:456');
            expect($savedPolicy->action)->toBe('read');
            expect($savedPolicy->effect)->toBe('Allow');
            expect($savedPolicy->priority)->toBe(1);
            expect($savedPolicy->domain)->toBe('tenant:1');
        });

        test('saves policy rules with null domain', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            // Act
            $this->repository->save($policy);

            // Assert
            $savedPolicy = DB::table('patrol_policies')->first();
            expect($savedPolicy->subject)->toBe('user:123');
            expect($savedPolicy->domain)->toBeNull();
        });

        test('saves multiple policies atomically with saveMany', function (): void {
            // Arrange
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
                ),
                new PolicyRule(
                    subject: 'user:789',
                    resource: 'document:999',
                    action: 'delete',
                    effect: Effect::Allow,
                    priority: new Priority(5),
                    domain: new Domain('tenant:1'),
                ),
            ]);

            // Act
            $this->repository->saveMany([$policy1, $policy2]);

            // Assert
            $savedPolicies = DB::table('patrol_policies')->get();
            expect($savedPolicies)->toHaveCount(3);
            expect($savedPolicies[0]->subject)->toBe('user:123');
            expect($savedPolicies[0]->action)->toBe('read');
            expect($savedPolicies[1]->subject)->toBe('user:456');
            expect($savedPolicies[1]->action)->toBe('write');
            expect($savedPolicies[2]->subject)->toBe('user:789');
            expect($savedPolicies[2]->action)->toBe('delete');
            expect($savedPolicies[2]->domain)->toBe('tenant:1');
        });

        test('saveMany handles empty array', function (): void {
            // Act
            $this->repository->saveMany([]);

            // Assert
            $savedPolicies = DB::table('patrol_policies')->get();
            expect($savedPolicies)->toBeEmpty();
        });

        test('saveMany includes timestamps', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            // Act
            $this->repository->saveMany([$policy]);

            // Assert
            $savedPolicy = DB::table('patrol_policies')->first();
            expect($savedPolicy->created_at)->not->toBeNull();
            expect($savedPolicy->updated_at)->not->toBeNull();
        });

        test('deletes multiple policies by IDs with deleteMany', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                ['id' => 1, 'subject' => 'user:123', 'resource' => 'doc:1', 'action' => 'read', 'effect' => 'Allow', 'priority' => 1],
                ['id' => 2, 'subject' => 'user:456', 'resource' => 'doc:2', 'action' => 'write', 'effect' => 'Deny', 'priority' => 1],
                ['id' => 3, 'subject' => 'user:789', 'resource' => 'doc:3', 'action' => 'delete', 'effect' => 'Allow', 'priority' => 1],
            ]);

            // Act
            $this->repository->deleteMany([1, 3]);

            // Assert
            $remainingPolicies = DB::table('patrol_policies')->get();
            expect($remainingPolicies)->toHaveCount(1);
            expect($remainingPolicies[0]->id)->toBe(2);
            expect($remainingPolicies[0]->subject)->toBe('user:456');
        });

        test('deleteMany handles empty array', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                ['subject' => 'user:123', 'resource' => 'doc:1', 'action' => 'read', 'effect' => 'Allow', 'priority' => 1],
            ]);

            // Act
            $this->repository->deleteMany([]);

            // Assert
            $remainingPolicies = DB::table('patrol_policies')->get();
            expect($remainingPolicies)->toHaveCount(1);
        });
    });

    describe('Soft Delete Operations', function (): void {
        test('soft deletes a policy rule', function (): void {
            // Arrange
            $id = DB::table('patrol_policies')->insertGetId([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act
            $this->repository->softDelete((string) $id);

            // Assert - soft deleted policy should have deleted_at timestamp
            $policy = DB::table('patrol_policies')->find($id);
            expect($policy->deleted_at)->not->toBeNull();

            // Assert - soft deleted policies are excluded from normal queries
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');
            $activePolicies = $this->repository->getPoliciesFor($subject, $resource);
            expect($activePolicies->rules)->toBeEmpty();
        });

        test('restores a soft deleted policy rule', function (): void {
            // Arrange
            $id = DB::table('patrol_policies')->insertGetId([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
                'deleted_at' => now(),
            ]);

            // Act
            $this->repository->restore((string) $id);

            // Assert - restored policy should have null deleted_at
            $policy = DB::table('patrol_policies')->find($id);
            expect($policy->deleted_at)->toBeNull();

            // Assert - restored policies appear in normal queries
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');
            $activePolicies = $this->repository->getPoliciesFor($subject, $resource);
            expect($activePolicies->rules)->toHaveCount(1);
        });

        test('force deletes permanently remove policy rule', function (): void {
            // Arrange
            $id = DB::table('patrol_policies')->insertGetId([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
                'deleted_at' => now(),
            ]);

            // Act
            $this->repository->forceDelete((string) $id);

            // Assert - policy should be permanently removed from database
            $policy = DB::table('patrol_policies')->find($id);
            expect($policy)->toBeNull();
        });

        test('getTrashed returns only soft deleted policies', function (): void {
            // Arrange
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
                [
                    'subject' => 'user:789',
                    'resource' => 'document:999',
                    'action' => 'delete',
                    'effect' => 'Allow',
                    'priority' => 3,
                    'deleted_at' => now(),
                ],
            ]);

            // Act
            $trashed = $this->repository->getTrashed();

            // Assert
            expect($trashed->rules)->toHaveCount(2);
            expect($trashed->rules[0]->subject)->toBe('user:789'); // Priority 3 (highest)
            expect($trashed->rules[1]->subject)->toBe('user:456'); // Priority 2
        });

        test('getWithTrashed returns both active and soft deleted policies', function (): void {
            // Arrange
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
            $all = $this->repository->getWithTrashed($subject, $resource);

            // Assert
            expect($all->rules)->toHaveCount(2);
            expect($all->rules[0]->action)->toBe('write'); // Priority 2 (higher)
            expect($all->rules[1]->action)->toBe('read');  // Priority 1
        });

        test('normal queries exclude soft deleted policies', function (): void {
            // Arrange
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
            $active = $this->repository->getPoliciesFor($subject, $resource);

            // Assert - should only return active policy
            expect($active->rules)->toHaveCount(1);
            expect($active->rules[0]->action)->toBe('read');
        });

        test('getTrashed skips policy rules with non-string action attribute', function (): void {
            // Arrange - temporarily allow NULL action for testing invalid data
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255),
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');

            DB::statement('INSERT INTO patrol_policies (subject, resource, action, effect, priority, deleted_at) VALUES ("user:123", "document:456", NULL, "Allow", 1, datetime("now"))');
            DB::statement('INSERT INTO patrol_policies (subject, resource, action, effect, priority, deleted_at) VALUES ("user:456", "document:789", "write", "Deny", 2, datetime("now"))');

            // Act
            $trashed = $this->repository->getTrashed();

            // Assert - should only return policy with valid string action
            expect($trashed->rules)->toHaveCount(1);
            expect($trashed->rules[0]->subject)->toBe('user:456');
            expect($trashed->rules[0]->action)->toBe('write');

            // Cleanup - restore original schema
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255) NOT NULL,
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');
        });

        test('getWithTrashed skips policy rules with non-string action attribute', function (): void {
            // Arrange - temporarily allow NULL action for testing invalid data
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255),
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');

            DB::statement('INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES ("user:123", "document:456", NULL, "Allow", 1)');
            DB::statement('INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES ("user:123", "document:456", "write", "Deny", 2)');

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $all = $this->repository->getWithTrashed($subject, $resource);

            // Assert - should only return policy with valid string action
            expect($all->rules)->toHaveCount(1);
            expect($all->rules[0]->action)->toBe('write');

            // Cleanup - restore original schema
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255) NOT NULL,
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');
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
                [
                    'subject' => 'user:1',
                    'resource' => 'doc:3',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
                new Resource('doc:3', 'document'),
            ];

            $policies = $this->repository->getPoliciesForBatch($subject, $resources);

            expect($policies)->toHaveCount(3);
            expect($policies['doc:1']->rules)->toHaveCount(1);
            expect($policies['doc:1']->rules[0]->action)->toBe('read');
            expect($policies['doc:2']->rules)->toHaveCount(1);
            expect($policies['doc:2']->rules[0]->effect)->toBe(Effect::Deny);
            expect($policies['doc:3']->rules)->toHaveCount(1);
            expect($policies['doc:3']->rules[0]->action)->toBe('write');
        });

        test('applies wildcard resources to all requested resources', function (): void {
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:1',
                    'resource' => '*',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
            ];

            $policies = $this->repository->getPoliciesForBatch($subject, $resources);

            expect($policies['doc:1']->rules)->toHaveCount(1);
            expect($policies['doc:1']->rules[0]->resource)->toBe('*');
            expect($policies['doc:2']->rules)->toHaveCount(1);
            expect($policies['doc:2']->rules[0]->resource)->toBe('*');
        });

        test('applies null resources to all requested resources', function (): void {
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:1',
                    'resource' => null,
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
            ];

            $policies = $this->repository->getPoliciesForBatch($subject, $resources);

            expect($policies['doc:1']->rules)->toHaveCount(1);
            expect($policies['doc:1']->rules[0]->resource)->toBeNull();
            expect($policies['doc:2']->rules)->toHaveCount(1);
            expect($policies['doc:2']->rules[0]->resource)->toBeNull();
        });

        test('handles empty resources array', function (): void {
            $subject = new Subject('user:1');

            $policies = $this->repository->getPoliciesForBatch($subject, []);

            expect($policies)->toBe([]);
        });

        test('returns empty policies for resources with no rules', function (): void {
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:1',
                    'resource' => 'doc:1',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
                new Resource('doc:3', 'document'),
            ];

            $policies = $this->repository->getPoliciesForBatch($subject, $resources);

            expect($policies['doc:1']->rules)->toHaveCount(1);
            expect($policies['doc:2']->rules)->toHaveCount(0);
            expect($policies['doc:3']->rules)->toHaveCount(0);
        });

        test('combines specific and wildcard rules', function (): void {
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:1',
                    'resource' => '*',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:1',
                    'resource' => 'doc:1',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 2,
                ],
            ]);

            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
            ];

            $policies = $this->repository->getPoliciesForBatch($subject, $resources);

            expect($policies['doc:1']->rules)->toHaveCount(2);
            expect($policies['doc:2']->rules)->toHaveCount(1);
            expect($policies['doc:2']->rules[0]->resource)->toBe('*');
        });

        test('handles wildcard subjects in batch', function (): void {
            DB::table('patrol_policies')->insert([
                [
                    'subject' => '*',
                    'resource' => 'doc:1',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
            ];

            $policies = $this->repository->getPoliciesForBatch($subject, $resources);

            expect($policies['doc:1']->rules)->toHaveCount(1);
            expect($policies['doc:1']->rules[0]->subject)->toBe('*');
        });

        test('getPoliciesForBatch skips policy rules with non-string action attribute', function (): void {
            // Arrange - temporarily allow NULL action for testing invalid data
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255),
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');

            DB::statement('INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES ("user:1", "doc:1", NULL, "Allow", 1)');
            DB::statement('INSERT INTO patrol_policies (subject, resource, action, effect, priority) VALUES ("user:1", "doc:2", "write", "Deny", 2)');

            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
            ];

            // Act
            $policies = $this->repository->getPoliciesForBatch($subject, $resources);

            // Assert - doc:1 should have no rules (action was NULL), doc:2 should have valid rule
            expect($policies['doc:1']->rules)->toHaveCount(0);
            expect($policies['doc:2']->rules)->toHaveCount(1);
            expect($policies['doc:2']->rules[0]->action)->toBe('write');

            // Cleanup - restore original schema
            DB::statement('DROP TABLE IF EXISTS patrol_policies');
            DB::statement('
                CREATE TABLE patrol_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject VARCHAR(255) NOT NULL,
                    resource VARCHAR(255),
                    action VARCHAR(255) NOT NULL,
                    effect VARCHAR(10) NOT NULL,
                    priority INTEGER NOT NULL,
                    domain VARCHAR(255),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');
        });
    });
});
