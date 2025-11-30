<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Middleware\PatrolMiddleware;
use Patrol\Laravel\Patrol;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    // Reset Patrol resolvers
    Patrol::reset();

    // Create mock policy repository
    $this->policyRepository = Mockery::mock(PolicyRepositoryInterface::class);

    // Use real PolicyEvaluator (it's final)
    $this->evaluator = new PolicyEvaluator(
        new AclRuleMatcher(),
        new EffectResolver(),
    );

    // Create middleware instance
    $this->middleware = new PatrolMiddleware(
        $this->evaluator,
        $this->policyRepository,
    );

    // Next closure that returns success response
    $this->next = fn (Request $request): Response => new Response('Success', Symfony\Component\HttpFoundation\Response::HTTP_OK);
});

afterEach(function (): void {
    Patrol::reset();
});

describe('Happy Paths', function (): void {
    test('allows request when subject is authorized', function (): void {
        // Arrange
        $subject = new Subject('user-1', ['role' => 'admin']);
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/users', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('user-1', 'api/users', 'GET', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject && $s->id === 'user-1'),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'api/users'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('Success');
    });

    test('uses explicit resource and action parameters', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/posts', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $policy = new Policy([
            new PolicyRule('user-1', 'posts', 'create', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'posts'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next, 'posts', 'create');

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('resolves resource using registered resolver', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $resolvedResource = new Resource('post-123', 'Post', ['title' => 'Test Post']);
        Patrol::resolveResource(fn ($id): ?Resource => $id === 'posts' ? $resolvedResource : null);

        $request = Request::create('/api/posts', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('user-1', 'post-123', 'GET', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'post-123'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next, 'posts');

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('defaults to request path when no resource specified', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/admin/dashboard', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('user-1', 'admin/dashboard', 'GET', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'admin/dashboard' && $r->type === 'api'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('defaults to HTTP method when no action specified', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/users', Symfony\Component\HttpFoundation\Request::METHOD_DELETE);
        $policy = new Policy([
            new PolicyRule('user-1', 'api/users', 'DELETE', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });
});

describe('Sad Paths', function (): void {
    test('returns 403 when subject is not authenticated', function (): void {
        // Arrange
        Patrol::resolveSubject(fn (): null => null);
        $request = Request::create('/api/users', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->headers->set('Accept', 'application/json');

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(403);
        expect(json_decode((string) $response->getContent(), true))->toBe([
            'message' => 'Unauthorized',
        ]);
    });

    test('returns 403 when no subject resolver is configured', function (): void {
        // Arrange
        // No subject resolver configured (Patrol::reset() already called in beforeEach)
        $request = Request::create('/api/users', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->headers->set('Accept', 'application/json');

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(403);
        expect(json_decode((string) $response->getContent(), true))->toBe([
            'message' => 'Unauthorized',
        ]);
    });

    test('returns 403 when policy evaluation denies access', function (): void {
        // Arrange
        $subject = new Subject('user-1', ['role' => 'guest']);
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/admin/users', Symfony\Component\HttpFoundation\Request::METHOD_DELETE);
        $request->headers->set('Accept', 'application/json');

        $policy = new Policy([
            new PolicyRule('user-1', 'admin/users', 'DELETE', Effect::Deny),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(403);
        expect(json_decode((string) $response->getContent(), true))->toBe([
            'message' => 'Unauthorized',
        ]);
    });

    test('returns 403 when no matching policy exists', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/secret', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->headers->set('Accept', 'application/json');

        $policy = new Policy([]); // Empty policy - will deny by default

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(403);
    });

    test('aborts with 403 for web requests expecting HTML', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/admin/users', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->headers->set('Accept', 'text/html');

        $policy = new Policy([
            new PolicyRule('user-1', 'admin/users', 'GET', Effect::Deny),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->andReturn($policy);

        // Act & Assert
        expect(fn () => $this->middleware->handle($request, $this->next))
            ->toThrow(HttpException::class);
    });
});

describe('Edge Cases', function (): void {
    test('handles PUT requests correctly', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/posts/123', Symfony\Component\HttpFoundation\Request::METHOD_PUT);
        $policy = new Policy([
            new PolicyRule('user-1', 'api/posts/123', 'PUT', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles PATCH requests correctly', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/posts/123', Symfony\Component\HttpFoundation\Request::METHOD_PATCH);
        $policy = new Policy([
            new PolicyRule('user-1', 'api/posts/123', 'PATCH', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles requests with query parameters in path', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/users?sort=name&filter=active', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('user-1', 'api/users', 'GET', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'api/users'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles subject with complex attributes', function (): void {
        // Arrange
        $subject = new Subject('user-1', [
            'role' => 'admin',
            'department' => 'IT',
            'permissions' => ['read', 'write', 'delete'],
        ]);
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/users', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('user-1', 'api/users', 'GET', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject && $s->id === 'user-1' && $s->attributes['role'] === 'admin'),
                Mockery::on(fn ($r): bool => $r instanceof Resource),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles resource resolver returning null', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        Patrol::resolveResource(fn ($id): null => null); // Resolver returns null

        $request = Request::create('/api/posts', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('user-1', 'posts', 'GET', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'posts' && $r->type === 'unknown'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next, 'posts');

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles nested resource paths', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/v1/admin/users/123/posts', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('user-1', 'api/v1/admin/users/123/posts', 'GET', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'api/v1/admin/users/123/posts'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles root path', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('user-1', '/', 'GET', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === '/'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('allows wildcard policies for any resource', function (): void {
        // Arrange
        $subject = new Subject('admin-1', ['role' => 'superadmin']);
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/anything', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('admin-1', '*', '*', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });
});

describe('Middleware Configuration', function (): void {
    test('explicit resource parameter takes precedence over request path', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/posts', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $policy = new Policy([
            new PolicyRule('user-1', 'articles', 'GET', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'articles' && $r->type === 'unknown'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next, 'articles');

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('explicit action parameter takes precedence over HTTP method', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/posts', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $policy = new Policy([
            new PolicyRule('user-1', 'api/posts', 'publish', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next, null, 'publish');

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('both explicit resource and action can be used together', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/some/path', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $policy = new Policy([
            new PolicyRule('user-1', 'documents', 'archive', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'documents'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next, 'documents', 'archive');

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('auto-detection mode works without any parameters', function (): void {
        // Arrange
        $subject = new Subject('user-1');
        Patrol::resolveSubject(fn (): Subject => $subject);

        $request = Request::create('/api/comments', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $policy = new Policy([
            new PolicyRule('user-1', 'api/comments', 'POST', Effect::Allow),
        ]);

        $this->policyRepository
            ->shouldReceive('getPoliciesFor')
            ->once()
            ->with(
                Mockery::on(fn ($s): bool => $s instanceof Subject),
                Mockery::on(fn ($r): bool => $r instanceof Resource && $r->id === 'api/comments' && $r->type === 'api'),
            )
            ->andReturn($policy);

        // Act
        $response = $this->middleware->handle($request, $this->next);

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });
});
