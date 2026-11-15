<!-- phil-dev source-sha256=61a7124ece2fde7999efc9f636e4702ec66ee60f5fa6f3617e954af2879a09a0 -->
# Sécurité

Les fichiers de sous-titres et de transcriptions viennent de membres, d'éditeurs, de services de reconnaissance vocale
et d'autres sites. La bibliothèque les lit comme hostiles.

## Un travail borné

`Limits` plafonne les octets d'un document, ses repères, le texte d'un repère et les problèmes qu'une inspection
liste ; au-delà d'un plafond, le lecteur lève `Exception\LimitExceeded` avant que cela ne coûte davantage. Le JSON est
décodé avec une imbrication bornée à huit niveaux et ses grands entiers gardés comme chaînes, qui sont ensuite refusées
comme secondes. Chaque analyseur s'exécute en temps linéaire de son entrée : aucun motif ne revient en arrière sur une
ligne entière. Les analyseurs sont soumis au fuzzing — voir [Tests](testing.md).

L'UTF-8 invalide est remplacé, et la marque d'ordre des octets retirée, avant la lecture d'un fichier.

## Du HTML échappé par construction

Deux méthodes écrivent du HTML : `CueText::toHtml()`, pour la transcription interactive d'un lecteur, et
`Html\HtmlTranscriptWriter`. Aucune ne copie de balisage de la source :

- chaque suite de texte et chaque valeur d'attribut passe par `htmlspecialchars()`, avec `ENT_QUOTES`, `ENT_HTML5` et
  `ENT_SUBSTITUTE` ;
- `toHtml()` n'écrit que `b`, `i`, `u`, `ruby`, `rt`, `span` et `br` ; un `span` de classe garde les classes qui sont de
  simples jetons de lettres, de chiffres, de `-` et de `_`, un `span` de langue une balise de langue de lettres, de
  chiffres et de `-`, une voix son nom dans `data-voice` ; toute autre balise est écartée et les balises restées
  ouvertes sont fermées ;
- `HtmlTranscriptWriter` écrit le texte brut de chaque repère, jamais son balisage.

Un texte de repère de n'importe quelle source peut être affiché tel que ces méthodes le retournent.

## Les liens des chapitres

L'`url` et l'`img` d'un chapitre de Podcasting 2.0 sont lues par `phil/url` : une adresse qui n'est pas `http` ou
`https`, qui contient des identifiants de connexion, ou qui nomme une adresse IP ou `localhost`, fait refuser le
fichier. La bibliothèque ne les récupère jamais.

## Ce qui reste à l'application

- La taille d'un téléversement avant qu'il n'atteigne la bibliothèque, et les limites qu'elle donne aux lecteurs.
- Les blocs `STYLE` d'un fichier WebVTT sont du CSS de l'auteur du fichier : une application qui sert le fichier tel
  quel aux navigateurs les garde, et celle qui stylise son propre lecteur les écarte avec `StylePolicy::Drop`.
- Les fragments HTML vont dans la page de l'application et sous sa Content Security Policy.
