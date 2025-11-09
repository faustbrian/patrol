<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Resolvers;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Override;
use Patrol\Core\Contracts\SubjectResolverInterface;
use Patrol\Core\ValueObjects\Subject;

use function assert;
use function auth;
use function is_int;
use function is_string;
use function method_exists;

/**
 * Laravel-specific implementation of subject resolution for policy evaluation.
 *
 * Integrates with Laravel's authentication system to resolve the current
 * authenticated user as a Subject value object. Handles both authenticated
 * and guest users, providing appropriate subject information for policy
 * evaluation in either case.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class LaravelSubjectResolver implements SubjectResolverInterface
{
    /**
     * Resolve the current authenticated user as a Subject value object.
     *
     * Integrates with Laravel's authentication system to retrieve the current user
     * and transform it into a Subject for policy evaluation. Extracts user attributes
     * to support attribute-based access control (ABAC), enabling policies that check
     * user properties like roles, permissions, or custom fields. Gracefully handles
     * unauthenticated requests by returning a guest subject.
     *
     * ```php
     * // Authenticated user resolution
     * auth()->login($user); // User ID: 123
     * $subject = $resolver->resolve();
     * // Result: Subject(id: "123", attributes: ["id" => 123, "name" => "...", ...])
     *
     * // Guest user resolution
     * auth()->logout();
     * $subject = $resolver->resolve();
     * // Result: Subject(id: "guest", attributes: [])
     * ```
     *
     * @return Subject Immutable Subject value object representing the current authentication
     *                 context. For authenticated users, includes user ID (as string) and full
     *                 attributes from toArray(). For guests, returns subject with ID "guest"
     *                 and empty attributes array.
     */
    #[Override()]
    public function resolve(): Subject
    {
        /** @var Guard|StatefulGuard $auth */
        $auth = auth();
        $user = $auth->user();

        if ($user === null) {
            return new Subject('guest');
        }

        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */

        /** @var array<string, mixed> $attributes */
        $attributes = method_exists($user, 'toArray') ? $user->toArray() : [];

        $authId = $user->getAuthIdentifier();
        assert(is_string($authId) || is_int($authId));

        return new Subject(
            id: (string) $authId,
            attributes: $attributes,
        );
    }
}
