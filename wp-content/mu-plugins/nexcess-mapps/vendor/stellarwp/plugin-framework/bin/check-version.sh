#!/usr/bin/env bash
#
# Verify that a plugin's bootstrap file matches the given version.
#
# Generally there are two places a version is defined within StellarWP plugins:
#
# 1. In the docblock at the top of the file
# 2. As a `PLUGIN_VERSION` PHP constant in the plugin's namespace
#
# This script ensures those two values match each other and the provided version, if present.
#
# USAGE
#
#   check-version.sh [-d|--debug] [-v|--version=<version>]
#
# OPTIONS
#
#   -d, --debug                 Display debug information.
#   -v, --version=<version>     A version that all other detected versions should match.
#   -h, --help                  Show the help screen.

# Set up colors.
color_cyan="\033[0;36m"
color_green="\033[0;32m"
color_red="\033[0;31m"
color_reset="\033[0;0m"
color_yellow="\033[0;33m"
bold="$(tput bold)"
nobold="$(tput sgr0)"
underline="$(tput smul)"
nounderline="$(tput rmul)"

debug_mode=0
plugin_constant_version="<none>"
plugin_file=""
plugin_header_version="<none>"
script_name="$0"
target_version="<none>"

# Print the usage instructions.
function print_usage {
    cat <<EOT
Verify that a plugin's bootstrap file matches the given version.

Generally there are two places a version is defined within StellarWP plugins:

    1. In the docblock at the top of the file
    2. As a ${underline}PLUGIN_VERSION${nounderline} PHP constant in the plugin's namespace

This script ensures those two values match each other and the provided version, if present.

${bold}USAGE${nobold}

    ${script_name} -f|--file=<${underline}file${nounderline}> [-d|--debug] [-v|--version=<${underline}version${nounderline}>]

${bold}OPTIONS${nobold}

    -d, --debug                 Display debug information.
    -f, --file=<${underline}file${nounderline}>           The path to the main plugin file.
    -v, --version=<${underline}version${nounderline}>     A version that all other detected versions should match.
    -h, --help                  Show this help screen.

EOT
}

debug() {
    if [[ $debug_mode -eq 1 ]]; then
        printf "${color_cyan}%s:${color_reset} %s\n" "$1" "$2"
    fi
}

error() {
    if [[ $debug_mode -eq 1 ]]; then
        printf "\n${color_red}%s${color_reset}\n" "$1"
    fi
}

# Parse arguments
while [ $# -gt 0 ]; do
    case "$1" in
        -d|--debug)
            debug_mode=1
            shift
            ;;
        -f|--file)
            shift
            plugin_file="$1"
            shift
            ;;
        -h|--help)
            print_usage
            exit 0
            ;;
        -v|--version)
            shift
            target_version="$1"
            shift
            ;;
        *)
            shift
            ;;
    esac
done

# Don't proceed if we don't have a file to check
if [[ -z "$plugin_file" || ! -f "$plugin_file" ]]; then
    error "A valid plugin filepath must be specified!"
    printf "\n    %s --file=<${underline}file${nounderline}> [-d|--debug] [-v|--version=<${underline}version${nounderline}>]\n\n" "$script_name"
    exit 1;
fi

debug "Plugin file" "$plugin_file"
debug "Target version" "$target_version"

# Parse the version from the plugin header.
plugin_header_version=$(grep 'Version:' "$plugin_file" | awk -F':' '{print $2}' | tr -d ' ')
debug "Docblock version" "$plugin_header_version"

if [[ -z "$plugin_header_version" ]]; then
    error "Unable to find 'Version' docblock in ${plugin_file}"
    exit 1
fi

# Parse the version from the PLUGIN_VERSION constant.
plugin_constant_version="$(grep -Eo "PLUGIN_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]" "$plugin_file" | awk -F',' '{print $2}' | tr -d "\"' ")"
debug "Constant version" "$plugin_constant_version"

if [[ -z "$plugin_constant_version" ]]; then
    error "Unable to find PLUGIN_VERSION constant in ${plugin_file}"
    exit 1
fi

# Compare the header and constant versions.
if [[ "$plugin_header_version" != "$plugin_constant_version" ]]; then
    error "The plugin header and PLUGIN_VERSION constants do not match!"
    printf "${color_yellow}Header:${color_reset}   %s\n" "$plugin_header_version"
    printf "${color_yellow}Constant:${color_reset} %s\n" "$plugin_constant_version"
    exit 1
fi

# If we've been given a version, compare the versions that we just verified match.
if [[ -n "$target_version" && "$target_version" != "$plugin_header_version" ]]; then
    error "The plugin header and PLUGIN_VERSION constants do not match target version ${target_version}!"
    printf "${color_yellow}Target:${color_reset} %s\n" "$target_version"
    printf "${color_yellow}Actual:${color_reset} %s\n" "$plugin_header_version"
    exit 1
fi

printf "Successfully verified version ${color_green}%s${color_reset}\n" "${plugin_header_version}"
