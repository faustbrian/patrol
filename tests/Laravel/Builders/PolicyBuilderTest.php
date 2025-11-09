<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Laravel\Builders\PolicyBuilder;

describe('PolicyBuilder', function (): void {
    describe('Happy Paths', function (): void {
        test('builds simple allow rule', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('editor')
                ->on('document:123')
                ->allow('edit')
                ->build();

            // Assert
            expect($policy)->toBeInstanceOf(Policy::class);
            expect($policy->rules)->toHaveCount(1);

            $rule = $policy->rules[0];
            expect($rule)->toBeInstanceOf(PolicyRule::class);
            expect($rule->subject)->toBe('editor');
            expect($rule->resource)->toBe('document:123');
            expect($rule->action)->toBe('edit');
            expect($rule->effect)->toBe(Effect::Allow);
            expect($rule->priority->value)->toBe(1);
            expect($rule->domain)->toBeNull();
        });

        test('builds simple deny rule', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('guest')
                ->on('admin:panel')
                ->deny('access')
                ->build();

            // Assert
            expect($policy)->toBeInstanceOf(Policy::class);
            expect($policy->rules)->toHaveCount(1);

            $rule = $policy->rules[0];
            expect($rule)->toBeInstanceOf(PolicyRule::class);
            expect($rule->subject)->toBe('guest');
            expect($rule->resource)->toBe('admin:panel');
            expect($rule->action)->toBe('access');
            expect($rule->effect)->toBe(Effect::Deny);
            expect($rule->priority->value)->toBe(1);
            expect($rule->domain)->toBeNull();
        });

        test('builds rule with priority', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('admin')
                ->on('system:config')
                ->withPriority(100)
                ->allow('modify')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);

            $rule = $policy->rules[0];
            expect($rule->subject)->toBe('admin');
            expect($rule->resource)->toBe('system:config');
            expect($rule->action)->toBe('modify');
            expect($rule->effect)->toBe(Effect::Allow);
            expect($rule->priority)->toBeInstanceOf(Priority::class);
            expect($rule->priority->value)->toBe(100);
        });

        test('builds rule with domain', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('user:123')
                ->on('document:456')
                ->inDomain('tenant-1')
                ->allow('read')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);

            $rule = $policy->rules[0];
            expect($rule->subject)->toBe('user:123');
            expect($rule->resource)->toBe('document:456');
            expect($rule->action)->toBe('read');
            expect($rule->effect)->toBe(Effect::Allow);
            expect($rule->domain)->toBeInstanceOf(Domain::class);
            expect($rule->domain->id)->toBe('tenant-1');
        });

        test('builds rule without resource (null)', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('moderator')
                ->on(null)
                ->allow('moderate')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);

            $rule = $policy->rules[0];
            expect($rule->subject)->toBe('moderator');
            expect($rule->resource)->toBeNull();
            expect($rule->action)->toBe('moderate');
            expect($rule->effect)->toBe(Effect::Allow);
        });

        test('builds multiple rules in sequence', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('editor')
                ->on('document:*')
                ->allow('read')
                ->for('editor')
                ->on('document:*')
                ->allow('write')
                ->for('admin')
                ->on('document:*')
                ->allow('delete')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(3);

            expect($policy->rules[0]->subject)->toBe('editor');
            expect($policy->rules[0]->action)->toBe('read');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);

            expect($policy->rules[1]->subject)->toBe('editor');
            expect($policy->rules[1]->action)->toBe('write');
            expect($policy->rules[1]->effect)->toBe(Effect::Allow);

            expect($policy->rules[2]->subject)->toBe('admin');
            expect($policy->rules[2]->action)->toBe('delete');
            expect($policy->rules[2]->effect)->toBe(Effect::Allow);
        });

        test('chains multiple allow/deny calls', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('user:123')
                ->on('document:456')
                ->allow('read')
                ->deny('delete')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);

            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[0]->resource)->toBe('document:456');
            expect($policy->rules[0]->action)->toBe('read');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);

            expect($policy->rules[1]->subject)->toBe('user:123');
            expect($policy->rules[1]->resource)->toBe('document:456');
            expect($policy->rules[1]->action)->toBe('delete');
            expect($policy->rules[1]->effect)->toBe(Effect::Deny);
        });
    });

    describe('Sad Paths', function (): void {
        test('building without setting subject throws exception', function (): void {
            // Arrange
            $builder = PolicyBuilder::make();

            // Act & Assert
            expect(fn (): Policy => $builder->on('document:123')->allow('read')->build())
                ->toThrow(InvalidArgumentException::class, 'Subject must be set before adding a rule');
        });

        test('building with subject but no action returns empty policy', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('editor')
                ->build();

            // Assert
            expect($policy)->toBeInstanceOf(Policy::class);
            expect($policy->rules)->toBeEmpty();
        });

        test('deny without subject throws exception', function (): void {
            // Arrange
            $builder = PolicyBuilder::make();

            // Act & Assert
            expect(fn (): Policy => $builder->on('document:123')->deny('delete')->build())
                ->toThrow(InvalidArgumentException::class, 'Subject must be set before adding a rule');
        });

        test('allow without subject throws exception', function (): void {
            // Arrange
            $builder = PolicyBuilder::make();

            // Act & Assert
            expect(fn (): Policy => $builder->on('document:123')->allow('read')->build())
                ->toThrow(InvalidArgumentException::class, 'Subject must be set before adding a rule');
        });
    });

    describe('Edge Cases', function (): void {
        test('wildcard subject (*)', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('*')
                ->on('public:resource')
                ->allow('read')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('*');
            expect($policy->rules[0]->resource)->toBe('public:resource');
            expect($policy->rules[0]->action)->toBe('read');
        });

        test('wildcard resource (*)', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('admin')
                ->on('*')
                ->allow('*')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('admin');
            expect($policy->rules[0]->resource)->toBe('*');
            expect($policy->rules[0]->action)->toBe('*');
        });

        test('very high priority values', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('system')
                ->on('critical:resource')
                ->withPriority(999_999)
                ->deny('access')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->priority->value)->toBe(999_999);
        });

        test('special characters in domain names', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('user:123')
                ->on('document:456')
                ->inDomain('tenant-org_2024.example.com')
                ->allow('access')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->domain)->toBeInstanceOf(Domain::class);
            expect($policy->rules[0]->domain->id)->toBe('tenant-org_2024.example.com');
        });

        test('empty policy (no rules added)', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()->build();

            // Assert
            expect($policy)->toBeInstanceOf(Policy::class);
            expect($policy->rules)->toBeEmpty();
        });

        test('unicode characters in subject and resource', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('user:josé@example.com')
                ->on('document:文档123')
                ->allow('read')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('user:josé@example.com');
            expect($policy->rules[0]->resource)->toBe('document:文档123');
        });

        test('complex chaining with priority and domain', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('editor')
                ->on('document:*')
                ->withPriority(10)
                ->inDomain('tenant-1')
                ->allow('edit')
                ->for('admin')
                ->on('document:*')
                ->withPriority(20)
                ->inDomain('tenant-2')
                ->deny('delete')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);

            expect($policy->rules[0]->subject)->toBe('editor');
            expect($policy->rules[0]->priority->value)->toBe(10);
            expect($policy->rules[0]->domain->id)->toBe('tenant-1');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);

            expect($policy->rules[1]->subject)->toBe('admin');
            expect($policy->rules[1]->priority->value)->toBe(20);
            expect($policy->rules[1]->domain->id)->toBe('tenant-2');
            expect($policy->rules[1]->effect)->toBe(Effect::Deny);
        });
    });

    describe('Regressions', function (): void {
        test('priority resets between rules', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('user:123')
                ->on('document:456')
                ->withPriority(100)
                ->allow('read')
                ->for('user:123')
                ->on('document:789')
                ->allow('write')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->priority->value)->toBe(100);
            expect($policy->rules[1]->priority->value)->toBe(1); // Should reset to default
        });

        test('domain resets between rules', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('user:123')
                ->on('document:456')
                ->inDomain('tenant-1')
                ->allow('read')
                ->for('user:123')
                ->on('document:789')
                ->allow('write')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->domain)->toBeInstanceOf(Domain::class);
            expect($policy->rules[0]->domain->id)->toBe('tenant-1');
            expect($policy->rules[1]->domain)->toBeNull(); // Should reset to null
        });

        test('resource persists across multiple action calls', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('editor')
                ->on('document:123')
                ->allow('read')
                ->deny('delete')
                ->allow('write')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(3);
            expect($policy->rules[0]->resource)->toBe('document:123');
            expect($policy->rules[1]->resource)->toBe('document:123');
            expect($policy->rules[2]->resource)->toBe('document:123');
        });

        test('subject persists across multiple action calls', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('editor')
                ->on('document:123')
                ->allow('read')
                ->on('document:456')
                ->allow('write')
                ->on('document:789')
                ->deny('delete')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(3);
            expect($policy->rules[0]->subject)->toBe('editor');
            expect($policy->rules[1]->subject)->toBe('editor');
            expect($policy->rules[2]->subject)->toBe('editor');
        });

        test('action and effect reset after each rule', function (): void {
            // Arrange & Act
            $policy = PolicyBuilder::make()
                ->for('user:123')
                ->on('document:456')
                ->allow('read')
                ->for('user:789')
                ->on('document:999')
                ->deny('delete')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);

            expect($policy->rules[0]->action)->toBe('read');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);

            expect($policy->rules[1]->action)->toBe('delete');
            expect($policy->rules[1]->effect)->toBe(Effect::Deny);
        });
    });
});
