#!/usr/bin/env bash
#
# Bootstrap a new services site from the services-template.
#
#   scripts/new-site.sh "Site Name" ["Description"]
#
# Creates the repo on GitHub (private), clones the template WITH its full
# history into a sibling directory, repoints the remotes (origin = the new repo,
# template = services-template) and leaves the site installed —
# ready for `composer run dev`.
#
# Keeping the template as a remote is deliberate: porting a later template
# improvement becomes `git fetch template && git cherry-pick <hash>` instead of
# a hand-written migration guide.

set -euo pipefail

# ── Per-template configuration ───────────────────────────────────────────────
# The only part that differs between directory-template, services-template and
# rental-template. Keep the rest of the script identical across the three.
TEMPLATE_REPO="git@github.com:soyCristianRico/services-template.git"
ORG="soyCristianRico"
DEFAULT_BRANCH="main"

# Runs inside the new project, after the dependencies are installed.
post_install() {
    log "Setting APP_NAME to \"$SITE_NAME\""
    set_env APP_NAME "\"$SITE_NAME\""

    # Geographic dimension. Left ON here because programmatic local SEO is the
    # common case and it is the safe default. /services-map-source derives the
    # real value from the source site (URL pattern C means no locations) and
    # /services-scaffold-structure flips it — nobody has to guess at this point.
    set_env SITE_LOCATIONS true

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

  That token goes in .mcp.json: paste the `services` block from
  .mcp.json.example, with the real domain and the token. It is left out of the
  generated .mcp.json because the domain is not live yet.

  Review in .env before launch: the SEO_* block and GOOGLE_TAG_MANAGER_ID.

  Going live: paste .env.production.example into Forge, fill the blanks it
  lists, deploy, and then over SSH run

    php artisan env:check

  which reports the misconfigurations that otherwise fail silently.
NEXT
}
# ─────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_ROOT="$(dirname "$SCRIPT_DIR")"
PROJECTS_DIR="$(dirname "$SOURCE_ROOT")"

INSTALL_DEPS=1
SEED_DEMO=1
DB_ENGINE="mysql"
SLUG=""
DOMAIN=""
DESCRIPTION=""
SITE_NAME=""

usage() {
    cat <<'USAGE'
Usage: scripts/new-site.sh "Site Name" ["Description"] [options]

  "Site Name"       The site's name. Written to APP_NAME, and the slug is
                    derived from it (MakerGuia -> makerguia).
  [Description]     One-line repo description (the niche).

  --slug <slug>     Override the derived slug (repo + folder name).
  --db <engine>     mysql (default, matches production) or sqlite. The database
                    and its user are created for you; sudo may ask for your
                    password. Falls back to SQLite if no server is reachable.
  --domain <host>   Production domain (makerguia.com). Everything derivable from
                    it is written into .env.production.example, ready to paste
                    into Forge. Asked for if omitted.
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
    local key="$1" value="$2" file="${3:-.env}"

    if grep -qE "^${key}=" "$file"; then
        sed -i -E "s|^${key}=.*|${key}=${value}|" "$file"
    elif grep -qE "^# ?${key}=" "$file"; then
        # .env.example ships the MySQL block commented out.
        sed -i -E "s|^# ?${key}=.*|${key}=${value}|" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >>"$file"
    fi
}

# The suite reads .env.testing (phpunit.xml deliberately pins no connection),
# so it has to point at the same engine as .env — on its own database, because
# RefreshDatabase wipes it.
#
# Laravel REPLACES .env with this file when APP_ENV=testing; it does not merge
# them. So it must be a COMPLETE env file, or the app boots without an APP_KEY.
# It is built from .env.example, which is also why it carries no real
# credentials and can stay in git.
write_testing_env() {
    local connection="$1" database="$2" username="${3:-}" password="${4:-}"

    cp .env.example .env.testing

    set_env APP_ENV testing .env.testing
    set_env DB_CONNECTION "$connection" .env.testing
    set_env DB_DATABASE "$database" .env.testing

    if [[ -n "$username" ]]; then
        set_env DB_HOST 127.0.0.1 .env.testing
        set_env DB_PORT 3306 .env.testing
        set_env DB_USERNAME "$username" .env.testing
        set_env DB_PASSWORD "$password" .env.testing
    fi
}

