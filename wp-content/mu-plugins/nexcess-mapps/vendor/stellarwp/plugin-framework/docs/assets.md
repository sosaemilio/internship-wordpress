# Building and Loading Static Assets

Beyond PHP, the StellarWP Plugin Framework exposes a number of CSS and JavaScript assets for use in partner plugins.

Generally speaking, there are two ways of using these within plugins:

1. [Load pre-built versions, bundled in the framework's `dist/` directory](#loading-pre-built-assets)
2. [Import the source files directly and include them in the plugin's build process](#importing-source-files-into-custom-assets)

Whichever route you take, remember that all assets should be loaded via WordPress' native dependency API (e.g. [`wp_enqueue_style()`](https://developer.wordpress.org/reference/functions/wp_enqueue_style/) and [`wp_enqueue_script()`](https://developer.wordpress.org/reference/functions/wp_enqueue_script/)).

## Loading pre-built assets

If your plugin doesn't require any customizations to the assets provided by the framework, you may include the bundled files directly. In fact, modules that depend on these assets will likely already be doing this for you.

To ensure that the core modules can find the scripts, please make sure that your plugin's `Settings` object includes a `framework_url` property (if you built your plugin using [the scaffold-plugin script](scaffold-plugin.md), this should already be present):

```php
protected function loadSettings(array $settings)
{
    return [
        'framework_url'  => plugins_url('/vendor/stellarwp/plugin-framework/', __FILE__),
        // ... Other settings.
    ];
}
```

You may then use this setting in your dependency API calls, e.g.:

```php
wp_enqueue_script('my-script', $this->settings->framework_url . 'dist/js/some-script.js');
```

## Building assets from source

To ensure we're serving production-optimized assets to our customers and their visitors, we're using [Laravel Mix](https://laravel-mix.com/)—a simplified wrapper around [Webpack](https://webpack.js.org/)—to transpile, concatenate, and minify our assets.

### What is Laravel Mix?

If you have worked with [Laravel](https://laravel.com), Mix should be very familiar as its the dominant (and default) static asset bundler in that ecosystem. Mix exposes a nice abstraction layer over Webpack, [which is configured via `webpack.mix.js`](https://laravel-mix.com/docs/6.0/api).

Generally speaking, the raw assets live in `resources/`, while the bundled assets are then sent to `plugin-name/assets/` (or, in the case of the framework itself, `dist/`).

### Working with Mix

By introducing Mix/Webpack into the mix, we can write modern JavaScript and have it transpiled down to satisfy older browsers. We're now also able to more-easily split our JavaScript into separate files, letting Mix concatenate them for us.

Of course, this also means that we need to build our assets during development (the release scripting has already been updated to handle this). Fortunately, building the development version of the assets is as simple as running the following:

```sh
$ npm run dev
```

This will cause Mix to build un-minified versions of our assets. You may also instruct Mix to watch for changes and rebuild automatically:

```sh
$ npm run watch
```

Should you need to build production-ready assets manually, you may do so by running:

```sh
$ npm run prod
```

### Importing source files into custom assets

Should you need to customize the behavior of framework assets, all scripts and styles in the framework are defined as JavaScript modules and CSS partials, respectively:

**JavaScript:**

```js
import { SomeModule } from '@stellarwp';

// ...
```

```css
@import '@stellarwp/resources/css/some-css-partial.css';
```

In order for these examples to work, you must have the `@stellarwp` alias defined in `webpack.mix.js` (again, if you built your plugin using [the scaffold-plugin script](scaffold-plugin.md), this should already be present):

```js
mix.webpackConfig({
    resolve: {
		alias: {
			'@stellarwp': __dirname + '/learndash-cloud/vendor/stellarwp/plugin-framework',
		},
	},
    // ...
});
```

If you're meaning to replace existing scripts and/or styles, please inspect the relevant module(s) to determine how to de-register the framework-provided versions!
