<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
|--------------------------------------------------------------------------
| Laravel Integration Tests
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    // Clear any resolver bindings from previous tests
});

afterEach(function (): void {
    // Cleanup after Laravel tests
});

/*
|--------------------------------------------------------------------------
| Test Groups
|--------------------------------------------------------------------------
*/

pest()->group('macros')->in('Macros');
pest()->group('builders')->in('Builders');
pest()->group('repositories')->in('Repositories');
pest()->group('commands')->in('Console');
pest()->group('concerns')->in('Concerns');
pest()->group('rules')->in('Rules');
pest()->group('audit')->in('AuditLoggers');
pest()->group('delegation')->in('DelegationTest.php');
pest()->group('visualization')->in('Visualization');
pest()->group('versioning')->in('PolicyVersioning');
pest()->group('testing-helpers')->in('Testing');
pest()->group('support')->in('Support');
pest()->group('facades')->in('Facades');
pest()->group('resolvers')->in('Resolvers');
