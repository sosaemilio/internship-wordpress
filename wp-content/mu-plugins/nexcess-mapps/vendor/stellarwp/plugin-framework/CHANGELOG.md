# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Version 1.5.0]

### Added

* Support for Feature Flags ([#85])

### Fixed

* Correctly set the `WP_REDIS_DISABLED` constant for Object Cache Pro ([#83])

## [Version 1.4.0]

### Added

* Adding support for Object Cache Pro ([#58])

## [Version 1.3.0]

### Added

* Create a Service and Module for recording telemetry data about the hosted site for platform improvements. ([#57]) 

### Updated

* Coding Standards and Static Analysis improvements ([#57])

## [Version 1.2.0]

### Added

* Provide documentation for the various testing tools within the framework ([#54])

### Fixed

* Use case-insensitive compare when determining if site is on a custom domain ([#55])

## [Version 1.1.0]

### Added

* Add the `enableProvisioningLogs()` command to the abstract Setup command ([#42])
* Add the `refresh()` method to the ProvidesSettings contract ([#48])
* Add a shell script for comparing plugin versions ([#49])
* Automatically create a draft release when a release branch is merged into main ([#51])
* Automatically rebuild the `dist/` directory when preparing a release ([#52])

### Fixed

* Don't put equals signs between arguments when invoking commands ([#50])

## [Version 1.0.1]

### Fixed

* Don't attempt to retrieve setup instructions from the StellarWP Partner Gateway without a valid ID ([#45])
* Log the attempted URL if setup instruction requests fail ([#44])

## [Version 1.0.0]

Initial release of the framework, including the following modules:

* AutoLogin
* ExtensionConfig
* GoLiveWidget
* Maintenance
* PurgeCaches
* SupportUsers

[Unreleased]: https://github.com/stellarwp/plugin-framework/compare/main...develop
[Version 1.0.0]: https://github.com/stellarwp/plugin-framework/releases/tag/v1.0.0
[Version 1.0.1]: https://github.com/stellarwp/plugin-framework/releases/tag/v1.0.1
[Version 1.1.0]: https://github.com/stellarwp/plugin-framework/releases/tag/v1.1.0
[Version 1.2.0]: https://github.com/stellarwp/plugin-framework/releases/tag/v1.2.0
[Version 1.3.0]: https://github.com/stellarwp/plugin-framework/releases/tag/v1.3.0
[Version 1.4.0]: https://github.com/stellarwp/plugin-framework/releases/tag/v1.4.0
[Version 1.5.0]: https://github.com/stellarwp/plugin-framework/releases/tag/v1.5.0
[#42]: https://github.com/stellarwp/plugin-framework/pull/42
[#44]: https://github.com/stellarwp/plugin-framework/pull/44
[#45]: https://github.com/stellarwp/plugin-framework/pull/45
[#48]: https://github.com/stellarwp/plugin-framework/pull/48
[#49]: https://github.com/stellarwp/plugin-framework/pull/49
[#50]: https://github.com/stellarwp/plugin-framework/pull/50
[#51]: https://github.com/stellarwp/plugin-framework/pull/51
[#52]: https://github.com/stellarwp/plugin-framework/pull/52
[#54]: https://github.com/stellarwp/plugin-framework/pull/54
[#55]: https://github.com/stellarwp/plugin-framework/pull/55
[#57]: https://github.com/stellarwp/plugin-framework/pull/57
[#58]: https://github.com/stellarwp/plugin-framework/pull/58
[#83]: https://github.com/stellarwp/plugin-framework/pull/83
[#85]: https://github.com/stellarwp/plugin-framework/pull/85
