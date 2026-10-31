#!/usr/bin/env bash
# Met une base a jour : schema/ (base de depart, gelee) puis chaque fichier de migrations/ pas encore joue.
#   ./infra/sql/migrate.sh [base]            applique ce qui manque (defaut : DB_NAME de back/.env)
#   ./infra/sql/migrate.sh --status [base]   liste appliquees / en attente, sans rien modifier
#   ./infra/sql/migrate.sh --new nom_court   cree migrations/AAAAMMJJHHMMSS_nom_court.sql
# Suivi dans la table schema_migrations (version, checksum, applied_at). Connexion : voir lib.sh.
set -euo pipefail
cd "$(dirname "$0")"
source ./lib.sh

STATUS=0
DB_ARG=""
while [ $# -gt 0 ]; do
    case "$1" in
        --status) STATUS=1 ;;
        --new)
            name="${2:-}"
            if ! [[ "$name" =~ ^[a-z0-9_]+$ ]]; then
                echo "Usage : migrate.sh --new nom_court (minuscules, chiffres, _)" >&2
                exit 2
            fi
            # Horodatage UTC plutot qu'un numero : deux branches ne prennent jamais le meme.
            file="migrations/$(date -u +%Y%m%d%H%M%S)_$name.sql"
            printf -- '-- %s\n-- Ne plus modifier une fois mergee : ecrire une nouvelle migration.\n\n' "$name" > "$file"
            echo "Cree : infra/sql/$file"
            exit 0 ;;
        -*) echo "Option inconnue : $1" >&2; exit 2 ;;
        *)  DB_ARG="$1" ;;
    esac
    shift
done
load_db_config "$DB_ARG"

# Memes reglages de session que schema/00_reset.sql : toute migration s'execute en UTC et en mode strict.
SESSION="SET NAMES utf8mb4; SET time_zone = '+00:00';
SET sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO';"

query() { run_mysql "$DB_NAME" --batch --skip-column-names -e "$1"; }

if [ "$(run_mysql --batch --skip-column-names -e "SHOW DATABASES LIKE '$DB_NAME'")" = "" ]; then
    echo "Base '$DB_NAME' introuvable : la creer (reset_db.sh, ou CREATE DATABASE) avant de migrer." >&2
    exit 1
fi

has_journal="$(query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'")"
table_count="$(query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")"
applied=""
if [ "$has_journal" = "1" ]; then
    applied="$(query "SELECT CONCAT(version, ' ', checksum) FROM schema_migrations")"
fi

migrations=(migrations/*.sql)
[ -e "${migrations[0]}" ] || migrations=()

# Version -> checksum enregistre ; une migration deja jouee puis modifiee ne serait rejouee nulle part.
pending=()
verify() {
    local version="$1" expected="$2" recorded
    recorded="$(printf '%s\n' "$applied" | awk -v v="$version" '$1 == v { print $2 }')"
    if [ -z "$recorded" ]; then
        return 1
    fi
    if [ "$recorded" != "$expected" ]; then
        echo "ERREUR : $version a ete modifiee apres avoir ete appliquee sur '$DB_NAME'." >&2
        echo "  Restaurer le fichier et ecrire une nouvelle migration (base locale : reset_db.sh)." >&2
        exit 1
    fi
}

baseline_sum="$(checksum schema/*.sql)"
baseline_state=applied
if [ "$has_journal" != "1" ]; then
    baseline_state=$([ "$table_count" = "0" ] && echo install || echo adopt)
elif ! verify "$BASELINE" "$baseline_sum"; then
    echo "ERREUR : schema_migrations existe sans $BASELINE : base incoherente, la recreer (reset_db.sh)." >&2
    exit 1
fi
for file in "${migrations[@]}"; do
    verify "$(basename "$file" .sql)" "$(checksum "$file")" || pending+=("$file")
done

if [ "$STATUS" -eq 1 ]; then
    echo "Base '$DB_NAME' sur $DB_HOST:$DB_PORT"
    case "$baseline_state" in
        applied) echo "  [x] $BASELINE" ;;
        install) echo "  [ ] $BASELINE (base vide : schema/ sera joue)" ;;
        adopt)   echo "  [ ] $BASELINE (tables deja presentes : sera marque comme applique)" ;;
    esac
    for file in "${migrations[@]}"; do
        version="$(basename "$file" .sql)"
        if printf '%s\n' "${pending[@]}" | grep -qx "$file"; then
            echo "  [ ] $version"
        else
            echo "  [x] $version"
        fi
    done
    exit 0
fi

# Le script et son INSERT partent dans le meme flux : mysql s'arrete a la 1re erreur,
# une migration en echec n'est donc jamais marquee comme appliquee.
apply() {
    local version="$1" sum="$2"
    shift 2
    {
        echo "$SESSION"
        cat "$@"
        printf '\nDELIMITER ;\n%s\n' "$JOURNAL"
        journal_insert "$version" "$sum"
    } | run_mysql "$DB_NAME"
}

case "$baseline_state" in
    install)
        apply "$BASELINE" "$baseline_sum" schema/*.sql
        echo "Schema de depart importe dans '$DB_NAME' ($(ls schema/*.sql | wc -l) fichiers)" ;;
    adopt)
        # Base creee avant les migrations : son schema est celui de schema/, on ne le rejoue pas.
        { echo "$JOURNAL"; journal_insert "$BASELINE" "$baseline_sum"; } | run_mysql "$DB_NAME"
        echo "Base existante '$DB_NAME' rattachee au schema de depart (si elle date d'avant le dernier schema/, faire un reset_db.sh)." ;;
esac

if [ ${#pending[@]} -eq 0 ]; then
    echo "Base '$DB_NAME' a jour."
    exit 0
fi
for file in "${pending[@]}"; do
    version="$(basename "$file" .sql)"
    echo "Migration $version..."
    if ! apply "$version" "$(checksum "$file")" "$file"; then
        echo "ECHEC de $version : rien n'est enregistre pour elle, mais le DDL deja execute reste (MariaDB ne l'annule pas)." >&2
        echo "  Corriger la base ou le fichier, puis relancer." >&2
        exit 1
    fi
done
echo "Base '$DB_NAME' a jour (${#pending[@]} migration(s) appliquee(s))."
