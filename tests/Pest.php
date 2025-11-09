<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Tests\CoreTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Suites Configuration
|--------------------------------------------------------------------------
*/

// Laravel integration tests
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Laravel');

// Framework-agnostic core tests
pest()->extend(CoreTestCase::class)
    ->in('Core');

// Unit tests requiring Laravel container
pest()->extend(CoreTestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toAllowAccess', fn () => $this->toBe(Effect::Allow));

expect()->extend('toDenyAccess', fn () => $this->toBe(Effect::Deny));

expect()->extend('toMatchRule', fn () => $this->toBeTrue());

/*
|--------------------------------------------------------------------------
| Global Helper Functions
|--------------------------------------------------------------------------
*/

function subject(string $id, array $attributes = []): Subject
{
    return new Subject($id, $attributes);
}

function resource(string $id, string $type, array $attributes = []): Resource
{
    return new Resource($id, $type, $attributes);
}

function patrol_action(string $name): Action
{
    return new Action($name);
}

function allow(): Effect
{
    return Effect::Allow;
}

function deny(): Effect
{
    return Effect::Deny;
}
