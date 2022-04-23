<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Query;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\CustomPostTypes;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\SiteChange;

/**
 * The `SiteChangeQuery` class queries the REST API and returns
 * `SiteChange` instances that match the query.
 *
 * Note: The class only exists on its own to provide more accurate
 * type hints using PHPDoc. See the `@method` declarations below.
 *
 * @method SiteChange|null get(int $post_id)
 * @method SiteChange|null getMostRecent()
 * @method SiteChange[]    getByParent(int $parent_id, array $query_params = [])
 * @method SiteChange[]    query(array $query_params)
 */
class SiteChangeQuery extends RestQuery {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( CustomPostTypes::POST_TYPES['site_change'] );
	}
}
