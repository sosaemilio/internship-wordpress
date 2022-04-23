<?php

namespace Nexcess\MAPPS\Integrations\StoreBuilder;

use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Integrations\StoreBuilder;

class StoreBuilderFTC {
	use ManagesGroupedOptions;

	const OPTION_NAME          = '_storebuilder_ftc';
	const STOREBUILDER_VERSION = 'storebuilder_version';

	/**
	 * @var int
	 */
	private $logo = 0;

	/**
	 * @var string
	 */
	private $sitename;

	/**
	 * @var string
	 */
	private $description;

	/**
	 * @var string
	 */
	private $address_one;

	/**
	 * @var string
	 */
	private $address_two;

	/**
	 * @var string
	 */
	private $city;

	/**
	 * @var string
	 */
	private $region;

	/**
	 * @var string
	 */
	private $postcode;

	/**
	 * @var string
	 */
	private $currency;

	/**
	 * @var array
	 */
	public $errors = [];

	/**
	 * Returns the current users user_login.
	 *
	 * @return string
	 */
	public function getUsername() {
		$current_user = wp_get_current_user();
		if ( isset( $current_user->user_login ) ) {
			return $current_user->user_login;
		}
		return '';
	}

	/**
	 * Returns the logo url if a logo exists, an empty string otherwise.
	 *
	 * @return string
	 */
	public function getLogoUrl() {
		if ( ! $this->getLogoId() ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $this->getLogoId(), 'full' );

		if ( ! $url ) {
			return '';
		}

		return $url;
	}

	/**
	 * Returns the logo id if a logo exists, or 0.
	 *
	 * @return int
	 */
	public function getLogoId() {
		if ( ! $this->logo ) {
			$this->logo = get_option( 'site_logo', 0 );
		}
		return (int) $this->logo;
	}

	/**
	 * Sets the logo.
	 *
	 * @param int $logo
	 */
	public function setLogo( $logo ) {
		if ( empty( $logo ) ) {
			update_option( 'site_logo', null );
			return;
		}

		$logo = filter_var( $logo, FILTER_SANITIZE_NUMBER_INT );

		if ( ! $logo ) {
			$this->errors[] = [ 'logo' => __( 'Invalid Logo', 'nexcess-mapps' ) ];
			return;
		}

		if ( (int) $logo === $this->getLogoId() ) {
			return;
		}

		if ( ! update_option( 'site_logo', $logo ) ) {
			$this->errors[] = [ 'logo' => __( 'Unable to save the Logo', 'nexcess-mapps' ) ];
		}
	}

	/**
	 * Gets the current site name.
	 *
	 * @return string
	 */
	public function getSitename() {
		if ( ! $this->sitename ) {
			$this->sitename = get_bloginfo( 'name' );
		}
		return $this->sitename;
	}

	/**
	 * Sets the site name.
	 *
	 * @param string $sitename
	 */
	public function setSitename( $sitename ) {
		$sitename = filter_var( $sitename, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $sitename === $this->getSitename() ) {
			return;
		}
		if ( ! update_option( 'blogname', $sitename ) ) {
			$this->errors[] = [ 'sitename' => __( 'Invalid Sitename', 'nexcess-mapps' ) ];
		}
	}

	/**
	 * Gets the current site description.
	 *
	 * @return string
	 */
	public function getDescription() {
		if ( ! $this->description ) {
			$this->description = get_bloginfo( 'description' );
		}
		return $this->description;
	}

	/**
	 * Sets the site description (tagLine).
	 *
	 * @param string $description
	 */
	public function setDescription( $description ) {
		$description = filter_var( $description, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $description === $this->getDescription() ) {
			return;
		}
		if ( ! update_option( 'blogdescription', $description ) ) {
			$this->errors[] = [ 'tagLine' => __( 'Invalid Tagline', 'nexcess-mapps' ) ];
		}
	}

	/**
	 * Gets the WooCommerce Address Line One.
	 *
	 * @return string
	 */
	public function getAddressOne() {
		if ( ! $this->address_one ) {
			$this->address_one = WC()->countries->get_base_address();
		}
		return $this->address_one;
	}

