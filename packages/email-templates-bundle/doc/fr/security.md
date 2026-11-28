<!-- phil-dev source-sha256=5505e6faead3455509f8d3967fc96c07d3fc4f292388c36f9c5d1b1a1ba9bd22 -->
# Sécurité

Les gabarits sont écrits par des gens à qui l'application confie ses courriels, non son serveur. Le bundle garde ce
qu'ils écrivent à l'intérieur du courriel.

## Le bac à sable

- **Une liste d'éléments autorisés, en mode strict.** Chaque source produit son rendu avec le `Sandbox` de Twig et une
  `SecurityPolicy` stricte : une balise, un filtre, une fonction ou un test qui n'est pas listé est refusé, et les seules
  méthodes sont `format()` et `getTimestamp()` d'une date. Rien n'est inclus, étendu, importé ni lu depuis un fichier ;
  `constant`, `attribute`, `source`, `range` et les fonctions fléchées de `map`, `filter` et `reduce` sont hors de
  portée.
- **Un environnement distinct.** Le bac à sable ne voit ni le Twig de l'application, ni ses variables globales, ni ses
  extensions, ni ses gabarits ; les seuls gabarits qu'il peut charger sont les sources qu'on lui donne, par l'empreinte
  de leur contenu.
- **Un travail borné.** Les boucles s'imbriquent sur deux niveaux au plus, sur des listes d'au plus 1000 valeurs, et
  chaque sortie s'arrête à `max_html_bytes`. Les valeurs sont vérifiées selon le schéma avant le rendu : leurs types, la
  longueur d'un texte, la taille d'une liste.
- **L'échappement.** Un corps est échappé comme du HTML, `raw` est refusé, et les composants échappent chaque texte et
  chaque attribut qu'ils écrivent. Un corps en Markdown est purifié par `phil/markup`.
- **Les adresses.** Un bouton ne mène qu'à une adresse `http`, `https` ou `mailto`, une image qu'à une image intégrée ou
  à une adresse `https`, chacune lue par `phil/url` ou `phil/email-address` ; `javascript:` et les adresses relatives
  sont refusés.
- **Vérifié avant d'être enregistré, et de nouveau avant d'être publié.** Un gabarit est compilé, vérifié selon la
  politique et rendu dans chaque variante avant que son brouillon soit enregistré et avant qu'il soit publié : un gabarit
  qui échouerait à l'envoi est donc refusé à l'écriture. Le bac à sable s'applique aussi à chaque rendu : un gabarit
  écrit en contournant le bundle — dans les tables des contenus, ou publié directement par `phil/content-bundle` — est
  rendu selon la même politique et les mêmes limites, et une ligne conservée qui ne contient aucun gabarit lisible est
  refusée.
- **Rien d'envoyé sans être publié.** Un brouillon n'est rendu que dans l'aperçu et dans
  `phil:email-templates:render --draft` ; les courriels envoyés ne lisent que des versions publiées.

## L'aperçu

Le cadre d'aperçu est un `<iframe sandbox="">` — scripts, formulaires, fenêtres contextuelles et navigation désactivés,
origine opaque — et il est servi avec une politique qui ne charge que des images et des styles en ligne, n'exécute aucun
script et n'envoie aucun formulaire, avec `nosniff`, `no-referrer` et `no-store`. Chaque page demande le rôle configuré ;
chaque formulaire porte un jeton CSRF sans état, vérifié selon l'origine de la requête.

## Les renseignements personnels

Les messages ne portent que des identifiants, les valeurs revenant par le `DeliveryResolver` de l'application quand le
message est traité ; les événements et le journal d'audit ne portent que des identifiants ; une violation du schéma
nomme un chemin, jamais une valeur ; un auteur qui n'est pas un identifiant — une adresse courriel — n'est pas conservé.
Les valeurs d'exemple des schémas sont fictives : gardez-les ainsi.

## Ce qui reste à l'application

- Le rôle de l'administration, et qui le détient ; et le rôle de l'administration de `phil/content-bundle`, qui liste
  les racines des gabarits parmi les contenus.
- La mise en page, qui est du code de confiance de l'application, et les extensions Twig qu'elle donne au bac à sable.
- Les images sous `embeds.directory` : tout ce qui s'y trouve peut être intégré.
- Le débit et les destinataires de ce qu'elle envoie.
