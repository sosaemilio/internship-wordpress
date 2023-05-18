# Starting a new plugin with the StellarWP Plugin Framework

> ⚠️ **Heads up!**<br>It's **strongly** recommended that you [read through the plugin architecture](architecture.md) before starting a new plugin!

The easiest way to get started with the StellarWP Plugin Framework is by creating a new repo using the `stellarwp/plugin-starter` as a template. Luckily, [this repository contains a shell script to make that a breeze](../bin/scaffold-plugin.sh):

```sh
curl https://github.com/stellarwp/plugin-framework/blob/main/bin/scaffold-plugin.sh | sh
```

In order, the script does all of the following:

1. Verify that [Composer](https://getcomposer.org) and [WP-CLI](https://wp-cli.org) are installed locally
2. Prompt you for the new plugin name (e.g. "LearnDash Cloud")
3. Ask you where the files should be created (default is the plugin name in kebab-case)
4. Create a new project using stellarwp/plugin-starter as a template in the given directory, initialize a clean git repository
5. Update references to "plugin-starter", "PluginStarter", and "{{Plugin Starter}}" with the kebab-case, PascalCase, and provided version of your new plugin name
6. Install all Composer and npm dependencies

Once the script completes, you should have a working (albeit bare-bones) plugin using the StellarWP Plugin Framework!

## Next steps

Once your new plugin has been scaffolded, you may want to update a few things:

1. The "Plugin Description" at the top of the plugin bootstrap file
2. The project description and repo details in `composer.json`
3. The contents of the plugin's README file

If you're running your new plugin as a MU plugin, you'll also want to generate a bootstrap file within your `wp-content/mu-plugins/` directory so the plugin is picked up by WordPress; this may be done by running `composer make:bootstrap` from within the project directory, then adjusting `wp-content/mu-plugins/stellarwp-bootstrap.php` to suit your needs.

From here on out, everything added to the plugin is what makes each StellarWP plugin unique! You may add modules and commands, expand the `Setup` command class suit your needs, and override anything necessary via the DI container.
