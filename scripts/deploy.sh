#!/bin/sh
set -eu

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [ -z "${NS_DEPLOY_CMD:-}" ]; then
    echo "NS_DEPLOY_CMD is not set."
    echo "Set an explicit deploy command, for example:"
    echo "  NS_DEPLOY_CMD='echo deploy-step-here' make deploy"
    exit 2
fi

echo "Deploy command: ${NS_DEPLOY_CMD}"
cd "${REPO_ROOT}"

# Execute the project-specific deploy command provided by the user/environment.
/bin/sh -lc "${NS_DEPLOY_CMD}"