	/**
	 * Sets the WooCommerce Address Line One.
	 *
	 * @param string $address_one
	 */
	public function setAddressOne( $address_one ) {
		$address_one = filter_var( $address_one, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $address_one === $this->getAddressOne() ) {
			return;
		}
		if ( ! update_option( 'woocommerce_store_address', $address_one ) ) {
			$this->errors[] = [ 'addressLine1' => __( 'Invalid Address Line 1', 'nexcess-mapps' ) ];
		}
	}

	/**
	 * Gets the WooCommerce Address Line Two.
	 *
	 * @return string
	 */
	public function getAddressTwo() {
		if ( ! $this->address_two ) {
			$this->address_two = WC()->countries->get_base_address_2();
		}
		return $this->address_two;
	}

	/**
	 * Sets the WooCommerce Address Line Two.
	 *
	 * @param string $address_two
	 */
	public function setAddressTwo( $address_two ) {
		$address_two = filter_var( $address_two, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $address_two === $this->getAddressTwo() ) {
			return;
		}
		if ( ! update_option( 'woocommerce_store_address_2', $address_two ) ) {
			$this->errors[] = [ 'addressLine2' => __( 'Invalid Address Line 2', 'nexcess-mapps' ) ];
		}
	}

	/**
	 * Gets the WooCommerce.
	 *
	 * @return string
	 */
	public function getCity() {
		if ( ! $this->city ) {
			$this->city = WC()->countries->get_base_city();
		}
		return $this->city;
	}

	/**
	 * Sets the WooCommerce City.
	 *
	 * @param string $city
	 */
	public function setCity( $city ) {
		$city = filter_var( $city, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $city === $this->getCity() ) {
			return;
		}
		if ( ! update_option( 'woocommerce_store_city', $city ) ) {
			$this->errors[] = [ 'city' => __( 'Invalid City', 'nexcess-mapps' ) ];
		}
	}

	/**
	 * Gets the WooCommerce Region.  The region is a mix of the country and
	 * state formatted as Country:State.
	 *
	 * @return string
	 */
	public function getRegion() {
		if ( ! $this->region ) {
			$base         = wc_get_base_location();
			$this->region = $base['country'] . ':' . $base['state'];
		}
		return $this->region;
	}

	/**
	 * Sets the WooCommerce Region.
	 *
	 * @param string $region
	 */
	public function setRegion( $region ) {
		$region = filter_var( $region, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $region === $this->getRegion() ) {
			return;
		}
		if ( ! update_option( 'woocommerce_default_country', $region ) ) {
			$this->errors[] = [ 'region' => __( 'Invalid Region', 'nexcess-mapps' ) ];
		}
	}

	/**
	 * Gets the WooCommerce Postal Code.
	 *
	 * @return string
	 */
	public function getPostcode() {
		if ( ! $this->postcode ) {
			$this->postcode = WC()->countries->get_base_postcode();
		}
		return $this->postcode;
	}

	/**
	 * Sets the WooCommerce PostCode.
	 *
	 * @param string $postcode
	 */
	public function setPostcode( $postcode ) {
		$postcode = filter_var( $postcode, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $postcode === $this->getPostcode() ) {
			return;
		}
		if ( ! update_option( 'woocommerce_store_postcode', $postcode ) ) {
			$this->errors[] = [ 'postCode' => __( 'Invalid Postcode', 'nexcess-mapps' ) ];
		}
	}

	/**
	 * Gets the WooCommerce Currency.
	 *
	 * @return string
	 */
	public function getCurrency() {
		if ( ! $this->currency ) {
			$this->currency = get_woocommerce_currency();
		}
		return $this->currency;
	}

	/**
	 * Sets the WooCommerce Currency.  Currency must match once of the keys
	 * provided by get_woocommerce_currencies().
	 *
	 * @param string $currency
	 */
	public function setCurrency( $currency ) {
		$currency = filter_var( $currency, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $currency === $this->getCurrency() ) {
			return;
		}

		if ( ! in_array( $currency, array_keys( get_woocommerce_currencies() ), true ) ) {
			$this->errors[] = [ 'currency' => __( 'Invalid Currency', 'nexcess-mapps' ) ];
			return;
		}

		if ( ! update_option( 'woocommerce_currency', $currency ) ) {
			$this->errors[] = [ 'currency' => __( 'Invalid Currency', 'nexcess-mapps' ) ];
		}
	}

