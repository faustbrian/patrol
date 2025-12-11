<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Throwable;

use function array_map;
use function array_slice;
use function assert;
use function config;
use function count;
use function explode;
use function is_int;
use function is_string;
use function now;
use function sprintf;
use function str_contains;

/**
 * Artisan command to migrate Spatie Laravel Permission policies to Patrol.
 *
 * Converts roles and permissions from spatie/laravel-permission to Patrol's
 * policy-based authorization system. Creates policy rules that map:
 * - Role permissions -> role-based policy rules (subject: "role:{name}")
 * - Direct user permissions -> user-specific policy rules (subject: "user:{id}")
 *
 * ROLE-BASED PERMISSIONS (RBAC):
 * User-role assignments are NOT migrated as denormalized rules. Instead, users
 * get permissions through roles at runtime by passing role information in the
 * Subject's attributes when calling authorize():
 *
 *   $subject = new Subject('user-123', ['roles' => ['role:editor']]);
 *   $patrol->authorize($subject, $resource, $action);
 *
 * The RbacRuleMatcher will check if any of the user's roles match the rule's
 * subject pattern, enabling true RBAC without denormalization overhead.
 *
 * DIRECT USER PERMISSIONS (ACL):
 * Users with direct permission grants (via Spatie's model_has_permissions) are
 * migrated as user-specific rules and work immediately without runtime changes.
 * This supports mixed scenarios where some users have role-based permissions
 * while others have direct grants.
 *
 * Usage:
 *   php artisan patrol:migrate-from-spatie
 *   php artisan patrol:migrate-from-spatie --dry-run
 *   php artisan patrol:migrate-from-spatie --connection=tenant_db
 *   php artisan patrol:migrate-from-spatie --priority=50
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PatrolMigrateFromSpatieCommand extends Command
{
    /**
     * The console command signature with options for migration configuration.
     *
     * @var string
     */
    protected $signature = 'patrol:migrate-from-spatie
                            {--dry-run : Show what would be migrated without persisting}
                            {--connection= : Database connection to use}
                            {--priority=10 : Default priority for migrated rules}
                            {--spatie-connection= : Spatie database connection (defaults to --connection)}';

    /**
     * The console command description displayed in command listings.
     *
     * @var string
     */
    protected $description = 'Migrate Spatie Laravel Permission roles and permissions to Patrol policies';

    /**
     * Execute the migration command.
     *
     * Reads roles and permissions from Spatie tables and creates equivalent
     * Patrol policy rules. Supports dry-run mode for verification before
     * persisting changes.
     *
     * @return int self::SUCCESS (0) on successful migration, self::FAILURE (1) on error
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run') === true;
        $connectionOption = $this->option('connection');
        $spatieConnectionOption = $this->option('spatie-connection');
        $priorityOption = $this->option('priority');

        assert(is_string($priorityOption), 'Priority option must be a string');
        $priority = new Priority((int) $priorityOption);

        $defaultConnection = '';
        $defaultConnectionValue = config('database.default');

        if (is_string($defaultConnectionValue)) {
            $defaultConnection = $defaultConnectionValue;
        }

        $connection = is_string($connectionOption) && $connectionOption !== '' ? $connectionOption : $defaultConnection;
        $spatieConnection = is_string($spatieConnectionOption) && $spatieConnectionOption !== '' ? $spatieConnectionOption : $connection;

        if ($dryRun) {
            $this->components->warn('DRY RUN MODE - No changes will be persisted');
            $this->newLine();
        }

        $this->components->info('Migrating Spatie permissions to Patrol...');
        $this->newLine();

        try {
            // Verify Spatie tables exist
            if (!$this->verifySpatieTablesExist($spatieConnection)) {
                $this->components->error('Spatie permission tables not found. Is spatie/laravel-permission installed?');

                return self::FAILURE;
            }

            $rules = [];

            // Migrate role permissions
            $this->components->task('Migrating role permissions', function () use ($spatieConnection, $priority, &$rules): void {
                $rules = [...$rules, ...$this->migrateRolePermissions($spatieConnection, $priority)];
            });

            // User role assignments are NOT migrated as denormalized rules.
            // Users should pass their roles in Subject attributes at runtime.
            // See migrateUserRoleAssignments() docblock for details.
            $this->components->info('Skipping user role assignments (use Subject attributes at runtime)');

            // Migrate direct user permissions
            $this->components->task('Migrating direct user permissions', function () use ($spatieConnection, $priority, &$rules): void {
                $rules = [...$rules, ...$this->migrateDirectUserPermissions($spatieConnection, $priority)];
            });

            $this->newLine();
            $this->components->info(sprintf('Found %d permission(s) to migrate', count($rules)));
            $this->newLine();

            if ($rules === []) {
                $this->components->warn('No permissions found to migrate');

                return self::INVALID;
            }

            // Display sample of rules
            $this->displayRuleSummary($rules);

            if (!$dryRun) {
                $this->components->task('Persisting policy rules', function () use ($connection, $rules): void {
                    $records = [];

                    foreach ($rules as $rule) {
                        $records[] = [
                            'subject' => $rule->subject,
                            'resource' => $rule->resource,
                            'action' => $rule->action,
                            'effect' => $rule->effect->value,
                            'priority' => $rule->priority->value,
                            'domain' => $rule->domain?->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    DB::connection($connection)->table('patrol_policies')->insert($records);
                });

                $this->newLine();
                $this->components->info('Migration completed successfully!');
            } else {
                $this->newLine();
                $this->components->warn('Dry run complete - no changes were persisted');
            }

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->components->error('Migration failed: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Verify that Spatie permission tables exist in the database.
     *
     * @param  string $connection The database connection to check
     * @return bool   True if all required tables exist, false otherwise
     */
    private function verifySpatieTablesExist(string $connection): bool
    {
        $schema = DB::connection($connection)->getSchemaBuilder();

        return $schema->hasTable('permissions')
            && $schema->hasTable('roles')
            && $schema->hasTable('role_has_permissions')
            && $schema->hasTable('model_has_permissions')
            && $schema->hasTable('model_has_roles');
    }

    /**
     * Migrate role-based permissions to Patrol policy rules.
     *
     * Converts Spatie role permissions into Patrol rules with subject pattern "role:{name}".
     * Each permission becomes an ALLOW rule for the role.
     *
     * @param  string            $connection Database connection
     * @param  Priority          $priority   Default priority for rules
     * @return array<PolicyRule> Array of policy rules created from role permissions
     */
    private function migrateRolePermissions(string $connection, Priority $priority): array
    {
        $rolePermissions = DB::connection($connection)
            ->table('role_has_permissions')
            ->join('roles', 'role_has_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->select('roles.name as role_name', 'permissions.name as permission_name')
            ->get();

        $rules = [];

        foreach ($rolePermissions as $rp) {
            $roleName = $rp->role_name;
            $permissionName = $rp->permission_name;

            // @codeCoverageIgnoreStart
            if (!is_string($roleName)) {
                continue;
            }

            if (!is_string($permissionName)) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            // Parse permission name: "edit posts" -> action="edit", resource="posts"
            [$action, $resource] = $this->parsePermissionName($permissionName);

            $rules[] = new PolicyRule(
                subject: 'role:'.$roleName,
                resource: $resource,
                action: $action,
                effect: Effect::Allow,
                priority: $priority,
            );
        }

        return $rules;
    }

    /**
     * Migrate user role assignments to Patrol policy rules.
     *
     * NOTE: This method is intentionally left empty in the default implementation.
     *
     * In Patrol's RBAC model, users get permissions through roles at runtime by
     * including the user's roles in the Subject's attributes['roles'] array when
     * calling authorize(). The RbacRuleMatcher then checks if any of the user's
     * roles match the rule's subject pattern.
     *
     * To properly migrate user-role assignments:
     * 1. Store the model_has_roles data in your application's User model
     * 2. When creating a Subject for authorization, populate the roles attribute:
     *    new Subject('user-123', ['roles' => ['role:editor', 'role:author']])
     * 3. The role-based rules (created by migrateRolePermissions) will automatically
     *    apply to users with those roles
     *
     * If you prefer denormalized user-specific rules instead of runtime evaluation,
     * you can uncomment the implementation below.
     *
     * @return array<PolicyRule> Empty array (denormalization not recommended for RBAC)
     * @codeCoverageIgnore
     *
     * @phpstan-ignore-next-line method.unused
     */
    private function migrateUserRoleAssignments(): array
    {
        // By default, we do NOT denormalize role permissions into user-specific rules.
        // Instead, applications should pass user roles in Subject attributes at runtime.
        return [];
        // Uncomment below if you prefer denormalized approach (not recommended):
        /*
        $userRoles = DB::connection($connection)
            ->table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->join('role_has_permissions', 'roles.id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->select(
                'model_has_roles.model_id as user_id',
                'roles.name as role_name',
                'permissions.name as permission_name',
            )
            ->get();

        $rules = [];

        foreach ($userRoles as $ur) {
            $userId = $ur->user_id;
            $permissionName = $ur->permission_name;

            if (!is_string($permissionName)) {
                continue;
            }

            [$action, $resource] = $this->parsePermissionName($permissionName);

            $rules[] = new PolicyRule(
                subject: "user:{$userId}",
                resource: $resource,
                action: $action,
                effect: Effect::Allow,
                priority: $priority,
            );
        }

        return $rules;
        */
    }

    /**
     * Migrate direct user permissions to Patrol policy rules.
     *
     * Converts Spatie direct user permissions (model_has_permissions) into Patrol rules
     * with subject pattern "user:{id}". Each permission becomes an ALLOW rule for the user.
     *
     * @param  string            $connection Database connection
     * @param  Priority          $priority   Default priority for rules
     * @return array<PolicyRule> Array of policy rules created from user permissions
     */
    private function migrateDirectUserPermissions(string $connection, Priority $priority): array
    {
        $userPermissions = DB::connection($connection)
            ->table('model_has_permissions')
            ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->select(
                'model_has_permissions.model_id as user_id',
                'permissions.name as permission_name',
            )
            ->get();

        $rules = [];

        foreach ($userPermissions as $up) {
            $userId = $up->user_id;
            $permissionName = $up->permission_name;

            // @codeCoverageIgnoreStart
            if (!is_string($permissionName)) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            // Parse permission name: "edit posts" -> action="edit", resource="posts"
            [$action, $resource] = $this->parsePermissionName($permissionName);

            // Ensure userId is cast to string for subject concatenation
            $userIdString = is_string($userId) ? $userId : (is_int($userId) ? (string) $userId : '');

            // @codeCoverageIgnoreStart
            if ($userIdString === '') {
                continue;
            }

            // @codeCoverageIgnoreEnd

            $rules[] = new PolicyRule(
                subject: 'user:'.$userIdString,
                resource: $resource,
                action: $action,
                effect: Effect::Allow,
                priority: $priority,
            );
        }

        return $rules;
    }

    /**
     * Parse a Spatie permission name into action and resource components.
     *
     * Supports multiple formats:
     * - "edit posts" -> ["edit", "posts"]
     * - "posts.edit" -> ["edit", "posts"]
     * - "posts-edit" -> ["edit", "posts"]
     * - "edit" -> ["edit", "*"]
     *
     * @param  string                $permissionName The permission name from Spatie
     * @return array{string, string} Tuple of [action, resource]
     */
    private function parsePermissionName(string $permissionName): array
    {
        // Try space-separated format: "edit posts"
        if (str_contains($permissionName, ' ')) {
            $parts = explode(' ', $permissionName, 2);

            return [$parts[0], $parts[1]];
        }

        // Try dot notation: "posts.edit"
        if (str_contains($permissionName, '.')) {
            $parts = explode('.', $permissionName, 2);

            return [$parts[1], $parts[0]];
        }

        // Try dash notation: "posts-edit"
        if (str_contains($permissionName, '-')) {
            $parts = explode('-', $permissionName, 2);

            return [$parts[1], $parts[0]];
        }

        // Single word permissions treated as action on all resources
        return [$permissionName, '*'];
    }

    /**
     * Display a summary table of rules to be migrated.
     *
     * Shows first 10 rules in a formatted table for user verification.
     *
     * @param array<PolicyRule> $rules The rules to summarize
     */
    private function displayRuleSummary(array $rules): void
    {
        $this->components->info('Sample of rules to be created:');
        $this->newLine();

        $headers = ['Subject', 'Resource', 'Action', 'Effect', 'Priority'];
        $sample = array_slice($rules, 0, 10);

        $rows = array_map(fn (PolicyRule $rule): array => [
            $rule->subject,
            $rule->resource ?? '*',
            $rule->action,
            '<fg=green>ALLOW</>',
            (string) $rule->priority->value,
        ], $sample);

        $this->table($headers, $rows);

        if (count($rules) <= 10) {
            return;
        }

        $this->components->info(sprintf('... and %d more', count($rules) - 10));
        $this->newLine();
    }
}
