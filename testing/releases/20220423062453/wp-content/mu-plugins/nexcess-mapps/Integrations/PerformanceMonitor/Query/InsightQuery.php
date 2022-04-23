<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Query;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\CustomPostTypes;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Insight;

/**
 * The `InsightQuery` class queries the REST API and returns
 * `Insight` instances that match the query.
 *
 * Note: The class only exists on its own to provide more accurate
 * type hints using PHPDoc. See the `@method` declarations below.
 *
 * @method Insight|null get(int $post_id)
 * @method Insight|null getMostRecent()
 * @method Insight[]    getByParent(int $parent_id, array $query_params = [])
 * @method Insight[]    query(array $query_params)
 */
class InsightQuery extends RestQuery {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( CustomPostTypes::POST_TYPES['insight'] );
	}
}
