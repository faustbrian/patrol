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
    | used by Patrol when evaluating authorization policies. Each matcher
    | provides different capabilities for policy evaluation and should
    | be selected based on your application's requirements.
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
    | Configure how Patrol stores and retrieves policies and delegations.
    | Multiple storage drivers are supported, each with different characteristics
    | to suit various deployment scenarios and requirements.
    |
    | File-based drivers support versioning and can operate in single-file
    | or multiple-file modes for different organizational needs.
    |
    */

    'storage' => [
        /*
        |--------------------------------------------------------------------------
        | Storage Driver
        |--------------------------------------------------------------------------
        |
        | The default storage driver for policies and delegations. This driver
        | determines how authorization rules are persisted and retrieved.
        |
        | Supported: "eloquent", "json", "yaml", "xml", "toml", "csv", "ini", "json5", "serialized"
        |
        */

        'driver' => env('PATROL_STORAGE_DRIVER', 'eloquent'),
        /*
        |--------------------------------------------------------------------------
        | Storage Path
        |--------------------------------------------------------------------------
        |
        | For file-based drivers, this defines the base directory where policy
        | and delegation files will be stored. Each driver will organize files
        | within subdirectories based on the configured file mode.
        |
        */

        'path' => env('PATROL_STORAGE_PATH', storage_path('patrol')),
        /*
        |--------------------------------------------------------------------------
        | File Organization Mode
        |--------------------------------------------------------------------------
        |
        | Determines how file-based drivers organize policy and delegation data.
        | Single mode stores all data in one file per type, while multiple mode
        | creates individual files for better version control and organization.
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
        | When using file-based storage with versioning enabled, this option
        | allows you to pin the system to a specific version. Leave null to
        | automatically use the latest available version.
        |
        */

        'version' => env('PATROL_STORAGE_VERSION'),
        /*
        |--------------------------------------------------------------------------
        | Database Configuration
        |--------------------------------------------------------------------------
        |
        | These options are used when the "eloquent" storage driver is selected.
        | You may specify which database connection and tables should be used
        | for storing policies and delegations.
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
        | These settings control semantic versioning for file-based storage.
        | Versioning helps maintain an audit trail and enables rollback to
        | previous policy configurations when needed.
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
        | The number of seconds that file contents should be cached in memory.
        | This improves performance for file-based drivers by reducing disk I/O.
        | Set to 0 to disable caching entirely.
        |
        */

        'cache_ttl' => env('PATROL_STORAGE_CACHE_TTL', 3_600),
    ],
    /*
    |--------------------------------------------------------------------------
    | Gates Integration
    |--------------------------------------------------------------------------
    |
    | Enable integration with Laravel's Gate system. When enabled, Patrol
    | policies are checked before Laravel's native authorization gates,
    | allowing you to use both Patrol and traditional Laravel authorization
    | together seamlessly.
    |
    | This allows you to use Gate::allows(), $user->can(), and @can directives
    | with Patrol policies automatically.
    |
    */

    'integrate_gates' => env('PATROL_INTEGRATE_GATES', true),
    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | These options control the behaviour of the Patrol authorization
    | middleware. You may define a custom handler to process unauthorized
    | access attempts, allowing you to customize error responses or
    | redirect users to specific routes.
    |
    | The default effect determines what happens when no policy explicitly
    | matches the request. Setting this to "deny" provides a secure
    | default, requiring explicit permission for all actions.
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
    | identified for authorization checks. By default, the LaravelSubjectResolver
    | uses Laravel's built-in authentication system to retrieve the authenticated
    | user.
    |
    | You may provide a custom resolver class that implements the
    | SubjectResolverInterface if your application uses alternative authentication
    | mechanisms or needs to identify subjects from different sources such as API
    | tokens or service accounts.
    |
    */

    'subject_resolver' => LaravelSubjectResolver::class,
    /*
    |--------------------------------------------------------------------------
    | Tenant Resolver
    |--------------------------------------------------------------------------
    |
    | For multi-tenant applications, this resolver determines the current
    | tenant or domain context for authorization checks. This allows you
    | to scope policies to specific tenants, ensuring proper isolation
    | of authorization rules between different organisational units.
    |
    | Set this to a closure that returns the current tenant instance, or
    | leave it null if your application doesn't require tenant isolation.
    |
    */

    'tenant_resolver' => null,
    /*
    |--------------------------------------------------------------------------
    | Resource Resolver
    |--------------------------------------------------------------------------
    |
    | The resource resolver translates resource identifiers into actual
    | model instances or resource objects. This allows your policies to
    | work with string identifiers while still having access to the full
    | resource context when evaluating authorization rules.
    |
    | Define a closure that accepts a resource identifier and returns the
    | corresponding resource instance, or null to use default behaviour.
    |
    */

    'resource_resolver' => null,
    /*
    |--------------------------------------------------------------------------
    | Database Schema Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the database schema options for Patrol's tables including
    | primary key type and soft delete behaviour.
    |
    */

    'database' => [
        /*
        |--------------------------------------------------------------------------
        | Primary Key Type
        |--------------------------------------------------------------------------
        |
        | The primary key type to use for Patrol's database tables. This setting
        | determines how primary keys are generated for policies and delegations.
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
        | Enable soft deletes for Patrol's database tables. When enabled, records
        | are marked as deleted rather than being permanently removed from the
        | database. This provides an audit trail and allows for data recovery.
        |
        */

        'soft_deletes' => env('PATROL_SOFT_DELETES', false),
    ],
    /*
    |--------------------------------------------------------------------------
    | Delegation Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control permission delegation, allowing users to temporarily
    | grant their permissions to other users. This feature is particularly useful
    | for vacation coverage, task handoffs, and collaborative workflows where
    | temporary access needs to be granted without modifying core permissions.
    |
    | When enabled, the system evaluates delegated permissions alongside direct
    | permissions during authorization checks. Delegated permissions are always
    | additive - they cannot override explicit deny rules in your policies,
    | ensuring that security constraints remain enforced.
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
