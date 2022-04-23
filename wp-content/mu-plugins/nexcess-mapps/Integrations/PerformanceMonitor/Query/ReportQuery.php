<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Query;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\CustomPostTypes;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Report;

/**
 * The `ReportQuery` class queries the REST API and returns
 * `Report` instances that match the query.
 *
 * Note: The class only exists on its own to provide more accurate
 * type hints using PHPDoc. See the `@method` declarations below.
 *
 * @method Report|null get(int $post_id)
 * @method Report|null getMostRecent()
 * @method Report[]    getByParent(int $parent_id, array $query_params = [])
 * @method Report[]    query(array $query_params)
 */
class ReportQuery extends RestQuery {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( CustomPostTypes::POST_TYPES['report'] );
	}
}
