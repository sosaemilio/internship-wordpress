<?php

/**
 * A brief description of your REST API route.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\StoreBuilder\StoreBuilderFTC;
use WP_REST_Request;

class StoreBuilderAppFTCPost extends RestRoute {
	const LOGO           = 'logo';
	const SITENAME       = 'siteName';
	const TAGLINE        = 'tagLine';
	const ADDRESSLINEONE = 'addressLine1';
	const ADDRESSLINETWO = 'addressLine2';
	const CITY           = 'city';
	const STATE          = 'state';
	const REGION         = 'region';
	const POSTCODE       = 'postCode';
	const CURRENCY       = 'currency';
	const PRODUCTSTYPE   = 'productsType';
	const PRODUCTCOUNT   = 'productCount';

	/**
	 * Supported HTTP methods for this route.
	 *
	 * @var string[]
	 */
	protected $methods = [
		'POST',
	];

	/**
	 * An instance of the StoreBuilderFTC class.
	 *
	 * @var StoreBuilderFTC
	 */
	protected $storebuilder_ftc;

	/**
	 * The REST route.
	 *
	 * @var string
	 */
	protected $route = '/storebuilderapp/wizard/ftc';

	/**
	 * @param StoreBuilderFTC $storebuilder_ftc
	 */
	public function __construct( StoreBuilderFTC $storebuilder_ftc ) {
		$this->storebuilder_ftc = $storebuilder_ftc;
	}

	/**
	 * Determine whether or not the current request is authorized.
	 *
	 * This corresponds to the "permission_callback" argument within the WP REST API.
	 *
	 * @link https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#permissions-callback
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool
	 */
	public function authorizeRequest( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * The primary callback to execute for the route. You cannot set the
	 * username or password from an endpoint due to the need to reset
	 * the users cookies in order for them to not be logged out.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return mixed
	 */
	public function handleRequest( WP_REST_Request $request ) {
		if ( $request->has_param( self::LOGO ) ) {
			$this->storebuilder_ftc->setLogo( $request->get_param( self::LOGO ) );
		}

		if ( $request->has_param( self::SITENAME ) ) {
			$this->storebuilder_ftc->setSitename( $request->get_param( self::SITENAME ) );
		}

		if ( $request->has_param( self::TAGLINE ) ) {
			$this->storebuilder_ftc->setDescription( $request->get_param( self::TAGLINE ) );
		}

		if ( $request->has_param( self::ADDRESSLINEONE ) ) {
			$this->storebuilder_ftc->setAddressOne( $request->get_param( self::ADDRESSLINEONE ) );
		}

		if ( $request->has_param( self::ADDRESSLINETWO ) ) {
			$this->storebuilder_ftc->setAddressTwo( $request->get_param( self::ADDRESSLINETWO ) );
		}

		if ( $request->has_param( self::CITY ) ) {
			$this->storebuilder_ftc->setCity( $request->get_param( self::CITY ) );
		}

		if ( $request->has_param( self::STATE ) ) {
			$this->storebuilder_ftc->setState( $request->get_param( self::STATE ) );
		}

		if ( $request->has_param( self::REGION ) ) {
			$this->storebuilder_ftc->setRegion( $request->get_param( self::REGION ) );
		}

		if ( $request->has_param( self::POSTCODE ) ) {
			$this->storebuilder_ftc->setPostcode( $request->get_param( self::POSTCODE ) );
		}

		if ( $request->has_param( self::CURRENCY ) ) {
			$this->storebuilder_ftc->setCurrency( $request->get_param( self::CURRENCY ) );
		}

		if ( $request->has_param( self::PRODUCTSTYPE ) ) {
			$this->storebuilder_ftc->setProductstype( $request->get_param( self::PRODUCTSTYPE ) );
		}

		if ( $request->has_param( self::PRODUCTCOUNT ) ) {
			$this->storebuilder_ftc->setProductcount( $request->get_param( self::PRODUCTCOUNT ) );
		}

		return $this->storebuilder_ftc->save();
	}
}
