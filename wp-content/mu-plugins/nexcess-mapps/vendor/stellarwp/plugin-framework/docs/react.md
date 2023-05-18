# React.js

As we start to build more sophisticated interfaces within the StellarWP partner plugins, it makes sense to leverage a modern JavaScript framework for the UI.

Since [React](https://reactjs.org/) is already bundled with WordPress 5.0 and newer (via Gutenberg), it makes for a sensible choice.

Caution must be taken, however, so as to not impact customer sites' performance or weight. We've taken a number of steps to prevent this through the following measures:

1. Explicitly specifying "wp-element" as a dependency when enqueueing our React code within WordPress.
    * "wp-element" is a reference to [@wordpress/element](https://www.npmjs.com/package/@wordpress/element), one of many npm packages that make up Gutenberg. It's an abstraction layer over React, enabling multiple plugins to use the bundled copy rather than compiling their own.
2. Specifying "@wordpress/element" and "@wordpress/i18n" as [Webpack externals](https://webpack.js.org/configuration/externals/) within [our Laravel Mix configuration](building-assets.md), preventing Webpack from bundling this code.
3. Splitting individual React instances into separate enqueues, ensuring that we're only ever loading them (along with React) when necessary.


## Concepts

Traditionally, a React application consists of two elements:

1. A hierarchy of React components (e.g. "App", "Button", "Table", etc.)
2. A single DOM node that will serve as the "root" for the app.

In practice, this might look something like this:

```html
<!-- index.html -->
<body>
  <div id="app"></div>
</body>
```

```js
/* scripts.js */
import App from './components/app.jsx';
import React from 'react';
import ReactDOM from 'react-dom';

ReactDOM.render(
  React.createElement(App),
  document.getElementById('app')
);
```

When the script loads, it finds the `div#app` element and replaces it with the rendered `App` React component.

However, since we're creating individual React components for particular pages (as opposed to a full React application), we need to think of things a little differently: **consider each (major) component to be its own mini-application.**

Under this model, we don't have a single "root" node, but rather a root node for each top-level component we need to inject.

## Digging into a React component

For the sake of example, let's look at our first React component: [`DomainChangeForm`](../resources/js/components/domain-change-form.jsx).

This component is responsible for creating a form that collects a domain name and passes it to an Ajax handler that makes a request to NocWorx to change a site's domain. It's designed to be displayed in the "Go Live!" widget on the WP Admin Dashboard.

Fortunately, [the code to render this form](../resources/js/go-live-widget.js) is rather minimal:

```js
# resources/js/domain-change-form.jsx

import DomainChangeForm from './components/domain-change-form.jsx';

// Locate the root element.
const el = document.getElementById('stellarwp-change-domain-form');

if (el) {
	wp.element.render(wp.element.createElement(DomainChangeForm, el.dataset), el);
}
```

This code is responsible for the following:

1. Import the DomainChangeForm component
2. Look for the `#stellarwp-change-domain-form` root element in the DOM
3. If found, use `wp.element.render()` to render an instance of `DomainChangeForm` into `#stellarwp-change-domain-form`, using the data-attributes of that element as properties.

Meanwhile, [the widget](../src/Modules/GoLiveWidget.php) is responsible for two things:

1. Enqueue the appropriate scripts (and styles)

    ```php
    add_action('admin_enqueue_scripts', function () {
        wp_enqueue_script(
            'stellarwp-go-live',
            $this->settings->framework_url . 'dist/js/go-live-widget.js',
            ['wp-element'], // <-- Necessary to load React!
            $this->settings->plugin_version,
            true
        );
    });
    ```

2. Render the root element

    ```php
    <div id="stellarwp-change-domain-form" data-someprop="some value"></div>
    ```

In this example, the "Go Live Widget" will render the `DomainChangeForm` React component, and `props.someprop` will be available with a value of "some value".

> **Note:** Tehnically, HTML data-attributes should be lowercased; if you need to pass a camelCased property, [you should separate the parts with hyphens](https://developer.mozilla.org/en-US/docs/Learn/HTML/Howto/Use_data_attributes#javascript_access):
>
> ```diff
> - <div data-someProp="value">
> + <div data-some-prop="value">
> ```

### Localization

Prior to Gutenberg, localization strings would typically be passed into JavaScript via [`wp_localize_script()`](https://developer.wordpress.org/reference/functions/wp_localize_script/). However, the newer tooling enables us to import localization functions (`__()`, `_x()`, etc.) via [the `@wordpress/i18n` package](https://developer.wordpress.org/reference/functions/wp_localize_script/):

```js
import { __ } from '@wordpress/i18n';

window.console.log(__('This is a localized string', 'stellarwp-framework'));
```

[Tooling exists to extract these strings into `.pot` files](https://developer.wordpress.org/block-editor/developers/internationalization/), but until we need to worry about localizing the MU plugin we should just make sure we're making all customer-facing strings localization-ready.