use_sqlite() {
    DB_ENGINE="sqlite"
    set_env DB_CONNECTION sqlite
    touch database/database.sqlite
    write_testing_env sqlite ":memory:"
}

# The env file to paste into Forge, with everything derivable from the domain
# already filled in.
#
# It exists because three of the values that ship in .env.example are right for
# local and silently wrong in production: MAIL_MAILER=log writes mail to a file,
# the example MAIL_FROM_ADDRESS gets the message rejected, and a queue driver
# that does not match the Forge worker leaves jobs queued forever. None of them
# raises an error — the form thanks the user and the email never arrives.
#
# A complete file on purpose: Forge's environment editor holds the whole thing,
# so a fragment would drop everything it does not mention. Local values are left
# alone; this is a separate file.
write_production_env() {
    local target=".env.production.example"

    cp .env.example "$target"

    set_env APP_ENV production "$target"
    set_env APP_DEBUG false "$target"
    set_env APP_URL "https://$DOMAIN" "$target"

    set_env MAIL_MAILER mailgun "$target"
    set_env MAIL_FROM_ADDRESS "\"noreply@$DOMAIN\"" "$target"
    set_env MAILGUN_DOMAIN "mg.$DOMAIN" "$target"
    set_env MAILGUN_ENDPOINT api.eu.mailgun.net "$target"

    set_env CACHE_STORE redis "$target"
    set_env SESSION_DRIVER redis "$target"
    set_env QUEUE_CONNECTION redis "$target"

    set_env LEAD_NOTIFY_EMAIL "hola@$DOMAIN" "$target"
    set_env LEGAL_EMAIL "hola@$DOMAIN" "$target"

    # Secrets and per-server values stay empty: they are filled in Forge.
    set_env APP_KEY "" "$target"
    set_env MAILGUN_SECRET "" "$target"
    set_env DB_CONNECTION mysql "$target"
    set_env DB_HOST 127.0.0.1 "$target"
    set_env DB_PORT 3306 "$target"
    set_env DB_DATABASE "" "$target"
    set_env DB_USERNAME "" "$target"
    set_env DB_PASSWORD "" "$target"

    {
        printf '\n# ── Fill these in Forge ───────────────────────────────────────────\n'
        printf '#   APP_KEY          php artisan key:generate --show\n'
        printf '#   DB_DATABASE / DB_USERNAME / DB_PASSWORD\n'
        printf '#   MAILGUN_SECRET\n'
        printf '#   DISCORD_WEBHOOK_URL   (optional)\n'
        printf '#   GOOGLE_TAG_MANAGER_ID (optional)\n'
        printf '#\n'
        printf '# QUEUE_CONNECTION above MUST match the connection the Forge worker\n'
        printf '# listens on. That lives in Supervisor, outside this repo, so nothing\n'
        printf '# here can verify it — check it by eye.\n'
        printf '#\n'
        printf '# Then, over SSH: php artisan env:check\n'
    } >>"$target"

    log "Wrote $target for $DOMAIN"
}

