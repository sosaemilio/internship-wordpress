# Templating

The StellarWP Plugin Framework includes a method for loading in template files with several levels of fallback, making it easy for partner plugins to override default views rendered by the framework.

Templates are handled via the `StellarWP\PluginFramework\Concerns\RendersTemplates` trait, which exposes three methods:

<dl>
<dt>locateTemplate($name): string</dt>
<dd>Locate a template, looking through registered paths before falling back to the framework.</dd>
<dd>The <strong>last</strong> place to be checked will be the framework's <code>resources/views/</code> directory</dd>
<dt>renderTemplate(string $name[, array $data = []]): void</dt>
<dd>Locate and render a template, optionally passing data into the template.</dd>
<dd>This method will first locate the template (via <code>locateTemplate()</code>), <a href="#passing-data-to-templates">then populate variables using the <code>$data</code> array</a>.</dd>
<dt>setTemplateDirectories(array $directories): self</dt>
<dd>Define the prioritized list of directories to check for a matching template before falling back to <code>resources/views/</code> within the framework.</dd>
</dl>

## Locating templates

When attempting to locate a template, each registered directory will be checked for a match before falling back to the framework's `resources/views/` directory.

For the sake of example, imagine the following method within a module:

```php
namespace StellarWP\SomePlugin\Modules;

use StellarWP\PluginFramework\Concerns\RendersTemplates;
use StellarWP\PluginFramework\Modules\Module;

class SomeModule extends Module
{
    use RendersTemplates;

    /**
     * Render the module settings.
     */
    public function renderSettingsScreen()
    {
        $this->setTemplateDirectories([
            dirname(__DIR__) . '/templates/',
        ])->renderTemplate('some-template', [
            'settings' => $this->moduleSettings,
        ]);
    }
}
```

With this configuration, `locateTempate()` (and thus `renderTemplate()`) will attempt to find the first existing file that matches on the following:

1. `/path/to/plugin/templates/some-template`
2. `/path/to/plugin/templates/some-template.php`
3. `/path/to/framework/resources/views/some-template`
4. `/path/to/framework/resources/views/some-template.php`

If no match can be found, a `StellarWP\PluginFramework\Exceptions\TemplateException` will be thrown.

## Passing data to templates

Instead of relying on global variables or re-instantiating code we already have running, `RendersTemplates::renderTemplate()` accepts a `$data` array as its second argument.

When rendering the template, this array will be run through [`extract()`](https://www.php.net/manual/en/function.extract.php), making the data available within the scope of the included template.

For example:

```php
# order-up.php
<?php
/**
 * @var MealOrder $order
 */

?>

Hey <?php echo esc_html($order->customerName); ?>!

We wanted to let you know that your order is now ready for pickup:

<ul>
    <?php foreach ($order->items as $item): ?>
        <li>
            <?php echo esc_html($item); ?>
            <?php if ($item->specialInstructions) : ?>
                <p class="special-instructions">
                    <?php echo esc_html($item->specialInstructions); ?>
                </p>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

See you soon!
```

This template might be rendered with the following:

```php
$this->renderTemplate('order-up', [
    'order' => $currentOrder,
]);
```
