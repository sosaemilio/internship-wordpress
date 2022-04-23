<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Query;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\CustomPostTypes;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Page;

/**
 * The `PageQuery` class queries the REST API and returns
 * `SiteChange` instances that match the query.
 *
 * Note: The class only exists on its own to provide more accurate
 * type hints using PHPDoc. See the `@method` declarations below.
 *
 * @method Page|null get(int $post_id)
 * @method Page|null getMostRecent()
 * @method Page[]    getByParent(int $parent_id, array $query_params = [])
 * @method Page[]    query(array $query_params)
 */
class PageQuery extends RestQuery {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( CustomPostTypes::POST_TYPES['page'] );
	}
}
