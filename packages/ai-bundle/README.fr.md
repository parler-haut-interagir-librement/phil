<!-- phil-dev source-sha256=24f5fcf0d3da1fe7b6eb9b5f4a2893f0d19b8482cf495b2c88aaac359f4f5296 -->
# phil/ai-bundle

**Des tâches de modèle de langage qui proposent sans jamais imposer, comptées au jeton près, et qui oublient chaque
mot.**

phil/ai-bundle exécute les tâches de modèle de langage d'une application Symfony sur Symfony AI. Une tâche a une sorte,
comme `draft.rewrite` ou `character.describe`, dont les invites sont des messages de traduction, rendus dans la locale
que lit la personne. Elle s'exécute sur une plateforme et un modèle de la configuration, avec la clé de l'application
ou avec la clé personnelle de la personne gardée par `phil/user-secrets-bundle`. Le sujet à qui elle est facturée (un
compte, un locataire, un projet) doit avoir de la place dans ses quotas et son budget de `phil/usage-meter` avant
qu'elle commence, et il est débité ensuite des jetons et du coût qu'elle a consommés ; un appel qui échoue est rendu.
Une tâche demande du texte, en flux continu ou entier, des données façonnées par une classe, une image ou de la parole,
et peut offrir au modèle des outils à proposer. La réponse du modèle revient sous forme de `Suggestion` : rien n'est
appliqué, et aucun outil n'est exécuté, tant que l'application ne l'accepte pas, telle que proposée ou modifiée par la
personne, ou ne la rejette pas. Un journal garde, pour chaque tâche, la sorte, le sujet, la plateforme, le modèle, la clé qui a payé, les
jetons, le coût, la durée et la décision. Il ne garde jamais l'invite, la réponse ni rien de ce que la personne a écrit.

Le bundle s'adresse aux applications Symfony qui placent un modèle à côté du travail d'une personne : un outil
d'écriture qui réécrit, corrige ou traduit un passage, un assistant de meneur de jeu qui invente un personnage, un lieu
ou une image de couverture, une offre gratuite comptée en jetons et une offre payante avec un budget mensuel, ou des
membres qui apportent leur propre clé. Il dit aux gens qu'ils parlent à une IA, divulgue ce qu'une suggestion a produit,
dresse la liste des usages de l'IA de l'application et répond aux demandes de vie privée par `phil/privacy-bundle`.

C'est un bundle bâti sur `symfony/ai-platform`, Doctrine ORM, `phil/usage-meter`, `phil/creation-disclosure`,
`phil/url`, les contrats de traduction et de client HTTP de Symfony et PSR-20. `phil/user-secrets-bundle` et
`phil/privacy-bundle` sont facultatifs. Symfony AI dialogue avec les fournisseurs ; le bundle ajoute ce dont une
application a besoin autour de chaque appel, et un adaptateur d'images Replicate que Symfony AI n'offre pas.

La conception suit les couches du monodépôt. `Domain` contient la ligne de journal `JournalEntry`, ses types de valeur,
ses totaux et le port du journal. `Application` contient `AiTasks`, le catalogue des tâches, les gabarits d'invite, la
liste de prix, le quota, `Suggestion` et `SuggestionStream`, les avis, le registre des usages et les ports `Completions`
et `UserKeys`. `Infrastructure` contient l'adaptateur Symfony AI, la plateforme d'images Replicate, l'adaptateur de
`phil/user-secrets-bundle`, le module de vie privée, les journaux Doctrine et en mémoire, et le câblage des services.

## État

| Partie | État |
|---|---|
| Des tâches par sorte sur les plateformes Symfony AI, avec leur modèle et leurs options | Implémenté et testé |
| Les gabarits d'invite comme messages de traduction, simples ou ICU, avec les replis du traducteur | Implémenté et testé |
| Des suggestions acceptées, acceptées après modification, ou rejetées par l'application | Implémenté et testé |
| Un journal de la sorte, de la sortie, du sujet, de la plateforme, du modèle, de la clé, des jetons, du coût, de la durée et de la décision, sans contenu | Implémenté et testé |
| Les coûts tirés d'une liste de prix par modèle et par appel, les totaux additionnés en SQL par sujet et par période, l'effacement par sujet | Implémenté et testé, sur SQLite, MySQL 8.4 et PostgreSQL 17 |
| Les quotas de jetons et de tâches, les budgets de coût et un plafond pour toute l'application par `phil/usage-meter` ; un appel échoué est rendu | Implémenté et testé |
| Les réponses en flux continu, la sortie structurée, les appels d'outils proposés et jamais exécutés, par Symfony AI | Implémenté et testé |
| Les images et la parole : Hugging Face et la synthèse vocale par les ponts de Symfony AI, les images Replicate par l'adaptateur du bundle | Implémenté et testé |
| Les avis qu'une personne parle à une IA, la divulgation de ce qu'une suggestion a produit par `phil/creation-disclosure`, le registre des usages | Implémenté et testé |
| Le module du journal de `phil/privacy-bundle` : exportation et effacement | Implémenté et testé |
| La clé personnelle de la personne par `phil/user-secrets-bundle`, selon une politique | Implémenté et testé |
| Le stockage Doctrine, et un journal en mémoire pour les tests | Implémenté et testé |
| Un serveur MCP | Ne fait pas partie du bundle : `phil/mcp-bundle` en sert un |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, une plateforme, la table. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_ai`. |
| [Tâches et suggestions](doc/fr/tasks.md) | Définir des tâches, écrire leurs invites, les exécuter, décider de ce qu'elles proposent. |
| [Sorties, flux continu et outils](doc/fr/outputs.md) | La sortie structurée, les appels d'outils proposés, les images et la parole, l'adaptateur Replicate, le flux continu. |
| [Clés et quotas](doc/fr/keys-and-quotas.md) | Avec quelle clé une tâche s'exécute, la clé personnelle, les quotas de jetons et de tâches, les budgets et le plafond. |
| [Le journal](doc/fr/journal.md) | Ce qui est enregistré et ce qui ne l'est jamais, les coûts, les totaux, l'effacement. |
| [Transparence et vie privée](doc/fr/transparency.md) | Les avis, la divulgation de ce qu'une suggestion a produit, le registre des usages, les demandes de vie privée. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lève le bundle, et quand. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/ai-bundle symfony/ai-platform
```

```yaml
# config/packages/phil_ai.yaml
phil_ai:
    platforms:
        main: { platform: 'app.ai_platform' }    # a Symfony AI PlatformInterface service
    prices:
        the-model: { input: 3000000, output: 15000000 }    # micros per million tokens
    tasks:
        draft.rewrite: { platform: main, model: 'the-model', options: { max_tokens: 1024 } }
```

```yaml
# translations/phil_ai.en.yaml
draft.rewrite.system: 'You are a careful editor. Keep the author''s voice.'
draft.rewrite.user: 'Rewrite this passage: {text}'
```

```php
$suggestion = $tasks->run(new TaskRequest('draft.rewrite', $project->reference(), $locale, ['text' => $passage], $member->reference()));

// Show $suggestion->text; then, on the person's answer:
$tasks->accept($suggestion->id);        // or acceptEdited(), or reject()
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Symfony AI Platform 0.15, Doctrine ORM 3.7. `symfony/http-client` pour
l'adaptateur Replicate, `phil/user-secrets-bundle` pour les clés personnelles des gens, `phil/privacy-bundle` pour les
demandes de vie privée, chacun seulement quand il sert.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
