#!/usr/bin/env bash
#
# Print the given input to STDOUT and/or STDERR, then exit with the given exit code.
#
# This script is designed to aid in testing console commands in environments that properly-escape
# commands before trying to run them.
#
# USAGE:
#
#   console-stub.sh [-c|--code=<code>] [-e|--error=<error>] [-o,--output=<output>]
#
# OPTIONS
#
#   -c, --code=<code>       The exit code for the command. Default is 0.
#   -e, --error=<error>     Output to print to STDERR. Default is empty.
#   -o, --output=<output>   Output to print to STDOUT. Default is empty.
#   -h, --help              Show the help screen.

# Print the usage instructions.
function print_usage {
    bold="$(tput bold)"
    nobold="$(tput sgr0)"
    underline="$(tput smul)"
    nounderline="$(tput rmul)"

    cat <<EOT
Print the given input to STDOUT and/or STDERR, then exit with the given exit code.

${bold}USAGE${nobold}

    [-c|--code=<${underline}code${nounderline}>] [-e|--error=<${underline}error${nounderline}>] [-o,--output=<${underline}output${nounderline}>]

${bold}OPTIONS${nobold}

    -c, --code=<${underline}code${nounderline}>         The exit code for the command. Default is 0.
    -e, --error=<${underline}error${nounderline}>       Output to print to STDERR. Default is empty.
    -o, --output=<${underline}output${nounderline}>     Output to print to STDOUT. Default is empty.
    -h, --help                Show this help screen.

EOT
}

exit_code=0
to_stdout=""
to_stderr=""

# Parse arguments
while [ $# -gt 0 ]; do
    case "$1" in
        -c|--code)
            shift
            exit_code="$1"
            shift
            ;;
        -e|--error)
            shift
            to_stderr="$1"
            shift
            ;;
        -h|--help)
            print_usage
            exit 0
            ;;
        -o|--output)
            shift
            to_stdout="$1"
            shift
            ;;
        *)
            shift
            ;;
    esac
done

if [[ -n "$to_stdout" ]]; then
    echo "$to_stdout"
fi

if [[ -n "$to_stderr" ]]; then
    echo "$to_stderr" >&2
fi

exit "$exit_code"
