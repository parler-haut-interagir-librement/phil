#!/bin/sh
# Plays the OpenID Foundation conformance suite against the OpenID Connect provider of the bundle, in Docker:
#
#   tests/Conformance/run.sh            the Basic OP, Config OP and RP-Initiated Logout OP plans
#   tests/Conformance/run.sh down       stops and removes the containers
#
# The scripts that drive the suite come from its repository at the same release as its images
# (PHIL_CONFORMANCE_SUITE_VERSION, release-v5.3.4 by default). The exported results land in
# var/conformance/results of the monorepo, one zip per test plan; the summary is printed at the end. The run fails on
# any failure, and on a warning or a skip that expected-warnings.json or expected-skips.json does not explain. Modules
# that end in REVIEW wait for a person to look at the screenshot the browser took: they are not failures.
set -eu

here=$(cd "$(dirname "$0")" && pwd)
root=$(cd "$here/../../../.." && pwd)
version=${PHIL_CONFORMANCE_SUITE_VERSION:-release-v5.3.4}
work=${PHIL_CONFORMANCE_WORK:-$root/var/conformance}
export PHIL_CONFORMANCE_SUITE_VERSION="$version" PHIL_CONFORMANCE_WORK="$work"
compose="docker compose -f $here/compose.yaml"

if [ "${1:-}" = down ]; then
    $compose --profile run down --volumes
    exit 0
fi

mkdir -p "$work/results" "$work/plan"

if [ ! -f "$work/suite/.version" ] || [ "$(cat "$work/suite/.version")" != "$version" ]; then
    rm -rf "$work/suite"
    git -c advice.detachedHead=false clone --quiet --depth 1 --branch "$version" --filter=blob:none --sparse https://gitlab.com/openid/conformance-suite.git "$work/suite"
    git -C "$work/suite" sparse-checkout set scripts
    echo "$version" > "$work/suite/.version"
fi

$compose up -d --build --wait mongodb server nginx op

echo "Waiting for the suite and the provider..."
ready=no
for _ in $(seq 1 90); do
    if curl -ksf https://localhost.emobix.co.uk:8443/api/runner/available > /dev/null \
        && $compose exec -T op curl -ksf https://op.phil.test/.well-known/openid-configuration > /dev/null 2>&1; then
        ready=yes
        break
    fi
    sleep 2
done

if [ "$ready" != yes ]; then
    echo "The suite or the provider did not answer within three minutes: docker compose -f $here/compose.yaml logs" >&2
    exit 1
fi

# A fresh container cache, database and sessions: the application compiles the bundle as it stands.
$compose exec -T op rm -rf /tmp/phil-conformance
$compose exec -T op php /app/packages/identity-provider-bundle/tests/Conformance/bin/setup.php
$compose exec -T op cat /tmp/phil-conformance/plan.json > "$work/plan/plan.json"

$compose run --rm runner sh -c '
    pip install --quiet --disable-pip-version-check --root-user-action=ignore httpx pyparsing &&
    python run-test-plan.py --export-dir /results \
        --expected-failures-file /expected/expected-warnings.json --expected-skips-file /expected/expected-skips.json \
        "oidcc-basic-certification-test-plan[server_metadata=discovery][client_registration=static_client]" /plan/plan.json \
        "oidcc-config-certification-test-plan" /plan/plan.json \
        "oidcc-rp-initiated-logout-certification-test-plan[response_type=code][client_registration=static_client]" /plan/plan.json
'
