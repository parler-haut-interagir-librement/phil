<!-- phil-dev source-sha256=647f064bd82ba590a6be305034c693804320bf8acbd56a2b1ca455719d5bb647 -->
# Formats

## Le CSV lu

`CsvRowSource::fromString()` et `CsvRowSource::fromFile()` lisent le CSV tel que le décrit la RFC 4180 :

- cellules entre guillemets ou non, un guillemet doublé dans une cellule entre guillemets, des sauts de ligne à
  l'intérieur ;
- enregistrements terminés par CRLF, LF ou CR, un dernier enregistrement avec ou sans fin de ligne ;
- la marque d'ordre des octets retirée ;
- le premier enregistrement qui n'est pas vide pris pour l'en-tête, ses cellules débarrassées de leurs espaces de
  bordure ;
- chaque enregistrement gardant la **ligne physique** où il commence, la ligne de l'en-tête et les lignes vides
  comptées, afin qu'une ligne refusée se retrouve dans un tableur par son numéro ;
- une cellule suivie de texte après son guillemet fermant, conservée telle qu'écrite, sa ligne refusée comme mal
  formée.

Le `CsvDialect` fixe le délimiteur — une virgule, un point-virgule, une tabulation ou une barre —, le guillemet, `"` ou
`'`, et l'encodage : UTF-8, Windows-1252 ou ISO-8859-1, converti en UTF-8. Sans dialecte, le délimiteur est celui dont
la première ligne contient le plus, une virgule s'il n'y en a aucun, et l'encodage est UTF-8. Un fichier doté d'une
marque d'ordre des octets est en UTF-8 quoi que dise le dialecte ; un fichier qui n'est pas de l'UTF-8 valide est
refusé, avec le conseil de l'enregistrer en CSV UTF-8 ou de déclarer son encodage.

`SourceLimits` borne ce qui est lu : par défaut 20 Mio, 100 000 lignes après l'en-tête et 256 cellules par ligne. Un
fichier qui les dépasse, un guillemet resté ouvert ou un fichier sans en-tête est refusé en entier avec
`SourceRefused`, avant qu'aucune ligne soit planifiée ; `fromFile()` ne lit jamais plus que la limite et un octet.

## Le CSV écrit

`CsvWriter` écrit de l'UTF-8 avec CRLF, une marque d'ordre des octets en premier pour qu'un tableur lise les accents, et
met entre guillemets une cellule qui contient le délimiteur, le guillemet, un saut de ligne ou une espace à l'une de ses
extrémités. Une cellule qu'un tableur exécuterait comme une formule — commençant par `=`, `+`, `-`, `@`, une tabulation
ou un retour chariot, et qui n'est pas un nombre — est écrite après une apostrophe, et `CsvRowSource` retire cette
apostrophe quand le fichier revient. `guardFormulas: false` désactive les deux.

`TemplateFile::csv()` écrit l'en-tête de chaque colonne d'un profil, et `TemplateFile::filename()` le nom sous lequel
l'offrir, `<type>-template.csv`. `RejectedRowsFile` est décrit dans
[L'essai à blanc](dry-run.md#le-fichier-des-lignes-refusées).

## Les formats d'une plateforme d'apprentissage

`LmsProfiles` contient trois profils. Leurs gabarits écrivent les noms de colonnes ci-dessous ; les autres en-têtes
listés sont reconnus eux aussi, normalisés comme l'explique [Profils](profiles.md#la-correspondance).
`LmsProfiles::all()` les liste dans l'ordre où on les importe : les unités, les personnes, puis les inscriptions.

### Personnes

Type `person`, clé naturelle `email`.

| Colonne | Type | Vérifications | Aussi reconnu |
|---|---|---|---|
| `email` | email | obligatoire, 254 | e-mail, email address, mail, courriel, adresse courriel |
| `given_name` | texte | obligatoire, 100 | first name, firstname, given name, prénom |
| `family_name` | texte | obligatoire, 100 | last name, lastname, surname, family name, nom, nom de famille |
| `external_id` | texte | 64 | id, identifier, employee number, student number, matricule, identifiant |
| `locale` | texte | 35 | language, langue |
| `unit_code` | texte | 64, une référence à `organisational-unit` | unit, department, unité, service, code unité |

### Inscriptions

Type `enrolment`, clé naturelle `person_email` et `course_code` ; `ends_on` n'est jamais antérieur à `starts_on`.

| Colonne | Type | Vérifications | Aussi reconnu |
|---|---|---|---|
| `person_email` | email | obligatoire, 254, une référence à `person` | email, e-mail, courriel, learner email |
| `course_code` | texte | obligatoire, 64, une référence à `course` | course, cours, code cours, code du cours |
| `role` | texte | `learner`, `instructor` ou `observer` | rôle |
| `starts_on` | date | | start, start date, début, date de début |
| `ends_on` | date | | end, end date, fin, date de fin |

Ces formats n'importent pas les cours : la cible répond à la question de savoir si un code de cours existe.

### Unités organisationnelles

Type `organisational-unit`, clé naturelle `code`.

| Colonne | Type | Vérifications | Aussi reconnu |
|---|---|---|---|
| `code` | texte | obligatoire, 64 | unit code, code unité, id |
| `name` | texte | obligatoire, 200 | unit name, unit, nom, unité |
| `parent_code` | texte | 64, une référence à `organisational-unit` | parent, parent unit, code parent, unité parente |

Une unité sans parent est à la racine. Un parent se trouve dans le même fichier, plus haut ou plus bas, ou parmi les
unités déjà connues ; le plan écrit chaque parent avant ses enfants.

```csv
code,name,parent_code
sci-math,Mathematics,sci
sci,Sciences,
sci-math-alg,Algebra,sci-math
```

### Questions

Les questions d'un quiz et d'un sondage ont chacune un profil, dans les paquets qui les possèdent, et partagent les
conventions de [Fichiers de questions](question-files.md).