# The template ships a bare-bones robots.txt (blanket Disallow: with no
# Sitemap line). Every site this script creates should start allowing
# crawlers, pointing them at its real sitemap, and keeping the known bad-bot
# list out — so it is written here instead of depending on whatever
# public/robots.txt happened to ship with the template.
write_robots_txt() {
    cat >public/robots.txt <<ROBOTS
User-agent: *
Allow: /

## Sitemap files
Sitemap: https://$DOMAIN/sitemap.xml

## No Bad Bots allowed
User-agent: 008
Disallow: /
User-agent: Alexibot
Disallow: /
User-agent: Aqua_Products
Disallow: /
User-agent: b2w/0.1
Disallow: /
User-agent: BackDoorBot/1.0
Disallow: /
User-agent: Bookmark search tool
Disallow: /
User-agent: BotALot
Disallow: /
User-agent: BotRightHere
Disallow: /
User-agent: BuiltBotTough
Disallow: /
User-agent: Bullseye/1.0
Disallow: /
User-agent: BunnySlippers
Disallow: /
User-agent: CherryPicker
Disallow: /
User-agent: CherryPickerSE/1.0
Disallow: /
User-agent: CherryPickerElite/1.0
Disallow: /
User-agent: CheeseBot
Disallow: /
User-agent: CNCDialer
Disallow: /
User-agent: Copernic
Disallow: /
User-agent: cosmos
Disallow: /
User-agent: Crescent
Disallow: /
User-agent: Crescent Internet ToolPak HTTP OLE Control v.1.0
Disallow: /
User-agent: DittoSpyder
Disallow: /
User-agent: DOC
Disallow: /
User-agent: Download Ninja
Disallow: /
User-agent: EmailCollector
Disallow: /
User-agent: EmailSiphon
Disallow: /
User-agent: EmailWolf
Disallow: /
User-agent: EroCrawler
Disallow: /
User-agent: ExtractorPro
Disallow: /
User-agent: FairAd Client
Disallow: /
User-agent: fast
Disallow: /
User-agent: Fetch
Disallow: /
User-agent: Flaming AttackBot
Disallow: /
User-agent: Foobot
Disallow: /
User-agent: Gaisbot
Disallow: /
User-agent: GetRight/4.2
Disallow: /
User-agent: grub-client
Disallow: /
User-agent: Harvest/1.5
Disallow: /
User-agent: hloader
Disallow: /
User-agent: httplib
Disallow: /
User-agent: HTTrack
Disallow: /
User-agent: HTTrack 3.0
Disallow: /
User-agent: humanlinks
Disallow: /
User-agent: InfoNaviRobot
Disallow: /
User-agent: Iron33/1.0.2
Disallow: /
User-agent: JennyBot
Disallow: /
User-agent: JikeSpider
Disallow: /
User-agent: Jyxobot/1
Disallow: /
User-agent: k2spider
Disallow: /
User-agent: Kenjin Spider
Disallow: /
User-agent: Keyword Density/0.9
Disallow: /
User-agent: larbin
Disallow: /
User-agent: LexiBot
Disallow: /
User-agent: libWeb/clsHTTP
Disallow: /
User-agent: libwww
Disallow: /
User-agent: LinkextractorPro
Disallow: /
User-agent: linko
Disallow: /
User-agent: LinkScan/8.1a Unix
Disallow: /
User-agent: LinkWalker
Disallow: /
User-agent: LNSpiderguy
Disallow: /
User-agent: lwp-trivial/1.34
Disallow: /
User-agent: lwp-trivial
Disallow: /
User-agent: Mata Hari
Disallow: /
User-agent: Maxthon
Disallow: /
User-agent: MIIxpc
Disallow: /
User-agent: MIIxpc/4.2
Disallow: /
User-agent: Mister PiX
Disallow: /
User-agent: moget
Disallow: /
User-agent: moget/2.1
Disallow: /
User-agent: NetAnts
Disallow: /
User-agent: NICErsPRO
Disallow: /
User-agent: NPBot
Disallow: /
User-agent: Offline Explorer
Disallow: /
User-agent: Openbot
Disallow: /
User-agent: Openfind data gatherer
Disallow: /
User-agent: Openfind
Disallow: /
User-agent: Oracle Ultra Search
Disallow: /
User-agent: PerMan
Disallow: /
User-agent: ProWebWalker
Disallow: /
User-agent: ProPowerBot/2.14
Disallow: /
User-agent: Python-urllib
Disallow: /
User-agent: QueryN Metasearch
Disallow: /
User-agent: Radiation Retriever 1.1
Disallow: /
User-agent: RepoMonkey Bait & Tackle/v1.01
Disallow: /
User-agent: RepoMonkey
Disallow: /
User-agent: RMA
Disallow: /
User-agent: searchpreview
Disallow: /
User-agent: sitecheck.internetseer.com
Disallow: /
User-agent: SiteSnagger
Disallow: /
User-agent: SpankBot
Disallow: /
User-agent: spanner
Disallow: /
User-agent: suzuran
Disallow: /
User-agent: Szukacz/1.4
Disallow: /
User-agent: Teleport
Disallow: /
User-agent: TeleportPro
Disallow: /
User-agent: Telesoft
Disallow: /
User-agent: The Intraformant
Disallow: /
User-agent: TightTwatBot
Disallow: /
User-agent: TheNomad
Disallow: /
User-agent: toCrawl/UrlDispatcher
Disallow: /
User-agent: True_Robot/1.0
Disallow: /
User-agent: True_Robot
Disallow: /
User-agent: turingos
Disallow: /
User-agent: TurnitinBot
Disallow: /
User-agent: TurnitinBot/1.5
Disallow: /
User-agent: UbiCrawler
Disallow: /
User-agent: UnisterBot
Disallow: /
User-agent: URL Control
Disallow: /
User-agent: URL_Spider_Pro
Disallow: /
User-agent: URLy Warning
Disallow: /
User-agent: VCI WebViewer VCI WebViewer Win32
Disallow: /
User-agent: VCI
Disallow: /
User-agent: WebBandit
Disallow: /
User-agent: WebBandit/3.50
Disallow: /
User-agent: WebCapture 2.0
Disallow: /
User-agent: WebCopier v3.2a
Disallow: /
User-agent: WebCopier v.2.2
Disallow: /
User-agent: WebCopier
Disallow: /
User-agent: Web Image Collector
Disallow: /
User-agent: WebEnhancer
Disallow: /
User-agent: wget
Disallow: /
User-agent: WebReaper
Disallow: /
User-agent: WebSauger
Disallow: /
User-agent: Website Quester
Disallow: /
User-agent: Webster Pro
Disallow: /
User-agent: WebStripper
Disallow: /
User-agent: WebZIP
Disallow: /
User-agent: WebZip/4.0
Disallow: /
User-agent: WebZIP/4.21
Disallow: /
User-agent: WebZIP/5.0
Disallow: /
User-agent: Wget/1.6
Disallow: /
User-agent: Wget/1.5.3
Disallow: /
User-agent: Wget
Disallow: /
User-agent: WWW-Collector-E
Disallow: /
User-agent: Xenu
Disallow: /
User-agent: Xenu's Link Sleuth 1.1c
Disallow: /
User-agent: Xenu's
Disallow: /
User-agent: Zao
Disallow: /
User-agent: Zealbot
Disallow: /
User-agent: Zeus
Disallow: /
User-agent: Zeus Link Scout
Disallow: /
User-agent: Zeus 32297 Webster Pro V2.9 Win32
Disallow: /
User-agent: ZyBORG
Disallow: /

## Bots settings
User-agent: bingbot
Crawl-delay: 30
User-agent: Slurp
Crawl-delay: 10
User-agent: Yahoo! Slurp China
Disallow: /
User-agent: DataForSeoBot
Crawl-delay: 30
User-agent: AhrefsBot
Crawl-Delay: 10
ROBOTS

    log "Wrote public/robots.txt for $DOMAIN"
}

