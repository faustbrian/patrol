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

    describe('Enhanced Operators', function (): void {
        describe('startsWith operator', function (): void {
            test('evaluates true when string starts with value', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class() {};
                $resource = new class()
                {
                    public string $path = '/api/admin/users';
                };

                $result = $resolver->evaluateCondition('resource.path startsWith /api/admin/', $subject, $resource);

                expect($result)->toBeTrue();
            });

            test('evaluates false when string does not start with value', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class() {};
                $resource = new class()
                {
                    public string $path = '/api/public/users';
                };

                $result = $resolver->evaluateCondition('resource.path startsWith /api/admin/', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('evaluates false when left operand is not a string', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class() {};
                $resource = new class()
                {
                    public int $path = 123;
                };

                $result = $resolver->evaluateCondition('resource.path startsWith /api/', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('evaluates false when right operand is not a string', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public int $prefix = 456;
                };
                $resource = new class()
                {
                    public string $path = '/api/admin/users';
                };

                $result = $resolver->evaluateCondition('resource.path startsWith subject.prefix', $subject, $resource);

                expect($result)->toBeFalse();
            });
        });

        describe('endsWith operator', function (): void {
            test('evaluates true when string ends with value', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class() {};
                $resource = new class()
                {
                    public string $filename = 'report.pdf';
                };

                $result = $resolver->evaluateCondition('resource.filename endsWith .pdf', $subject, $resource);

                expect($result)->toBeTrue();
            });

            test('evaluates false when string does not end with value', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class() {};
                $resource = new class()
                {
                    public string $filename = 'report.docx';
                };

                $result = $resolver->evaluateCondition('resource.filename endsWith .pdf', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('evaluates false when left operand is not a string', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class() {};
                $resource = new class()
                {
                    public int $filename = 789;
                };

                $result = $resolver->evaluateCondition('resource.filename endsWith .pdf', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('evaluates false when right operand is not a string', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public int $extension = 123;
                };
                $resource = new class()
                {
                    public string $filename = 'report.pdf';
                };

                $result = $resolver->evaluateCondition('resource.filename endsWith subject.extension', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('handles multiple file extensions', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class() {};
                $resource1 = new class()
                {
                    public string $filename = 'document.pdf';
                };
                $resource2 = new class()
                {
                    public string $filename = 'document.doc';
                };

                expect($resolver->evaluateCondition('resource.filename endsWith .pdf', $subject, $resource1))->toBeTrue();
                expect($resolver->evaluateCondition('resource.filename endsWith .pdf', $subject, $resource2))->toBeFalse();
            });
        });

        describe('between operator', function (): void {
            test('evaluates true when value is within range', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public int $age = 25;
                };
                $resource = new class() {};

                $result = $resolver->evaluateCondition('subject.age between 18 and 65', $subject, $resource);

                expect($result)->toBeTrue();
            });

            test('evaluates true when value equals minimum', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public int $age = 18;
                };
                $resource = new class() {};

                $result = $resolver->evaluateCondition('subject.age between 18 and 65', $subject, $resource);

                expect($result)->toBeTrue();
            });

            test('evaluates true when value equals maximum', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public int $age = 65;
                };
                $resource = new class() {};

                $result = $resolver->evaluateCondition('subject.age between 18 and 65', $subject, $resource);

                expect($result)->toBeTrue();
            });

            test('evaluates false when value is below range', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public int $age = 17;
                };
                $resource = new class() {};

                $result = $resolver->evaluateCondition('subject.age between 18 and 65', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('evaluates false when value is above range', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public int $age = 66;
                };
                $resource = new class() {};

                $result = $resolver->evaluateCondition('subject.age between 18 and 65', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('works with dynamic attribute values', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public int $level = 5;
                };
                $resource = new class()
                {
                    public int $min_level = 1;

                    public int $max_level = 10;
                };

                $result = $resolver->evaluateCondition('subject.level between resource.min_level and resource.max_level', $subject, $resource);

                expect($result)->toBeTrue();
            });
        });

        describe('not in operator', function (): void {
            test('evaluates true when value is not in array', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public array $blocked_categories = ['admin', 'secret'];
                };
                $resource = new class()
                {
                    public string $category = 'public';
                };

                $result = $resolver->evaluateCondition('resource.category not in subject.blocked_categories', $subject, $resource);

                expect($result)->toBeTrue();
            });

            test('evaluates false when value is in array', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public array $blocked_categories = ['admin', 'secret'];
                };
                $resource = new class()
                {
                    public string $category = 'admin';
                };

                $result = $resolver->evaluateCondition('resource.category not in subject.blocked_categories', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('evaluates false when right side is not an array', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public string $blocked = 'admin';
                };
                $resource = new class()
                {
                    public string $category = 'public';
                };

                $result = $resolver->evaluateCondition('resource.category not in subject.blocked', $subject, $resource);

                expect($result)->toBeFalse();
            });
        });

        describe('not contains operator', function (): void {
            test('evaluates true when array does not contain value', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public array $tags = ['public', 'draft'];
                };
                $resource = new class() {};

                $result = $resolver->evaluateCondition('subject.tags not contains restricted', $subject, $resource);

                expect($result)->toBeTrue();
            });

            test('evaluates false when array contains value', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public array $tags = ['public', 'restricted', 'draft'];
                };
                $resource = new class() {};

                $result = $resolver->evaluateCondition('subject.tags not contains restricted', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('evaluates false when left side is not an array', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public string $tag = 'public';
                };
                $resource = new class() {};

                $result = $resolver->evaluateCondition('subject.tag not contains restricted', $subject, $resource);

                expect($result)->toBeFalse();
            });

            test('uses strict comparison', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public array $numbers = [1, 2, 3];
                };
                $resource = new class() {};

                // String '1' should not match integer 1
                $result = $resolver->evaluateCondition('subject.numbers not contains "1"', $subject, $resource);

                expect($result)->toBeTrue();
            });
        });

        describe('Operator Precedence', function (): void {
            test('not in is evaluated before in', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public array $allowed = ['read', 'write'];

                    public array $blocked = ['admin'];
                };
                $resource = new class()
                {
                    public string $action = 'read';
                };

                // "not in" should be detected first
                $result = $resolver->evaluateCondition('resource.action not in subject.blocked', $subject, $resource);
                expect($result)->toBeTrue();

                // Regular "in" should still work
                $result2 = $resolver->evaluateCondition('resource.action in subject.allowed', $subject, $resource);
                expect($result2)->toBeTrue();
            });

            test('not contains is evaluated before contains', function (): void {
                $resolver = new AttributeResolver();
                $subject = new class()
                {
                    public array $tags = ['public', 'draft'];
                };
                $resource = new class() {};

                // "not contains" should be detected first
                $result = $resolver->evaluateCondition('subject.tags not contains restricted', $subject, $resource);
                expect($result)->toBeTrue();

                // Regular "contains" should still work
                $result2 = $resolver->evaluateCondition('subject.tags contains draft', $subject, $resource);
                expect($result2)->toBeTrue();
            });
        });
    });
});
