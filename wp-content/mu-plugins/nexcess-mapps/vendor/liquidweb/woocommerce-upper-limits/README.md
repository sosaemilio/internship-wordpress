# WooCommerce Upper Limits

This WordPress plugin enables WooCommerce to be constrained in one of two ways:

1. Limit the number of products a store may have.
	- Restrictions are based on the number of **published** products; attempts to create more will force them to stay as drafts.
2. Limit the number of orders the store may accept within a month
	- After the store reaches its order limit, orders will be prevented until the first day of the next month.
	- Restrictions are based on when the order is placed, not the status of the order.

Number of products is the default constraint once the plugin is activated, but the store owner may either confirm the product constraint or switch to the order-based constraint **once** via the WooCommerce &rsaquo; Settings &rsaquo; Integrations page within the WordPress administration area.

## Configuration

The constraints are configured via environment variables on the web server:

<dl>
	<dt>WOOCOMMERCE_MAX_PRODUCTS</dt>
	<dd>Set the maximum number of products permitted. Default is 15.</dd>
	<dt>WOOCOMMERCE_MAX_ORDERS</dt>
	<dd>Limit the number of orders a store may accept each month. Default is 150.</dd>
</dl>

## License

Copyright 2018 Liquid Web, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
