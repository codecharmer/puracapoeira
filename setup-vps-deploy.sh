#!/usr/bin/env bash
set -euo pipefail

SSH_HOST="72.167.225.151"
SSH_PORT="22"
SSH_USER="root"
BRANCH="master"
SITE_NAME=""
DEPLOY_PATH=""
DEFAULT_DEPLOY_FOLDER="puracapoeirasite"
OWNER=""
GROUP=""
YES="0"

usage() {
  cat <<USAGE
Usage:
  ./setup-vps-static-deploy.sh \\
    --site-name SITE_NAME \\
    --owner CPANEL_USER

Optional:
  --deploy-path PATH        Default: /home/OWNER/public_html/puracapoeirasite
  --host HOST              Default: 72.167.225.151
  --port PORT              Default: 22
  --ssh-user USER          Default: root
  --branch BRANCH          Default: master
  --group GROUP            Default: same as owner
  --yes                    Skip confirmation prompt

Example:
  ./setup-vps-static-deploy.sh \\
    --site-name puracapoeirasite \
    --owner exampleuser
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --site-name)
      SITE_NAME="${2:?Missing value for --site-name}"
      shift 2
      ;;
    --deploy-path)
      DEPLOY_PATH="${2:?Missing value for --deploy-path}"
      shift 2
      ;;
    --owner)
      OWNER="${2:?Missing value for --owner}"
      shift 2
      ;;
    --group)
      GROUP="${2:?Missing value for --group}"
      shift 2
      ;;
    --host)
      SSH_HOST="${2:?Missing value for --host}"
      shift 2
      ;;
    --port)
      SSH_PORT="${2:?Missing value for --port}"
      shift 2
      ;;
    --ssh-user)
      SSH_USER="${2:?Missing value for --ssh-user}"
      shift 2
      ;;
    --branch)
      BRANCH="${2:?Missing value for --branch}"
      shift 2
      ;;
    --yes)
      YES="1"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$SITE_NAME" || -z "$OWNER" ]]; then
  echo "Error: --site-name and --owner are required."
  usage
  exit 1
fi

if [[ -z "$DEPLOY_PATH" ]]; then
  DEPLOY_PATH="/home/$OWNER/public_html/$DEFAULT_DEPLOY_FOLDER"
fi

if [[ -z "$GROUP" ]]; then
  GROUP="$OWNER"
fi

SAFE_SITE_NAME="$(printf '%s' "$SITE_NAME" | tr -cs '[:alnum:]' '_' | sed 's/^_//;s/_$//')"
KEY_PATH="$HOME/.ssh/${SAFE_SITE_NAME}_github_actions"
WORKFLOW_FILE=".github/workflows/deploy.yml"

cat <<INFO

Static VPS deploy setup

Site name:     $SITE_NAME
Branch:        $BRANCH
SSH target:    $SSH_USER@$SSH_HOST:$SSH_PORT
Deploy path:   $DEPLOY_PATH
Owner/group:   $OWNER:$GROUP
SSH key:       $KEY_PATH
Workflow:      $WORKFLOW_FILE

INFO

if [[ "$YES" != "1" ]]; then
  read -r -p "Continue? [y/N] " CONFIRM
  if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
    echo "Aborted."
    exit 1
  fi
fi

echo "Creating local site structure..."
mkdir -p public/assets/css public/assets/js public/assets/images
mkdir -p .github/workflows

if [[ ! -f public/index.html ]]; then
  cat > public/index.html <<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>$SITE_NAME</title>
</head>
<body>
  <h1>$SITE_NAME</h1>
  <p>Deployed from GitHub Actions to VPS.</p>
</body>
</html>
HTML
fi

echo "Creating SSH deploy key if needed..."
mkdir -p "$HOME/.ssh"
chmod 700 "$HOME/.ssh"

if [[ ! -f "$KEY_PATH" ]]; then
  ssh-keygen -t ed25519 -C "github-actions-$SAFE_SITE_NAME" -f "$KEY_PATH" -N ""
else
  echo "SSH key already exists: $KEY_PATH"
fi

echo "Adding public key to VPS root authorized_keys..."
PUB_KEY="$(cat "$KEY_PATH.pub")"

ssh -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "PUB_KEY='$PUB_KEY' bash -s" <<'REMOTE'
set -euo pipefail
mkdir -p ~/.ssh
chmod 700 ~/.ssh
touch ~/.ssh/authorized_keys
grep -qxF "$PUB_KEY" ~/.ssh/authorized_keys || printf '%s\n' "$PUB_KEY" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
REMOTE

echo "Testing new deploy key..."
ssh -i "$KEY_PATH" \
  -o IdentitiesOnly=yes \
  -p "$SSH_PORT" \
  "$SSH_USER@$SSH_HOST" \
  "echo 'SSH key works as:' \$(whoami)@\$(hostname)"

