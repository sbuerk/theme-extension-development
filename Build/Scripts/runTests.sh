#!/usr/bin/env bash

# ----------------------------------------------------------------------------------------------------------------------
# sbuerk/theme-extension-development test runner based on docker/podman.
# Adopted from TYPO3 Core Development and extension based additions.
# ----------------------------------------------------------------------------------------------------------------------

# The containers and the network of a run are removed on every way out of this script: its end,
# every "exit" below, and SIGINT, SIGTERM and SIGHUP. A database container is started detached
# ("run -d"), so no client process of it is left to take a signal - with SIGINT trapped alone, a
# run stopped by SIGTERM or SIGHUP (a timeout, a closed terminal) left it running for
# hours after its "functional-*" sibling was gone. SIGKILL cannot be trapped, it is left to the
# reaper started once the network exists.
#
# The trap is installed in CI as well. The core excludes it there for debugging a cancelled job
# on its own runners (review of https://review.typo3.org/c/Packages/TYPO3.CMS/+/85303); a GitHub
# hosted runner is discarded with its job, so nothing is lost here by removing the containers.
#
# The exit code is kept: EXIT only cleans up, the signals exit with 128 + their number - except
# SIGINT, which keeps the 2 it always had. The signals are ignored while the cleanup runs, so a
# second ctrl-c does not abandon it half way. bash runs a trap only once the command in the
# foreground has returned: a signal to the process group - ctrl-c, a closed terminal, a
# supervisor - stops the container as well and is handled at once, a signal to this script alone
# waits for the container to finish. See "docs/development/environment.md".
NETWORK=""
CLEANED_UP=0
trap 'cleanUp' EXIT
trap 'handleSignal INT 2' INT
trap 'handleSignal TERM 143' TERM
trap 'handleSignal HUP 129' HUP

handleSignal() {
    trap '' INT TERM HUP
    echo "runTests.sh SIG${1} signal emitted" >&2
    cleanUp
    exit "${2}"
}

printSummary() {
    cleanUp

    # Print summary
    echo "" >&2
    echo "###########################################################################" >&2
    echo "Result of ${TEST_SUITE}" >&2
    echo "Container runtime: ${CONTAINER_BIN}" >&2
    echo "Container suffix: ${SUFFIX}"
    if [[ ${IS_CORE_CI} -eq 1 ]]; then
        echo "Environment: CI" >&2
    else
        echo "Environment: local" >&2
    fi
    echo "PHP: ${PHP_VERSION}" >&2
    echo "TYPO3: ${CORE_VERSION}" >&2
    if [[ ${TEST_SUITE} =~ ^(functional)$ ]]; then
        case "${DBMS}" in
            mariadb|mysql|postgres)
                echo "DBMS: ${DBMS}  version ${DBMS_VERSION}  driver ${DATABASE_DRIVER}" >&2
                ;;
            sqlite)
                echo "DBMS: ${DBMS}" >&2
                ;;
        esac
    fi
    if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
        echo "SUCCESS" >&2
    else
        echo "FAILURE" >&2
    fi
    echo "###########################################################################" >&2
    echo "" >&2

    # Exit with code of test suite - This script return non-zero if the executed test failed.
    exit $SUITE_EXIT_CODE
}

waitForServer() {
    # Waits until a server started in a container of its own answers on its port, asked by its
    # container name from a container on the network of this run, and aborts the whole run when
    # it does not - the counterpart of "waitForDatabase()" below for a plain TCP server.
    #
    # The probe connects rather than resolving the name alone: a name that resolves belongs to a
    # container whose server may not listen yet. "fsockopen()" does both, and the PHP image is the
    # one the server itself runs in, so nothing is installed for the probe.
    local HOST=${1}
    local PORT=${2}
    local TESTCOMMAND="
        COUNT=0;
        until php -r 'exit(@fsockopen(\"${HOST}\", ${PORT}) ? 0 : 1);'; do
            if [ \"\${COUNT}\" -gt 30 ]; then
              echo \"The server \\\"${HOST}:${PORT}\\\" did not answer within 30 seconds. Aborting.\";
              exit 1;
            fi;
            sleep 1;
            COUNT=\$((COUNT + 1));
        done;
    "
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name wait-for-${SUFFIX} ${IMAGE_PHP} /bin/sh -c "${TESTCOMMAND}"
    if [[ $? -gt 0 ]]; then
        cleanUp
        exit 1
    fi
}

waitForDatabase() {
    # Waits until the database server answers a query, and aborts the whole run
    # when it does not.
    #
    # It asks for a query rather than probing the TCP port, because an open port
    # is not a ready server: the mysql image runs a temporary server while it
    # initialises its data directory.
    #
    # The probe runs the vendor's own client from the database image itself,
    # which is the only client guaranteed to speak the protocol of the version
    # under test and needs no extension compiled into the PHP image. MariaDB
    # renamed that client, so both names are tried - "mysql" is a deprecated
    # symlink in current MariaDB and absent from future ones, while "mariadb"
    # does not exist in the older images this repository still supports.
    #
    # The budget is 60 seconds rather than 10. Measured under docker with the
    # data directory on a tmpfs, "mysql:8.0" needs 12-13 seconds to initialise a
    # fresh data directory, about twice as long as under podman, and the
    # workflows select docker - so an 11 second budget aborted the functional
    # mysql suites at random. Waiting too long costs a slower run; waiting too
    # briefly costs a suite that fails for a reason unrelated to the code.
    local KIND=${1}
    local HOST=${2}
    local IMAGE=${3}
    local PROBE=""
    case ${KIND} in
        mariadb|mysql)
            # MYSQL_PWD rather than "-p", which warns about the password on the
            # command line once per probe iteration.
            PROBE="MYSQL_PWD=funcp sh -c 'mysql -h ${HOST} -u root -e \"SELECT 1\" || mariadb -h ${HOST} -u root -e \"SELECT 1\"' >/dev/null 2>&1"
            ;;
        postgres)
            PROBE="PGPASSWORD=funcp psql -h ${HOST} -U funcu -d funcu -c 'SELECT 1' >/dev/null 2>&1"
            ;;
        *)
            echo "waitForDatabase() does not know the DBMS \"${KIND}\"." >&2
            cleanUp
            exit 1
            ;;
    esac
    local TESTCOMMAND="
        COUNT=0;
        until ${PROBE}; do
            if [ \"\${COUNT}\" -gt 60 ]; then
              echo \"The ${KIND} server \\\"${HOST}\\\" did not answer a query within 60 seconds. Aborting.\";
              exit 1;
            fi;
            sleep 1;
            COUNT=\$((COUNT + 1));
        done;
    "
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name wait-for-${SUFFIX} ${IMAGE} /bin/sh -c "${TESTCOMMAND}"
    if [[ $? -gt 0 ]]; then
        # Not "kill -SIGINT -$$": that signals the process group "$$" leads,
        # and this script leads one only when an interactive shell started
        # it. Started by a CI step it is a plain child of the step's shell,
        # the kill failed with "No such process", the run carried on and the
        # test suite connected to a database that was not listening -
        # reporting dozens of "Connection refused" errors instead of the
        # readiness timeout that had actually happened.
        cleanUp
        exit 1
    fi
}

cleanUp() {
    # Removes every container attached to the network of this run, in whatever state - "-a", so
    # one that was created but never started, or has stopped, goes as well - and then the network.
    # Runs more than once on most paths - "printSummary" calls it, and the EXIT trap after it - and
    # on the early exits before the network exists, so it does its work once and only once there is
    # a network.
    if [[ ${CLEANED_UP} -eq 1 ]] || [[ -z "${NETWORK}" ]] || [[ -z "${CONTAINER_BIN}" ]]; then
        return 0
    fi
    ATTACHED_CONTAINERS=$(${CONTAINER_BIN} ps -a --filter network=${NETWORK} --format='{{.Names}}')
    for ATTACHED_CONTAINER in ${ATTACHED_CONTAINERS}; do
        ${CONTAINER_BIN} rm -f ${ATTACHED_CONTAINER} >/dev/null
    done
    ${CONTAINER_BIN} network rm -f ${NETWORK} >/dev/null
    CLEANED_UP=1
}

ensureImages() {
    # Makes sure every image given is present, pulling one that is not with a bounded retry, and
    # ends the run naming the image when that does not succeed.
    #
    # "run" pulls a missing image by itself, once, and gives up: a registry that did not answer in
    # time ("context deadline exceeded", exit code 125 of "docker run") failed a CI job before its
    # suite started, and a rerun of the same job passed. An image that is present is not pulled
    # again, so a local run needs no registry once its images are there; "-u" updates them.
    local IMAGE
    local ATTEMPT
    for IMAGE in "$@"; do
        if ${CONTAINER_BIN} image inspect "${IMAGE}" >/dev/null 2>&1; then
            continue
        fi
        for ATTEMPT in 1 2 3; do
            if ${CONTAINER_BIN} pull "${IMAGE}" >&2; then
                continue 2
            fi
            if [[ ${ATTEMPT} -lt 3 ]]; then
                echo "Pulling \"${IMAGE}\" failed, attempt ${ATTEMPT} of 3. Retrying in $((ATTEMPT * 10)) seconds." >&2
                sleep $((ATTEMPT * 10))
            fi
        done
        echo "The image \"${IMAGE}\" is not present and could not be pulled in 3 attempts. Nothing was run." >&2
        SUITE_EXIT_CODE=1
        printSummary
    done
}

