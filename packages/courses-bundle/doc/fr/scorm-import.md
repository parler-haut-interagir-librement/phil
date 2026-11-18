<!-- phil-dev source-sha256=8b7c5b12ac2d54beb3221ed53392c43ce8cabb208126a5dbefff6403e25df931 -->
# Import SCORM

Le bundle importe des paquets SCORM 1.2 et SCORM 2004 pour migrer des cours d'une autre plateforme. Il lit le paquet et
son manifeste, crée le cours et stocke les fichiers ; il n'exécute aucun environnement d'exécution SCORM. Un SCO est
présenté à l'apprenant, encadré depuis l'origine du contenu, et l'apprenant le marque terminé.

```sh
php bin/console phil:courses:import var/import/safety-course.zip
php bin/console phil:courses:import var/import/safety-course.zip --title="Workshop safety"
```

```php
$report = $importer->import('/path/to/package.zip');
$report->course;     // the new course
$report->warnings;   // what was left out or kept for information
```

## Lecture du manifeste

`imsmanifest.xml`, à la racine de l'archive, est lu par phil/elearning-packages dans les limites de [Sécurité des
paquets importés](security.md) : aucun type de document, aucune entité, une taille, une profondeur et un nombre
d'éléments bornés. Les éléments sont repérés par leur nom local, de sorte qu'un manifeste avec ou sans les espaces de
noms usuels se lit de la même façon.

La version vient de `<schemaversion>` : `1.2` est SCORM 1.2 ; `CAM 1.3`, `1.3…` et toute édition de `2004` sont
SCORM 2004. Sans elle, les espaces de noms des extensions d'empaquetage de contenu d'ADL décident. Un manifeste d'une
autre version est refusé.

## Ce que devient le paquet

| Dans le manifeste | Dans le cours |
|---|---|
| L'organisation par défaut, sinon la première | Le cours, dont le titre est celui de l'organisation |
| Un élément du premier niveau qui a des enfants | Un module ; ses feuilles deviennent ses activités, les titres des éléments intermédiaires en préfixe, comme dans « Chisels / Care » |
| Un élément du premier niveau sans enfants | Une activité d'un module nommé d'après l'organisation |
| Une feuille dont la ressource a un fichier dans le paquet | Une activité `scorm`, son fichier de lancement résolu par rapport à chaque `xml:base`, et les `parameters` de l'élément ajoutés |
| Une feuille dont la ressource est une URL absolue | Une activité `link` |
| L'identifiant du manifeste, et celui de chaque élément | La source du cours, et celle de chaque activité |
| Chaque fichier de l'archive | Stocké sous le répertoire du cours |

Chaque activité est obligatoire, satisfaite une fois que l'apprenant la marque terminée. Les paramètres d'une activité
`scorm` conservent, à titre d'information, le type SCORM de sa ressource, la version, le score de maîtrise de SCORM 1.2
comme score normalisé, les prérequis de SCORM 1.2, et le seuil d'achèvement et le séquencement de SCORM 2004.

## Ce qui n'est pas importé

| Laissé de côté, ou conservé à titre d'information | Pourquoi |
|---|---|
| Les autres organisations | Un cours a un seul plan |
| Les éléments cachés, et les éléments qui ne lancent rien | Rien à montrer ; le rapport nomme chacun |
| Les fichiers de lancement et les fichiers déclarés absents du paquet | L'activité est créée ; le rapport nomme chaque fichier, avant les autres notes |
| Les règles de séquencement et de navigation de SCORM 2004, la collection de séquencement | Conservées avec les activités, non appliquées : imposez plutôt l'ordre du cours |
| Les prérequis de SCORM 1.2 | Conservés, non appliqués : imposez plutôt l'ordre du cours |
| Les scores de maîtrise et les seuils d'achèvement | Conservés : sans environnement d'exécution, le contenu ne rapporte aucun score |
| Les objectifs, le modèle de données d'exécution, les données de suspension | Ils vivent dans un environnement d'exécution que le bundle n'exécute pas |
| Les résultats des apprenants | Un paquet n'en porte aucun ; voir ci-dessous |

## Import des résultats

Un paquet SCORM ne porte aucun résultat d'apprenant : ils vivent dans la plateforme précédente. Son exportation, en CSV,
s'importe pour un cours importé :

```sh
php bin/console phil:courses:import-results 0192f0c1-5c5e-7a3b-9e1d-0123456789ab var/import/results.csv
```

La première ligne nomme les colonnes, dans n'importe quel ordre :

| Colonne | Contient |
|---|---|
| `learner` | L'identifiant stable de l'apprenant sur ce site, jamais une adresse courriel |
| `item` | L'identifiant de l'élément dans le manifeste |
| `status` | Le `lesson_status` de SCORM 1.2 — `passed`, `completed`, `failed`, `incomplete`, `browsed`, `not attempted` —, ou le `completion_status` et le `success_status` de SCORM 2004 joints par `+`, comme `completed+passed` |
| `score` | Facultatif : un score sur cent |
| `date` | Facultatif : le moment, en ISO 8601, ou une date prise en UTC ; maintenant si vide |

L'apprenant est inscrit s'il ne l'est pas. Une ligne illisible est signalée par son numéro et laissée de côté, les autres
sont importées ; 100 000 lignes au plus sont lues. `ResultImporter::import()` fait de même à partir de lignes que
l'application lit elle-même.
