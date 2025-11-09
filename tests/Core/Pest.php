<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Effect;

/*
|--------------------------------------------------------------------------
| Core Tests - Framework Agnostic
|--------------------------------------------------------------------------
| These tests MUST NOT use any Laravel-specific features.
*/

dataset('effects', [
    'allow' => [Effect::Allow],
    'deny' => [Effect::Deny],
]);

dataset('actions', [
    'read' => ['read'],
    'write' => ['write'],
    'delete' => ['delete'],
    'update' => ['update'],
]);

dataset('priorities', [
    'low' => [1],
    'medium' => [50],
    'high' => [100],
]);

dataset('subject_ids', [
    'user-1',
    'user-2',
    'admin-1',
]);
