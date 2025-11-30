<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

describe('PatrolExplainCommand', function (): void {
    describe('Happy Paths', function (): void {
        test('patrol:explain command shows evaluation trace', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user:123', 'post:456', 'edit', Effect::Allow, new Priority(10)),
                        new PolicyRule('*', '*', 'delete', Effect::Deny, new Priority(1)),
                    ]),
                );

            // Act & Assert
            $this->artisan('patrol:explain user:123 post:456 edit')
                ->expectsOutput('Authorization Evaluation Trace')
                ->assertExitCode(0);
        });

        test('patrol:explain shows success when action is allowed', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user:789', 'doc:111', 'read', Effect::Allow, new Priority(1)),
                    ]),
                );

            // Act & Assert
            $this->artisan('patrol:explain user:789 doc:111 read')
                ->assertExitCode(0);
        });

        test('patrol:explain supports JSON output', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user:555', 'file:666', 'download', Effect::Allow, new Priority(1)),
                    ]),
                );

            // Act & Assert
            $this->artisan('patrol:explain user:555 file:666 download --json')
                ->expectsOutputToContain('"decision": "granted"')
                ->assertExitCode(0);
        });

        test('patrol:explain shows rule matching information', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('role:editor', 'post:*', 'edit', Effect::Allow, new Priority(10)),
                    ]),
                );

            // Act & Assert
            $this->artisan('patrol:explain role:editor post:123 edit')
                ->expectsOutputToContain('This rule matches the query')
                ->assertExitCode(0);
        });

        test('patrol:explain command accepts all required arguments', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            // Act & Assert
            $this->artisan('patrol:explain subject resource action')
                ->assertExitCode(1);
        });
    });

    describe('Sad Paths', function (): void {
        test('patrol:explain shows failure when action is denied', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user:999', 'secret:888', 'access', Effect::Deny, new Priority(1)),
                    ]),
                );

            // Act & Assert
            $this->artisan('patrol:explain user:999 secret:888 access')
                ->assertExitCode(1);
        });

        test('patrol:explain shows no rules found message', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            // Act & Assert
            $this->artisan('patrol:explain user:000 none:000 nothing')
                ->expectsOutput('No rules found for this query')
                ->assertExitCode(1);
        });

        test('patrol:explain shows resource mismatch reason when rule does not match resource', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user:123', 'document:456', 'read', Effect::Allow, new Priority(1)),
                    ]),
                );

            // Act & Assert - request different resource than rule expects
            $this->artisan('patrol:explain user:123 file:999 read')
                ->expectsOutputToContain('resource mismatch')
                ->assertExitCode(1);
        });

        test('patrol:explain shows subject mismatch reason when rule does not match subject', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user:123', 'document:456', 'read', Effect::Allow, new Priority(1)),
                    ]),
                );

            // Act & Assert - request different subject than rule expects
            $this->artisan('patrol:explain user:999 document:456 read')
                ->expectsOutputToContain('subject mismatch')
                ->assertExitCode(1);
        });

        test('patrol:explain shows action mismatch reason when rule does not match action', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user:123', 'document:456', 'read', Effect::Allow, new Priority(1)),
                    ]),
                );

            // Act & Assert - request different action than rule expects
            $this->artisan('patrol:explain user:123 document:456 write')
                ->expectsOutputToContain('action mismatch')
                ->assertExitCode(1);
        });

        test('patrol:explain shows JSON output when action is denied', function (): void {
            // Arrange
            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user:999', 'secret:888', 'access', Effect::Deny, new Priority(1)),
                    ]),
                );

            // Act & Assert
            $this->artisan('patrol:explain user:999 secret:888 access --json')
                ->expectsOutputToContain('"decision": "denied"')
                ->assertExitCode(1);
        });
    });
});