handleDbmsOptions() {
    # -a, -d, -i depend on each other. Validate input combinations and set defaults.
    case ${DBMS} in
        mariadb)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="10.4"
            if ! [[ ${DBMS_VERSION} =~ ^(10.4|10.5|10.6|10.7|10.8|10.9|10.10|10.11|11.0|11.1|11.2|11.3|11.4|11.5|11.6|11.7|11.8)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        mysql)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="8.0"
            if ! [[ ${DBMS_VERSION} =~ ^(8.0|8.1|8.2|8.3|8.4)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        postgres)
            if [ -n "${DATABASE_DRIVER}" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="10"
            if ! [[ ${DBMS_VERSION} =~ ^(10|11|12|13|14|15|16|17|18)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        sqlite)
            if [ -n "${DATABASE_DRIVER}" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            if [ -n "${DBMS_VERSION}" ]; then
                echo "Invalid combination -d ${DBMS} -i ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        *)
            echo "Invalid option -d ${DBMS}" >&2
            echo >&2
            echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
            exit 1
            ;;
    esac
}

cleanCacheFiles() {
    echo -n "Clean caches ... "
    rm -rf \
        .cache \
        .php-cs-fixer.cache
    echo "done"
}

cleanTestFiles() {
    # test related
    echo -n "Clean test related files ... "
    rm -rf \
        .Build/Web/typo3temp/var/tests/ \
        .Build/functional-runs/ \
        .Build/acceptance/ \
        .Build/visual/ \
        Tests/Acceptance/node_modules/
    echo "done"
}

cleanRenderedDocumentationFiles() {
    echo -n "Clean rendered documentation files ... "
    rm -rf \
        Documentation-GENERATED-temp
    echo "done"
}

loadHelp() {
    # Load help text into $HELP
    read -r -d '' HELP <<EOF
sbuerk/theme-extension-development test runner. Execute unit, functional and other test suites
in a container based test environment. Handles execution of single test files,
sending xdebug information to a local IDE and more.

Usage: $0 [options] [file]

Options:
    -s <...>
        Specifies which test suite to run
            - acceptance: Playwright tests against a development instance built from nothing,
              needs no composerUpdate, "-- <arguments>" go to "playwright test"
            - buildCss: compile Resources/Private/Scss into Resources/Public/Css
            - buildIcons: copy the solid icons, the allowlisted brand logos,
              LICENSE.txt and categories.yml of the pinned Font Awesome Free into
              Resources/Public/Icons/FontAwesome
            - cgl: test and fix all php files
            - checkBom: check UTF-8 files do not contain BOM
            - checkCssBuild: check the committed CSS matches its SCSS sources
            - checkIconsBuild: check Resources/Public/Icons/FontAwesome is exactly what buildIcons
              writes from the pinned Font Awesome Free package, plus ATTRIBUTION.txt
            - checkExceptionCodes: check for duplicate and missing exception codes
            - checkMarkdownTables: check markdown tables are formatted, "-- --fix" to format them
            - checkTestMethodsPrefix: check test methods do not start with "test"
            - clean: clean up build, cache, rendered documentation and testing related files
            - cleanCache: clean up cache related files and folders
            - cleanRenderedDocumentation: clean up rendered documentation (Documentation-GENERATED-temp)
            - cleanTests: clean up test related files and folders
            - composer: "composer" with all remaining arguments dispatched
            - composerInstall: "composer install"
            - composerUpdate: "composer update", handy if host has no PHP
            - composerValidate: "composer validate --strict" of the root composer.json
            - functional: PHP functional tests
            - lintPhp: PHP linting
            - npm: "npm" with all remaining arguments dispatched
            - phpstan: phpstan analyze
            - phpstanGenerateBaseline: regenerate phpstan baseline, handy after phpstan updates
            - recordFunctionalTestTimes: write "Build/phpunit/FunctionalTestTimes-<dbms>.json" of "-d"
              from the JUnit logs given after "--", the durations "-j" balances its chunks by
            - renderDocumentation: render the extension documentation into Documentation-GENERATED-temp
            - setVersion: apply a version across the repository, "-- <version> <type>"
            - unit (default): PHP unit tests
            - unitRandom: PHP unit tests in random order, "-o <number>" to use a specific seed
            - visual: screenshots and axe of the styleguide partials, rendered without TYPO3,
              needs composerUpdate, "-- <arguments>" go to "playwright test",
              "-- --update-snapshots" rewrites the baselines - only after looking at the diff
            - watchCss: compile the SCSS and re-compile it on every change
            - watchDocumentation: render the documentation and re-render it on every change,
              served on port 1337, a different port as first argument

    -b <docker|podman>
        Container environment:
            - docker
            - podman

        If not specified, podman will be used if available. Otherwise, docker is used.

    -a <mysqli|pdo_mysql>
        Only with -s functional
        Specifies to use another driver, following combinations are available:
            - mysql
                - mysqli (default)
                - pdo_mysql
            - mariadb
                - mysqli (default)
                - pdo_mysql

    -d <sqlite|mariadb|mysql|postgres>
        Only with -s functional
        Specifies on which DBMS tests are performed
            - sqlite: (default): use sqlite
            - mariadb: use mariadb
            - mysql: use MySQL
            - postgres: use postgres

    -i version
        Specify a specific database version
        With "-d mariadb":
            - 10.4   short-term, maintained until 2024-06-18 (default)
            - 10.5   short-term, maintained until 2025-06-24
            - 10.6   long-term, maintained until 2026-06
            - 10.7   short-term, no longer maintained
            - 10.8   short-term, maintained until 2023-05
            - 10.9   short-term, maintained until 2023-08
            - 10.10  short-term, maintained until 2023-11
            - 10.11  long-term, maintained until 2028-02
            - 11.0   development series
            - 11.1   short-term development series
            - 11.2   short-term development series, maintained until 2024-11
            - 11.3   short-term development series, rolling release
            - 11.4   long-term, maintained until 2029-05
            - 11.5   short-term development series, maintained until 2024-11
            - 11.6   short-term development series, maintained until 2025-02
            - 11.7   short-term development series, maintained until 2025-05
            - 11.8   long-term, maintained until 2030-06
        With "-d mysql":
            - 8.0   maintained until 2026-04 (default) LTS
            - 8.1   unmaintained since 2023-10
            - 8.2   unmaintained since 2024-01
            - 8.3   maintained until 2024-04
            - 8.4   maintained until 2032-04 LTS
        With "-d postgres":
            - 10    unmaintained since 2022-11-10 (default)
            - 11    maintained until 2023-11-09
            - 12    maintained until 2024-11-14
            - 13    maintained until 2025-11-13
            - 14    maintained until 2026-11-12
            - 15    maintained until 2027-11-11
            - 16    maintained until 2028-11-09
            - 17    maintained until 2029-11-08
            - 18    maintained until 2030-11-14

    -t <12|13>
        Specifies the TYPO3 CORE Version to be used
            - 12: (default) use TYPO3 v12
            - 13: use TYPO3 v13
        The default is the lowest supported version, because the gates that do
        not depend on a core version are run against it. Everything downstream
        is derived from this option - the "typo3/minimal" requirement of
        composerUpdate, the "Build/phpstan/Core<version>/" configuration and the
        "--exclude-group not-core-<version>" of the test suites.
        Note that the dependencies must be installed for the selected core
        version first, which is done by the composerUpdate suite:
            ./Build/Scripts/runTests.sh -t 12 -s composerUpdate
        Gates executed with a different core version installed than selected
        report false positives.

    -p <8.1|8.2|8.3|8.4>
        Specifies the PHP minor version to be used
            - 8.1: use PHP 8.1 - TYPO3 v12 only
            - 8.2: use PHP 8.2 (default)
            - 8.3: use PHP 8.3
            - 8.4: use PHP 8.4
        "-p 8.1" is only meaningful together with "-t 12": "typo3/cms-core"
        13.4 requires PHP "^8.2", so composerUpdate refuses the combination
        rather than installing something the version does not support. The
        default is the lowest version valid for both core versions.

    -x
        Only with -s functional|unit|unitRandom
        Send information to host instance for test or system under test break points. This is especially
        useful if a local PhpStorm instance is listening on default xdebug port 9003. A different port
        can be selected with -y

    -y <port>
        Send xdebug information to a different port than default 9003 if an IDE like PhpStorm
        is not listening on default port.

    -j <number|auto>
        Only with -s functional
        Split the functional suite into <number> chunks that take about the same time and
        run them in parallel, each with its own container network, database container and
        PHP container. A test class is never split, so the slowest class is the floor of a
        run, and there are never more chunks than test classes. "auto" picks the number: at
        most half the CPU cores and no more than GB of memory available, and of those the
        fewest that leave every chunk but the slowest well below the floor. The run fails
        unless the chunks together executed exactly the tests phpunit listed for it. The
        chunks are balanced by the recorded durations in
        "Build/phpunit/FunctionalTestTimes-<dbms>.json" when that file exists, and by the
        number of tests otherwise. The output of the chunks is streamed while they run,
        every line prefixed with its chunk. The files of a run - the list, the chunk
        configurations, and per chunk its output, its JUnit log and its PHPUnit event log -
        are kept in ".Build/functional-runs/<suffix>/"; the JUnit logs are the input of
        "-s recordFunctionalTestTimes". A test path or phpunit options after "--" apply to
        the whole run, before it is split - except "--filter", "--group", "--exclude-group",
        "--covers" and "--uses", which PHPUnit 10.5 ignores when it lists the tests, and which
        "-j" therefore refuses. Without "-j", or with "-j 1", the suite runs in one PHP container
        as it always did.

    -c <chunk>/<number-of-chunks>
        Internal, set by "-j" for each chunk it starts. Not meant to be given by hand.

    -o <number>
        Only with -s unitRandom
        Set specific random seed to replay a random run in this order again. The phpunit randomizer
        outputs the used seed at the end. Use that number to replay the unit tests in that order.

    -n
        Only with -s cgl
        Activate dry-run in CGL check that does not actively change files and only prints broken ones.

    -u
        Update existing typo3/core-testing-*:latest container images and remove dangling local volumes.
        New images are published once in a while and only the latest ones are supported by core testing.
        Use this if weird test errors occur. Also removes obsolete image versions of typo3/core-testing-*.

    -h
        Show this help.

Examples:
    # Install dependencies for TYPO3 v12 on PHP 8.2 (default matrix)
    ./Build/Scripts/runTests.sh -t 12 -p 8.2 -s composerUpdate

    # Run all unit tests using PHP 8.2
    ./Build/Scripts/runTests.sh -s unit
    ./Build/Scripts/runTests.sh -s unit -p 8.2

    # Run all unit tests and enable xdebug (have a PhpStorm listening on port 9003!)
    ./Build/Scripts/runTests.sh -s unit -x

    # Run a single functional test class on sqlite, phpunit arguments after "--"
    ./Build/Scripts/runTests.sh -s functional -d sqlite -- --filter DummyTest

    # Run functional tests on postgres 10
    ./Build/Scripts/runTests.sh -s functional -d postgres -i 10

    # Run functional tests on MariaDB 10.6 in four parallel chunks
    ./Build/Scripts/runTests.sh -s functional -d mariadb -i 10.6 -j 4

    # Run functional tests on SQLite in as many chunks as are worth it on this machine
    ./Build/Scripts/runTests.sh -s functional -d sqlite -j auto

    # Check the coding guidelines without changing files, as CI does
    ./Build/Scripts/runTests.sh -s cgl -n

    # Write documentation with a browser preview reloading on every save
    ./Build/Scripts/runTests.sh -s watchDocumentation
    ./Build/Scripts/runTests.sh -s watchDocumentation 4711

    # Apply a version across the repository, without needing PHP on the host
    ./Build/Scripts/runTests.sh -s setVersion -- 1.2.0 release --dry-run
EOF
}

# Test if docker exists, else exit out with error
if ! type "docker" >/dev/null 2>&1 && ! type "podman" >/dev/null 2>&1; then
    echo "This script relies on docker or podman. Please install" >&2
    exit 1
fi

# Option defaults
TEST_SUITE="help"
CORE_VERSION="12"
DBMS="sqlite"
PHP_VERSION="8.2"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
PHPUNIT_RANDOM=""
CGLCHECK_DRY_RUN=0
DATABASE_DRIVER=""
DBMS_VERSION=""
CONTAINER_BIN=""
CONTAINER_HOST="host.docker.internal"
DOCUMENTATION_PORT="1337"
FUNCTIONAL_CHUNK=""
FUNCTIONAL_PARALLEL=1

# Kept for "-j", which starts this script once per chunk with the same arguments.
ORIGINAL_ARGUMENTS=("$@")

# Option parsing updates above default vars
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=()
# Simple option parsing based on getopts (! not getopt)
while getopts "a:b:c:j:s:d:i:p:t:xy:o:nhu" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        c)
            FUNCTIONAL_CHUNK=${OPTARG}
            if ! [[ ${FUNCTIONAL_CHUNK} =~ ^[1-9][0-9]*/[1-9][0-9]*$ ]]; then
                INVALID_OPTIONS+=("c ${OPTARG}")
            fi
            ;;
        j)
            FUNCTIONAL_PARALLEL=${OPTARG}
            if ! [[ ${FUNCTIONAL_PARALLEL} =~ ^([1-9][0-9]*|auto)$ ]]; then
                INVALID_OPTIONS+=("j ${OPTARG}")
            fi
            ;;
        b)
            if ! [[ ${OPTARG} =~ ^(docker|podman)$ ]]; then
                INVALID_OPTIONS+=("${OPTARG}")
            fi
            CONTAINER_BIN=${OPTARG}
            ;;
        a)
            DATABASE_DRIVER=${OPTARG}
            ;;
        d)
            DBMS=${OPTARG}
            ;;
        i)
            DBMS_VERSION=${OPTARG}
            ;;
        p)
            PHP_VERSION=${OPTARG}
            if ! [[ ${PHP_VERSION} =~ ^(8.1|8.2|8.3|8.4)$ ]]; then
                INVALID_OPTIONS+=("p ${OPTARG}")
            fi
            ;;
        t)
            CORE_VERSION=${OPTARG}
            if ! [[ ${CORE_VERSION} =~ ^(12|13)$ ]]; then
                INVALID_OPTIONS+=("t ${OPTARG}")
            fi
            ;;
        x)
            PHP_XDEBUG_ON=1
            ;;
        y)
            PHP_XDEBUG_PORT=${OPTARG}
            ;;
        o)
            PHPUNIT_RANDOM="--random-order-seed=${OPTARG}"
            ;;
        n)
            CGLCHECK_DRY_RUN=1
            ;;
        h)
            loadHelp
            echo "${HELP}"
            exit 0
            ;;
        u)
            TEST_SUITE=update
            ;;
        \?)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
        :)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
    esac
