<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Laravel\Resolvers\LaravelSubjectResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Rule Matcher
    |--------------------------------------------------------------------------
    |
    | This option controls the default rule matching strategy that will be
    | used by Patrol when evaluating authorization policies. The matcher
    | determines how policies are matched and evaluated against incoming
    | requests. You may change this value based on your requirements.
    |
    | Supported: "acl", "rbac", "abac", "restful"
    |
    */

    'default_matcher' => env('PATROL_MATCHER', 'acl'),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure how Patrol stores and retrieves authorization
    | policies and delegations. Multiple storage drivers are supported to
    | suit various deployment scenarios. File-based drivers also support
    | versioning for maintaining an audit trail of policy changes.
    |
    */

    'storage' => [

        /*
        |--------------------------------------------------------------------------
        | Storage Driver
        |--------------------------------------------------------------------------
        |
        | This option controls the default storage driver for policies and
        | delegations. The driver determines how authorization rules are
        | persisted and retrieved throughout your application.
        |
        | Supported: "eloquent", "json", "yaml", "xml", "toml",
        |            "csv", "ini", "json5", "serialized"
        |
        */

        'driver' => env('PATROL_STORAGE_DRIVER', 'eloquent'),

        /*
        |--------------------------------------------------------------------------
        | Storage Path
        |--------------------------------------------------------------------------
        |
        | When utilizing file-based storage drivers, this option defines the
        | base directory where policy and delegation files will be stored.
        | Files are organized within this path based on your file mode.
        |
        */

        'path' => env('PATROL_STORAGE_PATH', storage_path('patrol')),

        /*
        |--------------------------------------------------------------------------
        | File Organization Mode
        |--------------------------------------------------------------------------
        |
        | This option determines how file-based drivers organize policy and
        | delegation data. The "single" mode stores all data in one file per
        | type, while "multiple" mode creates individual files for better
        | version control and easier organization of complex rule sets.
        |
        | Supported: "single", "multiple"
        |
        */

        'file_mode' => env('PATROL_STORAGE_FILE_MODE', 'multiple'),

        /*
        |--------------------------------------------------------------------------
        | Storage Version
        |--------------------------------------------------------------------------
        |
        | When utilizing file-based storage with versioning enabled, you may
        | specify a particular version to use. Setting this to null will
        | automatically use the latest available version of your policies.
        |
        */

        'version' => env('PATROL_STORAGE_VERSION'),

        /*
        |--------------------------------------------------------------------------
        | Database Configuration
        |--------------------------------------------------------------------------
        |
        | These options are utilized when the "eloquent" storage driver is
        | selected. Here you may specify which database connection should
        | be used as well as the table names for policies and delegations.
        |
        */

        'database' => [
            'connection' => env('PATROL_DB_CONNECTION', 'default'),
            'policies_table' => env('PATROL_POLICIES_TABLE', 'patrol_policies'),
            'delegations_table' => env('PATROL_DELEGATIONS_TABLE', 'patrol_delegations'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Versioning Configuration
        |--------------------------------------------------------------------------
        |
        | These settings control semantic versioning for file-based storage
        | drivers. Versioning maintains an audit trail of policy changes and
        | enables rollback to previous configurations when needed. You may
        | configure automatic version bumping and retention policies here.
        |
        */

        'versioning' => [
            'enabled' => env('PATROL_VERSIONING_ENABLED', true),
            'auto_bump' => env('PATROL_VERSIONING_AUTO_BUMP', 'patch'),
            'keep_versions' => env('PATROL_VERSIONING_KEEP', 5),
        ],

        /*
        |--------------------------------------------------------------------------
        | Cache Time-To-Live
        |--------------------------------------------------------------------------
        |
        | Here you may specify the number of seconds that file contents should
        | be cached in memory. Caching improves performance for file-based
        | drivers by reducing disk I/O operations. Set to 0 to disable it.
        |
        */

        'cache_ttl' => env('PATROL_STORAGE_CACHE_TTL', 3_600),

    ],

    /*
    |--------------------------------------------------------------------------
    | Gates Integration
    |--------------------------------------------------------------------------
    |
    | This option enables integration with Laravel's Gate authorization
    | system. When enabled, Patrol policies are automatically checked
    | before Laravel's native gates, allowing you to seamlessly use both
    | systems together with Gate::allows(), $user->can(), and @can.
    |
    */

    'integrate_gates' => env('PATROL_INTEGRATE_GATES', true),

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the behaviour of Patrol's authorization
    | middleware. The unauthorized handler allows you to customize how
    | unauthorized access attempts are processed. The default effect
    | determines behaviour when no policy explicitly matches a request.
    |
    */

    'middleware' => [
        'unauthorized_handler' => null,
        'default_effect' => 'deny',
    ],

    /*
    |--------------------------------------------------------------------------
    | Subject Resolver
    |--------------------------------------------------------------------------
    |
    | The subject resolver determines how the current user or subject is
    | identified for authorization checks. By default, the resolver uses
    | Laravel's authentication system to retrieve the authenticated user.
    | You may provide a custom resolver implementing the interface if you
    | use alternative authentication mechanisms or service accounts.
    |
    */

    'subject_resolver' => LaravelSubjectResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolver
    |--------------------------------------------------------------------------
    |
    | For multi-tenant applications, this resolver determines the current
    | tenant context for authorization checks. This allows you to scope
    | policies to specific tenants, ensuring proper isolation between
    | different organisational units. Set this to a closure returning
    | the current tenant, or leave null if not using multi-tenancy.
    |
    */

    'tenant_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Resource Resolver
    |--------------------------------------------------------------------------
    |
    | The resource resolver translates resource identifiers into model
    | instances or resource objects. This allows your policies to work
    | with string identifiers while accessing the full resource context
    | when evaluating rules. Define a closure accepting an identifier
    | and returning the instance, or null to use default behaviour.
    |
    */

    'resource_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Database Schema Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the database schema options for Patrol's
    | database tables, including the primary key type and soft delete
    | behaviour for policies and delegations stored in the database.
    |
    */

    'database' => [

        /*
        |--------------------------------------------------------------------------
        | Primary Key Type
        |--------------------------------------------------------------------------
        |
        | This option defines the primary key type for Patrol's database tables.
        | The setting determines how primary keys are generated for policies
        | and delegations when using the database storage driver.
        |
        | Supported: "autoincrement", "uuid", "ulid"
        |
        */

        'primary_key_type' => env('PATROL_PRIMARY_KEY_TYPE', 'uuid'),

        /*
        |--------------------------------------------------------------------------
        | Soft Deletes
        |--------------------------------------------------------------------------
        |
        | This option enables soft deletes for Patrol's database tables. When
        | enabled, records are marked as deleted rather than being removed
        | from the database. This provides an audit trail and enables the
        | recovery of accidentally deleted policies and delegations.
        |
        */

        'soft_deletes' => env('PATROL_SOFT_DELETES', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Delegation Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control permission delegation, allowing users to
    | temporarily grant their permissions to others. This is useful for
    | vacation coverage and task handoffs. Delegated permissions are
    | evaluated alongside direct permissions but cannot override explicit
    | deny rules, ensuring that security constraints remain enforced.
    |
    */

    'delegation' => [
        'enabled' => env('PATROL_DELEGATION_ENABLED', false),
        'driver' => env('PATROL_DELEGATION_DRIVER', 'database'),
        'max_duration_days' => env('PATROL_DELEGATION_MAX_DAYS', 90),
        'allow_transitive' => env('PATROL_DELEGATION_TRANSITIVE', false),
        'auto_cleanup' => env('PATROL_DELEGATION_AUTO_CLEANUP', true),
        'retention_days' => env('PATROL_DELEGATION_RETENTION', 90),
        'cache_ttl' => env('PATROL_DELEGATION_CACHE_TTL', 3_600),
        'table' => 'patrol_delegations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control rate limiting for authorization checks to
    | prevent denial of service attacks, resource exhaustion, and policy
    | enumeration. The key strategy determines how attempts are tracked,
    | whether by user ID, IP address, or session ID based on your needs.
    |
    */

    'rate_limiting' => [
        'enabled' => env('PATROL_RATE_LIMIT_ENABLED', false),
        'max_attempts' => env('PATROL_RATE_LIMIT_MAX_ATTEMPTS', 60),
        'decay_seconds' => env('PATROL_RATE_LIMIT_DECAY_SECONDS', 60),
        'key_strategy' => env('PATROL_RATE_LIMIT_KEY', 'user'),
    ],

];

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// Here endeth thy configuration, noble developer!                            //
// Beyond: code so wretched, even wyrms learned the scribing arts.            //
// Forsooth, they but penned "// TODO: remedy ere long"                       //
// Three realms have fallen since...                                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
//                                                  .~))>>                    //
//                                                 .~)>>                      //
//                                               .~))))>>>                    //
//                                             .~))>>             ___         //
//                                           .~))>>)))>>      .-~))>>         //
//                                         .~)))))>>       .-~))>>)>          //
//                                       .~)))>>))))>>  .-~)>>)>              //
//                   )                 .~))>>))))>>  .-~)))))>>)>             //
//                ( )@@*)             //)>))))))  .-~))))>>)>                 //
//              ).@(@@               //))>>))) .-~))>>)))))>>)>               //
//            (( @.@).              //))))) .-~)>>)))))>>)>                   //
//          ))  )@@*.@@ )          //)>))) //))))))>>))))>>)>                 //
//       ((  ((@@@.@@             |/))))) //)))))>>)))>>)>                    //
//      )) @@*. )@@ )   (\_(\-\b  |))>)) //)))>>)))))))>>)>                   //
//    (( @@@(.@(@ .    _/`-`  ~|b |>))) //)>>)))))))>>)>                      //
//     )* @@@ )@*     (@)  (@) /\b|))) //))))))>>))))>>                       //
//   (( @. )@( @ .   _/  /    /  \b)) //))>>)))))>>>_._                       //
//    )@@ (@@*)@@.  (6///6)- / ^  \b)//))))))>>)))>>   ~~-.                   //
// ( @jgs@@. @@@.*@_ VvvvvV//  ^  \b/)>>))))>>      _.     `bb                //
//  ((@@ @@@*.(@@ . - | o |' \ (  ^   \b)))>>        .'       b`,             //
//   ((@@).*@@ )@ )   \^^^/  ((   ^  ~)_        \  /           b `,           //
//     (@@. (@@ ).     `-'   (((   ^    `\ \ \ \ \|             b  `.         //
//       (*.@*              / ((((        \| | |  \       .       b `.        //
//                         / / (((((  \    \ /  _.-~\     Y,      b  ;        //
//                        / / / (((((( \    \.-~   _.`" _.-~`,    b  ;        //
//                       /   /   `(((((()    )    (((((~      `,  b  ;        //
//                     _/  _/      `"""/   /'                  ; b   ;        //
//                 _.-~_.-~           /  /'                _.'~bb _.'         //
//               ((((~~              / /'              _.'~bb.--~             //
//                                  ((((          __.-~bb.-~                  //
//                                              .'  b .~~                     //
//                                              :bb ,'                        //
//                                              ~~~~                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
