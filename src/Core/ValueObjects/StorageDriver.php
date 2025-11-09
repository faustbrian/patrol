<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Enumeration of supported storage mechanisms for policies and delegations.
 *
 * Defines the available storage backends that can be used to persist and retrieve
 * authorization policies and delegation records. Each driver provides different
 * characteristics in terms of performance, portability, and operational requirements.
 *
 * Storage drivers can be selected via configuration and switched at runtime to
 * support different deployment scenarios, testing strategies, and scaling requirements.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum StorageDriver: string
{
    /**
     * Database storage using Laravel's Eloquent ORM.
     *
     * Stores policies and delegations in relational database tables with full
     * ACID guarantees, query optimization, and transaction support. Best for
     * production environments requiring concurrent access and complex queries.
     */
    case Eloquent = 'eloquent';

    /**
     * JSON file-based storage.
     *
     * Persists data as JSON files with human-readable formatting. Suitable for
     * configuration management, version control integration, and development
     * environments. Supports versioning and single/multiple file modes.
     */
    case Json = 'json';

    /**
     * YAML file-based storage.
     *
     * Uses YAML format for data persistence with Symfony YAML component. Provides
     * excellent readability and supports complex nested structures. Ideal for
     * configuration-as-code workflows and GitOps practices.
     */
    case Yaml = 'yaml';

    /**
     * XML file-based storage.
     *
     * Stores data in XML format using Saloon Wrangler package. Supports schema
     * validation and transformation pipelines. Useful for enterprise integrations
     * and environments requiring XML-based audit trails.
     */
    case Xml = 'xml';

    /**
     * TOML file-based storage.
     *
     * Uses TOML format via yosymfony/Toml package. Provides clean syntax for
     * configuration files with strong typing. Suitable for structured configuration
     * management with minimal nesting.
     */
    case Toml = 'toml';

    /**
     * CSV file-based storage.
     *
     * Uses CSV format via League CSV package. Provides excellent compatibility
     * with spreadsheet applications and data analysis tools. Ideal for bulk
     * imports/exports and integration with business intelligence workflows.
     */
    case Csv = 'csv';

    /**
     * INI file-based storage.
     *
     * Uses PHP's native INI parsing with zero dependencies. Provides simple,
     * familiar configuration file format. Ideal for legacy system integration
     * and environments where external dependencies must be minimized.
     */
    case Ini = 'ini';

    /**
     * JSON5 file-based storage.
     *
     * Uses JSON5 format with comments, trailing commas, and unquoted keys via
     * colinodell/json5 package. Provides human-friendly JSON editing while
     * maintaining standard JSON output for maximum compatibility.
     */
    case Json5 = 'json5';

    /**
     * PHP serialized storage.
     *
     * Stores data using PHP's native serialize/unserialize functions. Fastest
     * file-based option for PHP-to-PHP persistence. Not human-readable and
     * not portable to other languages.
     */
    case Serialized = 'serialized';
}
