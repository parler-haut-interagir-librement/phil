<!-- phil-dev source-sha256=9394f3811e912b3c735f3a1c226e27590168ee8896af7acd4f57b02101e70cfd -->
# Fichiers de questions

Les questions d'un quiz et d'un sondage viennent de fichiers CSV qui suivent les mêmes conventions : la personne qui en
écrit un dans un tableur peut écrire l'autre. Chaque fichier est un `ImportProfile` du paquet qui possède les
questions, avec sa propre cible ; cette bibliothèque lit le fichier, fait l'essai à blanc et écrit le plan, comme pour
tout autre type.

| Questions de | Profil | Type | Cible | Sa documentation |
|---|---|---|---|---|
| Une évaluation : les questions de quiz d'une banque, avec leurs bonnes réponses | `Phil\Assessment\Import\QuestionImportProfile` | `assessment-question` | `QuestionImportTarget`, la banque versionnée | [CSV import](https://github.com/parler-haut-interagir-librement/assessment/blob/main/doc/csv-import.md) de `phil/assessment` |
| Un sondage : les questions d'un questionnaire, avec ses sections | `Phil\SurveyBundle\Application\Import\QuestionCsvProfile` | `survey-question` | `QuestionImportTarget`, un questionnaire | [CSV import](https://github.com/parler-haut-interagir-librement/survey-bundle/blob/main/doc/csv-import.md) de `phil/survey-bundle` |

## Les conventions communes

- **Une ligne par question**, selon son identifiant dans la colonne `question_id`, la clé naturelle : une ligne dont
  l'identifiant est connu met à jour cette question — une nouvelle version dans une banque, une question remplacée dans
  un questionnaire —, toute autre ligne en crée une.
- **`type`** nomme le type de la question, parmi les types de son paquet, écrit comme ce paquet l'écrit :
  `single_choice`, `multiple_choice`, `true_false`, `scale`…
- **Le texte de la question** est obligatoire : `stem` pour une évaluation, `label` pour un sondage ; les deux lisent
  les en-têtes `question`, `question text` et `text`, et un sondage lit aussi `stem`.
- **Les listes dans une cellule sont séparées par `|`** : les choix, les éléments à ordonner, les paires à associer, les
  lignes d'une matrice, les étiquettes. Les éléments vides sont écartés et chaque élément est rogné. Un texte qui
  contient `|` ne peut pas s'écrire dans ces fichiers : les formats JSON des paquets n'ont pas cette limite.
- **Un choix sans valeur propre prend sa position**, `1`, `2`, `3`…, que nomme la cellule des bonnes réponses d'une
  évaluation. Un sondage lit aussi `value = label`, la valeur étant ce que lisent ses conditions et ses résultats ;
  dans une évaluation, `=` sépare la prémisse et la cible d'une paire à associer.
- **Oui ou non** se lit comme le lit chaque colonne booléenne de cette bibliothèque : `yes`, `y`, `no`, `n`, `true`,
  `false`, `1`, `0`, `oui`, `o`, `non`, en majuscules ou en minuscules.
- **En-têtes en anglais ou en français**, en majuscules ou en minuscules, avec ou sans accents ; le gabarit d'un profil,
  donné par `TemplateFile::csv()`, écrit les noms des colonnes.
- **Chaque ligne est vérifiée comme son paquet vérifie une question.** Le profil porte une `RowRule` qui lit la ligne
  comme une question dans l'essai à blanc : une ligne que son paquet refuserait est refusée dans l'aperçu, avec la
  cellule en cause et une raison qui ne répète jamais un texte de la ligne. Ce que seule la cible peut dire — une
  banque qui contient la question avec un autre type, un questionnaire qui enfreindrait une règle avec la ligne — est
  dit par l'importation, ligne par ligne.
- **Les lignes refusées reviennent** sous forme d'un fichier CSV de `RejectedRowsFile`, chacune avec sa ligne et ses
  erreurs, à corriger puis à importer de nouveau.

## Limites

Chaque profil donne les `SourceLimits` dans lesquelles son fichier est lu, plus serrées que les valeurs par défaut de
cette bibliothèque : `QuestionImportProfile::limits()` pour une évaluation, 4 Mio, 1000 lignes après l'en-tête et 32
cellules par ligne ; `QuestionCsvProfile::limits()` pour un sondage, 1 Mio, 300 lignes et 32 cellules. Chaque colonne
des deux profils borne ses cellules — un identifiant de 64 caractères, un texte de question, la cellule d'une liste par
la plus longue liste que son paquet accepte. Un fichier hors de ses limites est refusé en entier, avant qu'aucune ligne
soit planifiée.

## Formats non lus

Les fichiers GIFT et QTI ne sont pas lus par ces profils. Un adaptateur peut les lire comme une `RowSource` qui donne
les cellules des conventions ci-dessus, ou écrire les questions par les formats JSON des paquets.
