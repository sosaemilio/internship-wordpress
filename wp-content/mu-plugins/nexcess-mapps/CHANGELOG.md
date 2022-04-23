# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Version 1.27.5 - 2022-03-30

### Added

* Show a notice on the plugins screen if a user has a cart fragments replacement plugin installed
* Set Active Template for Look and Feel Templates

### Updated

* Added StudioPress/Synthesis to migration cleaner process enhancement
* Allow internationalized domain names in go live domain validation
* Improve Kadence Import

### Fixed

* Add Step 1 to Go Live Widget

## Version 1.27.4 - 2022-03-24

### Updated

* Update WP-CLI commands to support either `wp nxmapps` or `wp nexcess-mapps`
* Update WP-CLI commands for support are now `nxmapps support details` and `nxmapps support user`
* Deprecate `wp nxmapps` for details and `wp nxmapps support-user` for a support user


## Version 1.27.3 - 2022-03-16

### Fixed

* Fix various bugs for StoreBuilder
* Fix PHP 8 errors in Performance Monitor
* Add more checks for WooCommerce Automated Testing enabling/disabling
* Fix password reset failing for StoreBuilder

### Updated

* Update StoreBuilder Documentation
* Show error for invalid image type when uploading logo

## Version 1.27.2 – 2022-03-15

### Fixed

* Update quickstart_app_url to use default value if set to a blank string


## Version 1.27.1 – 2022-03-15

### Fixed

* Remove the multiple fallback which is setting the quickstart_app_url to an empty string


## Version 1.27.0 - 2022-03-15

### Added

* Add StoreBuilder 3.0 interface and functionality
* Package StellarWP Installer
* Add `nexcess_mapps_show_admin_bar` filter to allow hiding the Nexcess MAPPS admin bar item

### Updated

* Remove dependency on Fail2Ban
* Update Performance Monitor crons when enabling/disabling
* Prevent Performance Monitor from loading on temp domains
* Update README to clarify customizations options
* Remove Fast checkout integration

### Fixed

* Add check for date in dateToTitle helper inside Performance Monitor
* Fire an action when the Options page is saved
* Correctly save WooCommerce Automated Testing setting via wp-cli


## Version 1.26.5 – 2022-02-02

### Added

* Add new Settings/Options page functionality and interface

### Updated

* Prevent WCAT from being registered for multisite
* Remove temporary maintenance task that deactivates WCAT on some sites
* Include active theme & QuickStart ID in telemetry report
* Use Kadence as the default theme
* Update README to clarify customization options

### Fixed

* Fix date display for Performance Monitor
* Address Performance Monitor feedbac
* Fix an error when an empty string was returned for `active_plugins`
* Fix cronjob adding extra lines in .htaccess


## Version 1.26.4 – 2022-01-10

### Added

* Add PHP 8.1 EOL date
* Show a default message on the Settings screen if none are available
* Add data center location and name to Performance Monitor data
* Add migration cleanup rules for Pantheon, GoDaddy, RunCloud, DreamPress, and SiteGround

### Updated

* Disable Restrict Content Pro emails on staging sites
* Ensure that WCAT is only activated on eligible sites
* Update StoreBuilder help link

### Fixed

* Don't attempt to load a PluginConfig if the `$plugin` variable is not a string
* Default to a known Performance Monitor endpoint if one is not set in config
* Fix PHP and JS errors in Performance Monitor
* Fix PHP warning in PluginConfManager
* Remove additional files from Pagely in the MigrationCleaner service
* Expose the priority pages regardless of automatic plugin updates

## Version 1.26.3 – 2021-12-17

This version reverts the changes from 1.26.2 back to 1.26.1.

## Version 1.26.2 — 2021-12-17

### Added

* Default to a known Performance Monitor endpoint if one is not set
* Add data center location and name to Performance Monitor data

### Updated

* Remove Ultimate Dashboard dependency
* Ensure that WCAT is only activated on eligible sites
* Update StoreBuilder help link

### Fixed

* Fix PHP and JS errors in Performance Monitor
* Fix PHP warning in PluginConfManager

## Version 1.26.1 — 2021-11-22

### Updated

