#!/usr/bin/env bash

#########################
#
# Check no file starts with a UTF-8 BOM.
#
# The first three bytes are compared against EF BB BF directly instead of
# grepping the output of "file": that wording is not stable. "file" 5.47
# prints "Unicode text, UTF-8 (with BOM) text" where older versions printed
# "UTF-8 Unicode (with BOM)", and a grep for the old wording passes on every
# file with a BOM.
#
# It expects to be run from the extension root.
#
# "pipefail" and the status check below exist because an empty result is
# what "no BOM found" looks like: a scan that failed part way - find, xargs,
# head or od missing or erroring - would otherwise report success.
#
##########################

set -o pipefail

FILES=`find . -type f \
    ! -path "./.Build/*" \
    ! -path "./.git/*" \
    ! -path "./.agent/*" \
    ! -path "./.cache/*" \
    ! -path "./var/*" \
    ! -path "./.php-cs-fixer.cache" \
    ! -path "./Documentation-GENERATED-temp/*" \
    ! -path "./node_modules/*" \
    ! -path "./Tests/Acceptance/node_modules/*" \
    ! -path "./theme/*" \
    ! -path "./instance-core-*/vendor/*" \
    ! -path "./instance-core-*/public/*" \
    ! -path "./instance-core-*/var/*" \
    -print0 | xargs -0 -n64 -P8 sh -c '
        for file in "$@"; do
            # Exit 255 makes xargs stop and fail the pipeline, instead of an
            # od that could not run comparing as "no BOM".
            bytes=$(head -c 3 "${file}" | od -An -tx1) || exit 255
            if [ "$(printf "%s" "${bytes}" | tr -d " \n")" = "efbbbf" ]; then
                echo "${file}"
            fi
        done
    ' sh`
SCAN_STATUS=$?

if [ ${SCAN_STATUS} -ne 0 ]; then
    echo "The scan for UTF-8 BOMs failed with exit code ${SCAN_STATUS}; nothing was checked." >&2
    exit 2
fi

if [ -n "${FILES}" ]; then
    echo "Found UTF-8 files with BOM:";
    echo "${FILES}";
    exit 1;
fi

exit 0
