# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Version 1.0.3]
* Prevent order limit emails from being sent out to customers using the "Product" constraint ([#43])
* Fix a portability issue in the `composer build` command ([#40]).
* Tweaks to the upgrade email copy and headers ([#38], [#42])
* Add additional PHP_CodeSniffer exclusions ([#39])
* Add PHP 7.3 to the testing matrix ([#41])

## [Version 1.0.2]
* Updated email text to provide an upgrade path.

## [Version 1.0.1]

* Adjust the line spacing on the welcome panel to prevent conflicts with iThemes Sales Accelerator Dashboard ([#30])
* Conditionally alter the default constraint — if an imported site already has > 15 products but hasn't set a constraint, default to the order constraint ([#32])

## [Version 1.0.0]

* Initial release of WooCommerce Upper Limits for the Liquid Web WooCommerce Beginner plan.


[Unreleased]: https://github.com/liquidweb/woocommerce-upper-limits/compare/master...develop
[Version 1.0.3]: https://github.com/liquidweb/woocommerce-upper-limits/releases/tag/v1.0.3
[Version 1.0.2]: https://github.com/liquidweb/woocommerce-upper-limits/releases/tag/v1.0.2
[Version 1.0.1]: https://github.com/liquidweb/woocommerce-upper-limits/releases/tag/v1.0.1
[Version 1.0.0]: https://github.com/liquidweb/woocommerce-upper-limits/releases/tag/v1.0.0
[#30]: https://github.com/liquidweb/woocommerce-upper-limits/pull/30
[#32]: https://github.com/liquidweb/woocommerce-upper-limits/pull/32
[#38]: https://github.com/liquidweb/woocommerce-upper-limits/pull/38
[#39]: https://github.com/liquidweb/woocommerce-upper-limits/pull/39
[#40]: https://github.com/liquidweb/woocommerce-upper-limits/pull/40
[#41]: https://github.com/liquidweb/woocommerce-upper-limits/pull/41
[#42]: https://github.com/liquidweb/woocommerce-upper-limits/pull/42
[#43]: https://github.com/liquidweb/woocommerce-upper-limits/pull/43
