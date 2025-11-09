[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

Flexible authorization for Laravel with support for 11+ access control models. Choose the model that fits your needs - from simple ACL to complex ABAC - or combine multiple models in the same application.

## Requirements

> **Requires [PHP 8.2+](https://php.net/releases/)**
> **Requires [Laravel 11.0+](https://laravel.com/docs)**

## Installation

```bash
composer require patrol/patrol
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=patrol-config
```

## Documentation

### Getting Started
- **[Quick Start](cookbook/guides/QUICK-START.md)** - Get up and running in minutes
- **[Quick Reference](cookbook/guides/QUICK-REFERENCE.md)** - Choose your model in 2 minutes
- **[Beginner's Path](cookbook/guides/GETTING-STARTED.md)** - ACL → RBAC → ABAC learning path

### Authorization Models
- **[Authorization Models Overview](cookbook/guides/AUTHORIZATION-MODELS-OVERVIEW.md)** - All 11 models with examples
- **[ACL Models](cookbook/models/ACL.md)** - Access Control List (basic, superuser, without users, without resources)
- **[RBAC Models](cookbook/models/RBAC.md)** - Role-Based Access Control (basic, resource roles, domains)
- **[ABAC](cookbook/models/ABAC.md)** - Attribute-Based Access Control
- **[RESTful](cookbook/models/RESTful.md)** - HTTP path/method authorization
- **[Security Patterns](cookbook/models/Deny-Override.md)** - Deny-Override and Priority-Based

### Guides
- **[API Reference](cookbook/guides/API-REFERENCE.md)** - Complete API documentation
- **[Policy Builders](cookbook/guides/POLICY-BUILDERS.md)** - Fluent APIs for building policies
- **[CLI Tools](cookbook/guides/CLI-TOOLS.md)** - Test and debug from command line
- **[Configuration](cookbook/guides/CONFIGURATION.md)** - Complete configuration guide
- **[Persisting Policies](cookbook/guides/PERSISTING-POLICIES.md)** - Database, cache, and file storage

### Advanced Topics
- **[Complete Cookbook](cookbook/README.md)** - Comprehensive guides and patterns
- **[Delegation](cookbook/Delegation.md)** - Policy delegation patterns
- **[Native Delegation](cookbook/NativeDelegation.md)** - Laravel native authorization

## Change log

Please see the [Releases](https://github.com/faustbrian/patrol/releases) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/patrol/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/patrol/patrol.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/patrol/patrol.svg

[link-tests]: https://github.com/faustbrian/patrol/actions
[link-packagist]: https://packagist.org/packages/patrol/patrol
[link-downloads]: https://packagist.org/packages/patrol/patrol
[link-security]: https://github.com/faustbrian/patrol/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