* Hide Fast promotional banner for StoreBuilder sites

## Version 1.26.0 — 2021-11-11

> ⚠️ **Notice:** This release adds a deprecation notice for sites running WordPress 5.1 or earlier. The MU plugin will cease loading on any version of WordPress lower than 5.2 in January 2022.

### Added

* Define a standard way to customize plugin installations
* Enable versions of WordPress to be marked as **deprecated** ahead of dropping support
* Automatically enable WooCommerce Automated Testing on new Managed WooCommerce sites
* Expose a setting to enable/disable WooCommerce Automated Testing after initial activation
* Add new data points to the telemetry report
* Disable iThemes Security modules on regression sites
* Apply filterable branding across the plugin

### Updated

* Update Nexcess branding
* Convert the `AdminBar` class into a service
* Update the SiteWorx configuration cache time from five minutes to one hour
* Update coding standards and linting rules across the codebase

### Fixed

* Fix permissions issue with pages using customized menus
* Fix non-static calls to static class methods
* Prevent unnecessary redirection upon activation of Iconic plugins

### Removed

* Removed the OPCache integration

## Version 1.25.1 — 2021-10-01

### Added

* Installs WC PDF Invoices & Packing slips for StoreBuilder sites
* Updates WooCommerce database on StoreBuilder set up

### Updated

* Updates welcome video for StoreBuilder sites
* Numbers nameservers in Go Live widget

### Fixed

* Fixes whitescreen/fatal error when changing from Simple Admin menu to full menu
* Fixes StoreBuilder admin CSS being applied to all sites

## Version 1.25.0 — 2021-09-18

### Added

* Enables the FastCheckout integration
* Adds help link to the Fast admin notification
* Adds automatic configuration of Kadence & plugins to auto-install during StoreBuilder setup
* Adds automatic installation of Better Product Reviews For WooCommerce during StoreBuilder setup
* Adds automatic installation of WooCommerce PDF Invoices & Packing Slip during StoreBuilder setup
* Adds automatic installation of Spotlight Social Media Feeds during StoreBuilder setup
* Sets sensible options for the site and for WooCommerce during StoreBuilder setup
* Adds helpful Tools & Advanced Steps widgets to StoreBuilder sites

### Updated

* Updates WooCoomerce Automated Testing icons, including a new state for skipped tests
* Updates Settings Page label from Enabled to Active
* Changes environment display plugin
* Updates VisualComparision URL limit to 10 to match the amount that the server processes
* Updates Domain Change widget interface to be more friendly
* Updates language in StoreBuilder support widget
* Updates the WordPress dashboard & admin experience for StoreBuilder with a brand new UI

### Fixed

* Fixes settings page not always saving options
* Fixes Settings Page leaving you on the wrong page when saving
* Fixes fatal error when W3 Total Cache is network activated
* Fixes display of internal Nexcess email address when changing API credentials for Kadence


## Version 1.24.2 — 2021-09-01

### Updated

* Restore the Fast Checkout integration

### Fixed

* Don't let the FatalErrorHandler class move files within the MU plugin
* Prevent priority pages from returning responses that are not arrays
* Fix alignment of tabs on dashboard screens
* Ensure the brand logo doesn't get overlapped by the page title on the dashboard screens


## Version 1.24.1 — 2021-08-26

### Fixed

* Fixes an issue where an update to the Ultimate Dashboard plugin could cause a Fatal Error on a site.


## Version 1.24.0 — 2021-08-20

Re-release of version 1.23.0 with correctly structured artifact.


## Version 1.23.1 — 2021-08-18

Revert deployment of version 1.23.0, as the release artifact were structured improperly.


## Version 1.23.0 — 2021-08-18

### Added

* Add "Settings" page to WP-Admin
* Add ability to enable or disable WooCommerce Cart Fragments script
* Add FastCheckout integration with Fast Checkout For WooCommerce
* Add `nexcess_mapps_before_loading` and `nexcess_mapps_loaded` actions

### Updated

* Update dashboard RSS feed URLs to use combined feed
* Increase number of items per page of the Plugin Performance Monitor

### Fixed

