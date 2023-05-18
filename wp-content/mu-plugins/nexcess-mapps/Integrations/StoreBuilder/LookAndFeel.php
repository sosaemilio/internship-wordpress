<?php

namespace Nexcess\MAPPS\Integrations\StoreBuilder;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;

class LookAndFeel {
	use HasHooks;
	use ManagesGroupedOptions;

	const AJAX_STARTED_ACTION = 'storebuilder_look_and_feel_started';
	const OPTION_NAME         = '_storebuilder_look_and_feel';

	/**
	 * @var StoreBuilderFTC
	 */
	private $storebuilder_ftc;

	/**
	 * @param StoreBuilderFTC $storebuilder_ftc
	 */
	public function __construct( StoreBuilderFTC $storebuilder_ftc ) {
		$this->storebuilder_ftc = $storebuilder_ftc;

		$this->addHooks();
	}

	/**
	 * Sets the actions.
	 */
	protected function getActions() {
		return [
			[ 'wp_ajax_' . self::AJAX_STARTED_ACTION, [ $this, 'ajaxStarted' ] ],
		];
	}

	/**
	 * AJAX action to register telemetry that wizard started.
	 */
	public function ajaxStarted() {
		if ( empty( $_REQUEST['_wpnonce'] ) ) {
			return wp_send_json_error( 'Missing required parameters.', 400 );
		}

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], self::AJAX_STARTED_ACTION ) ) {
			return wp_send_json_error( 'Nonce is invalid.', 403 );
		}

		do_action( 'wme_event_wizard_started', 'look_and_feel' );

		return wp_send_json_success();
	}

	/**
	 * Returns the Template value.
	 *
	 * @return string
	 */
	public function getTemplate() {
		return ( $this->getOption()->template ) ? $this->getOption()->template : '';
	}

	/**
	 * Returns the Font value.
	 *
	 * @return string
	 */
	public function getFont() {
		return ( $this->getOption()->font ) ? $this->getOption()->font : '';
	}

	/**
	 * Return the Color value.
	 *
	 * @return string
	 */
	public function getColor() {
		return ( $this->getOption()->color ) ? $this->getOption()->color : '';
	}

	/**
	 * Sets the Look and Feel Template value.
	 *
	 * @param array $value
	 */
	public function setTemplate( $value ) {
		$value = filter_var_array( $value, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $value ) {
			$this->getOption()->template = $value;
		}
	}

	/**
	 * Sets the Look and Feel Font value.
	 *
	 * @param string $value
	 */
	public function setFont( $value ) {
		$value = filter_var( $value, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $value ) {
			$this->getOption()->font = $value;
		}
	}

	/**
	 * Sets the Look and Feel Color value.
	 *
	 * @param string $value
	 */
	public function setColor( $value ) {
		$value = filter_var( $value, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $value ) {
			$this->getOption()->color = $value;
		}
	}

	/**
	 * Saves the options.  Only save if the values are 'dirty' in order to
	 * keep from hitting database when it isn't needed.
	 *
	 * @return bool
	 */
	public function save() {
		if ( ! $this->getOption()->isDirty() ) {
			return true;
		}

		do_action( 'wme_event_wizard_completed', 'look_and_feel' );

		return $this->getOption()->save();
	}

	/**
	 * Checks to see if the Card has been completed.
	 *
	 * @return bool True if the Card has been completed, false otherwise.
	 */
	public function isComplete() {
		if ( version_compare( $this->storebuilder_ftc->getStorebuilderVersion(), '3.0', '<' ) ) {
			return true;
		}
		return (bool) count( $this->getOption()->all() );
	}

	/**
	 * Updates the Kadence Fonts.
	 *
	 * @param string $selected_font
	 */
	public static function setNewFonts( $selected_font ) {
		if ( class_exists( 'Kadence\Theme' ) ) {
			switch ( $selected_font ) {
				case 'montserrat':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Montserrat';
					$current['google']  = true;
					$current['variant'] = [ '100', '100italic', '200', '200italic', '300', '300italic', 'regular', 'italic', '500', '500italic', '600', '600italic', '700', '700italic', '800', '800italic', '900', '900italic' ];
					set_theme_mod( 'heading_font', $current );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Source Sans Pro';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'playfair':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Playfair Display';
					$current['google']  = true;
					$current['variant'] = [ 'regular', 'italic', '500', '500italic', '600', '600italic', '700', '700italic', '800', '800italic', '900', '900italic' ];
					set_theme_mod( 'heading_font', $current );
					$h1_font            = \Kadence\kadence()->option( 'h1_font' ); // @phpstan-ignore-line
					$h1_font['weight']  = 'normal';
					$h1_font['variant'] = 'regualar';
					set_theme_mod( 'h1_font', $h1_font );
					$h2_font            = \Kadence\kadence()->option( 'h2_font' ); // @phpstan-ignore-line
					$h2_font['weight']  = 'normal';
					$h2_font['variant'] = 'regualar';
					set_theme_mod( 'h2_font', $h2_font );
					$h3_font            = \Kadence\kadence()->option( 'h3_font' ); // @phpstan-ignore-line
					$h3_font['weight']  = 'normal';
					$h3_font['variant'] = 'regualar';
					set_theme_mod( 'h3_font', $h3_font );
					$h4_font            = \Kadence\kadence()->option( 'h4_font' ); // @phpstan-ignore-line
					$h4_font['weight']  = 'normal';
					$h4_font['variant'] = 'regualar';
					set_theme_mod( 'h4_font', $h4_font );
					$h5_font            = \Kadence\kadence()->option( 'h5_font' ); // @phpstan-ignore-line
					$h5_font['weight']  = 'normal';
					$h5_font['variant'] = 'regualar';
					set_theme_mod( 'h5_font', $h5_font );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Raleway';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'oswald':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Oswald';
					$current['google']  = true;
					$current['variant'] = [ '200', '300', 'regular', '500', '600', '700' ];
					set_theme_mod( 'heading_font', $current );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Open Sans';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'antic':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Antic Didone';
					$current['google']  = true;
					$current['variant'] = [ 'regular' ];
					set_theme_mod( 'heading_font', $current );
					$h1_font            = \Kadence\kadence()->option( 'h1_font' ); // @phpstan-ignore-line
					$h1_font['weight']  = 'normal';
					$h1_font['variant'] = 'regualar';
					set_theme_mod( 'h1_font', $h1_font );
					$h2_font            = \Kadence\kadence()->option( 'h2_font' ); // @phpstan-ignore-line
					$h2_font['weight']  = 'normal';
					$h2_font['variant'] = 'regualar';
					set_theme_mod( 'h2_font', $h2_font );
					$h3_font            = \Kadence\kadence()->option( 'h3_font' ); // @phpstan-ignore-line
					$h3_font['weight']  = 'normal';
					$h3_font['variant'] = 'regualar';
					set_theme_mod( 'h3_font', $h3_font );
					$h4_font            = \Kadence\kadence()->option( 'h4_font' ); // @phpstan-ignore-line
					$h4_font['weight']  = 'normal';
					$h4_font['variant'] = 'regualar';
					set_theme_mod( 'h4_font', $h4_font );
					$h5_font            = \Kadence\kadence()->option( 'h5_font' ); // @phpstan-ignore-line
					$h5_font['weight']  = 'normal';
					$h5_font['variant'] = 'regualar';
					set_theme_mod( 'h5_font', $h5_font );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Raleway';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'gilda':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Gilda Display';
					$current['google']  = true;
					$current['variant'] = [ 'regular' ];
					set_theme_mod( 'heading_font', $current );
					$h1_font            = \Kadence\kadence()->option( 'h1_font' ); // @phpstan-ignore-line
					$h1_font['weight']  = 'normal';
					$h1_font['variant'] = 'regualar';
					set_theme_mod( 'h1_font', $h1_font );
					$h2_font            = \Kadence\kadence()->option( 'h2_font' ); // @phpstan-ignore-line
					$h2_font['weight']  = 'normal';
					$h2_font['variant'] = 'regualar';
					set_theme_mod( 'h2_font', $h2_font );
					$h3_font            = \Kadence\kadence()->option( 'h3_font' ); // @phpstan-ignore-line
					$h3_font['weight']  = 'normal';
					$h3_font['variant'] = 'regualar';
					set_theme_mod( 'h3_font', $h3_font );
					$h4_font            = \Kadence\kadence()->option( 'h4_font' ); // @phpstan-ignore-line
					$h4_font['weight']  = 'normal';
					$h4_font['variant'] = 'regualar';
					set_theme_mod( 'h4_font', $h4_font );
					$h5_font            = \Kadence\kadence()->option( 'h5_font' ); // @phpstan-ignore-line
					$h5_font['weight']  = 'normal';
					$h5_font['variant'] = 'regualar';
					set_theme_mod( 'h5_font', $h5_font );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Raleway';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'cormorant':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Cormorant Garamond';
					$current['google']  = true;
					$current['variant'] = [ '300', '300italic', 'regular', 'italic', '500', '500italic', '600', '600italic', '700', '700italic' ];
					set_theme_mod( 'heading_font', $current );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Proza Libre';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'libre':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Libre Franklin';
					$current['google']  = true;
					$current['variant'] = [ '100', '100italic', '200', '200italic', '300', '300italic', 'regular', 'italic', '500', '500italic', '600', '600italic', '700', '700italic', '800', '800italic', '900', '900italic' ];
					set_theme_mod( 'heading_font', $current );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Libre Baskerville';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'lora':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Lora';
					$current['google']  = true;
					$current['variant'] = [ 'regular', 'italic', '500', '500italic', '600', '600italic', '700', '700italic' ];
					set_theme_mod( 'heading_font', $current );
					$h1_font            = \Kadence\kadence()->option( 'h1_font' ); // @phpstan-ignore-line
					$h1_font['weight']  = 'normal';
					$h1_font['variant'] = 'regualar';
					set_theme_mod( 'h1_font', $h1_font );
					$h2_font            = \Kadence\kadence()->option( 'h2_font' ); // @phpstan-ignore-line
					$h2_font['weight']  = 'normal';
					$h2_font['variant'] = 'regualar';
					set_theme_mod( 'h2_font', $h2_font );
					$h3_font            = \Kadence\kadence()->option( 'h3_font' ); // @phpstan-ignore-line
					$h3_font['weight']  = 'normal';
					$h3_font['variant'] = 'regualar';
					set_theme_mod( 'h3_font', $h3_font );
					$h4_font            = \Kadence\kadence()->option( 'h4_font' ); // @phpstan-ignore-line
					$h4_font['weight']  = 'normal';
					$h4_font['variant'] = 'regualar';
					set_theme_mod( 'h4_font', $h4_font );
					$h5_font            = \Kadence\kadence()->option( 'h5_font' ); // @phpstan-ignore-line
					$h5_font['weight']  = 'normal';
					$h5_font['variant'] = 'regualar';
					set_theme_mod( 'h5_font', $h5_font );
					$body            = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family']  = 'Merriweather';
					$body['google']  = true;
					$body['weight']  = '300';
					$body['variant'] = '300';
					set_theme_mod( 'base_font', $body );
					break;

				case 'proza':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Proza Libre';
					$current['google']  = true;
					$current['variant'] = [ 'regular', 'italic', '500', '500italic', '600', '600italic', '700', '700italic', '800', '800italic' ];
					set_theme_mod( 'heading_font', $current );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Open Sans';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'worksans':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Work Sans';
					$current['google']  = true;
					$current['variant'] = [ '100', '100italic', '200', '200italic', '300', '300italic', 'regular', 'italic', '500', '500italic', '600', '600italic', '700', '700italic', '800', '800italic', '900', '900italic' ];
					set_theme_mod( 'heading_font', $current );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Work Sans';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'josefin':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Josefin Sans';
					$current['google']  = true;
					$current['variant'] = [ '100', '100italic', '200', '200italic', '300', '300italic', 'regular', 'italic', '500', '500italic', '600', '600italic', '700', '700italic' ];
					set_theme_mod( 'heading_font', $current );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Lato';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'nunito':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Nunito';
					$current['google']  = true;
					$current['variant'] = [ '200', '200italic', '300', '300italic', 'regular', 'italic', '600', '600italic', '700', '700italic', '800', '800italic', '900', '900italic' ];
					set_theme_mod( 'heading_font', $current );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Roboto';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;

				case 'rubik':
					$current            = \Kadence\kadence()->option( 'heading_font' ); // @phpstan-ignore-line
					$current['family']  = 'Rubik';
					$current['google']  = true;
					$current['variant'] = [ '300', '300italic', 'regular', 'italic', '500', '500italic', '600', '600italic', '700', '700italic', '800', '800italic', '900', '900italic' ];
					set_theme_mod( 'heading_font', $current );
					$body           = \Kadence\kadence()->option( 'base_font' ); // @phpstan-ignore-line
					$body['family'] = 'Karla';
					$body['google'] = true;
					set_theme_mod( 'base_font', $body );
					break;
			}
		}
	}
}
