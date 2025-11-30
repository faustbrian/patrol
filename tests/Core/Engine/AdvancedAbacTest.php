<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;
use Patrol\Core\Engine\AttributeResolver;

describe('AttributeResolver - Advanced ABAC Operations', function (): void {
    beforeEach(function (): void {
        $this->resolver = new AttributeResolver();
    });

    describe('Happy Paths', function (): void {
        describe('Numeric Greater Than Operator', function (): void {
            test('returns true when subject level is greater than resource required level', function (): void {
                $subject = subject('user-1', ['level' => 10]);
                $resource = resource('doc-1', 'document', ['required_level' => 5]);

                $result = $this->resolver->evaluateCondition(
                    'subject.level > resource.required_level',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('returns false when subject level is not greater than resource required level', function (): void {
                $subject = subject('user-1', ['level' => 5]);
                $resource = resource('doc-1', 'document', ['required_level' => 10]);

                $result = $this->resolver->evaluateCondition(
                    'subject.level > resource.required_level',
                    $subject,
                    $resource,
                );

                expect($result)->toBeFalse();
            });

            test('returns false when subject level equals resource required level', function (): void {
                $subject = subject('user-1', ['level' => 5]);
                $resource = resource('doc-1', 'document', ['required_level' => 5]);

                $result = $this->resolver->evaluateCondition(
                    'subject.level > resource.required_level',
                    $subject,
                    $resource,
                );

                expect($result)->toBeFalse();
            });
        });

        describe('Numeric Less Than Operator', function (): void {
            test('returns true when resource embargo is less than current time', function (): void {
                $subject = subject('user-1');
                $resource = resource('doc-1', 'document', ['embargo_until' => Date::now()->subHours(1)->getTimestamp()]);

                $result = $this->resolver->evaluateCondition(
                    'resource.embargo_until < request.time',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('returns false when resource embargo is greater than current time', function (): void {
                $subject = subject('user-1');
                $resource = resource('doc-1', 'document', ['embargo_until' => Date::now()->getTimestamp() + 3_600]);

                $result = $this->resolver->evaluateCondition(
                    'resource.embargo_until < request.time',
                    $subject,
                    $resource,
                );

                expect($result)->toBeFalse();
            });

            test('returns true when subject level is less than resource max level', function (): void {
                $subject = subject('user-1', ['level' => 3]);
                $resource = resource('doc-1', 'document', ['max_level' => 5]);

                $result = $this->resolver->evaluateCondition(
                    'subject.level < resource.max_level',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });
        });

        describe('Numeric Greater Than or Equal Operator', function (): void {
            test('returns true when subject level is greater than required level', function (): void {
                $subject = subject('user-1', ['level' => 10]);
                $resource = resource('doc-1', 'document', ['required_level' => 5]);

                $result = $this->resolver->evaluateCondition(
                    'subject.level >= resource.required_level',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('returns true when subject level equals required level', function (): void {
                $subject = subject('user-1', ['level' => 5]);
                $resource = resource('doc-1', 'document', ['required_level' => 5]);

                $result = $this->resolver->evaluateCondition(
                    'subject.level >= resource.required_level',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('returns false when subject level is less than required level', function (): void {
                $subject = subject('user-1', ['level' => 3]);
                $resource = resource('doc-1', 'document', ['required_level' => 5]);

                $result = $this->resolver->evaluateCondition(
                    'subject.level >= resource.required_level',
                    $subject,
                    $resource,
                );

                expect($result)->toBeFalse();
            });
        });

        describe('Numeric Less Than or Equal Operator', function (): void {
            test('returns true when subject clearance is less than resource classification', function (): void {
                $subject = subject('user-1', ['clearance' => 2]);
                $resource = resource('doc-1', 'document', ['classification' => 5]);

                $result = $this->resolver->evaluateCondition(
                    'subject.clearance <= resource.classification',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('returns true when subject clearance equals resource classification', function (): void {
                $subject = subject('user-1', ['clearance' => 5]);
                $resource = resource('doc-1', 'document', ['classification' => 5]);

                $result = $this->resolver->evaluateCondition(
                    'subject.clearance <= resource.classification',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('returns false when subject clearance is greater than resource classification', function (): void {
                $subject = subject('user-1', ['clearance' => 10]);
                $resource = resource('doc-1', 'document', ['classification' => 5]);

                $result = $this->resolver->evaluateCondition(
                    'subject.clearance <= resource.classification',
                    $subject,
                    $resource,
                );

                expect($result)->toBeFalse();
            });
        });

        describe('Numeric Comparisons with Floats', function (): void {
            test('handles float comparisons with greater than', function (): void {
                $subject = subject('user-1', ['budget' => 150.50]);
                $resource = resource('doc-1', 'document', ['price' => 99.99]);

                $result = $this->resolver->evaluateCondition(
                    'subject.budget > resource.price',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('handles float comparisons with less than', function (): void {
                $subject = subject('user-1', ['budget' => 50.25]);
                $resource = resource('doc-1', 'document', ['price' => 99.99]);

                $result = $this->resolver->evaluateCondition(
                    'subject.budget < resource.price',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('handles float comparisons with literals', function (): void {
                $subject = subject('user-1');
                $resource = resource('doc-1', 'document', ['price' => 89.99]);

                $result = $this->resolver->evaluateCondition(
                    'resource.price < 99.99',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });
        });

        describe('Array Contains Operator', function (): void {
            test('returns true when subject tags contain resource category', function (): void {
                $subject = subject('user-1', ['tags' => ['admin', 'editor', 'viewer']]);
                $resource = resource('doc-1', 'document', ['category' => 'editor']);

                $result = $this->resolver->evaluateCondition(
                    'subject.tags contains resource.category',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('returns false when subject tags do not contain resource category', function (): void {
                $subject = subject('user-1', ['tags' => ['viewer', 'guest']]);
                $resource = resource('doc-1', 'document', ['category' => 'admin']);

                $result = $this->resolver->evaluateCondition(
                    'subject.tags contains resource.category',
                    $subject,
                    $resource,
                );

                expect($result)->toBeFalse();
            });

            test('returns true when subject permissions contain required permission', function (): void {
                $subject = subject('user-1', ['permissions' => ['read', 'write', 'delete']]);
                $resource = resource('doc-1', 'document', ['required_permission' => 'write']);

                $result = $this->resolver->evaluateCondition(
                    'subject.permissions contains resource.required_permission',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });
        });

        describe('Array In Operator', function (): void {
            test('returns true when resource category is in subject allowed categories', function (): void {
                $subject = subject('user-1', ['allowed_categories' => ['public', 'internal', 'confidential']]);
                $resource = resource('doc-1', 'document', ['category' => 'internal']);

                $result = $this->resolver->evaluateCondition(
                    'resource.category in subject.allowed_categories',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('returns false when resource category is not in subject allowed categories', function (): void {
                $subject = subject('user-1', ['allowed_categories' => ['public', 'internal']]);
                $resource = resource('doc-1', 'document', ['category' => 'secret']);

                $result = $this->resolver->evaluateCondition(
                    'resource.category in subject.allowed_categories',
                    $subject,
                    $resource,
                );

                expect($result)->toBeFalse();
            });

            test('returns true when resource type is in subject accessible types', function (): void {
                $subject = subject('user-1', ['accessible_types' => ['document', 'article', 'report']]);
                $resource = resource('doc-1', 'document', ['type' => 'article']);

                $result = $this->resolver->evaluateCondition(
                    'resource.type in subject.accessible_types',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });
        });

        describe('Time-Based Conditions', function (): void {
            test('returns true when current time is after resource publication time', function (): void {
                $subject = subject('user-1');
                $resource = resource('doc-1', 'document', ['published_at' => Date::now()->subHours(1)->getTimestamp()]);

                $result = $this->resolver->evaluateCondition(
                    'request.time > resource.published_at',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('returns false when current time is before resource available time', function (): void {
                $subject = subject('user-1');
                $resource = resource('doc-1', 'document', ['available_at' => Date::now()->getTimestamp() + 3_600]);

                $result = $this->resolver->evaluateCondition(
                    'request.time >= resource.available_at',
                    $subject,
                    $resource,
                );

                expect($result)->toBeFalse();
            });

            test('request time resolves to current timestamp', function (): void {
                $subject = subject('user-1');
                $resource = resource('doc-1', 'document', ['expires_at' => Date::now()->getTimestamp() + 1_000]);

                $before = Date::now()->getTimestamp();
                $result = $this->resolver->evaluateCondition(
                    'resource.expires_at > request.time',
                    $subject,
                    $resource,
                );
                $after = Date::now()->getTimestamp();

                expect($result)->toBeTrue();
                expect($after)->toBeGreaterThanOrEqual($before);
            });
        });

        describe('Numeric Literal Comparisons', function (): void {
            test('handles integer literal in greater than or equal comparison', function (): void {
                $subject = subject('user-1', ['level' => 10]);
                $resource = resource('doc-1', 'document');

                $result = $this->resolver->evaluateCondition(
                    'subject.level >= 5',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('handles integer literal in less than comparison', function (): void {
                $subject = subject('user-1', ['level' => 3]);
                $resource = resource('doc-1', 'document');

                $result = $this->resolver->evaluateCondition(
                    'subject.level < 5',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });

            test('handles float literal in comparison', function (): void {
                $subject = subject('user-1');
                $resource = resource('doc-1', 'document', ['price' => 89.99]);

                $result = $this->resolver->evaluateCondition(
                    'resource.price < 100.00',
                    $subject,
                    $resource,
                );

                expect($result)->toBeTrue();
            });
        });
    });

    describe('Sad Paths', function (): void {
        test('returns false when array contains is used on non-array attribute', function (): void {
            $subject = subject('user-1', ['tags' => 'not-an-array']);
            $resource = resource('doc-1', 'document', ['category' => 'editor']);

            $result = $this->resolver->evaluateCondition(
                'subject.tags contains resource.category',
                $subject,
                $resource,
            );

            expect($result)->toBeFalse();
        });

        test('returns false when array in is used on non-array attribute', function (): void {
            $subject = subject('user-1', ['allowed_categories' => 'not-an-array']);
            $resource = resource('doc-1', 'document', ['category' => 'internal']);

            $result = $this->resolver->evaluateCondition(
                'resource.category in subject.allowed_categories',
                $subject,
                $resource,
            );

            expect($result)->toBeFalse();
        });

        test('handles invalid numeric comparison with null values', function (): void {
            $subject = subject('user-1', ['level' => null]);
            $resource = resource('doc-1', 'document', ['required_level' => 5]);

            $result = $this->resolver->evaluateCondition(
                'subject.level >= resource.required_level',
                $subject,
                $resource,
            );

            expect($result)->toBeFalse();
        });

        test('handles missing attributes in numeric comparison', function (): void {
            $subject = subject('user-1'); // No level attribute
            $resource = resource('doc-1', 'document', ['required_level' => 5]);

            $result = $this->resolver->evaluateCondition(
                'subject.level >= resource.required_level',
                $subject,
                $resource,
            );

            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty array in contains operation', function (): void {
            $subject = subject('user-1', ['tags' => []]);
            $resource = resource('doc-1', 'document', ['category' => 'editor']);

            $result = $this->resolver->evaluateCondition(
                'subject.tags contains resource.category',
                $subject,
                $resource,
            );

            expect($result)->toBeFalse();
        });

        test('handles empty array in in operation', function (): void {
            $subject = subject('user-1', ['allowed_categories' => []]);
            $resource = resource('doc-1', 'document', ['category' => 'internal']);

            $result = $this->resolver->evaluateCondition(
                'resource.category in subject.allowed_categories',
                $subject,
                $resource,
            );

            expect($result)->toBeFalse();
        });

        test('handles zero values in numeric comparisons', function (): void {
            $subject = subject('user-1', ['level' => 0]);
            $resource = resource('doc-1', 'document', ['required_level' => 0]);

            $resultGte = $this->resolver->evaluateCondition(
                'subject.level >= resource.required_level',
                $subject,
                $resource,
            );
            $resultLte = $this->resolver->evaluateCondition(
                'subject.level <= resource.required_level',
                $subject,
                $resource,
            );
            $resultGt = $this->resolver->evaluateCondition(
                'subject.level > resource.required_level',
                $subject,
                $resource,
            );

            expect($resultGte)->toBeTrue();
            expect($resultLte)->toBeTrue();
            expect($resultGt)->toBeFalse();
        });

        test('handles negative numbers in comparisons', function (): void {
            $subject = subject('user-1', ['balance' => -100]);
            $resource = resource('doc-1', 'document', ['minimum_balance' => 0]);

            $result = $this->resolver->evaluateCondition(
                'subject.balance < resource.minimum_balance',
                $subject,
                $resource,
            );

            expect($result)->toBeTrue();
        });

        test('handles very large numbers in comparisons', function (): void {
            $subject = subject('user-1', ['quota' => \PHP_INT_MAX]);
            $resource = resource('doc-1', 'document', ['required_quota' => 1_000_000]);

            $result = $this->resolver->evaluateCondition(
                'subject.quota > resource.required_quota',
                $subject,
                $resource,
            );

            expect($result)->toBeTrue();
        });

        test('handles mixed type array comparisons with strict equality', function (): void {
            $subject = subject('user-1', ['tags' => ['1', '2', '3']]);
            $resource = resource('doc-1', 'document', ['category' => 1]); // Integer, not string

            $result = $this->resolver->evaluateCondition(
                'subject.tags contains resource.category',
                $subject,
                $resource,
            );

            expect($result)->toBeFalse(); // Strict comparison should fail
        });

        test('handles string numbers in numeric comparisons', function (): void {
            $subject = subject('user-1', ['level' => '10']);
            $resource = resource('doc-1', 'document', ['required_level' => '5']);

            $result = $this->resolver->evaluateCondition(
                'subject.level > resource.required_level',
                $subject,
                $resource,
            );

            expect($result)->toBeTrue(); // String numbers should be compared as strings
        });

        test('handles numeric literal conversion between integers and floats', function (): void {
            $subject = subject('user-1', ['level' => 5]);
            $resource = resource('doc-1', 'document');

            $resultInt = $this->resolver->evaluateCondition(
                'subject.level >= 5',
                $subject,
                $resource,
            );
            $resultFloat = $this->resolver->evaluateCondition(
                'subject.level >= 5.0',
                $subject,
                $resource,
            );

            expect($resultInt)->toBeTrue();
            expect($resultFloat)->toBeTrue();
        });

        test('preserves operator precedence with multiple conditions', function (): void {
            $subject = subject('user-1', ['level' => 5]);
            $resource = resource('doc-1', 'document', ['required_level' => 5]);

            // >= is checked before >
            $result = $this->resolver->evaluateCondition(
                'subject.level >= resource.required_level',
                $subject,
                $resource,
            );

            expect($result)->toBeTrue();
        });

        test('handles null values in array operations', function (): void {
            $subject = subject('user-1', ['tags' => ['admin', null, 'editor']]);
            $resource = resource('doc-1', 'document', ['category' => null]);

            $result = $this->resolver->evaluateCondition(
                'subject.tags contains resource.category',
                $subject,
                $resource,
            );

            expect($result)->toBeTrue(); // Strict comparison includes null
        });

        test('handles boolean values in array operations', function (): void {
            $subject = subject('user-1', ['flags' => [true, false, 'active']]);
            $resource = resource('doc-1', 'document', ['status' => true]);

            $result = $this->resolver->evaluateCondition(
                'subject.flags contains resource.status',
                $subject,
                $resource,
            );

            expect($result)->toBeTrue();
        });
    });

    describe('Regressions', function (): void {
        test('prevents operator confusion between greater than and greater than or equal', function (): void {
            $subject = subject('user-1', ['level' => 5]);
            $resource = resource('doc-1', 'document', ['required_level' => 5]);

            $resultGt = $this->resolver->evaluateCondition(
                'subject.level > resource.required_level',
                $subject,
                $resource,
            );
            $resultGte = $this->resolver->evaluateCondition(
                'subject.level >= resource.required_level',
                $subject,
                $resource,
            );

            expect($resultGt)->toBeFalse(); // Strictly greater than
            expect($resultGte)->toBeTrue(); // Greater than or equal
        });

        test('prevents operator confusion between less than and less than or equal', function (): void {
            $subject = subject('user-1', ['level' => 5]);
            $resource = resource('doc-1', 'document', ['max_level' => 5]);

            $resultLt = $this->resolver->evaluateCondition(
                'subject.level < resource.max_level',
                $subject,
                $resource,
            );
            $resultLte = $this->resolver->evaluateCondition(
                'subject.level <= resource.max_level',
                $subject,
                $resource,
            );

            expect($resultLt)->toBeFalse(); // Strictly less than
            expect($resultLte)->toBeTrue(); // Less than or equal
        });

        test('prevents accidental string concatenation in contains operator', function (): void {
            $subject = subject('user-1', ['tags' => ['adminviewer', 'editor']]);
            $resource = resource('doc-1', 'document', ['category' => 'admin']);

            $result = $this->resolver->evaluateCondition(
                'subject.tags contains resource.category',
                $subject,
                $resource,
            );

            expect($result)->toBeFalse(); // Should not match substring
        });

        test('ensures request time is evaluated at condition evaluation time', function (): void {
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['embargo_until' => Date::now()->subSeconds(1)->getTimestamp()]);

            Sleep::sleep(1); // Ensure time progresses

            $result = $this->resolver->evaluateCondition(
                'resource.embargo_until < request.time',
                $subject,
                $resource,
            );

            expect($result)->toBeTrue(); // request.time should be current, not cached
        });
    });
});
