#!/bin/sh
set -eu

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

DEPLOY_ENV_FILE="${REPO_ROOT}/.deploy.env"
if [ -f "${DEPLOY_ENV_FILE}" ]; then
    # shellcheck disable=SC1090
    . "${DEPLOY_ENV_FILE}"
fi

if [ -z "${NS_DEPLOY_CMD:-}" ]; then
    echo "NS_DEPLOY_CMD is not set."
    echo "Set an explicit deploy command, for example:"
    echo "  NS_DEPLOY_CMD='echo deploy-step-here' make deploy"
    echo "Or store it in ${DEPLOY_ENV_FILE} as:"
    echo "  NS_DEPLOY_CMD='your-real-deploy-command'"
    exit 2
fi

echo "Deploy command: ${NS_DEPLOY_CMD}"
cd "${REPO_ROOT}"

# Execute the project-specific deploy command provided by the user/environment.
/bin/sh -lc "${NS_DEPLOY_CMD}"
