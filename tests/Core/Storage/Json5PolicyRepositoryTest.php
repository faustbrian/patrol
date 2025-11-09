<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Storage\Json5PolicyRepository;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Tests\Helpers\FilesystemHelper;

describe('Json5PolicyRepository', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_json5_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths - JSON5 Features', function (): void {
        test('parses JSON5 with single-line comments', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    // This is Alice's read permission
    {
        "subject": "user:alice",
        "resource": "document:123",
        "action": "read",
        "effect": "Allow",
        "priority": 1
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
            expect($result->rules[0]->resource)->toBe('document:123');
            expect($result->rules[0]->action)->toBe('read');
            expect($result->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('parses JSON5 with multi-line comments', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    /*
     * Multi-line comment
     * describing Alice's permissions
     */
    {
        "subject": "user:alice",
        "resource": "document:123",
        "action": "write",
        "effect": "Allow"
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->action)->toBe('write');
        });

        test('parses JSON5 with trailing commas in objects', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        "subject": "user:alice",
        "resource": "document:123",
        "action": "read",
        "effect": "Allow",
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });

        test('parses JSON5 with trailing commas in arrays', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        "subject": "user:alice",
        "resource": "document:123",
        "action": "read",
        "effect": "Allow"
    },
    {
        "subject": "user:bob",
        "resource": "document:456",
        "action": "write",
        "effect": "Deny"
    },
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });

        test('parses JSON5 with unquoted object keys', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        subject: "user:alice",
        resource: "document:123",
        action: "read",
        effect: "Allow",
        priority: 1
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
            expect($result->rules[0]->priority->value)->toBe(1);
        });

        test('parses JSON5 with single-quoted strings', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        'subject': 'user:alice',
        'resource': 'document:123',
        'action': 'read',
        'effect': 'Allow'
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });

        test('encodes policies to standard JSON format', function (): void {
            // Arrange
            $json5Content = <<<'JSON5_WRAP'
            [
                // JSON5 comment
                {
                    subject: "user:alice",
                    action: "read",
                    effect: "Allow",
                }
            ]
            JSON5_WRAP;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act - Load and re-save to test encode
            $subject = subject('user:alice');
            $resource = resource('document:any', 'document');
            $policy = $repository->getPoliciesFor($subject, $resource);
            $repository->save($policy);

            // Assert - Verify saved content is standard JSON (no comments, no trailing commas)
            $savedContent = file_get_contents($policyFile);
            expect($savedContent)->not->toContain('//');
            expect($savedContent)->not->toContain('/*');
            expect(json_decode($savedContent, true))->toBeArray();
        });

        test('returns json5 as file extension', function (): void {
            // Arrange
            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act - Extension is used internally when building file paths
            // We verify by checking the file path pattern
            $json5Content = '[{"subject": "user:alice", "action": "read", "effect": "Allow"}]';
            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $subject = subject('user:alice');
            $resource = resource('any', 'document');
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - Verify .json5 extension is used
            expect(file_exists($policyFile))->toBeTrue();
            expect($result->rules)->toHaveCount(1);
        });

        test('parses JSON5 with domain field', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        subject: "user:alice",
        resource: "document:123",
        action: "read",
        effect: "Allow",
        domain: "tenant:acme",
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->domain)->toBeInstanceOf(Domain::class);
            expect($result->rules[0]->domain->id)->toBe('tenant:acme');
        });

        test('parses JSON5 with wildcard subject and resource', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        subject: "*",  // Wildcard subject
        resource: "*", // Wildcard resource
        action: "read",
        effect: "Allow"
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:anyone');
            $resource = resource('document:any', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('*');
            expect($result->rules[0]->resource)->toBe('*');
        });
    });

    describe('Sad Paths', function (): void {
        test('returns empty policy when JSON5 parsing fails with malformed syntax', function (): void {
            // Arrange
            $json5Content = '{invalid json5 syntax {{{';

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - decode() returns null, parent loadSingleFile() returns []
            expect($result->rules)->toBeEmpty();
        });

        test('returns empty policy when JSON5 contains non-array data', function (): void {
            // Arrange
            $json5Content = '"this is a string, not an array"';

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - decode() returns null for non-array, parent loadSingleFile() returns []
            expect($result->rules)->toBeEmpty();
        });

        test('gracefully handles Exception from Json5Decoder', function (): void {
            // Arrange
            $json5Content = '{unclosed object';

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - Exception caught in decode(), returns null, parent returns []
            expect($result->rules)->toBeEmpty();
        });

        test('returns empty policy when no policies match', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        subject: "user:bob",
        resource: "document:456",
        action: "read",
        effect: "Allow"
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('parses JSON5 with mixed comment styles', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    // Single-line comment
    {
        /* Multi-line
           comment */
        subject: "user:alice", // Inline comment
        resource: "document:123",
        action: "read",
        effect: "Allow"
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });

        test('parses JSON5 with hexadecimal numbers in priority', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        subject: "user:alice",
        resource: "document:123",
        action: "read",
        effect: "Allow",
        priority: 0x10
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->priority->value)->toBe(16); // 0x10 = 16 in decimal
        });

        test('parses JSON5 with positive sign numbers', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        subject: "user:alice",
        resource: "document:123",
        action: "read",
        effect: "Allow",
        priority: +5
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->priority->value)->toBe(5);
        });

        test('parses empty JSON5 array', function (): void {
            // Arrange
            $json5Content = '[]';

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toBeEmpty();
        });

        test('parses JSON5 with all features combined', function (): void {
            // Arrange
            $json5Content = <<<'JSON5_WRAP'
            [
                // Administrator policy with all JSON5 features
                {
                    subject: "user:alice",  // Unquoted key, trailing comma
                    'resource': 'document:123', // Single quotes
                    action: "admin",
                    effect: 'Allow',
                    priority: 0x0A, // Hexadecimal (10)
                    domain: "tenant:acme", // With domain
                }, // Trailing comma in array
                /* Another policy
                   with multi-line comment */
                {
                    subject: '*',
                    action: 'read',
                    effect: 'Allow',
                    priority: +1, // Explicit positive
                },
            ]
            JSON5_WRAP;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(2);
            expect($result->rules[0]->subject)->toBe('user:alice');
            expect($result->rules[0]->priority->value)->toBe(10);
            expect($result->rules[0]->domain->id)->toBe('tenant:acme');
            expect($result->rules[1]->subject)->toBe('*');
        });

        test('loads policies from multiple JSON5 files in multiple file mode', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $policy1 = <<<'JSON5_WRAP'
            {
                subject: "user:alice", // JSON5 comment
                resource: "document:123",
                action: "read",
                effect: "Allow",
            }
            JSON5_WRAP;

            $policy2 = <<<'JSON5'
{
    subject: "user:bob",
    resource: "document:456",
    action: "write",
    effect: "Deny",
}
JSON5;

            file_put_contents($policyDir.'/policy1.json5', $policy1);
            file_put_contents($policyDir.'/policy2.json5', $policy2);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });

        test('handles unicode characters in JSON5 policy data', function (): void {
            // Arrange
            $json5Content = <<<'JSON5'
[
    {
        subject: "user:José", // Unicode in value
        resource: "document:文档",
        action: "read",
        effect: "Allow",
    }
]
JSON5;

            $policyFile = $this->tempDir.'/policies/policies.json5';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:José');
            $resource = resource('document:文档', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:José');
            expect($result->rules[0]->resource)->toBe('document:文档');
        });

        test('skips invalid JSON5 files in multiple file mode', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $validPolicy = '{subject: "user:alice", resource: "document:123", action: "read", effect: "Allow"}';
            $invalidPolicy = '{invalid json5 {{{';

            file_put_contents($policyDir.'/valid.json5', $validPolicy);
            file_put_contents($policyDir.'/invalid.json5', $invalidPolicy);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });

        test('returns empty policy when directory does not exist in multiple file mode', function (): void {
            // Arrange
            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toBeEmpty();
        });

        test('handles versioned policies with JSON5 format', function (): void {
            // Arrange
            $versionDir = $this->tempDir.'/policies/1.0.0';
            mkdir($versionDir, 0o755, true);

            $json5Content = <<<'JSON5'
[
    // Version 1.0.0 policy
    {
        subject: "user:alice",
        resource: "document:123",
        action: "read",
        effect: "Allow",
    }
]
JSON5;

            file_put_contents($versionDir.'/policies.json5', $json5Content);

            $repository = new Json5PolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: '1.0.0',
                versioningEnabled: true,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });
    });
});
