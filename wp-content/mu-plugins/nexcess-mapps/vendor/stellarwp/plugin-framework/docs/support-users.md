# Support Users

Support Users are temporary, admin-level users that can be generated within a customer's WordPress site for troubleshooting purposes.

```none
$ wp stellarwp support-user create
Success: A new support user has been created:

	url: https://example.com/wp-login.php
	username: stellarwp_support_62214076d5a21
	password: s0me$tr0ngP4$$w0rd!

This user will automatically expire in 24 hours. You may also remove it manually by running:

	$ wp stellarwp support-user delete 67
```

## How does it work?

Upon creating the user, an expiration timestamp is stored in user meta. Each time a user logs into the WordPress site, the module will look for this meta key for the user as they log in and, if one is found, it will be compared against the current Unix timestamp. If the expiration timestamp is less than or equal to the current timestamp, login will be prevented and the support user immediately deleted.

Once successfully logged in, the timestamp is updated in user meta, effectively resetting the clock.

In order to keep customer sites tidy, a daily cron job is also registered to clean up any expired support users.

## Enabling support users within a plugin

To add Support User functionality into a plugin using the framework, the following entries should be added to the `Plugin` class:

```diff
  /**
   * An array containing all registered WP-CLI commands.
   *
   * @var Array<string,string>
   */
  protected $commands = [
      // ...
+     'stellarwp support-user' => \StellarWP\PluginFramework\Console\Commands\SupportUserCommand::class,
  ];

  /**
   * An array containing all registered modules.
   *
   * @var Array<string>
   */
  protected $modules = [
      // ...
+     \StellarWP\PluginFramework\Modules\SupportUsers::class,
  ];
```

Once configured, support users may be managed via WP-CLI:

<dl>
    <dt>stellarwp support-user all</dt>
    <dd>Retrieve a list of all support users on this site.</dd>
    <dt>stellarwp support-user all</dt>
    <dd>Create a new support user.</dd>
    <dt>stellarwp support-user delete [<id>...] [--all] [--expired]</dt>
    <dd>Delete one or more existing support users.</dd>
    <dd>
        <dl>
            <dt>[&lt;id&gt;...]</dt>
            <dd>One or more support user IDs to create.</dd>
            <dt>[--all]</dt>
            <dd>Remove all support users.</dd>
            <dt>[--expired]</dt>
            <dd>Only delete support users that have reached their expiration date.</dd>
        </dl>
    </dd>
</dl>

### Customizing behavior

The `SupportUsers` module is meant to be fairly self-contained, but there are a few ways you may wish to customize it:

1. [Changing the default attributes for support users](#changing-default-support-user-attributes)
2. [Changing the default support user lifetime](#overriding-the-support-user-lifetime)
3. [Changing the user meta key used for expiration](#changing-the-user-meta-key)

Regardless of the adjustments you wish to make, the process is the same:

1. Create a new sub-class of `StellarWP\PluginFramework\Modules\SupportUsers` in your plugin with your changes
2. Override the concrete instance for the `StellarWP\PluginFramework\Contracts\ProvidesSupportUsers` interface in your container:

    ```php
    /**
     * Retrieve a mapping of abstract identifiers to callables.
     *
     * When an abstract is requested through the container, the container will find the given
     * dependency in this array, execute the callable, and return the result.
     *
     * @return Array<string,callable|object|string|null> A mapping of abstracts to callables.
     */
    public function config()
    {
        return array_merge(parent::config(), [
            // ...
            \StellarWP\PluginFramework\Contracts\ProvidesSettings::class => Modules\SupportUsers::class,
        ]);
    }
    ```

#### Changing default support user attributes

Default user attributes come from the `SupportUsers::getDefaultUserAttributes()` method, which returns an array that can be passed to [`wp_insert_user()`](https://developer.wordpress.org/reference/functions/wp_insert_user/).

```php
/**
 * Retrieve default arguments for creating new support users.
 *
 * @return Array<string,scalar>
 */
protected function getDefaultUserAttributes()
{
    $uniqid = uniqid();

    return [
        'description'   => 'Temporary user for SomeCompany support',
        'display_name'  => 'SomeCompany Support',
        'first_name'    => 'SomeCompany',
        'last_name'     => 'Support',
        'nickname'      => 'SomeCompany Support',
        'use_ssl'       => true,
        'user_email'    => sprintf('devnull+%s@nexcess.net', $uniqid),
        'user_login'    => 'somecompany_support_' . $uniqid,
        'user_url'      => 'https://somecompany.com',
    ];
}
```

Be aware that the `user_email` value should be a valid email address, as WordPress will occasionally send emails to site administrators (such as upgrade or security notices). The default value uses the special `devnull@nexcess.net` address, which accepts and discards all mail.

## Overriding the support user lifetime

By default, support users have a lifetime of 24 hours, after which they should be expired.

If you need to change this value, define the `USER_EXPIRES_IN` class constant in your `SupportUsers` implementation:

```php
namespace StellarWP\SomePlugin\Modules;

use StellarWP\PluginFramework\Modules\SupportUsers as BaseSupportUsers;

class SupportUsers extends BaseSupportUsers
{
    /**
     * How long (in seconds) support users should exist before expiring.
     */
    const USER_EXPIRES_IN = 2 * DAY_IN_SECONDS;
}
```

## Changing the user meta key

The Unix timestamp that tracks support user expiration is stored in user meta under the "_stellarwp_expires_at" key; if you want to change this value, you may do so by defining the `USER_META_KEY` class constant:

```php
namespace StellarWP\SomePlugin\Modules;

use StellarWP\PluginFramework\Modules\SupportUsers as BaseSupportUsers;

class SupportUsers extends BaseSupportUsers
{
   /**
     * The meta key used to store support user expiration details.
     */
    const USER_META_KEY = '_somecompany_expires_at';
}
```
