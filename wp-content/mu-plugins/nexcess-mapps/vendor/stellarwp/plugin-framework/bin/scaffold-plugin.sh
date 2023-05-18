#!/usr/bin/env bash
#
# Helper for automatically creating a new StellarWP plugin.

set -e

# Set up colors.
color_cyan="\033[0;36m"
color_green="\033[0;32m"
color_red="\033[0;31m"
color_reset="\033[0;0m"
color_yellow="\033[0;33m"

# Output helpers
info() {
    printf "\n${color_cyan}%s${color_reset}\n" "$1"
}

error() {
    printf "\n${color_red}[ERROR] ${color_reset}%s\n" "$1"
}

prompt() {
    printf "\n${color_yellow}%s:${color_reset} " "$1"
}

# String helpers
to_kebab() {
    # Convert it to snake_case, then replace underscores with dashes.
    to_snake "$1" | sed 's/_/-/g'
}

to_pascal() {
    # In order:
    # 1. Convert all non-alphanumeric characters to underscores
    # 2. Replace occurrences of 2+ underscores with a single underscore
    # 3. Split the string on underscores, capitalize the first letter of each entry,
    #    and glue them back together.

    sed -E 's/[^A-Za-z0-9_]/_/g' <<< "$1" \
        | awk -F '_' '{for(i=1; i<=NF; i++) printf "%s", toupper(substr($i,1,1)) substr($i,2); print"";}'
}

to_snake() {
    # In order:
    # 1. Convert all non-alphanumeric characters to underscores
    # 2. Prefix all capital letters with an underscore
    # 3. Replace occurrences of 2+ underscores with a single underscore
    # 4. Strip a leading underscore, if present
    # 5. Convert everything to lowercase
    sed -E 's/[^A-Za-z0-9_]/_/g;s/([A-Z])/_\1/g;s/_{2,}/_/g;s/^_//' <<< "$1" \
        | tr '[:upper:]' '[:lower:]'
}

echo -e "${color_green}Ready to build a new plugin? Stellar!${color_reset}"

# Make sure Composer is already installed
info "Verifying that Composer is installed"
composer_path="$(command -v composer)"

if [[ -n $composer_path ]]; then
    printf "Using Composer at %s\n" "$composer_path"
else
    error "Composer could not be found locally!"
    echo "Please visit https://getcomposer.org/download/ for instructions"
    exit 2
fi

# Do the same for WP-CLI
info "Verifying that WP-CLI is installed"
wp_cli_path="$(command -v wp)"

if [[ -n $wp_cli_path ]]; then
    printf "Using WP-CLI at %s\n" "$wp_cli_path"
else
    error "WP-CLI could not be found locally!"
    echo "Please visit https://wp-cli.org/#installing for instructions"
    exit 2
fi

# Prompt the user for details about the new plugin.
#
# From the plugin name, we'll attempt to guess the kebab-case and PascalCase forms of the name, but
# will prompt just to make sure.
read -er -p "$(prompt "What will the new plugin be named?")" plugin_name

if [[ -z "$plugin_name" ]]; then
    error "The plugin name cannot be empty!"
    exit 1
fi

default_plugin_name_kebab="$(to_kebab "$plugin_name")"
read -er -p "$(prompt "Confirm kebab-case [${default_plugin_name_kebab}]")" plugin_name_kebab
plugin_name_kebab=${plugin_name_kebab:-"$default_plugin_name_kebab"}

default_plugin_name_pascal="$(to_pascal "$plugin_name")"
read -er -p "$(prompt "Confirm PascalCase [${default_plugin_name_pascal}]")" plugin_name_pascal
plugin_name_pascal=${plugin_name_pascal:-"$default_plugin_name_pascal"}

default_plugin_dir="${PWD}/${plugin_name_kebab}"
read -er -p "$(prompt "Where should the plugin files be created? [${default_plugin_dir}]")" plugin_dir
plugin_dir=${plugin_dir:-"$default_plugin_dir"}

if [ -d "$plugin_dir" ]; then
    error "Cannot create directory ${plugin_dir}: directory exists"
    exit 1
fi

# With this information, start building the plugin
info "Creating your new plugin in ${plugin_dir}"
composer create-project --repository '{"type": "vcs","url": "https://github.com/stellarwp/plugin-starter"}' \
   --no-install --prefer-source --remove-vcs stellarwp/plugin-starter "$plugin_dir"

# Rename the plugin-starter/ directory and plugin-starter.php file to match the plugin name
info "Updating filenames, namespaces, and other references"
mv "${plugin_dir}/plugin-starter" "${plugin_dir}/${plugin_name_kebab}"
mv "${plugin_dir}/plugin-starter.php" "${plugin_dir}/${plugin_name_kebab}.php"

# Do a search + replace on non-vendor files
LC_ALL=C find "${plugin_dir}" \
    -type f \
    ! -path '*/.git/*' \
    ! -path '*/vendor/*' \
    -exec sed -i '' \
    "s/plugin-starter/${plugin_name_kebab}/g;s/PluginStarter/${plugin_name_pascal}/g;s/{{Plugin Starter}}/${plugin_name}/g" \
    {} +

# Initialize an empty Git repository and set the default branch name
info "Initializing a fresh Git repository"
git init --initial-branch=develop "$plugin_dir"

# Remove composer.lock from the .gitignore.
sed -i '' '/composer\.lock/d' "${plugin_dir}/.gitignore"

# Now that everything's in-place, install third-party dependencies
info "Installing default Composer dependencies"
$composer_path update --working-dir "$plugin_dir"

npm_path="$(command -v npm)"

if [[ -n $npm_path ]]; then
    info "Installing default npm dependencies"
    $npm_path --prefix "$plugin_dir" update --no-fund || echo 'Failed installing npm dependencies'
else
    error "npm could not be found locally!"
    echo "Please visit https://nodejs.org for instructions, then install via \`npm install\`"
fi

printf "\n${color_green}%s${color_reset}\n" "🎉 Your new plugin has been built successfully!"