* Ensure the Streamlined Admin Menu does not error when switching between full menu and streamlined menu
* Prevent a fatal interaction between the Woo Performance monitor and the performance monitor WP-CLI command


## Version 1.22.1 — 2021-08-04

### Fixed

* Add fix to ensure the PPM endpoint has a protocol prior to uses

## Version 1.22.0 — 2021-08-03

### Added

* Pre-install WPForms Lite on new MWP sites
* Introduce the first version of WooCommerce Automated Testing
* Introduce the first version of the Plugin Performance Monitor

### Updated

* Rename "Visual Comparison" to "Priority Pages" in WP-Admin


## Version 1.21.2 — 2021-07-08

### Updated

* Moved the `SimpleAdminMenu` feature toggle to the bottom of the menu and updated notification messages
* Updated the copy on the domain change widget
* Updated the `QuickStart` welcome box styling and change content to load from the QuickStart API
* Updated Dashboard icon link widgets to support specific branded icons

### Fixed

* Fixed a class name conflict within the `wp nxmapps setup` command
* Prevent the Ultimate Dashboard rating request notice from displaying
* Updated StoreBuilder out of date links to moved and new help content


## Version 1.21.1 — 2021-07-01

### Updated

* Updated the documentation URL in the DomainChange widget

### Fixed

* Prevent WP QuickStart widgets from disappearing when the streamlined admin menus are disabled
* Don't load the `QuickStart` integration on StoreBuilder sites


## Version 1.21.0 — 2021-06-28

### Added

* Introduce the first version of WP QuickStart
* Introduce a simplified WP Admin menu for WP QuickStart sites
* Add a dashboard widget to enable domain changes from within WP Admin
* Introduce a WP-CLI command for interacting with the MAPPS API
* Add caching details to the support details command

### Updated

* Enable managers to resolve using the DI container
* Exclude specific EDD pages + cookies from the page cache
* Send the StoreBuilder welcome email through the StoreBuilder application instead of WordPress
* Refactor the logger class for future extensibility
* Update dependencies

### Fixed

* Fix improper command escaping by WP-CLI run-in-process runcommand handling


## Version 1.20.0 — 2021-05-27

### Added