echo "Creating deploy path and fixing ownership..."
ssh -i "$KEY_PATH" \
  -o IdentitiesOnly=yes \
  -p "$SSH_PORT" \
  "$SSH_USER@$SSH_HOST" \
  "id '$OWNER' >/dev/null && getent group '$GROUP' >/dev/null && mkdir -p '$DEPLOY_PATH' && chown -R '$OWNER:$GROUP' '$DEPLOY_PATH' && find '$DEPLOY_PATH' -type d -exec chmod 755 {} \; && find '$DEPLOY_PATH' -type f -exec chmod 644 {} \;"

echo "Generating SSH known_hosts..."
KNOWN_HOSTS="$(ssh-keyscan -p "$SSH_PORT" "$SSH_HOST" 2>/dev/null)"

if [[ -z "$KNOWN_HOSTS" ]]; then
  echo "Error: ssh-keyscan returned empty known_hosts."
  exit 1
fi

echo "Creating GitHub Actions workflow..."
cat > "$WORKFLOW_FILE" <<'YAML'
name: Deploy Static Site to VPS

on:
  push:
    branches:
      - __BRANCH__

  workflow_dispatch:

concurrency:
  group: production-deploy
  cancel-in-progress: true

jobs:
  deploy:
    name: Deploy static HTML
    runs-on: ubuntu-latest

    permissions:
      contents: read

    steps:
      - name: Checkout repo
        uses: actions/checkout@v4

      - name: Set up SSH
        run: |
          mkdir -p ~/.ssh

          echo "${{ secrets.SSH_PRIVATE_KEY }}" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key

          echo "${{ secrets.SSH_KNOWN_HOSTS }}" > ~/.ssh/known_hosts
          chmod 644 ~/.ssh/known_hosts

      - name: Test SSH connection
        run: |
          ssh -i ~/.ssh/deploy_key \
            -o IdentitiesOnly=yes \
            -p "${{ secrets.SSH_PORT }}" \
            "${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }}" \
            "whoami && hostname"

      - name: Deploy public folder
        run: |
          rsync -az --delete \
            --exclude='.well-known/' \
            --exclude='.htaccess' \
            -e "ssh -i ~/.ssh/deploy_key -o IdentitiesOnly=yes -p ${{ secrets.SSH_PORT }}" \
            public/ \
            "${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }}:${{ secrets.DEPLOY_PATH }}/"

      - name: Fix ownership and permissions
        run: |
          ssh -i ~/.ssh/deploy_key \
            -o IdentitiesOnly=yes \
            -p "${{ secrets.SSH_PORT }}" \
            "${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }}" \
            "chown -R __OWNER__:__GROUP__ '${{ secrets.DEPLOY_PATH }}' && find '${{ secrets.DEPLOY_PATH }}' -type d -exec chmod 755 {} \; && find '${{ secrets.DEPLOY_PATH }}' -type f -exec chmod 644 {} \;"
YAML

BRANCH_ENV="$BRANCH" OWNER_ENV="$OWNER" GROUP_ENV="$GROUP" perl -0pi -e '
  s/__BRANCH__/$ENV{BRANCH_ENV}/g;
  s/__OWNER__/$ENV{OWNER_ENV}/g;
  s/__GROUP__/$ENV{GROUP_ENV}/g;
' "$WORKFLOW_FILE"

echo "Checking whether GitHub CLI is available..."
if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
  echo "Setting GitHub repo secrets with gh..."

  printf '%s' "$SSH_HOST" | gh secret set SSH_HOST
  printf '%s' "$SSH_PORT" | gh secret set SSH_PORT
  printf '%s' "$SSH_USER" | gh secret set SSH_USER
  printf '%s' "$DEPLOY_PATH" | gh secret set DEPLOY_PATH
  gh secret set SSH_PRIVATE_KEY < "$KEY_PATH"
  printf '%s\n' "$KNOWN_HOSTS" | gh secret set SSH_KNOWN_HOSTS

  echo "GitHub secrets created."
else
  cat <<MANUAL

GitHub CLI is not installed or not authenticated.

Create these GitHub repo secrets manually:

SSH_HOST=$SSH_HOST
SSH_PORT=$SSH_PORT
SSH_USER=$SSH_USER
DEPLOY_PATH=$DEPLOY_PATH

For SSH_PRIVATE_KEY, run:
  pbcopy < "$KEY_PATH"

For SSH_KNOWN_HOSTS, run:
  ssh-keyscan -p "$SSH_PORT" "$SSH_HOST" | pbcopy

Then paste each value into:
  GitHub repo → Settings → Secrets and variables → Actions

MANUAL
fi

cat <<DONE

Setup complete.

Next commands:

  git status
  git add public .github/workflows/deploy.yml setup-vps-static-deploy.sh
  git commit -m "Add static VPS deployment"
  git push origin $BRANCH

After pushing, check:
  GitHub repo → Actions → Deploy Static Site to VPS

DONE
