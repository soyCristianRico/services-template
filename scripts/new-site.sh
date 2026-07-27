#!/usr/bin/env bash
#
# Bootstrap a new services site from the service-template.
#
#   scripts/new-site.sh "Site Name" ["Description"]
#
# Creates the repo on GitHub (private), clones the template WITH its full
# history into a sibling directory, repoints the remotes (origin = the new repo,
# template = service-template) and leaves the site installed —
# ready for `composer run dev`.
#
# Keeping the template as a remote is deliberate: porting a later template
# improvement becomes `git fetch template && git cherry-pick <hash>` instead of
# a hand-written migration guide.

set -euo pipefail

# ── Per-template configuration ───────────────────────────────────────────────
# The only part that differs between directory-template, services-template and
# rental-template. Keep the rest of the script identical across the three.
TEMPLATE_REPO="git@github.com:soyCristianRico/service-template.git"
ORG="soyCristianRico"
DEFAULT_BRANCH="main"

# Runs inside the new project, after the dependencies are installed.
post_install() {
    log "Setting APP_NAME to \"$SITE_NAME\""
    set_env APP_NAME "\"$SITE_NAME\""

    if [[ "$SEED_DEMO" == "1" ]]; then
        log "php artisan migrate --seed"
        php artisan migrate --force --no-interaction --seed
    else
        log "php artisan migrate"
        php artisan migrate --force --no-interaction
    fi

    log "php artisan storage:link"
    php artisan storage:link
}

next_steps() {
    cat <<'NEXT'
    composer run dev   # serve + vite + queue + logs -> http://localhost:8000

  The seeder leaves an admin ready: admin@services.test / password

  Only when you actually need it:

    php artisan services:mcp-token --email=you@domain.com

  Before going live, review in .env: APP_NAME, APP_URL, the DB_* block (the
  README recommends MySQL; .env.example ships with SQLite), LEAD_NOTIFY_EMAIL,
  LEAD_WEBHOOK_URL / LEAD_WEBHOOK_SECRET and the MAIL_* / MAILGUN_* block.
NEXT
}
# ─────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_ROOT="$(dirname "$SCRIPT_DIR")"
PROJECTS_DIR="$(dirname "$SOURCE_ROOT")"

INSTALL_DEPS=1
SEED_DEMO=1
SLUG=""
DESCRIPTION=""
SITE_NAME=""

usage() {
    cat <<'USAGE'
Usage: scripts/new-site.sh "Site Name" ["Description"] [options]

  "Site Name"       The site's name. Written to APP_NAME, and the slug is
                    derived from it (MakerGuia -> makerguia).
  [Description]     One-line repo description (the niche).

  --slug <slug>     Override the derived slug (repo + folder name).
  --no-demo         Skip the demo content seeding.
  --no-deps         Skip composer install / npm install (and everything after).
USAGE
}

log()  { printf '\033[1;34m▸\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m✗\033[0m %s\n' "$*" >&2; exit 1; }

# A failed push must never abort the bootstrap — the local project is still usable.
push_or_warn() {
    if git push -u origin "$DEFAULT_BRANCH"; then
        return 0
    fi
    printf '\n'
    warn "Push failed. Check that https://github.com/$ORG/$SLUG exists and that the"
    warn "Owner dropdown was set to '$ORG' when creating it. Then run:"
    warn "    git -C $TARGET push -u origin $DEFAULT_BRANCH"
    printf '\n'
    warn "Continuing with the local setup."
}

slugify() {
    local value="${1,,}"

    printf '%s' "$value" \
        | sed -e 's/[áàäâã]/a/g' -e 's/[éèëê]/e/g' -e 's/[íìïî]/i/g' \
              -e 's/[óòöôõ]/o/g' -e 's/[úùüû]/u/g' -e 's/ñ/n/g' -e 's/ç/c/g' \
        | sed -e 's/[^a-z0-9]\+/-/g' -e 's/^-\+//' -e 's/-\+$//'
}

set_env() {
    local key="$1" value="$2"

    if grep -qE "^${key}=" .env; then
        sed -i -E "s|^${key}=.*|${key}=${value}|" .env
    else
        printf '%s=%s\n' "$key" "$value" >>.env
    fi
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --slug)     SLUG="${2:-}"; shift 2 ;;
        --slug=*)   SLUG="${1#--slug=}"; shift ;;
        --no-demo)  SEED_DEMO=0; shift ;;
        --no-deps)  INSTALL_DEPS=0; shift ;;
        -h|--help)  usage; exit 0 ;;
        *)
            if [[ -z "$SITE_NAME" ]]; then
                SITE_NAME="$1"
            elif [[ -z "$DESCRIPTION" ]]; then
                DESCRIPTION="$1"
            fi
            shift
            ;;
    esac
