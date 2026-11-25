# CLAUDE.md — Règles fondamentales du projet KRAB

Ce fichier définit les règles que Claude (et tout contributeur) doit respecter
lors du développement de KRAB. Il doit être lu avant toute intervention.

---

## 1. Présentation du projet

KRAB est une plateforme communautaire professionnelle destinée à la Suisse.

Elle permet aux membres de :
- proposer leurs services ;
- rechercher du travail ;
- publier des missions ;
- trouver des collaborateurs ;
- organiser leurs activités avec un agenda personnel.

---

## 2. Valeurs et philosophie

KRAB possède une identité libertaire et anarchiste discrète, fondée sur
l'autonomie, l'entraide, la solidarité et la coopération.

La plateforme repose sur une organisation horizontale :
- Tous les membres ont le même statut et les mêmes droits.
- Pas de hiérarchie commerciale, de membres VIP ou de privilèges.
- Pas de publicité, de classement de popularité ou de mise en avant payante.
- Aucun système de notation ou d'avis entre membres.
- La modération sert uniquement à protéger la communauté.

Toute fonctionnalité proposée doit respecter ces principes. En cas de doute,
demander avant de développer.

---

## 3. Technologies

- PHP natif, compatible avec PHP 8.2.
- MySQL avec PDO.
- HTML.
- CSS externe.
- JavaScript natif, dans des fichiers séparés.
- Pas de framework lourd.
- Code simple, lisible, documenté et compréhensible par un débutant.

Environnement de développement local : **MAMP 6.8.1 sur macOS**.

---

## 4. Règle absolue concernant le CSS

**TOUT le graphisme doit être réalisé exclusivement dans des fichiers CSS externes.**

Il est strictement interdit d'utiliser :
- des attributs HTML `style="..."` ;
- des blocs HTML `<style>...</style>` ;
- du CSS intégré dans les fichiers PHP ;
- du CSS généré directement par le PHP.

Organisation :
- Les fichiers CSS se trouvent dans `assets/css/`.
- Un fichier `assets/css/variables.css` centralise les couleurs, les polices,
  les dimensions, les espacements et les arrondis (variables CSS).
- Les autres fichiers CSS utilisent ces variables plutôt que des valeurs en dur.

Le site doit être **responsive** : ordinateur, tablette et téléphone.

Pour modifier l'apparence d'un élément depuis le PHP ou le JavaScript, on
ajoute ou retire une **classe CSS**, jamais un style direct.

---

## 5. Architecture modulaire

Le projet est organisé en modules indépendants :

| Dossier                 | Rôle                                                   |
|-------------------------|--------------------------------------------------------|
| `includes/`             | Configuration, base de données, sécurité, fonctions communes |
| `modules/agenda/`       | Calendrier et disponibilités                           |
| `modules/members/`      | Profils et annuaire                                    |
| `modules/listings/`     | Offres et propositions de travail                      |
| `modules/applications/` | Candidatures et invitations                            |
| `modules/messages/`     | Messagerie privée                                      |
| `modules/account/`      | Paramètres personnels                                  |
| `modules/moderation/`   | Signalements et modération                             |
| `assets/css/`           | Feuilles de style                                      |
| `assets/js/`            | JavaScript                                             |
| `assets/images/`        | Images et logo                                         |
| `database/`             | Installation SQL et migrations                         |
| `admin/`                | Outils d'administration                                |
| `uploads/`              | Fichiers envoyés par les membres                       |

---

## 6. Fonctionnement de l'agenda

Chaque membre possède son propre agenda. Il peut :
- renseigner ses disponibilités ;
- consulter les missions proposées ;
- répondre aux offres ;
- recevoir des invitations ;
- accepter ou refuser des missions ;
- retrouver automatiquement les missions validées dans son agenda.

Chaque membre peut **simultanément** rechercher du travail et en proposer.
Il n'y a pas de distinction de type de compte (« client » / « prestataire »).

---

## 7. Confidentialité et visibilité

Les membres contrôlent la visibilité de leurs informations.

**Disponibilités** :
- privées ;
- visibles par tous les membres ;
- partagées uniquement avec certains membres.

**Offres de travail** :
- publiques pour tous les membres ;
- privées, accessibles uniquement aux personnes invitées ;
- ciblées vers certains membres sélectionnés.

Une mission ne devient **confirmée** dans les agendas qu'après validation de
toutes les personnes concernées.

**Les contrôles de confidentialité doivent être réalisés côté serveur en PHP**,
pas seulement dans l'interface (masquer un bouton ou un lien ne suffit pas).

---

## 8. Sécurité

À appliquer dans tout le projet :
- Mots de passe protégés avec `password_hash()` et `password_verify()`.
- Requêtes SQL **toujours** préparées avec PDO (jamais de variable concaténée
  dans une requête SQL).
- Protection CSRF sur tous les formulaires et actions qui modifient des données.
- Protection contre les injections SQL et les failles XSS (échapper toute
  donnée affichée, par exemple avec `htmlspecialchars()`).
- Sessions sécurisées.
- Vérification des autorisations pour chaque fonctionnalité et chaque action.
- Gestion sécurisée des fichiers envoyés (type, taille, nom, emplacement).

---

## 9. Méthode de travail

Le propriétaire du projet est **débutant en programmation** et souhaite
comprendre et pouvoir modifier lui-même le code.

Claude doit :
- Expliquer les modifications **avant** de les effectuer.
- Privilégier les solutions simples.
- Documenter le code (commentaires clairs, en français).
- Ne pas installer de dépendances sans demander.
- Ne pas supprimer ou réorganiser des fichiers sans autorisation.
- Ne pas modifier l'architecture générale sans accord préalable.
- Ne jamais commencer une fonctionnalité importante sans validation.
