<!-- phil-dev source-sha256=71b14d201ec349cffc76a0088c97c633c5dc4f67eb08e10383fa9e3994ffc8cb -->
# La version Web

L'archive garde de chaque numéro une version Web : du HTML pour une page, rendu et purifié une seule fois, à la
publication ou au partage du numéro, et conservé tel quel jusqu'à une nouvelle publication. Une page de l'archive ne
rend jamais une campagne.

## Rendue avec des données neutres

`NeutralWebVersion` rend une campagne comme `phil/newsletter-bundle` l'enverrait, sans abonné :

- une campagne mise en page par la mise en page de la maison donne son corps seul — son Markdown compilé par
  `phil/markup` —, sans le pied de page d'un courriel ;
- une campagne mise en page par un gabarit du site est rendue par le bac à sable de l'infolettre, avec des valeurs
  neutres : le nom de sa liste et de son site, et, pour `unsubscribe_url` et `preferences_url`, un substitut sur le
  domaine réservé `subscriber.invalid` ; un gabarit dont la clé n'existe plus donne le corps seul.

Puis, à partir du corps de ce qui a été rendu :

- tout lien vers le substitut est retiré, avec le paragraphe ou l'élément de liste qu'il laisse sans texte ni élément ;
- tout élément qu'un style en ligne cache — `display: none`, le texte d'aperçu d'un courriel — est retiré ;
- toute image d'un pixel ou moins, selon sa `width` ou sa `height`, et toute image du substitut sont retirées ;
- le substitut écrit n'importe où ailleurs est effacé ;
- le HTML est purifié par `phil/markup` à son niveau le plus large, qui supprime scripts, cadres, formulaires, styles et
  gestionnaires d'événements.

Aucune adresse n'est réécrite pour le suivi des clics et aucun lien signé n'est fabriqué : la version Web ne contient
aucun jeton.

## Vie privée

L'archive ne garde aucun renseignement personnel : la version Web est rendue sans abonné, et un numéro nomme sa
campagne, sa liste et son site, jamais une personne. Effacer un abonné — `Subscriptions::forget()` de
`phil/newsletter-bundle`, ou une demande d'effacement par `phil/privacy-bundle` — laisse l'archive telle quelle, et
l'archive n'a rien à répondre à une demande d'accès.

Ce que dit la campagne elle-même relève de l'éditeur : une campagne qui nomme une personne dans son corps la nomme aussi
dans l'archive. La dépublier la retire.
