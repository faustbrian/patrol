<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

arch('core value objects are readonly')
    ->expect('Patrol\Core\ValueObjects')
    ->classes()
    ->toBeReadonly();

arch('core value objects are final')
    ->expect('Patrol\Core\ValueObjects')
    ->classes()
    ->toBeFinal();

arch('contracts are interfaces')
    ->expect('Patrol\Core\Contracts')
    ->toBeInterfaces();

// Will be enabled once rule matchers are implemented
// arch('rule matchers implement RuleMatcherInterface')
//     ->expect('Patrol\Core\Engine')
//     ->classes()
//     ->that->haveSuffix('RuleMatcher')
//     ->toImplement('Patrol\Core\Contracts\RuleMatcherInterface');
