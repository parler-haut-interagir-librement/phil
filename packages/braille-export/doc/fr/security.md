<!-- phil-dev source-sha256=be361488d3dd96b59d546607beeb5f8a2a4510cc816f5868611908f04626ab53 -->
# Sécurité

Un document peut venir de n'importe qui : le texte d'un membre, un fichier importé. Ce qu'il peut faire est borné.

- **Son texte est une donnée.** Il est traduit cellule par cellule ; rien n'en est exécuté, lu comme du balisage ni
  employé comme chemin. Un texte qui n'est pas de l'UTF-8 valide voit ses octets invalides remplacés avant que rien ne
  le lise.
- **Sa taille est bornée.** `PageFormat::$maxPages`, 10,000 par défaut, arrête une mise en page avec
  `DocumentTooLong` ; le retrait des listes imbriquées est borné lui aussi. La traduction parcourt chaque texte une
  fois, et la mise en page chaque mot une fois.
- **Ce qu'il écrit est du braille.** Chaque texte qu'un traducteur rend est vérifié : des cellules braille seulement,
  une par texte envoyé, avant que la mise en page le lise, pour qu'un traducteur ne puisse pas glisser du texte dans un
  fichier. Un fichier PEF échappe ses métadonnées et omet ce que XML 1.0 ne peut pas contenir ; ses rangées ne
  contiennent que des cellules braille.
- **Rien n'est gardé.** L'exportateur, les traducteurs et les écrivains ne gardent aucun état entre deux documents.

La mise en page est fuzzée avec n'importe quel texte, en français et en anglais, sous la forme de chaque sorte de bloc :
chaque rangée est du braille, aucune rangée n'est plus large que sa ligne, aucune page plus longue que sa page, et le
fichier BRF peut toujours s'écrire. Voir [Tests](testing.md).

Un traducteur qui démarre un programme borne ce qu'il lui donne ; voyez les notes de sécurité de ce paquet.
