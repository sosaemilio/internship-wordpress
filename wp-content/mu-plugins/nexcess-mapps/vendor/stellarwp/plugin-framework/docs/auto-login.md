# Automatic Login

Automatic login is available one time after a site is first created.

## How does it work?

A valid auto login token must be sent to `/wp-login.php?token=xxxxxxxx`. The token is detected and validated by NocWorx, which returns the username that was generated at site creation. The username is used to fetch the user from the WordPress database, and if found, the user is fully logged in.

After the token has been used once, it is no longer usable and will result in an error displaying on the login screen asking the user to log in again.

### Customizing behavior

The `AutoLogin` module is not meant to be extended, though it is not declared final. This framework makes no guarantees that the method names and behavior of the module will remain consistent across versions.  However, the `stellarwp_autologin` action is fired when an automatic login succeeds, which allows other plugins or modules to hook in and customize the behavior when an automatic login has occurred.

For example, you might customize the redirect URL that happens post-login.

```php
add_action('stellarwp_autologin', function() {
    add_filter('login_redirect', function($redirect_to, $requested_redirect_to) {
		if (! empty($requested_redirect_to)) {
			return $redirect_to;
		}

        return '/wp-admin/options-general.php?page=my-setup-wizard';
    }, 10, 2);
});
```