# Claude Code reads .mcp.json, which is gitignored because it holds tokens — so a
# fresh clone starts without one and the editor comes up with no MCP servers at
# all. The versioned .mcp.json.example is the source.
#
# Two jobs, and the second is the one that bites:
#
#   1. Seed .mcp.json from the example when there is none.
#   2. Repoint laravel-boost at THIS checkout, whether the file was just seeded
#      or was carried over by hand from another site. A copied .mcp.json keeps
#      the OLD project's artisan path, and Boost then answers every question
#      about the wrong application — silently, because the server starts fine.
#      That has already happened. So the path is rewritten every run.
#
# Seeding also drops every `"type": "http"` server: those are the site's own MCP
# servers, served from the production domain, which does not exist on day one,
# and a server that cannot connect is an error on every Claude Code start. Paste
# the block back from .mcp.json.example once the site is live and the
# `*:mcp-token` command has issued a token.
#
# An existing .mcp.json keeps its http servers untouched — tokens included.
write_mcp_config() {
    local source=".mcp.json"

    if [[ ! -f .mcp.json ]]; then
        if [[ ! -f .mcp.json.example ]]; then
            warn "No .mcp.json.example in the template — skipping the MCP config."
            return
        fi

        source=".mcp.json.example"
    fi

    local result
    result="$(php -r '
        [, $source, $root] = $argv;

        $config = json_decode(file_get_contents($source), true);
        $before = $config;

        if ($source !== ".mcp.json") {
            $config["mcpServers"] = array_filter(
                $config["mcpServers"] ?? [],
                fn (array $server): bool => ($server["type"] ?? "stdio") !== "http"
            );
        }

        foreach (($config["mcpServers"]["laravel-boost"]["args"] ?? []) as $i => $arg) {
            if (str_ends_with((string) $arg, "/artisan")) {
                $config["mcpServers"]["laravel-boost"]["args"][$i] = $root . "/artisan";
            }
        }

        if ($source === ".mcp.json" && $config === $before) {
            echo "unchanged";

            exit;
        }

        file_put_contents(".mcp.json", json_encode(
            $config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n");

        echo $source === ".mcp.json" ? "repointed" : "seeded";
    ' "$source" "$TARGET")"

    case "$result" in
        seeded)    log "Wrote .mcp.json from .mcp.json.example, pointing laravel-boost at $TARGET" ;;
        repointed) log "Repointed laravel-boost in the existing .mcp.json at $TARGET" ;;
        *)         log "Keeping the existing .mcp.json — laravel-boost already points here" ;;
    esac
}