done

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo "-"${I} >&2
    done
    echo >&2
    echo "call \"Build/Scripts/runTests.sh -h\" to display help and valid options"
    exit 1
fi

handleDbmsOptions

COMPOSER_ROOT_VERSION="1.0.0-dev"
CONTAINER_INTERACTIVE="-it --init"
HOST_UID=$(id -u)
HOST_GID=$(id -g)
# Additional container arguments a caller may inject, for instance a CI runner that has to pass
# "--userns" or a network mode. Declared so the expansions below are defined without a caller.
CI_PARAMS="${CI_PARAMS:-}"
USERSET=""
if [ $(uname) != "Darwin" ]; then
    USERSET="--user $HOST_UID"
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called, then go up two dirs.
THIS_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null && pwd)"
cd "$THIS_SCRIPT_DIR" || exit 1
cd ../../ || exit 1
ROOT_DIR="${PWD}"

# Create .cache dir: composer need this.
mkdir -p .cache/composer
mkdir -p .Build/Web/typo3temp/var/tests

IS_CORE_CI=0
if [ "${CI}" == "true" ]; then
    # ENV var "CI" is set by the pipeline. We use it here to distinct 'local' and 'CI' environment.
    IS_CORE_CI=1
    CONTAINER_INTERACTIVE=""
elif [ ! -t 0 ] || [ ! -t 1 ]; then
    # If stdin or stdout is not a TTY (a wrapper script, a pipe, an IDE run configuration or any
    # other non-interactive shell), drop the interactive "-it" flags to avoid the podman warning
    # "The input device is not a TTY.", the corresponding docker failure, and TTY control
    # characters in redirected output. "--init" is kept so the PID 1 init process still forwards
    # signals such as ctrl-c to the test process.
    CONTAINER_INTERACTIVE="--init"
fi

# determine default container binary to use: 1. podman 2. docker
if [[ -z "${CONTAINER_BIN}" ]]; then
    if type "podman" >/dev/null 2>&1; then
        CONTAINER_BIN="podman"
    elif type "docker" >/dev/null 2>&1; then
        CONTAINER_BIN="docker"
    fi
fi

IMAGE_PHP="ghcr.io/typo3/core-testing-$(echo "php${PHP_VERSION}" | sed -e 's/\.//'):latest"
IMAGE_DOCS="ghcr.io/typo3-documentation/render-guides:latest"
# The PHP testing images ship no node, so the frontend asset build uses the
# TYPO3 node image instead. It is picked up by "-u" like every other image,
# because that globs "ghcr.io/typo3/core-testing-*".
IMAGE_NODEJS="ghcr.io/typo3/core-testing-nodejs24:latest"
# The browsers of the acceptance suite. Pinned to the exact "@playwright/test"
# version of "Tests/Acceptance/package.json": the image carries the browser
# builds of one Playwright release, and a different library version refuses to
# start them. Change both together, and rebaseline "-s visual"
# ("-- --update-snapshots") in the same commit: the browser build decides the
# pixels of every baseline.
IMAGE_PLAYWRIGHT="mcr.microsoft.com/playwright:v1.63.0-noble"
IMAGE_MARIADB="docker.io/mariadb:${DBMS_VERSION}"
IMAGE_MYSQL="docker.io/mysql:${DBMS_VERSION}"
IMAGE_POSTGRES="docker.io/postgres:${DBMS_VERSION}-alpine"
# PostgreSQL 18 moved "PGDATA" from "/var/lib/postgresql/data" to
# "/var/lib/postgresql/<major>/docker" and refuses to start when a mount point sits at the old
# location. Mounting one level above at "/var/lib/postgresql" is the documented recommendation
# for that case, while earlier versions expect the mount at the data directory itself.
POSTGRES_TMPFS_MOUNT="/var/lib/postgresql/data"
if [ "${DBMS}" = "postgres" ] && [ "${DBMS_VERSION}" -ge 18 ]; then
    POSTGRES_TMPFS_MOUNT="/var/lib/postgresql"
fi

# Set $1 to first mass argument, this is the optional test file or test directory to execute
shift $((OPTIND - 1))

SUFFIX=$(echo $RANDOM)
# A chunk of "-j" is named after the run that started it, with its chunk number appended:
# "<suffix of the run>-<chunk>". Its container network, its containers and everything else
# named by the suffix are then unique among the chunks by construction, and show which run
# they belong to. A random value of its own per chunk would not be unique: the chunks start
# in the same instant, "$RANDOM" has 15 bits, and two chunks drawing the same value collide
# on the network and the database container, each removing what the other one uses. That
# happened in the CI of fgtclb/academic-extensions, whose "-j" this one is modelled on.
if [[ -n "${FUNCTIONAL_CHUNK}" ]]; then
    if [[ -z "${FUNCTIONAL_RUN_SUFFIX:-}" || -z "${FUNCTIONAL_RUN_DIRECTORY:-}" || -z "${FUNCTIONAL_PARENT_PID:-}" ]]; then
        echo "-c ${FUNCTIONAL_CHUNK} is set by -j for the chunks it starts, and not meant to be given by hand." >&2
        exit 1
    fi
    SUFFIX="${FUNCTIONAL_RUN_SUFFIX}-${FUNCTIONAL_CHUNK%%/*}"