* Add a Partners integration
* Enable the Installer class to handle themes from WordPress.org
* Add migration cleanup definitions for EasyWP by Namecheap
* Move broken MU plugins aside if they cause fatal errors
* Automatically create an empty favicon (if one doesn't exist) during site provisioning

### Updated

* Update the list of Liquid Web MWP v2 files
* Refactor ManagesDropIns into the DropIn service class
* Refactor ManagesWpConfig trait into the WPConfig service
* Updated [the Nexcess Page Cache to version 1.9.0](https://github.com/nexcess/cache-enabler/releases/tag/v1.9.0%2Bmapps)


## Version 1.19.0 — 2021-04-21

### Added

* Register custom Site Health checks for full page caching, WP-Cron
* Introduce a framework for registering custom WP REST API routes
* Enable default visual regression URLs to be set via filter (see [README.md])
* Introduce a PSR-3 compliant logger
* Add a "nxmapps cache flush" WP-CLI command
* Add two sub-commands to the `nxmapps vc` namespace: `upload-dir` (path to the uploads directory) and `plugins` (list of plugins eligible for visual comparison)
* Register a daily maintenance task to look for broken object-cache.php drop-ins

### Updated

* Add Speed Booster Pack, Comet Cache, and Breeze to the list of known page cache alternatives
* Include the value of `WP_CONTENT_DIR` and the uploads directory in "nxmapps details"
* Improve the WP-CLI bootstrap performance by omitting heavier extensions that have no use in a WP-CLI context
* Enable AdminNotices to use alternative colors
* Move the check for the "nexcess_mapps_branding_enable_support_template" filter into `shouldLoadIntegration()`
* Disable Wordfence's Web Application Firewall (WAF) on regression sites
* Remove the temporary `PageCache::refreshPageCacheHtaccess` maintenance task


### Fixed

* Re-enabled the ability to update the Cache Enabler rewrite rules upon settings updates, but **only** if the page cache is enabled _and_ there's an existing snippet in the Htaccess file
* Explicitly call `ObjectCache::enableRedisCache()` when activating object cache via WP-CLI
* Short-circuit the "nexcess_selfinstall_pending_licensing" option on staging sites
* Don't create Cache Enabler directories unless the bundled page cache or Cache Enabler are active
* Fixed ambiguous messaging when activating and deactivating the bundled page cache via WP-CLI


## Version 1.18.6 — 2021-03-25

### Updated

* Updated the bundled version of Cache Enabler to [1.8.2+mapps](https://github.com/nexcess/cache-enabler/releases/tag/v1.8.2%2Bmapps)
* Add NitroPack to the list of alternate page caching plugins
* Explicitly replace any whitespace in Htaccess rewrite rules

### Fixed

* Only enable the bundled page cache in `PageCache::settingsUpdated()`


## Version 1.18.5 — 2021-03-25

### Updated

* Updated the bundled version of Cache Enabler to [1.8.0+mapps](https://github.com/nexcess/cache-enabler/releases/tag/v1.8.0%2Bmapps)
* Disable all animations on regression sites

### Fixed

* Update the Cache Enabler Htaccess snippet so it properly separates HTTP from HTTPS versions of the page cache


## Version 1.18.4 — 2021-03-23

### Updated

* Remove the migration to the bundled page cache
* Add Hummingbird to the list of known page cache plugins
* Listen for plugin changes via WP-CLI

### Fixed

* Disabling the page cache should set 'enabled' to 0, not 1
* Re-queue the pre-install plugin cron job if the MAPPS API request fails
* Remove leftover `/** Enables page caching for Cache Enabler. */` blocks from `wp-config.php`


## Version 1.18.3 — 2021-03-20

### Updated

* Updated the bundled version of Cache Enabler to [1.7.1+mapps](https://github.com/nexcess/cache-enabler/releases/tag/v1.7.1%2Bmapps)


## Version 1.18.2 — 2021-03-19

### Updated

* Periodically check the Htaccess file for rewrite Cache Enabler rewrite rules
* Add a temporary maintenance job to clean up sites that accidentally replaced the `advanced-cache.php` drop-in during the 1.18.0 release
* Prevent the bundled page cache from being loaded on Multisite installations

### Fixed

* Fix the overly-greedy page cache migration
* Attempt to re-activate page cache plugins that were mistakenly deactivated in v1.18.0
* Retry pre-install plugins immediately after site setup
* Write installer errors to the error log


## Version 1.18.1 — 2021-03-18

### Fixed

* Temporarily removed the migration to the built-in page cache.


## Version 1.18.0 — 2021-03-18

### Added

* Introduce a built-in page caching solution, powered by Nexcess' fork of Cache Enabler ([Documentation](https://nexcess.link/v1180-full-page-cache))
* Enable `AdminNotice` instances to be persisted via the Transients API
* Add infrastructure for the automatic validation of Htaccess files before writes, though its use is currently hidden behind a filter
* Register Bluehost artifacts within the MigrationCleaner service

### Updated

* Adjust Cache Enabler's Htaccess rules to match plugin settings
* Put Jetpack Photon into dev mode for regression sites

### Fixed

* Automatically prefix MAPPS API licensing WP-CLI commands with "wp"
* Be more considerate when setting the default Kadence header
* Fix the Nexcess icon color in the WP Admin Bar


## Version 1.17.1 — 2021-03-10

### Fixed

* Fixed an incorrect output call while setting default permalink structures


## Version 1.17.0 — 2021-03-10

### Added

* Enable site owners to automatically perform search-replace upon domain change
* Add a new `wp nxmapps migration` command for cleaning up after migrating sites onto Nexcess

### Updated

* Split product importing into a separate service class
* Set a minimum Jetpack version for the corresponding integration
* Leverage the new `pre_wp_mail` filter to short-circuit emails on regression sites (WordPress 5.7+)
* Update admin color schemes to be compatible with WordPress 5.7+
* Added rules for Flywheel to the MigrationCleaner service
* Use instance, not static methods on the `PageCache` integration
* Clean up the fatal error handler

### Fixed

* Restrict the scope of the CDN matching regex


## Version 1.16.6 — 2021-02-24

### Updated

* Include wp_get_environment_type() in the support details
* Clean up the fatal error handler

### Fixed

* Register a new CDN integration for the purpose of fixing compatibility issues with the Nexcess CDN and CDN Enabler


## Version 1.16.5 — 2021-02-15

### Updated

* Remove the temporary Cache Enabler migration task
* Re-use existing content when possible upon (re-)ingestion
* Split Kadence licensing into its own WP-CLI subcommand
* Support placeholder attributes during StoreBuilder ingestion
* Update StoreBuilder URLs

### Fixed

* Properly activate the Kadence Pro plugin

## Version 1.16.4 — 2021-02-10

### Fixed

* Further fixes around the licensing of premium Kadence plugins on StoreBuilder sites


## Version 1.16.3 — 2021-02-09

### Updated

* Use the Robots API (WordPress 5.7+) for adding noindex to support users
* Warn StoreBuilder customers if the ingestion process did not complete successfully
* Update the email address pattern used for support users

### Fixed

* Fixed a bug where Kadence plugins weren't being licensed during the first setup run
* Delay the sending of the StoreBuilder welcome email until after ingestion has completed
* Automatically re-create Cache Enabler directories after the cache is cleared


## Version 1.16.2 — 2021-02-03

### Updated

* Pre-install Astra as part of the setup script on MWCH sites

### Fixed

* Ensure the Kadence Pro theme add-on gets licensed
* Fixed a bug where MAPPS flags weren't being cached
* Ensure Kadence, not Astra, is the default theme for StoreBuilder sites
* Fixed an issue where the WordPress database wasn't being updated during `nxmapps setup`


## Version 1.16.1 — 2021-02-02

### Updated

* Update the StoreBuilder welcome screen and widgets with the latest content
* Install Kadence Pro as part of StoreBuilder site setup
* Hide the Redis Cache dashboard widget by default on StoreBuilder
* Prevent the Instagram Feed plugin from filling the dashboard with nags
* Hide the Recapture Promotion for StoreBuilder customers
* Send the password reset as part of StoreBuilder setup

### Fixed

* Automatically update the WordPress database in the setup script after updating core
* Fixed issue where Kadence Blocks Pro wasn't pre-activating
* Force Kadence as the default theme for StoreBuilder sites
* Fixed an issue where the site name + navigation weren't appearing on StoreBuilder sites


## Version 1.16.0 — 2021-02-02

### Added

* Introduce a PSR-11 Dependency Injection (DI) container
* Define a base WP-CLI command and resolve commands through the DI container
* Initial public release of the StoreBuilder integration
* Update WordPress core as part of the `nxmapps setup` command
* Automatically clean up migration artifacts from Kinsta, Pagely, and WP Engine
* Check visual regression URLs from the UI
* Introduce a `ConsoleCommand` support class, ensuring all WP-CLI commands invoked from within the MU plugin are wrapped with "nice" and "timeout"
* Attempt to insert missing anchors in the site's wp-config.php file
* Prevent Kadence from hijacking WP-Admin
* Add a temporary cleanup task to the PageCache integration

### Updated

* Split page cache functionality into a separate integration
* Rewrite the Visual Comparisons UI in React
* Hide the Redis Cache Pro dashboard widget by default
* Block search engines from indexing support users
* Update Cache Enabler rewrite rules and defaults, including the exclusion of Event Calendar Pro's `/events/*` path from Cache Enabler by default
* Update the namespace used for WPConfigTransformer
* Change the "Get Support" link in WooCommerce Admin
* Don't load the Feedback integration if disabled via filter
* Raise the default MAPPS API timeout to 30 seconds
* Move WooCommerce defaults into the WooCommerce integration
* Update Nexcess MAPPS Dashboard to 1.3.6
* Update wp-fail2ban to 4.3.0.9

### Fixed

* Ensure the `StagingSites` integration adds its hooks on staging sites
* Verify that wp-admin/includes/file.php is loaded before calling `WP_Filesystem()`
* Support both "wp_package" and "wp-package" in the Installer service


## Version 1.15.3 — 2020-12-22

### Updated

* Split full-page caching out of the `Cache` integration into `PageCache`
* Use the most recent setting keys and Htaccess rules for Cache Enabler

### Fixed

* Ensure default permalink structures are set on new sites
* Ensure that StagingSites' constants are actually being set


## Version 1.15.2 — 2020-12-09

### Fixed

* Bypass the cache to verify the current environment in the `StagingSites` and `RegressionSites` integrations


## Version 1.15.1 — 2020-11-24

### Added

* Display a notice encouraging users running WooCommerce, EDD, or RCP to install Recapture

### Fixed

* Fixed an issue where the `WPConfigTransformer` class was not loading properly.


## Version 1.15.0 — 2020-11-16

### Added

* Enable support to generate self-destructing users

### Updated

* Disable the "plugin_theme_auto_updates" Site Health check when updates are handled by MAPPS

### Fixed

* Check for `wp_is_maintenance_mode()` before calling it in `FatalErrorHandler::handle()`


## Version 1.14.0 — 2020-11-12

### Added

* Add a custom `fatal-error-handler.php` drop-in
* [Introduce new filters to replace Nexcess branding](README.md#change-the-branding-and-display-names-in-the-plugin)
* Add a notice under the auto-update UI (introduced in WordPress 5.6) if core updates are being handled by MAPPS
* [Enable the `DisplayEnvironment` integration to be disabled via filter](README.md#disable-the-environment-indicator)

### Updated

* Perform platform checks as early as possible
* Rotate the `WP_CACHE_KEY_SALT` constant on staging sites
* Explicitly set the `WP_ENVIRONMENT_TYPE` constant on staging sites
* Move `AdminNotice` under the `Nexcess\MAPPS\Support` namespace
* Soften the colors used to distinguish environments in the WP Admin Bar
* Updated Nexcess MAPPS Dashboard to version 1.3.5


## Version 1.13.1 — 2020-10-21

### Fixed

* Resolved ambiguous `Cache` reference within `Nexcess\MAPPS\Commands\Setup`


## Version 1.13.0 — 2020-10-13

### Added

* Introduce a UI for controlling visual regression URLs
* Add support for [Redis Cache](https://wordpress.org/plugins/redis-cache/)
* Enable the plugin to programmatically update `wp-config.php`
* Add a "Flush PHP OPcache" button the the admin bar
* Display the current environment in the admin bar
* Add common support articles to the support tab
* Add public-facing documentation to `nexcess-mapps/README.md`

### Updated

* Fire the `Telemetry::REPORT_CRON_ACTION` during site setup
* Install Redis Cache, not WP Redis, by default during site setup
* Separate the various cache integrations
* Rename the Security integration to Fail2Ban


## Version 1.12.3 — 2020-10-01

### Updated

* Disable the MU plugin for unsupported versions of WordPress
* Upgraded Nexcess MAPPS Dashboard to version 1.3.4
* Upgraded wp-fail2ban to version 4.3.0.8


## Version 1.12.2 — 2020-09-23

### Updated

* Respect customers' `wp_get_environment_type()` configuration
* Let customers manually update WordPress core
* Update the links on the support tab to point to the resolved URLs
* Upgraded Nexcess MAPPS Dashboard to version 1.3.3


## Version 1.12.1 — 2020-09-17

### Updated

* Disable the "WordPress X.X is available! Please notify the site administrator" messages
* Disable Sucuri scans on regression sites

### Fixed

* Prevent uninitialized offset notices in wp-fail2ban


## Version 1.12.0 — 2020-09-15

### Added

* Introduce a top-level "Nexcess" menu in WP-Admin
* Add a "Delete expired transients" button to the Nexcess Admin Bar menu
* Filter Site Health checks for MAPPS sites
* Include Nexcess information in the WP-Admin footer
* Introduce an `Integration::boot()` method, enabling specific code to _always_ be run
* Reduce randomness on regression sites to produce more consistent results
* Add scaffolding around deprecating functionality

### Updated

* Replace custom autoloaders with those generated by Composer
* Reduce the Siteworx cache time from 1hr to 5min
* Replace the `wp_mail()` function on regression sites to prevent emails from getting out
* Rename the `Dashboard` integration to `PluginInstaller`
* Removed the `WP_ENVIRONMENT_TYPES` environment variable, first defined in v1.11.0
* Nexcess MAPPS Dashboard has been upgraded to to 1.3.1


## Version 1.11.2 — 2020-08-13

### Updated

* Reduce the SiteWorx cache time from 1 hour to 5 minutes


## Version 1.11.1 — 2020-08-07

### Added

* Introduce a new Recapture integration


## Version 1.11.0 — 2020-08-06

### Added

* Introduce a simple autoloader for WordPress core classes referenced within the MU plugin
* Add a new WooCommerce integration class
  - Currently, the only task it's responsible for is disabling background image regeneration
* Add a `nexcess_mapps_disable_dashboard` filter to enable site owners to disable the Nexcess MAPPS Dashboard installer
* Define `WP_ENVIRONMENT_TYPE` and `WP_ENVIRONMENT_TYPES` environment variables ahead of WordPress 5.5

### Fixed

* Handle deprecated PHPMailer locations ahead of WordPress 5.5
* Be more judicious in setting `JETPACK_STAGING_MODE` on non-production sites
* Revert the temporary change made for the 1.10.1 hotfix release
* Use `wp_using_ext_object_cache()`, not `WP_CACHE` to determine if an external object cache is being used

### Updated

* Abstract the calculation of PHP EOL dates
* Removed the now-unnecessary Maintenance integration
* Remove the code that would clean up stand-alone copies of the Nexcess Dashboard (installer) plugin
* Move the telemetry reporter key into the Settings object
* Lazy-loading of individual settings
* Include "Protect" among the default Jetpack modules
* Disable automatic core + plugin updates through WordPress 5.5+ if plugin updates are enabled via MAPPS
* Remove the WP-fail2ban dashboard widget (introduced in 4.3)
* Put Jetpack into offline/development mode for sites on temp domains


## Version 1.10.1 — 2020-08-04

### Updated

* Disable theme and plugin updates during site setup #316


## Version 1.10.0 — 2020-06-25

### Added

* Introduced a new `Nexcess\MAPPS\Services\Installer` class, which provides a standard API for installing and licensing plugins on a site

### Updated

* MU plugins are now included in the telemetry reports
* Removed the "background_updates" Site Health status check, as the platform is responsible for these updates
* Extracted the Canny board token into the Settings object
* The `nxmapps setup` command will now attempt to pre-install (and license) plugins on a site based on the plan


## Version 1.9.1 — 2020-06-03

### Fixed

* Move the check for the block editor into the `adminEnqueueScripts()` method for the StoreBuilder integration


## Version 1.9.0 — 2020-05-29

### Added

* Introduce the `nxmapps cache` WP-CLI command

### Fixed

* Disable the StoreBuilder integration if the block editor (a.k.a. Gutenberg) is disabled
* Automatically install/remove `object-cache.php` based on WP Redis' state

### Updated

* Automatically update all plugins and themes as part of the `nxmapps setup` WP-CLI command
* Explicitly instruct Cache Enabler not to bypass the page cache if only `utm_*` query string parameters are present
* Nexcess MAPPS Dashboard has been updated to version 1.3.0


## Version 1.8.1 — 2020-05-21

### Updated

* Consider the value of the "beta_client" environment flag when determining the "is_beta_tester" setting


## Version 1.8.0 — 2020-05-20

### Added

* Introduce the StoreBuilder integration for beta customers
* Added the ability to solicit feedback from beta customers

### Fixed

* Fixed a logic issue that was preventing the `wp nxmapps setup` script from aborting if not on a MAPPS site
* Prevent the Nexcess MAPPS Dashboard plugin from loading on sites running WordPress < 5.0
* Prepare for WP fail2ban version 4.3 (forward compatibility)
* Only return URLs which return a 200 response code for Visual Comparison

### Updated

* Abort `wp nxmapps setup:woocommerce` with a warning, not an error, if WooCommerce is inactive


## Version 1.7.1 — 2020-05-09

### Added

* Allow Beta Tester flag to be overridden using a Constant `NEXCESS_MAPPS_BETA_TESTER`

### Fixed

* Don't attempt to call `new PHPMailer()` before the `PHPMailer` class is loaded


## Version 1.7.0 — 2020-05-08

### Added

* Enable developers to use a development copy of Nexcess MAPPS Dashboard
* Remember dismissed admin notices
* Add a WP-CLI command for licensing WP All Import Pro
* Add logic for the "is_beta_tester" setting
* Add a new Themes integration

### Updated

* Update plugin versions being loaded via Composer
* Nexcess MAPPS Dashboard has been updated to version 1.2.1

### Fixed

* Return early from the `nxmapps setup` command on non-MAPPS sites
* Unset unused integration objects
* Fix the EOL dates for PHP 7.3 and 7.4
* Don't load the MU plugin if `WP_INSTALLING`
* Prevent emails from being sent on regression sites
* Remove single attachment pages from the list of visual comparison URLs if the attachment page is redirected elsewhere


## Version 1.6.0 - 2020-02-28

### Added

* Add a `nxmapps dokan` WP-CLI command for licensing Dokan

### Updated

* Include v1.2.0 of the Nexcess MAPPS Dashboard plugin


## Version 1.5.0 - 2020-02-20

### Added

* Add the `nxmapps qubely` WP-CLI commands for managing Qubely Pro licenses
* Introduce a new Maintenance integration, which can be used to run weekly cleanup/maintenance scripts

### Fixed

* Interpret "M" as minutes, not months when creating a `DateInterval`


## Version 1.4.0 - 2020-02-12

### Added

* Install WP-fail2ban by default
* Add a new Debug integration

### Fixed

* Fix method used to determine MySQL version to be in line with WordPress core for debugging
* Use `set_site_transient()` instead of `wp_cache_set()` when caching Siteworx environment details, increase cache time to one hour
* Adjust spacing around Nexcess icon in the admin bar
* Only register the 'flush-object-cache' button if `WP_CACHE` is true

### Updated

* Add additional debug information to the `wp nxmapps details` command


## Version 1.3.1 - 2020-01-30

### Fixed

* Ensure the telemetry integration's API key is passed along with the report


## Version 1.3.0 — 2020-01-28

### Added

* Register a cron event for the telemetry report
* Add the `ICONIC_DISABLE_DASH` constant for disabling the Iconic plugins' dashboard
* Register a WP-CLI command for handling iThemes licensing
* Add a "Nexcess" WP Admin bar, along with a "Flush cache" button
* Display a warning to administrators on sites running old versions of PHP
* Bundle the Nexcess MAPPS Dashboard plugin

### Updated

* Rewrote the `wp nxmapps vc urls` WP-CLI command
* Remove the Cache Enabler Htaccess modifications when the plugin is deactivated
* Verify WooCommerce is installed + active before attempting to install
* Adjust Nexcess MAPPS plan codes
* Only load the full Jetpack integration on legacy plans and mwc.enterprise


## Version 1.2.0 - 2019-12-04

### Added

* Set default Cache Enabler settings

### Updated

* Track WooCommerce store currencies
* Check for `.flush-cache` files in the web root
* Convert changelog to follow [the Keep a Changelog standards](https://keepachangelog.com/en/1.0.0/)
* Set additional WooCommerce default settings


## Version 1.1.0 - 2019-11-21

### Added

* Create a WP CLI command to assist in Brainstorm plugin licensing
* Register the Object Cache integration

### Updated

* Cache the SiteWorx account configuration
* Include the overall product counts in WooCommerce telemetry data


## Version 1.0.0 - 2019-10-29

Initial release of the plugin.

### Added

* WP-CLI Commands
  - AffiliateWP License Activation / Deactivation
  - Platform Setup Command
  - Visual Comparison Page Listings
  - Regenerate `WP_CACHE_KEY_SALT`
* Plugin Integrations
  - Jetpack
  - PHPCompatability Checker
  - Staging Sites
  - Telemetry
  - Varnish
* Core Integrations
  - Core Updates
* Platform Features
  - WooCommerce Upper Limits - Restricts orders and products for our WooCommerce Basic plan.

[README.md]: README.md
