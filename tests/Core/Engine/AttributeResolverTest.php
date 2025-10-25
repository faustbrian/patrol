<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Date;
use Patrol\Core\Contracts\AttributeProviderInterface;
use Patrol\Core\Engine\AttributeResolver;

describe('AttributeResolver', function (): void {
    describe('Happy Paths', function (): void {
        test('resolves attribute using direct property access', function (): void {
            $resolver = new AttributeResolver();
            $context = new class()
            {
                public string $name = 'John Doe';
            };

            $result = $resolver->resolve('subject.name', $context);

            expect($result)->toBe('John Doe');
        });

        test('resolves attribute using array-based attributes property', function (): void {
            $resolver = new AttributeResolver();
            $context = new class()
            {
                public array $attributes = ['email' => 'john@example.com'];
            };

            $result = $resolver->resolve('subject.email', $context);

            expect($result)->toBe('john@example.com');
        });

        test('resolves attribute using custom provider', function (): void {
            $provider = new class() implements AttributeProviderInterface
            {
                public function getAttribute(object $entity, string $attribute): mixed
                {
                    return 'provider-value';
                }
            };

            $resolver = new AttributeResolver($provider);
            $context = new class()
            {
                public string $name = 'Direct Value';
            };

            $result = $resolver->resolve('subject.name', $context);

            expect($result)->toBe('provider-value');
        });

        test('evaluates equality condition successfully', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $id = 'user-1';
            };
            $resource = new class()
            {
                public string $owner = 'user-1';
            };

            $result = $resolver->evaluateCondition('resource.owner == subject.id', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('evaluates inequality condition successfully', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $id = 'user-1';
            };
            $resource = new class()
            {
                public string $status = 'active';
            };

            $result = $resolver->evaluateCondition('resource.status != archived', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('evaluates greater than condition', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public int $level = 10;
            };
            $resource = new class()
            {
                public int $requiredLevel = 5;
            };

            $result = $resolver->evaluateCondition('subject.level > resource.requiredLevel', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('evaluates less than condition', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public int $level = 3;
            };
            $resource = new class()
            {
                public int $requiredLevel = 5;
            };

            $result = $resolver->evaluateCondition('subject.level < resource.requiredLevel', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('evaluates greater than or equal condition', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public int $level = 5;
            };
            $resource = new class()
            {
                public int $requiredLevel = 5;
            };

            $result = $resolver->evaluateCondition('subject.level >= resource.requiredLevel', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('evaluates less than or equal condition', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public int $level = 5;
            };
            $resource = new class()
            {
                public int $requiredLevel = 5;
            };

            $result = $resolver->evaluateCondition('subject.level <= resource.requiredLevel', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('evaluates contains condition when array contains value', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public array $tags = ['admin', 'moderator', 'user'];
            };
            $resource = new class()
            {
                public string $category = 'admin';
            };

            $result = $resolver->evaluateCondition('subject.tags contains resource.category', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('evaluates in condition when value is in array', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public array $allowedCategories = ['tech', 'science', 'news'];
            };
            $resource = new class()
            {
                public string $category = 'tech';
            };

            $result = $resolver->evaluateCondition('resource.category in subject.allowedCategories', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('resolves true boolean literal', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class() {};
            $resource = new class()
            {
                public bool $active = true;
            };

            $result = $resolver->evaluateCondition('resource.active == true', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('resolves false boolean literal', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class() {};
            $resource = new class()
            {
                public bool $archived = false;
            };

            $result = $resolver->evaluateCondition('resource.archived == false', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('resolves integer numeric literals', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class() {};
            $resource = new class()
            {
                public int $count = 42;
            };

            $result = $resolver->evaluateCondition('resource.count == 42', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('resolves float numeric literals', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class() {};
            $resource = new class()
            {
                public float $price = 99.99;
            };

            $result = $resolver->evaluateCondition('resource.price == 99.99', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('resolves request.time for time-based conditions', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class() {};
            $resource = new class()
            {
                public int $embargoUntil;

                public function __construct()
                {
                    $this->embargoUntil = Date::now()->subHours(1)->getTimestamp(); // 1 hour ago
                }
            };

            $result = $resolver->evaluateCondition('resource.embargoUntil < request.time', $subject, $resource);

            expect($result)->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns null for invalid dotted notation with single part', function (): void {
            $resolver = new AttributeResolver();
            $context = new class()
            {
                public string $name = 'John Doe';
            };

            $result = $resolver->resolve('invalidexpression', $context);

            expect($result)->toBeNull();
        });

        test('returns null for invalid dotted notation with more than two parts', function (): void {
            $resolver = new AttributeResolver();
            $context = new class()
            {
                public string $name = 'John Doe';
            };

            $result = $resolver->resolve('subject.nested.name', $context);

            expect($result)->toBeNull();
        });

        test('returns null when attribute does not exist as property or in attributes array', function (): void {
            $resolver = new AttributeResolver();
            $context = new class()
            {
                public string $name = 'John Doe';
            };

            $result = $resolver->resolve('subject.nonexistent', $context);

            expect($result)->toBeNull();
        });

        test('returns false for invalid condition format without operators', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $id = 'user-1';
            };
            $resource = new class()
            {
                public string $owner = 'user-1';
            };

            $result = $resolver->evaluateCondition('invalid condition format', $subject, $resource);

            expect($result)->toBeFalse();
        });

        test('returns false when equality condition fails', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $id = 'user-1';
            };
            $resource = new class()
            {
                public string $owner = 'user-2';
            };

            $result = $resolver->evaluateCondition('resource.owner == subject.id', $subject, $resource);

            expect($result)->toBeFalse();
        });

        test('returns false when inequality condition fails', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $id = 'user-1';
            };
            $resource = new class()
            {
                public string $status = 'archived';
            };

            $result = $resolver->evaluateCondition('resource.status != archived', $subject, $resource);

            expect($result)->toBeFalse();
        });

        test('returns false when contains condition with non-array', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $tags = 'not-an-array';
            };
            $resource = new class()
            {
                public string $category = 'admin';
            };

            $result = $resolver->evaluateCondition('subject.tags contains resource.category', $subject, $resource);

            expect($result)->toBeFalse();
        });

        test('returns false when in condition with non-array', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $allowedCategories = 'not-an-array';
            };
            $resource = new class()
            {
                public string $category = 'tech';
            };

            $result = $resolver->evaluateCondition('resource.category in subject.allowedCategories', $subject, $resource);

            expect($result)->toBeFalse();
        });

        test('returns false when value not in contains array', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public array $tags = ['admin', 'moderator'];
            };
            $resource = new class()
            {
                public string $category = 'user';
            };

            $result = $resolver->evaluateCondition('subject.tags contains resource.category', $subject, $resource);

            expect($result)->toBeFalse();
        });

        test('returns false when value not in in array', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public array $allowedCategories = ['tech', 'science'];
            };
            $resource = new class()
            {
                public string $category = 'news';
            };

            $result = $resolver->evaluateCondition('resource.category in subject.allowedCategories', $subject, $resource);

            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles missing attributes in equality comparison', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $id = 'user-1';
            };
            $resource = new class() {};

            $result = $resolver->evaluateCondition('resource.owner == subject.id', $subject, $resource);

            expect($result)->toBeFalse();
        });

        test('handles missing attributes in inequality comparison', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class() {};
            $resource = new class()
            {
                public string $status = 'active';
            };

            $result = $resolver->evaluateCondition('subject.role != admin', $subject, $resource);

            expect($result)->toBeTrue(); // null !== 'admin'
        });

        test('compares null values with strict equality', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class() {};
            $resource = new class() {};

            $result = $resolver->evaluateCondition('subject.missing == resource.missing', $subject, $resource);

            expect($result)->toBeTrue(); // null === null
        });

        test('handles attributes property without requested key', function (): void {
            $resolver = new AttributeResolver();
            $context = new class()
            {
                public array $attributes = ['name' => 'John'];
            };

            $result = $resolver->resolve('subject.email', $context);

            expect($result)->toBeNull();
        });

        test('resolves literal string values in comparisons', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class() {};
            $resource = new class()
            {
                public string $type = 'document';
            };

            $result = $resolver->evaluateCondition('resource.type == document', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('handles whitespace in condition expressions', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $id = 'user-1';
            };
            $resource = new class()
            {
                public string $owner = 'user-1';
            };

            $result = $resolver->evaluateCondition('  resource.owner  ==  subject.id  ', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('handles empty string attributes', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public string $name = '';
            };
            $resource = new class()
            {
                public string $owner = '';
            };

            $result = $resolver->evaluateCondition('subject.name == resource.owner', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('handles numeric string comparisons', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public int $age = 25;
            };
            $resource = new class() {};

            $result = $resolver->evaluateCondition('subject.age > 18', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('handles negative numbers in comparisons', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public int $balance = -100;
            };
            $resource = new class() {};

            $result = $resolver->evaluateCondition('subject.balance < 0', $subject, $resource);

            expect($result)->toBeTrue();
        });

        test('handles complex operator precedence with >= before >', function (): void {
            $resolver = new AttributeResolver();
            $subject = new class()
            {
                public int $level = 10;
            };
            $resource = new class() {};

            $result = $resolver->evaluateCondition('subject.level >= 10', $subject, $resource);

            expect($result)->toBeTrue();
        });
    });
});
