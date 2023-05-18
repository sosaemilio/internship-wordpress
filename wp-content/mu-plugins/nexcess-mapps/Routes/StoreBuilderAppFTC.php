<?php

/**
 * A brief description of your REST API route.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\StoreBuilder\StoreBuilderFTC;
use WP_REST_Request;

class StoreBuilderAppFTC extends RestRoute {

	/**
	 * Supported HTTP methods for this route.
	 *
	 * @var string[]
	 */
	protected $methods = [
		'GET',
	];

	/**
	 * The REST route.
	 *
	 * @var string
	 */
	protected $route = '/storebuilderapp/wizard/ftc';

	/**
	 * @var StoreBuilderFTC $storebuilder_ftc
	 */
	private $storebuilder_ftc;

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
	 * The primary callback to execute for the route.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return mixed
	 */
	public function handleRequest( WP_REST_Request $request ) {
		$return = [
			'isFirstTimeConfiguration' => ! $this->storebuilder_ftc->isFtcComplete(),
			'username'                 => $this->storebuilder_ftc->getUsername(),
			'site'                     => [
				'siteName' => $this->storebuilder_ftc->getSitename(),
				'tagline'  => $this->storebuilder_ftc->getDescription(),
				'logo'     => [
					'id'  => $this->storebuilder_ftc->getLogoId(),
					'url' => $this->storebuilder_ftc->getLogoUrl(),
				],
			],
			'store'                    => [
				'addressLine1' => $this->storebuilder_ftc->getAddressOne(),
				'addressLine2' => $this->storebuilder_ftc->getAddressTwo(),
				'region'       => $this->storebuilder_ftc->getRegion(),
				'state'        => $this->storebuilder_ftc->getState(),
				'city'         => $this->storebuilder_ftc->getCity(),
				'postCode'     => $this->storebuilder_ftc->getPostcode(),
				'currency'     => $this->storebuilder_ftc->getCurrency(),
				'productsType' => $this->storebuilder_ftc->getProductsType(),
				'productCount' => $this->storebuilder_ftc->getProductCount(),
			],
			'currencies'               => $this->storebuilder_ftc->getWoocommerceCurrencies(),
			'regions'                  => $this->storebuilder_ftc->getWoocommerceRegions(),
			'states'                   => $this->storebuilder_ftc->getWoocommerceStates(),
			'locales'                  => $this->storebuilder_ftc->getWoocommerceLocales(),
			'admin_url'                => admin_url(),
		];
		return $return;
	}
}