# Production runs MySQL. A site developed on SQLite only meets MySQL's rules on
# the day it deploys — index key length, strict mode and collation all differ,
# and each of those has already broken a release. So MySQL is the default and
# SQLite the escape hatch, with a fallback so a missing server never blocks the
# bootstrap.
provision_database() {
    if [[ "$DB_ENGINE" == "sqlite" ]]; then
        log "Using SQLite (--db sqlite)"
        use_sqlite
        return
    fi

    local client
    client="$(command -v mariadb || command -v mysql || true)"

    if [[ -z "$client" ]]; then
        warn "No mysql/mariadb client found — falling back to SQLite."
        warn "Install one and re-run with --db mysql to match production."
        use_sqlite
        return
    fi

    local password
    password="$(openssl rand -hex 16)"

    log "Creating the '$SLUG' and '${SLUG}_test' databases (sudo may ask for your password)"

    # Two databases: the site's and the one the suite wipes on every run.
    # ALTER USER as well as CREATE, so re-running against an existing database
    # leaves .env holding a password that actually works.
    if ! sudo "$client" <<SQL
CREATE DATABASE IF NOT EXISTS \`$SLUG\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`${SLUG}_test\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$SLUG'@'localhost' IDENTIFIED BY '$password';
ALTER USER '$SLUG'@'localhost' IDENTIFIED BY '$password';
GRANT ALL PRIVILEGES ON \`$SLUG\`.* TO '$SLUG'@'localhost';
GRANT ALL PRIVILEGES ON \`${SLUG}_test\`.* TO '$SLUG'@'localhost';
FLUSH PRIVILEGES;
SQL
    then
        printf '\n'
        warn "Could not create the database — falling back to SQLite so the bootstrap finishes."
        warn "To move to MySQL later, create it by hand and fill DB_* in .env:"
        warn "    sudo $client -e \"CREATE DATABASE \\\`$SLUG\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
        printf '\n'
        use_sqlite
        return
    fi

    set_env DB_CONNECTION mysql
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 3306
    set_env DB_DATABASE "$SLUG"
    set_env DB_USERNAME "$SLUG"
    set_env DB_PASSWORD "$password"

    write_testing_env mysql "${SLUG}_test" "$SLUG" "$password"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --slug)     SLUG="${2:-}"; shift 2 ;;
        --slug=*)   SLUG="${1#--slug=}"; shift ;;
        --db)       DB_ENGINE="${2:-}"; shift 2 ;;
        --db=*)     DB_ENGINE="${1#--db=}"; shift ;;
        --domain)   DOMAIN="${2:-}"; shift 2 ;;
        --domain=*) DOMAIN="${1#--domain=}"; shift ;;
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

[[ "$DB_ENGINE" == "mysql" || "$DB_ENGINE" == "sqlite" ]] \
    || die "--db must be mysql or sqlite, got '$DB_ENGINE'."

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
log "DB:    $DB_ENGINE (plus a ${SLUG}_test database for the suite)"
printf '\n'

if [[ -z "$DOMAIN" ]]; then
    if [[ -t 0 ]]; then
        read -r -p "  Production domain [$SLUG.com]: " DOMAIN || true
    fi
    DOMAIN="${DOMAIN:-$SLUG.com}"
fi

DOMAIN="${DOMAIN#http://}"
DOMAIN="${DOMAIN#https://}"
DOMAIN="${DOMAIN%%/*}"

log "Domain: $DOMAIN"
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

provision_database
write_production_env
write_robots_txt
write_mcp_config

# 5. Dependencies, then install the site.
if [[ "$INSTALL_DEPS" == "1" ]]; then
    log "composer install"
    composer install

    log "npm install"
    npm install

    log "php artisan key:generate"
    php artisan key:generate

    # .env.testing is a full env file, so it needs a key of its own.
    set_env APP_KEY "$(php artisan key:generate --show)" .env.testing

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
