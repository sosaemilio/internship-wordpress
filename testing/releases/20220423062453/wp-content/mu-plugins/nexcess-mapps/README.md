# Building on Nexcess MAPPS

Thank you for choosing to build on Nexcess Managed Applications (MAPPS)!

This document is designed to explain the Nexcess MAPPS Must-Use (MU) plugin and how it integrates with our platform, as well as outline the hooks provided by the MU plugin for customers who require more control.


## What is the MU Plugin?

The Nexcess MAPPS MU plugin is designed to be a central place for all of Nexcess' customizations to WordPress at the application level. From cache configurations to the custom dashboard, this MU plugin is the entry point for everything Managed WordPress/WooCommerce.

The MU plugin is maintained by the Managed Applications Product Team within Liquid Web/Nexcess, with new releases about once a month. Our systems automatically update the MU plugin, so every site on our network will be running the same version of the plugin.


## Extending the MU plugin

Considering the amount of functionality in the MU plugin and the fact that it's guaranteed to be present on sites running on Nexcess' Managed WordPress/WooCommerce plans, it may be tempting to (for instance) extend a class defined within the MU plugin for your own purposes.

However, **it is not advised to rely on any existing APIs declared within the MU plugin**, as these may change at any time.

The exception to this rule are [the hooks outlined in this document](#customizing-mu-plugin-behavior), to which we commit to supporting for the foreseeable future.


### Deprecated features

Occasionally, we may need to deprecate APIs for the improvement of the overall platform. In those cases, the deprecated methods will be proxied to their replacements (when available), and details about the deprecation will be listed here:

| API | Type | Deprecated In | Alternative |
| --- | --- | --- | --- |
| `nexcess_mapps_disable_dashboard` | filter | 1.12.0 (2020-09-14) | [`nexcess_mapps_show_plugin_installer`](#hide-the-nexcess-plugin-installer) |
| `NEXCESS_MAPPS_USE_LOCAL_DASHBOARD` | constant | 1.12.0 (2020-09-14) | [`nexcess_mapps_show_plugin_installer`](#hide-the-nexcess-plugin-installer) |

Deprecated features will not be _removed_ until the next **major** release of the MU plugin (e.g. if something was deprecated in 1.12.0, it will not be removed before version 2.0.0).


## Customizing MU plugin behavior

The following hooks are provided for customers to modify the behavior of the MU plugin; **anything not covered here may change at any time!**

Things to remember while you are customizing the MU plugin

- Nexcess MAPPS loads very early in the WordPress bootstrap process, well before themes or regular (non-mu) plugins.
- Some filters, like `nexcess_mapps_show_dashboard`, will need to be loaded **before** the Nexcess plugin, so you will need to create your own MU plugin and add it to the `mu-plugins` folder.
- MU plugins are loaded in alphabetical order, so your custom plugin changing ours will need to be named something that comes before `nexcess-mapps.php`, eg: `000-nexcess-customizations.php`.

```php
//wp-content/mu-plugins/000-awesome-agency.php
<?php
/**
 * Plugin Name: Awesome Agency Branding
 * Description: Overrides for the Nexcess Managed Apps MU plugin.
 * Author:      Awesome Agency Co.
 */

/**
 * Override the company name used throughout the Nexcess MAPPS platform.
 *
 * @param string $name The branded company name.
 */
add_filter( 'nexcess_mapps_branding_company_name', function ( $name ) {
	return 'Awesome Agency Co.';
} );
```

### Disable the Nexcess MAPPS dashboard

You may hide the "Nexcess" menu item (and any children) with the following filter:

```php
/**
 * Disable the Nexcess MAPPS dashboard.
 */
add_filter( 'nexcess_mapps_show_dashboard', '__return_false' );
```


### Disable the Nexcess MAPPS Admin Bar Menu

You may hide the "Nexcess" admin bar menu item (and any children) at the top of the `wp-admin` with the following filter:

```php
/**
 * Disable the Nexcess MAPPS Admin Bar Menu.
 */
add_filter( 'nexcess_mapps_show_admin_bar', '__return_false' );
```


### Disable individual Nexcess MAPPS templates

You may disable individual portions of the Nexcess dashboard with the following filters:

```php
/**
 * Disable the Nexcess MAPPS dashboard tab.
 */
add_filter( 'nexcess_mapps_branding_enable_dashboard_template', '__return_false' );

/**
 * Disable the Nexcess MAPPS support page tab.
 */
add_filter( 'nexcess_mapps_branding_enable_support_template', '__return_false' );

/**
 * Disable the Nexcess MAPPS feedback tab.
 */
add_filter( 'nexcess_mapps_branding_enable_feedback_template', '__return_false' );
```


### Override individual Nexcess MAPPS templates

You may override individual templates of the Nexcess dashboard with your own with the following filter:

```php
add_filter( 'nexcess_mapps_branding_template_file', 'example_custom_template_files', 10, 2 );
/**
 * Change the displayed company name across the entire plugin.
 *
 * @param string $file     The complete file path to the template file.
 * @param string $template Which template was requested.
 */
function example_custom_template_files( $file, $template ) {
	switch ( $template ) {
		case 'admin' :
			return '/path/to/template/file.php';
			break;

		default :
			return $file;
			break;
	}
}
```


### Hide the Nexcess plugin installer

The Nexcess plugin installer allows customers to install and license a number of premium plugins included in their plan.

If you wish to disable this functionality, you may do so with the following filter:

```php
/**
 * Disable the Nexcess plugin installer.
 */
add_filter( 'nexcess_mapps_show_plugin_installer', '__return_false' );
```

Previously, this was also available via the `nexcess_mapps_disable_dashboard` filter or the `NEXCESS_MAPPS_USE_LOCAL_DASHBOARD` constant, both of which have been deprecated and will be removed in a future release.


### Change the branding and display names in the plugin

Should you need to re-brand the Nexcess MAPPS experience, the following filters are available for replacing Nexcess branding elements:

```php
/**
 * Override the company name used throughout the Nexcess MAPPS platform.
 *
 * @param string $name The branded company name.
 */
add_filter( 'nexcess_mapps_branding_company_name', function ( $name ) {
	return 'Awesome Agency Co.';
} );

/**
 * Override the platform name used throughout the Nexcess MAPPS platform.
 *
 * @param string $name The branded platform name.
 */
add_filter( 'nexcess_mapps_branding_platform_name', function ( $name ) {
	return 'Awesome Agency Managed Applications';
} );

/**
 * Override the company name used in the WP Admin Bar.
 *
 * @param string $name The branded company name.
 */
add_filter( 'nexcess_mapps_branding_admin_bar_name', function ( $name ) {
	return 'Awesome Agency Co.';
} );

/**
 * Override the title of the dashboard page.
 *
 * @param string $name The branded company name.
 */
add_filter( 'nexcess_mapps_branding_dashboard_page_title', function ( $name ) {
	return 'Awesome Agency Co.';
} );

/**
 * Override the label for the top-level admin menu item.
 *
 * @param string $name The branded company name.
 */
add_filter( 'nexcess_mapps_branding_dashboard_menu_item_title', function ( $name ) {
	return 'Awesome Agency Co.';
} );

/**
 * Override the icon branding for the Nexcess MAPPS dashboard.
 *
 * @param string $icon  An inline SVG of the branded icon.
 * @param string $color The SVG color. By default, this will be "currentColor".
 */
add_filter( 'nexcess_mapps_branding_company_icon_svg', function ( $icon, $color ) {
	return '<svg version="1.1" xmlns... </svg>';
} );

/**
 * Override the company logo image file for the Nexcess MAPPS dashboard.
 *
 * @param string $logo A URL for the branded company logo.
 */
add_filter( 'nexcess_mapps_branding_company_image', function ( $logo ) {
	return 'https://example.com/image/logo.svg';
} );

/**
 * Override the Nexcess support URL used throughout WP Admin.
 *
 * @param string $url The support URL.
 */
add_filter( 'nexcess_mapps_branding_support_url', function ( $url ) {
  return 'https://example.com/support';
} );
```


### Disable the environment indicator

Since version 1.13.0, the MU plugin has added an indication of the current environment (e.g. "Production", "Staging", or "Development") to the WordPress Admin Bar.

If you would like to disable this feature, you may do so with the following:

```php
/**
 * Disable the environment indicator in the Admin Bar.
 */
add_filter( 'nexcess_mapps_enable_environment_indicator', '__return_false' );
```


### Disabling full-page caching

Since version 1.18.0, the MU plugin has included an integrated full-page caching solution, which removes the need for external plugins such as [Cache Enabler](https://wordpress.org/plugins/cache-enabler/) (which was previously being installed across the platform).

As part of the move toward the integrated solution, sites that were previously running Cache Enabler have automatically been migrated, while preserving existing configurations.

If you wish to disable full-page caching on your site, you may do so in on Nexcess &rsaquo; Page Cache within WP Admin.


### Override default Visual Regression URLs

As part of the Managed Applications platform, we perform [visual regression testing](https://help.nexcess.net/74095-wordpress/how-to-use-visual-comparison-tool) on WordPress sites before updating plugins in a production environment: a snapshot is taken of each site, then our tool performs plugin updates in a separate environment. Screenshots are taken on key pages both before and after the plugin update and, if changes are detected, the site owner is notified and the plugin is not automatically updated.

The MU plugin will automatically compile a list of representative pages for the site (homepage, single page, archives, etc.), but also permits site owners to specify which pages get checked via the Nexcess &rsaquo; Dashboard &rsaquo; Visual Comparison screen within WP Admin.

If you would like to override the default visual regression URLs, you may do so with the "nexcess_mapps_default_visual_regression_urls" filter:

```php
use Nexcess\MAPPS\Support\VisualRegressionUrl;

/**
 * Override the default visual regression testing URLs.
 *
 * @param VisualRegressionUrl[] $urls An array of VisualRegressionUrl objects.
 *
 * @return VisualRegressionUrl[] The filtered $url array.
 */
add_filter( 'nexcess_mapps_default_visual_regression_urls', function ( $urls ) {
      return [
          new VisualRegressionUrl( '/', 'Homepage' ),
          new VisualRegressionUrl( '/shop', 'Shop page' ),
          new VisualRegressionUrl( '/some-important-page', 'Super important page'),
          // ...
      ];
} );
```

> **Note:** We currently impose a limit of 10 regression URLs per site; if the limit is exceeded, only the first 10 URLs will be processed.

It's important to note that this filter will only override the _default_ regression URLs; once a site has provided custom URLs, its values are stored in and read from the "nexcess_mapps_visual_regression_urls" option.


## Action Reference

|Action name|When?|
|---|---|
|`nexcess_mapps_loaded`|Fires at the end of the plugin being loaded.|
