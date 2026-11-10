#!/bin/sh
# Recopie dans SQLSandbox les bases, les exercices et le prompt de correction des
# intentions du package QueryCraft, qui en est la référence (sens unique).
#
#   outils/importe-querycraft.sh <dépôt QueryCraft> [--dry-run]
#
# - bases : chaque querycraft/data/<nom>.db remplace la base de bdd/*/ dont le config.xml
#   déclare <fichier nom="<nom>.db"/> ; une base sans dossier est signalée (il faut lui
#   écrire un config.xml, avec sa description) ;
# - exercices : querycraft/exos/*.json -> bdd/exercices/ (exercices locaux, servis quand
#   exos_source n'est pas renseigné dans sqlsandbox.ini) ;
# - prompt : querycraft/templates/intention_prompt.md -> templates/.
#
# Vérifier ensuite avec « php tests/run.php », puis committer.
set -eu

if [ $# -lt 1 ]; then
	echo "Usage : $0 <dépôt QueryCraft> [--dry-run]" >&2
	exit 1
fi
QC="$1/querycraft"
SIMULATION="${2:-}"
cd "$(dirname "$0")/.."
[ -d "$QC/data" ] || { echo "Dossier introuvable : $QC/data" >&2; exit 1; }

copie() { # copie <source> <destination> : seulement si le contenu diffère
	if [ -f "$2" ] && cmp -s "$1" "$2"; then return 0; fi
	echo "  $(basename "$1") -> $2"
	[ "$SIMULATION" = "--dry-run" ] || cp "$1" "$2"
}

echo "Bases :"
for db in "$QC"/data/*.db; do
	nom=$(basename "$db")
	conf=$(grep -l "<fichier nom=\"$nom\"" bdd/*/config.xml 2>/dev/null | head -n 1 || true)
	if [ -z "$conf" ]; then
		echo "  ⚠️  $nom : aucun dossier de bdd/ ne la déclare (créer bdd/<dossier>/config.xml)"
		continue
	fi
	copie "$db" "$(dirname "$conf")/$nom"
done

echo "Exercices :"
mkdir -p bdd/exercices
for exo in "$QC"/exos/*.json; do copie "$exo" "bdd/exercices/$(basename "$exo")"; done
for exo in bdd/exercices/*.json; do
	[ -f "$QC/exos/$(basename "$exo")" ] || echo "  ⚠️  $(basename "$exo") n'existe plus dans QueryCraft (à supprimer ?)"
done

echo "Prompt :"
mkdir -p templates
copie "$QC/templates/intention_prompt.md" templates/intention_prompt.md

[ "$SIMULATION" = "--dry-run" ] && echo "(simulation : rien n'a été copié)"
exit 0
