/**
 * The Laravel Mix configuration for the StellarWP Plugin Framework.
 *
 * @link https://laravel-mix.com
 */
const mix = require('laravel-mix');
const webpack = require('webpack');
const banner = 'This file is part of the StellarWP Plugin Framework and was generated automatically';

require('laravel-mix-eslint');

// Customize Mix options.
mix.setPublicPath('dist')
    .options({
        manifest: false,
        terser: {
            extractComments: false,
            terserOptions: {
                format: {
                    comments: false,
                    preamble: `/** ${ banner } */`,
                },
            },
        },
		postCss: [
			require('postcss-preset-env'),
		],
    });

// Customize the Webpack configuration.
mix.webpackConfig({
    plugins: [
        new webpack.BannerPlugin(banner),
    ],
    externals: {
		'@wordpress/element': 'wp.element',
		'@wordpress/components': 'wp.components',
		'@wordpress/hooks': 'wp.hooks',
		'@wordpress/i18n': 'wp.i18n',
	},
});

// Bundle CSS.
mix.css('resources/css/forms.css', 'dist/css')
	.sourceMaps(false);

// Bundle JavaScript.
mix.js('resources/js/go-live-widget.js', 'dist/js')
    .sourceMaps(false)
    .eslint()
    .react();
