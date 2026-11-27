<!-- phil-dev source-sha256=1b22dcdb1ebb85d47cb70ad8f737511cf16f32c0b82d711246d3d4425d0ac69e -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/mail-auth-check` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-24

### Ajouté

- `SenderProfile` : le domaine d'envoi, le chemin de retour, les sélecteurs DKIM, les inclusions SPF et la politique
  DMARC minimale qu'attend un expéditeur, ses noms en ASCII minuscule, les noms internationalisés convertis par
  ext-intl.
- `MailAuthCheck`, `Report`, `Finding` et `Status` : les vérifications d'un profil exécutées dans l'ordre, leurs
  constats rassemblés, et le profil autorisé à envoyer quand aucune n'a échoué.
- Le port `TxtResolver`, `DnsGetRecordResolver` sur le résolveur du système et `StaticTxtResolver` à partir d'une table
  ; chaque nom demandé une fois par vérification.
- `SpfParser`, `SpfAnalyser` et `SpfCheck` : les enregistrements SPF lus dans des bornes, les inclusions et les
  redirections suivies, les recherches DNS comptées d'après la limite de dix, les boucles, les inclusions brisées et les
  macros signalées, les inclusions attendues et le résultat `all` vérifiés.
- `DkimKeyParser` et `DkimCheck` : la clé de chaque sélecteur attendu lue, les clés révoquées et de test signalées, les
  clés RSA mesurées (au moins 1024 bits, un avertissement sous 2048) et les clés Ed25519 acceptées.
- `DmarcParser`, `DmarcTreeWalk` et `DmarcCheck` : l'enregistrement DMARC trouvé par le parcours de l'arbre DNS de la
  RFC 9989, le domaine organisationnel sans la Public Suffix List, la politique comparée à celle du profil, les rapports
  et les tests signalés, et l'alignement du chemin de retour vérifié.
- `ReturnPathCheck` : l'enregistrement SPF d'un chemin de retour qui diffère du domaine d'envoi, avec les inclusions
  attendues.
- Le port `Check`, pour les vérifications propres à une application.
- Une cible de fuzzing pour les analyseurs SPF, DKIM et DMARC et le parcours SPF.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