fi
NETWORK="theme-extension-development-${SUFFIX}"
# A network of that name exists when the suffix collides with a run still going on. Joining it
# would put this run's containers beside that run's, and the cleanup of either would remove the
# containers of both. NETWORK is cleared first, so the cleanup on exit leaves that network alone.
${CONTAINER_BIN} network create ${NETWORK} >/dev/null || {
    echo "The container network \"${NETWORK}\" could not be created, it may belong to another run. Nothing was run." >&2
    NETWORK=""
    exit 1
}

if [ "${CONTAINER_BIN}" == "docker" ]; then
    # docker needs the add-host for xdebug remote debugging. podman has host.container.internal built in
    CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} --rm --network ${NETWORK} --add-host ${CONTAINER_HOST}:host-gateway ${USERSET} -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR}"
    CONTAINER_SIMPLE_PARAMS="${CONTAINER_INTERACTIVE} --rm --network ${NETWORK} --add-host ${CONTAINER_HOST}:host-gateway ${USERSET} -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR}"
    DOCUMENTATION_COMMON_PARAMS="${CONTAINER_INTERACTIVE} --rm ${USERSET} -v ${ROOT_DIR}:/project"
    # docker creates the tmpfs owned by root, which the container user - "--user" above - may not
    # be able to write to, and SQLite then fails with "unable to open database file". podman maps
    # the container user to the host user and needs no ownership here.
    #
    # Ownership and mode are both set, because they fail in different environments. A probe inside
    # a container on a GitHub hosted runner showed the mount as "root:root" mode 0755 with the
    # container user at "uid=1001 gid=0" - the group is 0 because "--user" above passes no group -
    # so neither the owner nor the group bits applied. Locally the same mount comes up 0775, which
    # is why setting the owner alone was enough there and not on the runner.
    TMPFS_MOUNT_OPTIONS="rw,noexec,nosuid,uid=${HOST_UID},gid=${HOST_GID},mode=1777"
else
    # podman
    CONTAINER_HOST="host.containers.internal"
    TMPFS_MOUNT_OPTIONS="rw,noexec,nosuid"
    if [ $( uname ) = "Linux" ]; then
        CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} ${CI_PARAMS} --rm --network ${NETWORK} -v ${ROOT_DIR}:${ROOT_DIR}:Z -w ${ROOT_DIR}"
        CONTAINER_SIMPLE_PARAMS="${CONTAINER_INTERACTIVE} ${CI_PARAMS} --rm -v ${ROOT_DIR}:${ROOT_DIR}:Z -w ${ROOT_DIR}"
        DOCUMENTATION_COMMON_PARAMS="${CONTAINER_INTERACTIVE} ${CI_PARAMS} --rm -v ${ROOT_DIR}:${ROOT_DIR}:Z -v ${ROOT_DIR}:/project"
    else
        CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} ${CI_PARAMS} --rm --network ${NETWORK} -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR}"
        CONTAINER_SIMPLE_PARAMS="${CONTAINER_INTERACTIVE} ${CI_PARAMS} --rm -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR}"
        DOCUMENTATION_COMMON_PARAMS="${CONTAINER_INTERACTIVE} ${CI_PARAMS} --rm -v ${ROOT_DIR}:${ROOT_DIR} -v ${ROOT_DIR}:/project"
    fi
fi

# The traps at the top do not see SIGKILL, and a SIGTERM followed by SIGKILL can end this script
# before its trap is through: a supervisor may send SIGKILL shortly after SIGTERM, as "timeout -k"
# does, and the GitHub runner ends a step it cancels with SIGINT, SIGTERM and then SIGKILL. So a
# reaper waits for this script to end and removes what is left of the run; after a run that
# removed everything itself it finds nothing. It looks once a second and removes the containers
# in one call. It runs in a session of its own, out of reach of any signal to the process group
# of this script. Without "setsid" (macOS) it shares that process group, ignoring the signals
# that end a run, and a SIGKILL to the group ends it along with the run.
#
# A chunk of "-j" is a run of its own, with its own network and reaper. That reaper also acts
# when the run that started the chunk is gone: that run, terminated or killed alone, takes none
# of its chunks with it, and they would run on to the end, each with a database container. With
# its containers removed, the chunk ends as well.
REAPER_WATCHED_PID=$$
[[ -n "${FUNCTIONAL_CHUNK}" ]] && REAPER_WATCHED_PID=${FUNCTIONAL_PARENT_PID}
REAPER_SESSION=""
type setsid >/dev/null 2>&1 && REAPER_SESSION="setsid"
${REAPER_SESSION} /bin/sh -c '
    trap "" INT HUP TERM
    while kill -0 "$1" 2>/dev/null && kill -0 "$4" 2>/dev/null; do sleep 1; done
    CONTAINERS=$("$2" ps -a --filter "network=$3" --format "{{.Names}}")
    [ -n "${CONTAINERS}" ] && "$2" rm -f ${CONTAINERS}
    "$2" network rm -f "$3"
' reaper "$$" "${CONTAINER_BIN}" "${NETWORK}" "${REAPER_WATCHED_PID}" </dev/null >/dev/null 2>&1 &

if [ ${PHP_XDEBUG_ON} -eq 0 ]; then
    XDEBUG_MODE="-e XDEBUG_MODE=off"
    XDEBUG_CONFIG=" "
else
    XDEBUG_MODE="-e XDEBUG_MODE=debug -e XDEBUG_TRIGGER=foo"
    XDEBUG_CONFIG="client_port=${PHP_XDEBUG_PORT} client_host=${CONTAINER_HOST}"
fi

# The images of the suite, before its first "run", see "ensureImages()". A suite missing here
# still works: "run" pulls its image, only without the retry.
case ${TEST_SUITE} in
    acceptance|visual)
        ensureImages "${IMAGE_PHP}" "${IMAGE_PLAYWRIGHT}"
        ;;
    buildCss|buildIcons|checkCssBuild|checkIconsBuild|npm|watchCss)
        ensureImages "${IMAGE_NODEJS}"
        ;;
    renderDocumentation|watchDocumentation)
        ensureImages "${IMAGE_DOCS}"
        ;;
    functional)
        case ${DBMS} in
            mariadb) ensureImages "${IMAGE_PHP}" "${IMAGE_MARIADB}" ;;
            mysql) ensureImages "${IMAGE_PHP}" "${IMAGE_MYSQL}" ;;
            postgres) ensureImages "${IMAGE_PHP}" "${IMAGE_POSTGRES}" ;;
            *) ensureImages "${IMAGE_PHP}" ;;
        esac
        ;;
    cgl|checkBom|checkExceptionCodes|checkMarkdownTables|checkTestMethodsPrefix|composer|composerInstall|composerUpdate|composerValidate|lintPhp|phpstan|phpstanGenerateBaseline|recordFunctionalTestTimes|setVersion|unit|unitRandom)
        ensureImages "${IMAGE_PHP}"
        ;;
esac

