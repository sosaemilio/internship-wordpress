<?php

/**
 * Integration with Varnish.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use VarnishStatus;

class Varnish extends Integration {
	use HasHooks;
	use HasWordPressDependencies;

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return ! empty( $_SERVER['HTTP_X_VARNISH'] )
			&& get_site_option( 'permalink_structure' )
			&& ! $this->isPluginActive( 'varnish-http-purge/varnish-http-purge.php' )
			&& ! $this->isPluginBeingActivated( 'varnish-http-purge/varnish-http-purge.php' );
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->loadPlugin( 'wpackagist-plugin/varnish-http-purge/varnish-http-purge.php' );

		$this->addHooks();
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing
		return [
			[ 'admin_init', [ $this, 'overrideIpSection'     ], 100 ],
			[ 'admin_init', [ $this, 'removeFooterOverrides' ], 100 ],
		];
		// phpcs:enable
	}

	/**
	 * Override the text for the "Configure Custom IP" section of the settings screen.
	 *
	 * @global $wp_settings_sections
	 */
	public function overrideIpSection() {
		global $wp_settings_sections;

		if ( isset( $wp_settings_sections['varnish-ip-settings']['vhp-settings-ip-section'] ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_settings_sections['varnish-ip-settings']['vhp-settings-ip-section']['callback'] = [ $this, 'ipSettingSection' ];
		}
	}

	/**
	 * Provide the text at the beginning of the "Configure Custom IP" section.
	 */
	public function ipSettingSection() {
		?>

		<p><a name="#configureip"></a><?php esc_html_e( 'There are cases when a custom IP Address is needed to for the plugin to properly communicate with the cache service. If you\'re using a CDN like Cloudflare or a Firewall Proxy like Sucuri, or your cache is Nginx based, you may need to customize this setting.', 'nexcess-mapps' ); ?></p>
		<p><?php esc_html_e( 'Normally your Proxy Cache IP is the IP address of the server where your caching service (i.e. Varnish or Nginx) is installed. It must an address used by your cache service. If you use multiple IPs, or have customized your ACLs, you\'ll need to pick one that doesn\'t conflict with your other settings. For example, if you have Varnish listening on a public and private IP, pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1 or localhost).', 'nexcess-mapps' ); ?></p>
		<p><strong><?php esc_html_e( 'If you aren\'t sure what to do, please contact Nexcess support before making any changes.', 'nexcess-mapps' ); ?></strong></p>

		<?php
	}

	/**
	 * Remove overrides to the admin_footer_text filter.
	 *
	 * @global $wp_filter
	 */
	public function removeFooterOverrides() {
		global $wp_filter;

		if ( empty( $wp_filter['admin_footer_text']->callbacks['1'] ) ) {
			return;
		}

		foreach ( $wp_filter['admin_footer_text']->callbacks['1'] as $key => $callback ) {
			if ( ! is_array( $callback['function'] ) ) {
				continue;
			}

			if ( $callback['function'][0] instanceof VarnishStatus ) {
				unset( $wp_filter['admin_footer_text']->callbacks['1'][ $key ] );
			}
		}
	}
}