done

[[ -n "$SITE_NAME" ]] || { usage; exit 1; }

SLUG="${SLUG:-$(slugify "$SITE_NAME")}"
[[ "$SLUG" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?$ ]] \
    || die "Could not derive a valid slug from '$SITE_NAME'. Pass one with --slug."

TARGET="$PROJECTS_DIR/$SLUG"

[[ ! -e "$TARGET" ]] || die "$TARGET already exists."
command -v git >/dev/null || die "git is not installed."

# The slug names the repo, the Hetzner server, the Forge site and the Drive
# folder — worth one look before it is set in stone.
printf '\n'
log "Name:  $SITE_NAME"
log "Slug:  $SLUG"
log "Repo:  github.com/$ORG/$SLUG (private)"
log "Local: $TARGET"
printf '\n'

if [[ -t 0 ]]; then
    read -r -p "  Press Enter to continue, Ctrl-C to abort (--slug to change it): " _ || true
fi

HAS_GH=0
if command -v gh >/dev/null && gh auth status >/dev/null 2>&1; then
    HAS_GH=1
else
    log "gh CLI not authenticated — the repo will be created by hand (one step, see below)."
fi

# 1. Create the private repo.
if [[ "$HAS_GH" == "1" ]]; then
    log "Creating github.com/$ORG/$SLUG (private)"
    if [[ -n "$DESCRIPTION" ]]; then
        gh repo create "$ORG/$SLUG" --private --description "$DESCRIPTION"
    else
        gh repo create "$ORG/$SLUG" --private
    fi
fi

# 2. Clone the template with its history, then repoint the remotes.
log "Cloning the template into $TARGET"
git clone --branch "$DEFAULT_BRANCH" "$TEMPLATE_REPO" "$TARGET"

cd "$TARGET"
git remote rename origin template
git remote set-url --push template DISABLED_PUSH_TO_TEMPLATE
git remote add origin "git@github.com:$ORG/$SLUG.git"

if [[ "$HAS_GH" == "1" ]]; then
    log "Pushing $DEFAULT_BRANCH to origin"
    push_or_warn
else
    printf '\n'
    warn "Create the repo by hand — Private, without README or .gitignore,"
    warn "and make sure the Owner is '$ORG':"
    printf '    https://github.com/new?name=%s&owner=%s\n\n' "$SLUG" "$ORG"

    if [[ -t 0 ]]; then
        read -r -p "  Press Enter once it exists to push it (Ctrl-C to push later): " _ || true
        push_or_warn
    else
        warn "Then run: git -C $TARGET push -u origin $DEFAULT_BRANCH"
    fi
fi

# 3. Carry over the machine-local files git does not track.
if [[ -f "$SOURCE_ROOT/auth.json" ]]; then
    log "Copying auth.json (Flux Pro license) from the template checkout"
    cp "$SOURCE_ROOT/auth.json" "$TARGET/auth.json"
    chmod 600 "$TARGET/auth.json"
else
    warn "No auth.json in $SOURCE_ROOT — composer install will fail on flux-pro."
    warn "Fix with: composer config http-basic.composer.fluxui.dev EMAIL LICENSE_KEY"
fi

# 4. Environment.
log "Preparing .env"
cp .env.example .env

if grep -qE '^DB_CONNECTION=sqlite' .env; then
    touch database/database.sqlite
fi

# 5. Dependencies, then install the site.
if [[ "$INSTALL_DEPS" == "1" ]]; then
    log "composer install"
    composer install

    log "npm install"
    npm install

    log "php artisan key:generate"
    php artisan key:generate

    post_install
else
    warn "Skipping dependencies (--no-deps). Run composer install && npm install && php artisan key:generate."
fi

TEMPLATE_NAME="$(basename "$TEMPLATE_REPO" .git)"

cat <<NEXT

  Repo:    https://github.com/$ORG/$SLUG
  Local:   $TARGET
  Remotes: origin -> $ORG/$SLUG   |   template -> $TEMPLATE_NAME (fetch only)

  Next, from $TARGET:

$(next_steps)

  To port a later template improvement:
    git fetch template && git cherry-pick <hash>

NEXT