# Suite execution
case ${TEST_SUITE} in
    acceptance)
        # A throwaway development instance of the core version "-t" selects, built below
        # ".Build/acceptance/" from the committed "instance-core-<version>/" - its composer.json,
        # its site configurations and its "additional.php" - by the same "composer system:setup"
        # a DDEV instance runs on its first start. The committed instances are not touched.
        #
        # ".Build/acceptance/theme" and ".Build/acceptance/packages-dev" recreate the two paths an
        # instance resolves one level up, the way the repository root provides them for
        # "instance-core-*/". The instance is served by the PHP built-in server in a container of
        # its own and tested by Playwright in a third one, on the network of this run.
        #
        # Arguments after "--" go to "playwright test": "-- --grep login".
        # See "docs/testing/acceptance-tests.md".
        ACCEPTANCE_ROOT=".Build/acceptance"
        ACCEPTANCE_INSTANCE="${ACCEPTANCE_ROOT}/instance"
        if [ ! -d "instance-core-${CORE_VERSION}" ]; then
            echo "There is no development instance \"instance-core-${CORE_VERSION}\"." >&2
            SUITE_EXIT_CODE=1
            printSummary
        fi
        rm -rf "${ACCEPTANCE_ROOT}"
        mkdir -p "${ACCEPTANCE_INSTANCE}/config/system" "${ACCEPTANCE_ROOT}/home"
        ln -s ../.. "${ACCEPTANCE_ROOT}/theme"
        ln -s ../../packages-dev "${ACCEPTANCE_ROOT}/packages-dev"
        cp "instance-core-${CORE_VERSION}/composer.json" "${ACCEPTANCE_INSTANCE}/"
        cp -R "instance-core-${CORE_VERSION}/config/sites" "${ACCEPTANCE_INSTANCE}/config/"
        cp "instance-core-${CORE_VERSION}/config/system/additional.php" "${ACCEPTANCE_INSTANCE}/config/system/"

        COMMAND="composer install --no-progress --no-interaction && composer system:setup"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name acceptance-setup-${SUFFIX} -w "${ROOT_DIR}/${ACCEPTANCE_INSTANCE}" -e COMPOSER_CACHE_DIR="${ROOT_DIR}/.cache/composer" ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
            # One PHP worker: the instance is SQLite, which allows one writer, and parallel
            # requests of one backend page were enough to answer "database is locked". The
            # router of the core version sends every path which is not a file to a front
            # controller, as a web server rewrite does - which one differs between v12 and v13,
            # see "Tests/Acceptance/Core<version>/router.php". Its output goes to the log of
            # the instance, which a failed run keeps.
            ${CONTAINER_BIN} run -d ${CONTAINER_COMMON_PARAMS} --name acceptance-web-${SUFFIX} -w "${ROOT_DIR}/${ACCEPTANCE_INSTANCE}" ${IMAGE_PHP} /bin/sh -c "exec php -S 0.0.0.0:8000 -t public ${ROOT_DIR}/Tests/Acceptance/Core${CORE_VERSION}/router.php > var/log/php-server.log 2>&1" >/dev/null
            SUITE_EXIT_CODE=$?
            # The browser reaches the instance by a name that no DNS server knows,
            # "acceptance-instance", pinned to the address of the server container in "/etc/hosts"
            # of the Playwright container. Resolved by its container name, a page load failed now
            # and then with "NS_ERROR_UNKNOWN_HOST" or "net::ERR_NAME_NOT_RESOLVED" on a busy host,
            # and the log of the PHP server showed that the request never arrived: a lookup of the
            # network's DNS server that fails is a failed test, whenever it happens - the failure
            # "visual" pins "fixture-server" against. With the address pinned no lookup of the run
            # goes to DNS at all, and a pin that did not work would fail every test, not one in a
            # hundred. No wait before it, unlike "visual": the global setup polls the instance
            # through the pinned name for 60 seconds, which covers the start of the server, and
            # the address is known as soon as the container runs.
            # See "docs/testing/acceptance-tests.md".
            ACCEPTANCE_WEB_ADDRESS=""
            if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
                ACCEPTANCE_WEB_ADDRESS=$(${CONTAINER_BIN} inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' acceptance-web-${SUFFIX})
                if [[ -z "${ACCEPTANCE_WEB_ADDRESS}" ]]; then
                    echo "The address of the instance container \"acceptance-web-${SUFFIX}\" could not be read." >&2
                    SUITE_EXIT_CODE=1
                fi
            fi
            # Quoted one by one: the arguments run through "sh -c" as one string, and
            # "-- --grep 'logs in'" has to arrive as two arguments, not three.
            PLAYWRIGHT_ARGUMENTS=""
            [[ $# -gt 0 ]] && PLAYWRIGHT_ARGUMENTS=$(printf ' %q' "$@")
            COMMAND="cd Tests/Acceptance && npm ci --no-audit --no-fund && npx playwright test${PLAYWRIGHT_ARGUMENTS}"
            if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name acceptance-playwright-${SUFFIX} --add-host acceptance-instance:${ACCEPTANCE_WEB_ADDRESS} -e BASE_URL="http://acceptance-instance:8000" -e HOME="${ROOT_DIR}/${ACCEPTANCE_ROOT}/home" -e npm_config_cache="${ROOT_DIR}/.cache/npm" -e CI="${CI:-}" ${IMAGE_PLAYWRIGHT} /bin/sh -c "${COMMAND}"
                SUITE_EXIT_CODE=$?
            fi
        fi
        ;;
    buildCss)
        # The sass scripts in "package.json" pass "--no-charset": dart-sass
        # otherwise starts compressed output containing non-ASCII characters
        # with a BOM, which breaks the first selector when the file is inlined
        # or concatenated. Escaping the character does not help, sass writes
        # the literal. See "docs/development/frontend-assets.md".
        COMMAND="npm ci --no-audit --no-fund && npm run build"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name build-css-${SUFFIX} -e npm_config_cache=.cache/npm ${IMAGE_NODEJS} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    buildIcons)
        # Copies, from the "@fortawesome/fontawesome-free" version pinned in "package.json"
        # into "Resources/Public/Icons/FontAwesome/", unchanged: "svgs/solid/*.svg" into
        # "Solid/", the files of "svgs/brands/" named in "fontAwesomeBrands" of
        # "package.json" into "Brands/", and "LICENSE.txt" and "metadata/categories.yml".
        # A file there that the build does not write is left alone; "checkIconsBuild" names it.
        # "ATTRIBUTION.txt" beside them is maintained by hand. Committed like the
        # stylesheet, for the same reason: neither the composer dist archive nor the TER
        # artifact runs a build. See "docs/development/icons.md".
        COMMAND="npm ci --no-audit --no-fund && npm run build:icons"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name build-icons-${SUFFIX} -e npm_config_cache=.cache/npm ${IMAGE_NODEJS} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    cgl)
        # Active dry-run for cgl needs not "-n" but specific options
        CSFIXER_DRYRUN=""
        if [ "${CGLCHECK_DRY_RUN}" -eq 1 ]; then
            CSFIXER_DRYRUN="--dry-run --diff"
        fi
        COMMAND="php -dxdebug.mode=off .Build/bin/php-cs-fixer fix -v ${CSFIXER_DRYRUN} --config=Build/php-cs-fixer/config.php"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name cgl-${SUFFIX} ${IMAGE_PHP} ${COMMAND}
        SUITE_EXIT_CODE=$?
        ;;
    checkBom)
        COMMAND="Build/Scripts/checkUtf8Bom.sh"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name check-bom-${SUFFIX} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    checkCssBuild)
        # Compiles into ".Build/css-verify/" and diffs the result against the
        # committed stylesheet. Deliberately not the "git status" approach the
        # core uses: that writes the git index, and git in the node image
        # aborts with "detected dubious ownership" whenever the uid does not
        # match - which is exactly the "-b docker" path CI runs on.
        COMMAND="npm ci --no-audit --no-fund && npm run build:verify"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name check-css-build-${SUFFIX} -e npm_config_cache=.cache/npm ${IMAGE_NODEJS} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    checkIconsBuild)
        # "npm ci" installs the pinned package and checks it against the integrity hash
        # of "package-lock.json". Then the steps of "buildIcons" write a second copy into
        # ".Build/icons-verify/FontAwesome/", "ATTRIBUTION.txt" - the one file maintained by
        # hand - is copied beside it, failing when it is missing, and "diff -r" compares
        # that tree with "Resources/Public/Icons/FontAwesome/" in both directions and at
        # every depth: an edited, a missing and an extra file or directory each fail,
        # at the top of it as well as in "Solid/" and "Brands/". The content of
        # "ATTRIBUTION.txt" is not compared. Not a git based check, for the reason given at
        # "checkCssBuild". See "docs/development/icons.md".
        COMMAND="npm ci --no-audit --no-fund && npm run build:icons:verify"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name check-icons-build-${SUFFIX} -e npm_config_cache=.cache/npm ${IMAGE_NODEJS} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    checkExceptionCodes)
        COMMAND="Build/Scripts/duplicateExceptionCodeCheck.sh"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name check-exception-codes-${SUFFIX} ${IMAGE_PHP} /bin/bash -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    checkMarkdownTables)
        COMMAND="php -dxdebug.mode=off Build/Scripts/checkMarkdownTables.php $@"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name check-markdown-tables-${SUFFIX} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    checkTestMethodsPrefix)
        COMMAND="php -dxdebug.mode=off Build/Scripts/testMethodPrefixChecker.php"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name check-test-methods-prefix-${SUFFIX} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    clean)
        cleanCacheFiles
        cleanRenderedDocumentationFiles
        cleanTestFiles
        SUITE_EXIT_CODE=$?
        ;;
    cleanCache)
        cleanCacheFiles
        SUITE_EXIT_CODE=$?
        ;;
    cleanRenderedDocumentation)
        cleanRenderedDocumentationFiles
        SUITE_EXIT_CODE=$?
        ;;
    cleanTests)
        cleanTestFiles
        SUITE_EXIT_CODE=$?
        ;;
    composer)
        COMMAND=(composer "$@")
        ${CONTAINER_BIN} run ${CONTAINER_SIMPLE_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    composerInstall)
        ${CONTAINER_BIN} run ${CONTAINER_SIMPLE_PARAMS} --name composer-install-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} composer install
        SUITE_EXIT_CODE=$?
        ;;
    composerValidate)
        ${CONTAINER_BIN} run ${CONTAINER_SIMPLE_PARAMS} --name composer-validate-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} composer validate --strict --no-check-lock
        SUITE_EXIT_CODE=$?
        ;;
    composerUpdate)
        rm -rf .Build composer.lock composer.json.orig
        if [[ ${IS_CORE_CI} -eq 0 ]]; then
            # Locally the cache is dropped along with the dependency set, as it was while it still
            # lived below ".Build/". This is a precaution, not a fix for a reproduced defect:
            # switching between the core versions also switches the major version of
            # "typo3/class-alias-loader", a working copy accumulates months of such switches, and
            # an install resolving against a cache from the other major is a class of failure that
            # is tedious to recognize. One download of a dependency set that was about to be
            # replaced anyway is the cheaper side of that trade.
            #
            # In CI the trade goes the other way: every job starts from an empty checkout, installs
            # once and ends, so there is no earlier state to collide with, and the cache is restored
            # on purpose to avoid downloading the dependency set in every job.
            rm -rf .cache
            mkdir -p .cache/composer
        fi
        \cp -f composer.json composer.json.orig
        ${CONTAINER_BIN} run ${CONTAINER_SIMPLE_PARAMS} --name composer-require-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} composer require --dev --no-update "typo3/minimal":"^${CORE_VERSION}"
        SUITE_EXIT_CODE=$?
        if [[ "${SUITE_EXIT_CODE}" -eq 0 ]]; then
          ${CONTAINER_BIN} run ${CONTAINER_SIMPLE_PARAMS} --name composer-update-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} composer install
          SUITE_EXIT_CODE=$?
        fi
        [[ -f composer.json.orig ]] && \cp -f composer.json.orig composer.json
        ;;
    functional)
        PHPUNIT_CONFIG_FILE="Build/phpunit/FunctionalTests.xml"
        if [[ -z "${FUNCTIONAL_CHUNK}" && ( "${FUNCTIONAL_PARALLEL}" == "auto" || ${FUNCTIONAL_PARALLEL} -gt 1 ) ]]; then
            # "-j": list the tests of this run - with its group exclusions and any path given
            # after "--" - split the list into chunk configurations, and start
            # this script once per chunk, with "-c" and otherwise the same arguments. Every
            # chunk has a suffix of its own, and with it its own container network, database
            # container and PHP container. The instance directories below
            # "typo3temp/var/tests/" need no separation: the testing framework names each after
            # its test class, and a class is never split. The files of the run live in a
            # directory of its own, so two runs in one checkout do not overwrite each other's
            # chunk configurations and logs. See "docs/development/environment.md".
            #
            # Every chunk writes its own JUnit and event log. One given after "--" would be
            # written by every chunk to the same file, each overwriting the others.
            for ARGUMENT in "$@"; do
                if [[ "${ARGUMENT}" =~ ^--(log-junit|log-events-text|log-events-verbose-text)(=|$) ]]; then
                    echo "\"${ARGUMENT%%=*}\" cannot be combined with -j: every chunk would write the same file. The logs of each chunk are kept in .Build/functional-runs/<suffix>/." >&2
                    SUITE_EXIT_CODE=1
                    printSummary
                fi
            done
            FUNCTIONAL_RUN_DIRECTORY=".Build/functional-runs/${SUFFIX}"
            rm -rf "${FUNCTIONAL_RUN_DIRECTORY}"
            mkdir -p "${FUNCTIONAL_RUN_DIRECTORY}"
            FUNCTIONAL_TIMINGS="Build/phpunit/FunctionalTestTimes-${DBMS}.json"
            [[ -f "${FUNCTIONAL_TIMINGS}" ]] || FUNCTIONAL_TIMINGS=""
            # PHPUnit 10.5 writes the list before it applies any filter, and ignores
            # "--exclude-group", "--group" and "--filter" there with no more than a note
            # (Application::run() and ListTestsAsXmlCommand of phpunit/phpunit 10.5); "--covers"
            # and "--uses" are filters of the run as well (TestSuiteFilterProcessor), which the
            # list does not see either. The group exclusions of this run are therefore applied to
            # the list by the split and by the count check, from the "groups" every listed test
            # carries. A filter given after "--" cannot be applied that way, and every chunk would
            # run a different set of tests than the list holds: "-j" refuses them. A path after
            # "--" is kept, the list is built from it.
            # @todo PHPUnit 11 applies the filters to the list; drop the refusal and pass the
            #       exclusions to "--list-tests-xml" together with the PHPUnit major.
            for ARGUMENT in "$@"; do
                if [[ "${ARGUMENT}" =~ ^--(filter|group|exclude-group|covers|uses)(=|$) ]]; then
                    echo "-j cannot be combined with ${ARGUMENT%%=*}: PHPUnit 10.5 ignores it when it lists the tests to split. Run without -j, or name a test file or directory after \"--\"." >&2
                    SUITE_EXIT_CODE=1
                    printSummary
                fi
            done
            FUNCTIONAL_EXCLUDED_GROUPS="not-${DBMS},not-core-${CORE_VERSION}"
            FUNCTIONAL_SPLIT_OPTIONS=""
            if [[ "${FUNCTIONAL_PARALLEL}" == "auto" ]]; then
                # "-j auto": the most chunks the machine carries, and the split writes the fewest of
                # those that leave every chunk but the heaviest well below the floor - see
                # "splitFunctionalTests.php". Half the CPU cores, because a chunk of a DBMS run
                # keeps a database container busy next to its PHP process. No more chunks than GB
                # of available memory, which leaves room: measured here, a MySQL 8.0 chunk used
                # 0.55 to 0.67 GB - the database 410 to 450 MB, the PHP process 130 to 220 MB -
                # and a MariaDB 10.6 chunk some 0.4 GB. Linux reports the available memory in
                # "/proc/meminfo"; elsewhere only the cores count.
                CPU_CORES=$(nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo 2)
                FUNCTIONAL_PARALLEL=$(( CPU_CORES / 2 ))
                MEMORY_AVAILABLE_GB=$(awk '/^MemAvailable:/ { print int($2 / 1048576) }' /proc/meminfo 2>/dev/null)
                if [[ -n "${MEMORY_AVAILABLE_GB}" && ${MEMORY_AVAILABLE_GB} -lt ${FUNCTIONAL_PARALLEL} ]]; then
                    FUNCTIONAL_PARALLEL=${MEMORY_AVAILABLE_GB}
                fi
                [[ ${FUNCTIONAL_PARALLEL} -lt 1 ]] && FUNCTIONAL_PARALLEL=1
                echo "-j auto: at most ${FUNCTIONAL_PARALLEL} chunks (${CPU_CORES} CPU cores, ${MEMORY_AVAILABLE_GB:-unknown} GB memory available)"
                FUNCTIONAL_SPLIT_OPTIONS="--auto"
            fi
            ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-list-${SUFFIX} -e XDEBUG_MODE=off ${IMAGE_PHP} \
                .Build/bin/phpunit -c ${PHPUNIT_CONFIG_FILE} \
                --list-tests-xml "${FUNCTIONAL_RUN_DIRECTORY}/tests.xml" "$@" > "${FUNCTIONAL_RUN_DIRECTORY}/list.log" 2>&1
            SUITE_EXIT_CODE=$?
            [[ ${SUITE_EXIT_CODE} -ne 0 ]] && cat "${FUNCTIONAL_RUN_DIRECTORY}/list.log"
            if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-split-${SUFFIX} -e XDEBUG_MODE=off ${IMAGE_PHP} \
                    php Build/Scripts/splitFunctionalTests.php ${FUNCTIONAL_SPLIT_OPTIONS} --exclude-group "${FUNCTIONAL_EXCLUDED_GROUPS}" "${FUNCTIONAL_RUN_DIRECTORY}/tests.xml" ${FUNCTIONAL_PARALLEL} "${FUNCTIONAL_RUN_DIRECTORY}" ${FUNCTIONAL_TIMINGS}
                SUITE_EXIT_CODE=$?
            fi
            if [[ ${SUITE_EXIT_CODE} -eq 0 && "${DBMS}" == "sqlite" ]]; then
                # Prepared here, once, and never by a chunk: every chunk mounts its own tmpfs on
                # this directory, and removing the directory on the host detaches the tmpfs of
                # every container that has it mounted - the databases of a chunk that started a
                # moment earlier would vanish in the middle of its tests.
                rm -rf "${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/" \
                    && mkdir -p "${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/"
                SUITE_EXIT_CODE=$?
            fi
            if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
                # The split writes fewer chunks than asked for when there are fewer test classes
                # than chunks, for instance with a path after "--", and with "-j auto".
                FUNCTIONAL_PARALLEL=$(ls "${FUNCTIONAL_RUN_DIRECTORY}"/FunctionalTests-Job-*.xml | wc -l | tr -d ' ')
                # The output of every chunk is streamed while it runs, each line prefixed with its
                # chunk, and written unprefixed to "chunk-<n>.log". Streamed rather than printed once a
                # chunk is done: a log collected at the end gives every line of a chunk the same
                # timestamp in CI, and a chunk that is killed by a timeout shows nothing at all.
                # Whole lines only, so the chunks interleave by line and never within one; PHPUnit
                # ends a progress line every 63 tests. "events-<n>.txt", appended to by PHPUnit
                # event by event, names the test a chunk was in when it stopped.
                #
                # "tee" and the prefixing loop ignore SIGINT, SIGHUP and SIGTERM. The SIGINT of a
                # ctrl-c reaches every process of the run, and a chunk's own trap then writes to its
                # output before it cleans up: with "tee" gone the write ended the chunk with
                # SIGPIPE, before "cleanUp", and its database container stayed behind. Both end
                # with the output of the chunk.
                CHUNK_PIDS=()
                for CHUNK in $(seq 1 ${FUNCTIONAL_PARALLEL}); do
                    (
                        CHUNK_STARTED=${SECONDS}
                        FUNCTIONAL_RUN_DIRECTORY="${FUNCTIONAL_RUN_DIRECTORY}" FUNCTIONAL_RUN_SUFFIX="${SUFFIX}" FUNCTIONAL_PARENT_PID=$$ \
                            "${BASH_SOURCE[0]}" -c "${CHUNK}/${FUNCTIONAL_PARALLEL}" "${ORIGINAL_ARGUMENTS[@]}" 2>&1 \
                            | ( trap '' INT HUP TERM; exec tee "${FUNCTIONAL_RUN_DIRECTORY}/chunk-${CHUNK}.log" ) \
                            | (
                                trap '' INT HUP TERM
                                while IFS= read -r LINE || [[ -n "${LINE}" ]]; do
                                    printf '[chunk %s/%s] %s\n' "${CHUNK}" "${FUNCTIONAL_PARALLEL}" "${LINE}"
                                done
                            )
                        CHUNK_EXIT_CODE=${PIPESTATUS[0]}
                        echo "$(( SECONDS - CHUNK_STARTED ))" > "${FUNCTIONAL_RUN_DIRECTORY}/chunk-${CHUNK}.seconds"
                        exit ${CHUNK_EXIT_CODE}
                    ) &
                    CHUNK_PIDS+=($!)
                done
                CHUNK_RESULTS=()
                for CHUNK in $(seq 1 ${FUNCTIONAL_PARALLEL}); do
                    wait "${CHUNK_PIDS[$((CHUNK - 1))]}"
                    CHUNK_EXIT_CODE=$?
                    [[ ${CHUNK_EXIT_CODE} -ne 0 ]] && SUITE_EXIT_CODE=${CHUNK_EXIT_CODE}
                    CHUNK_RESULTS+=("Chunk ${CHUNK}/${FUNCTIONAL_PARALLEL}: exit code ${CHUNK_EXIT_CODE}, $(cat "${FUNCTIONAL_RUN_DIRECTORY}/chunk-${CHUNK}.seconds" 2>/dev/null || echo "?") s")
                done
                # The failure details of a chunk are repeated as one block, unprefixed: streamed,
                # they are interleaved with the progress of the chunks that still ran.
                for CHUNK in $(seq 1 ${FUNCTIONAL_PARALLEL}); do
                    [[ "${CHUNK_RESULTS[$((CHUNK - 1))]}" == *"exit code 0,"* ]] && continue
                    [[ "${GITHUB_ACTIONS:-}" == "true" ]] && echo "::group::${CHUNK_RESULTS[$((CHUNK - 1))]}"
                    echo "${CHUNK_RESULTS[$((CHUNK - 1))]}, its complete output:"
                    cat "${FUNCTIONAL_RUN_DIRECTORY}/chunk-${CHUNK}.log"
                    [[ "${GITHUB_ACTIONS:-}" == "true" ]] && echo "::endgroup::"
                done
                echo ""
                echo "The files of this run are in ${FUNCTIONAL_RUN_DIRECTORY}/."
                printf '%s\n' "${CHUNK_RESULTS[@]}"
            fi
            # Green chunks do not prove that every listed test ran: a class the split left out
            # would simply be absent. Compare what ran with what was listed.
            if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-count-${SUFFIX} -e XDEBUG_MODE=off ${IMAGE_PHP} \
                    php Build/Scripts/checkFunctionalTestCount.php --exclude-group "${FUNCTIONAL_EXCLUDED_GROUPS}" "${FUNCTIONAL_RUN_DIRECTORY}/tests.xml" $(seq -f "${FUNCTIONAL_RUN_DIRECTORY}/junit-%g.xml" 1 ${FUNCTIONAL_PARALLEL})
                SUITE_EXIT_CODE=$?
            fi
            printSummary
        fi
        FUNCTIONAL_ARGUMENTS=("$@")
        if [[ -n "${FUNCTIONAL_CHUNK}" ]]; then
            PHPUNIT_CONFIG_FILE="${FUNCTIONAL_RUN_DIRECTORY}/FunctionalTests-Job-${FUNCTIONAL_CHUNK%%/*}.xml"
            if [[ ! -f "${PHPUNIT_CONFIG_FILE}" ]]; then
                echo "No chunk configuration ${PHPUNIT_CONFIG_FILE} for -c ${FUNCTIONAL_CHUNK}: -c is set by -j and not meant to be given by hand." >&2
                SUITE_EXIT_CODE=1
                printSummary
            fi
            # A test path given after "--" would replace the file list of the chunk
            # configuration, and every chunk would run all of it. The list the split was made
            # from was already restricted to that path, so a chunk drops it and keeps options -
            # with their values: an argument after an option that takes a separate value is that
            # value, even when a file of that name exists ("--testsuite Build"). The options are
            # those PHPUnit 10.5 declares with a required value ("LONG_OPTIONS" ending in a single
            # "=" in "src/TextUI/Configuration/Cli/Builder.php", and "-c", "-d").
            FUNCTIONAL_ARGUMENTS=()
            PREVIOUS_ARGUMENT=""
            for ARGUMENT in "$@"; do
                if [[ "${ARGUMENT}" != -* && -e "${ARGUMENT}" ]] \
                    && ! [[ "${PREVIOUS_ARGUMENT}" =~ ^(-c|-d|--(atleast-version|bootstrap|cache-directory|cache-result-file|columns|configuration|coverage-cache|coverage-filter|coverage-clover|coverage-cobertura|coverage-crap4j|coverage-html|coverage-php|coverage-xml|default-time-limit|exclude-group|filter|generate-baseline|use-baseline|group|covers|uses|include-path|list-tests-xml|log-junit|log-teamcity|order-by|random-order-seed|testdox-html|testdox-text|test-suffix|testsuite|exclude-testsuite|log-events-text|log-events-verbose-text))$ ]]; then
                    PREVIOUS_ARGUMENT=${ARGUMENT}
                    continue
                fi
                FUNCTIONAL_ARGUMENTS+=("${ARGUMENT}")
                PREVIOUS_ARGUMENT=${ARGUMENT}
            done
            FUNCTIONAL_ARGUMENTS+=(
                --log-junit "${FUNCTIONAL_RUN_DIRECTORY}/junit-${FUNCTIONAL_CHUNK%%/*}.xml"
                --log-events-verbose-text "${FUNCTIONAL_RUN_DIRECTORY}/events-${FUNCTIONAL_CHUNK%%/*}.txt"
            )
        fi
        # One "--exclude-group" carrying a comma separated list, never two of
        # them. This branch pins PHPUnit to the 10.5 line, and 10.5 rejects a
        # repeated option outright: "Option --exclude-group cannot be used more
        # than once". That arrives as a runner warning, and
        # "failOnPhpunitWarning" in "Build/phpunit/FunctionalTests.xml" turns it
        # into a failed run - so the wrong spelling reports FAILURE on a fully
        # passing suite.
        # @todo PHPUnit 11 deprecates the comma separated list and PHPUnit 12
        #       drops it. Switch to the repeated form together with the PHPUnit
        #       major, not before: there is no spelling both accept.
        COMMAND=(.Build/bin/phpunit -c ${PHPUNIT_CONFIG_FILE} --exclude-group not-${DBMS},not-core-${CORE_VERSION} "${FUNCTIONAL_ARGUMENTS[@]}")
        # Server options of the MySQL and MariaDB containers. Their data directory is a tmpfs,
        # thrown away with the container, so durability buys nothing: no binary log (on by
        # default in MySQL 8, off in MariaDB anyway), the redo log written but not flushed on
        # every commit, no doublewrite buffer. Every version "-i" accepts whose image was at hand -
        # MySQL 8.0 and 8.4, MariaDB 10.4, 10.6, 10.7, 10.11 and 11.0 to 11.8 - came up with them
        # and reported log_bin 0, innodb_flush_log_at_trx_commit 2 and innodb_doublewrite off.
        # See "docs/testing/functional-tests.md".
        MYSQL_SERVER_OPTIONS="--skip-log-bin --innodb-flush-log-at-trx-commit=2 --innodb-doublewrite=0"
        case ${DBMS} in
            mariadb)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mariadb-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MARIADB} ${MYSQL_SERVER_OPTIONS} >/dev/null
                SUITE_EXIT_CODE=$? && [[ "${SUITE_EXIT_CODE}" -ne 0 ]] && printSummary
                waitForDatabase mariadb mariadb-func-${SUFFIX} ${IMAGE_MARIADB}
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mariadb-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            mysql)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mysql-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MYSQL} ${MYSQL_SERVER_OPTIONS} >/dev/null
                SUITE_EXIT_CODE=$? && [[ "${SUITE_EXIT_CODE}" -ne 0 ]] && printSummary
                waitForDatabase mysql mysql-func-${SUFFIX} ${IMAGE_MYSQL}
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mysql-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            postgres)
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name postgres-func-${SUFFIX} --network ${NETWORK} -d -e POSTGRES_PASSWORD=funcp -e POSTGRES_USER=funcu --tmpfs ${POSTGRES_TMPFS_MOUNT}:rw,noexec,nosuid ${IMAGE_POSTGRES} >/dev/null
                SUITE_EXIT_CODE=$? && [[ "${SUITE_EXIT_CODE}" -ne 0 ]] && printSummary
                waitForDatabase postgres postgres-func-${SUFFIX} ${IMAGE_POSTGRES}
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_pgsql -e typo3DatabaseName=bamboo -e typo3DatabaseUsername=funcu -e typo3DatabaseHost=postgres-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            sqlite)
                # create sqlite tmpfs mount typo3temp/var/tests/functional-sqlite-dbs/ to avoid permission issues
                # A chunk leaves the directory alone: "-j" prepared it, and the other chunks
                # have their tmpfs mounted on it.
                if [[ -z "${FUNCTIONAL_CHUNK}" ]]; then
                    rm -rf "${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/"
                    SUITE_EXIT_CODE=$? && [[ "${SUITE_EXIT_CODE}" -ne 0 ]] && printSummary
                fi
                mkdir -p "${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/"
                SUITE_EXIT_CODE=$? && [[ "${SUITE_EXIT_CODE}" -ne 0 ]] && printSummary
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_sqlite --tmpfs ${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/:${TMPFS_MOUNT_OPTIONS}"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
        esac
        ;;
    lintPhp)
        # "./theme" is the self referencing symlink the development instances
        # resolve the extension through. "find" does not follow symlinks, so it
        # never descends into it, but excluding it explicitly keeps that from
        # depending on a default. The generated trees of the instances are
        # excluded as well; their committed configuration below "config/" is
        # deliberately still linted.
        COMMAND="find . -name \\*.php ! -path "./.Build/\\*" ! -path "./.agent/\\*" ! -path "./.cache/\\*" ! -path "./var/\\*" ! -path "./node_modules/\\*" ! -path "./Tests/Acceptance/node_modules/\\*" ! -path "./theme/\\*" ! -path "./instance-core-\\*/vendor/\\*" ! -path "./instance-core-\\*/public/\\*" ! -path "./instance-core-\\*/var/\\*" -print0 | xargs -0 -n1 -P4 php -dxdebug.mode=off -l >/dev/null"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name lint-php-${SUFFIX} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    npm)
        COMMAND=(npm "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name npm-command-${SUFFIX} -e npm_config_cache=.cache/npm ${IMAGE_NODEJS} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    phpstan)
        PHPSTAN_CONFIG_FILE="Build/phpstan/Core${CORE_VERSION}/phpstan.neon"
        COMMAND=(php -dxdebug.mode=off .Build/bin/phpstan analyse -c ${PHPSTAN_CONFIG_FILE} --no-interaction --memory-limit 4G "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name phpstan-${SUFFIX} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    phpstanGenerateBaseline)
        PHPSTAN_CONFIG_FILE="Build/phpstan/Core${CORE_VERSION}/phpstan.neon"
        COMMAND=(php -dxdebug.mode=off .Build/bin/phpstan analyse -c ${PHPSTAN_CONFIG_FILE} --no-interaction --memory-limit 4G --allow-empty-baseline --generate-baseline=Build/phpstan/Core${CORE_VERSION}/phpstan-baseline.neon "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name phpstan-baseline-${SUFFIX} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    recordFunctionalTestTimes)
        # The JUnit logs come after "--": the ones "-j" leaves in ".Build/functional-runs/<suffix>/",
        # or those of a CI run. "-d" selects the file written, because the same class costs very
        # different times per DBMS. See "docs/development/environment.md".
        ${CONTAINER_BIN} run ${CONTAINER_SIMPLE_PARAMS} --name record-functional-test-times-${SUFFIX} -e XDEBUG_MODE=off ${IMAGE_PHP} \
            php Build/Scripts/recordFunctionalTestTimes.php "Build/phpunit/FunctionalTestTimes-${DBMS}.json" "$@"
        SUITE_EXIT_CODE=$?
        ;;
    renderDocumentation)
        cleanRenderedDocumentationFiles
        ${CONTAINER_BIN} run ${DOCUMENTATION_COMMON_PARAMS} --name render-documentation-${SUFFIX} ${IMAGE_DOCS} --no-progress --fail-on-error --config=Documentation Documentation
        SUITE_EXIT_CODE=$?
        ;;
    setVersion)
        # Arguments are the ones of the script itself, for instance:
        #   ./Build/Scripts/runTests.sh -s setVersion -- 1.2.0 release
        COMMAND=(Build/Scripts/setVersion.sh "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name set-version-${SUFFIX} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    unit)
        PHPUNIT_CONFIG_FILE="Build/phpunit/UnitTests.xml"
        COMMAND=(.Build/bin/phpunit -c ${PHPUNIT_CONFIG_FILE} --exclude-group not-core-${CORE_VERSION} "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name unit-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    unitRandom)
        PHPUNIT_CONFIG_FILE="Build/phpunit/UnitTests.xml"
        COMMAND=(.Build/bin/phpunit -c ${PHPUNIT_CONFIG_FILE} --exclude-group not-core-${CORE_VERSION} --order-by=random ${PHPUNIT_RANDOM} "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name unit-random-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    visual)
        # Screenshots and axe of the styleguide partials, without a TYPO3 instance.
        # "Build/Scripts/renderStyleguideFixtures.php" renders every partial below
        # "Resources/Private/Partials/Styleguide/" with the standalone Fluid of the installed
        # dependency set - so "composerUpdate" has to have run, for either core version - into one
        # static page per appearance and palette below ".Build/visual/". The PHP built-in server
        # serves those pages and the committed stylesheet they link through
        # "Tests/Acceptance/Visual/router.php", in a container of its own, and Playwright tests
        # them from a third one, on the network of this run.
        #
        # Arguments after "--" go to "playwright test": "-- --grep buttons". A changed baseline is
        # written by "-- --update-snapshots", and only after looking at the diff.
        # See "docs/testing/visual-tests.md".
        VISUAL_ROOT=".Build/visual"
        rm -rf "${VISUAL_ROOT}"
        mkdir -p "${VISUAL_ROOT}/home"
        COMMAND="php -dxdebug.mode=off Build/Scripts/renderStyleguideFixtures.php"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name visual-fixtures-${SUFFIX} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
            ${CONTAINER_BIN} run -d ${CONTAINER_COMMON_PARAMS} --name visual-web-${SUFFIX} ${IMAGE_PHP} /bin/sh -c "exec php -dxdebug.mode=off -S 0.0.0.0:8000 -t ${ROOT_DIR} ${ROOT_DIR}/Tests/Acceptance/Visual/router.php > ${VISUAL_ROOT}/php-server.log 2>&1" >/dev/null
            SUITE_EXIT_CODE=$?
            # The browser reaches the server by a name that no DNS server knows, "fixture-server",
            # pinned to the address of the server container in "/etc/hosts" of the Playwright
            # container. Resolved by its container name, a page load failed now and then with
            # "net::ERR_NAME_NOT_RESOLVED" on a busy host, although the global setup had reached
            # the same name a moment before: a lookup of the network's DNS server that fails is a
            # failed test, whenever it happens. With the address pinned no lookup of the run goes
            # to DNS at all, and a pin that did not work would fail every test, not one in a
            # hundred. The wait before it covers the start of the server; the address is known as
            # soon as the container runs. See "docs/testing/visual-tests.md".
            VISUAL_WEB_ADDRESS=""
            if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
                waitForServer visual-web-${SUFFIX} 8000
                VISUAL_WEB_ADDRESS=$(${CONTAINER_BIN} inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' visual-web-${SUFFIX})
                if [[ -z "${VISUAL_WEB_ADDRESS}" ]]; then
                    echo "The address of the fixture server container \"visual-web-${SUFFIX}\" could not be read." >&2
                    SUITE_EXIT_CODE=1
                fi
            fi
            # Quoted one by one, as for "acceptance".
            PLAYWRIGHT_ARGUMENTS=""
            [[ $# -gt 0 ]] && PLAYWRIGHT_ARGUMENTS=$(printf ' %q' "$@")
            # The baselines are the rendering of the pinned image on x86_64. The same image on
            # another architecture renders differently, so it is said up front rather than left to
            # a wall of screenshot failures - a warning, not a failure, as axe still applies.
            # Asked inside the container, as that is what renders.
            ARCH_WARNING="if [ \"\$(uname -m)\" != x86_64 ]; then echo \"WARNING: the visual baselines were written on x86_64, this is \$(uname -m). Expect screenshot differences that are not regressions, and do not rebaseline from here.\" >&2; fi"
            COMMAND="${ARCH_WARNING}; cd Tests/Acceptance && npm ci --no-audit --no-fund && npx playwright test -c Visual/playwright.config.ts${PLAYWRIGHT_ARGUMENTS}"
            if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name visual-playwright-${SUFFIX} --add-host fixture-server:${VISUAL_WEB_ADDRESS} -e BASE_URL="http://fixture-server:8000/${VISUAL_ROOT}/" -e HOME="${ROOT_DIR}/${VISUAL_ROOT}/home" -e npm_config_cache="${ROOT_DIR}/.cache/npm" -e CI="${CI:-}" ${IMAGE_PLAYWRIGHT} /bin/sh -c "${COMMAND}"
                SUITE_EXIT_CODE=$?
            fi
        fi
        ;;
    watchCss)
        # A writing aid like "watchDocumentation", never a gate: it blocks until
        # ctrl-c and writes the expanded, source mapped build, not the one that
        # is committed. Run "buildCss" before committing.
        COMMAND="npm ci --no-audit --no-fund && npm run watch"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name watch-css-${SUFFIX} -e npm_config_cache=.cache/npm ${IMAGE_NODEJS} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    watchDocumentation)
        # An optional first mass argument overrides the port, for a second instance or a taken one.
        DOCUMENTATION_PORT="${1:-${DOCUMENTATION_PORT}}"
        if ! [[ ${DOCUMENTATION_PORT} =~ ^[0-9]+$ ]]; then
            echo "Invalid port \"${DOCUMENTATION_PORT}\", expected a number." >&2
            SUITE_EXIT_CODE=1
        else
            cleanRenderedDocumentationFiles
            echo "Rendering Documentation/ and watching it for changes."
            echo "Open http://localhost:${DOCUMENTATION_PORT}/Index.html once the first render is done."
            echo "Press ctrl-c to stop."
            echo ""
            # Attached to the network so an interrupted run is caught by cleanUp(). Files added
            # while the server runs are not picked up; restart the suite for those.
            ${CONTAINER_BIN} run ${DOCUMENTATION_COMMON_PARAMS} --network ${NETWORK} --name watch-documentation-${SUFFIX} -p ${DOCUMENTATION_PORT}:${DOCUMENTATION_PORT} ${IMAGE_DOCS} --port ${DOCUMENTATION_PORT} --watch --config=Documentation Documentation
            SUITE_EXIT_CODE=$?
        fi
        ;;
    update)
        # pull typo3/core-testing-* versions of those ones that exist locally
        echo "> pull ghcr.io/typo3/core-testing-* versions of those ones that exist locally"
        ${CONTAINER_BIN} images "ghcr.io/typo3/core-testing-*" --format "{{.Repository}}:{{.Tag}}" | xargs -I {} ${CONTAINER_BIN} pull {}
        echo ""
        # remove "dangling" typo3/core-testing-* images (those tagged as <none>)
        echo "> remove \"dangling\" ghcr.io/typo3/core-testing-* images (those tagged as <none>)"
        ${CONTAINER_BIN} images --filter "reference=ghcr.io/typo3/core-testing-*" --filter "dangling=true" --format "{{.ID}}" | xargs -I {} ${CONTAINER_BIN} rmi -f {}
        echo ""
        SUITE_EXIT_CODE=0
        ;;
    help)
        loadHelp
        echo "${HELP}" >&2
        cleanUp
        exit 0
        ;;
    *)
        loadHelp
        echo "Invalid -s option argument ${TEST_SUITE}" >&2
        echo >&2
        echo "${HELP}" >&2
        cleanUp
        exit 1
        ;;
esac

# Cleanup, print summary && exit with exitcode
printSummary