	/**
	 * Gets the productType.
	 *
	 * @return mixed
	 */
	public function getProductstype() {
		return ( $this->getOption()->producttype ) ? $this->getOption()->producttype : [];
	}

	/**
	 * Sets the productType.
	 *
	 * @param array $productstype
	 */
	public function setProductstype( $productstype = [] ) {
		$productstype = filter_var( $productstype, FILTER_SANITIZE_FULL_SPECIAL_CHARS, [ 'flags' => FILTER_FORCE_ARRAY ] );

		if ( ! $productstype || $productstype === $this->getProductstype() ) {
			return;
		}

		$this->getOption()->producttype = $productstype;
	}

	/**
	 * Gets the productCount.
	 *
	 * @return string
	 */
	public function getProductcount() {
		return ( $this->getOption()->productcount ) ? $this->getOption()->productcount : '';
	}

	/**
	 * Sets the productCount.
	 *
	 * @param string $productcount
	 */
	public function setProductcount( $productcount ) {
		$productcount = filter_var( $productcount, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! $productcount || $productcount === $this->getProductcount() ) {
			return;
		}

		$this->getOption()->productcount = $productcount;
	}

	/**
	 * Runs a check to make sure that there were no errors in setting the values
	 * and then sets the section to complete.
	 *
	 * @return array success/error messages
	 */
	public function save() {
		if ( ! empty( $this->errors ) ) {
			$this->setFtcComplete( false );
			return [
				'success' => false,
				'errors'  => $this->errors,
			];
		}

		$this->setFtcComplete( true );

		if ( $this->getOption()->isDirty() ) {
			return [ 'success' => $this->getOption()->save() ];
		}

		return [ 'success' => true ];
	}

	/**
	 * Returns true if the FTC has been completed, false if not.
	 *
	 * @return bool
	 */
	public function isFtcComplete() {
		if ( version_compare( $this->getStorebuilderVersion(), '3.0', '<' ) || $this->getOption()->ftc_complete ) {
			return true;
		}

		return false;
	}

	/**
	 * Sets the storebuilder FTC complete value.
	 *
	 * @param bool $complete Whether the step is complete or not.
	 */
	private function setFtcComplete( $complete = true ) {
		$this->getOption()->ftc_complete = $complete;
	}

	/**
	 * Returns all the available currencies from WooCommerce.
	 *
	 * @return array
	 */
	public function getWoocommerceCurrencies() {
		if ( ! function_exists( 'get_woocommerce_currencies' ) ) {
			return [];
		}
		return get_woocommerce_currencies();
	}

	/**
	 * Returns the regions for WooCommerce Region dropdown.
	 *
	 * @return Array<mixed>
	 */
	public function getWoocommerceRegions() {
		$regions = [];
		$wc      = WC();
		if ( $wc->countries ) {
			foreach ( $wc->countries->get_countries() as $country_key => $country_name ) {
				$states = $wc->countries->get_states( $country_key );
				if ( $states ) {
					foreach ( $states as $state_key => $state_value ) {
						$country_state_key = esc_attr( $country_key ) . ':' . esc_attr( $state_key );
						$label             = esc_html( $country_name ) . ' — ' . esc_html( $state_value );
						$regions[]         = [
							'country' => $country_name,
							'value'   => $country_state_key,
							'label'   => $label,
						];
					}
				} else {
					$country_state_key = esc_attr( $country_key );
					$label             = esc_html( $country_name );
					$regions[]         = [
						'country' => $country_name,
						'value'   => $country_state_key,
						'label'   => $label,
					];
				}
			}
		}
		return $regions;
	}

	/**
	 * Returns the Storebuilder version saved in the Build Command.
	 *
	 * @return string
	 */
	public function getStorebuilderVersion() {
		return get_option( self::STOREBUILDER_VERSION, '2.0' );
	}
}
