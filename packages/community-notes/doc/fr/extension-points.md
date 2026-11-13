<!-- phil-dev source-sha256=3855c3479bcd436f4e27c2bb1feb2590e937e0c9af3ff234904eacc25fcb6478 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Dire qui peut rédiger et évaluer | Implémentez `Contract\Qualification`, ou utilisez `Security\AuthorizationQualification` avec des rôles ou des *voters* : voir [Qualification](qualification.md) |
| Conserver les notes | Implémentez `Contract\NoteRepository` |
| Conserver les évaluations et donner l'historique de deux évaluateurs | Implémentez `Contract\RatingRepository`, qui étend `Contract\RatingHistory` |
| Changer les nombres de la règle | Passez à `CommunityNotes` une `CrossAgreementRule` de votre cru |
| Agir après qu'une note est rédigée, évaluée, décidée, retirée ou effacée | Écoutez les événements : voir [Événements et messages](events.md) |

## Les stockages

`NoteRepository` obtient, énumère par sujet et par auteur — les plus anciennes d'abord —, enregistre et supprime des
notes. Un stockage en base de données conserve la décision en JSON, par `Decision::toArray()`, et reconstruit la note
avec `Note::restore()`.

`RatingRepository` conserve une évaluation par évaluateur et par note, la dernière remplaçant la précédente ; énumère
les évaluations d'une note et d'un évaluateur ; supprime les évaluations d'un évaluateur et d'une note ; et répond à
`between($first, $second, $exceptNote)` : les notes que les deux évaluateurs ont évaluées, celle en cours de décision
exclue, et celles sur lesquelles ils ont rendu le même verdict. Une base de données y répond en une seule requête à
partir d'un index sur l'évaluateur et la note ; la règle le lui demande pour jusqu'à `maxRaters × (maxRaters − 1) ÷ 2`
paires par décision.

Les stockages ne gardent aucun état entre les requêtes autre que ce qu'ils stockent, pour que les services restent
sûrs dans un *worker*.
