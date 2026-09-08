=== Extender for Advanced Shipment Tracking ===
Contributors: benoitbonavia
Tags: woocommerce, expedition, suivi de colis, advanced shipment tracking
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Étend Advanced Shipment Tracking for WooCommerce : règles et automatismes regroupés dans une extension unique.

== Description ==

Extender for Advanced Shipment Tracking remplace les snippets épars gravitant autour du suivi
d'expédition par une extension structurée.

Chaque règle devient un « module » autonome, activable individuellement depuis
WooCommerce → Réglages → Suivi d'expédition → Modules.

Le plugin dépend de WooCommerce et de Advanced Shipment Tracking for WooCommerce, sans lesquels il ne
s'active pas. Certains modules peuvent en outre dépendre de l'extension Boxtal Connect : celle-ci reste
optionnelle pour le plugin dans son ensemble — seuls les modules qui la déclarent restent inactivables
tant qu'elle n'est pas installée.

Le plugin déclare sa compatibilité avec le stockage haute performance des commandes (HPOS)
et avec les blocs Panier et Commande.

Les mises à jour sont distribuées depuis le dépôt GitHub du projet et apparaissent
directement dans l'écran Extensions de WordPress.

== Installation ==

1. Téléverser l'archive depuis Extensions → Ajouter → Téléverser une extension.
2. Activer l'extension. WooCommerce 9.9+ et Advanced Shipment Tracking for WooCommerce doivent être actifs.
3. Configurer depuis WooCommerce → Réglages → Suivi d'expédition.

== Frequently Asked Questions ==

= Le plugin nécessite-t-il Boxtal Connect ? =

Non. Boxtal Connect n'est requis que par certains modules. Le plugin s'installe et s'active sans lui ;
seuls les modules qui en dépendent restent grisés dans l'onglet Modules tant qu'il n'est pas actif.

= Le plugin nécessite-t-il un jeton GitHub ? =

Non. Le dépôt est public : les mises à jour fonctionnent sans configuration.
Définir la constante `EAST_GITHUB_TOKEN` dans wp-config.php reste possible pour relever
la limite de l'API GitHub (60 requêtes par heure et par adresse IP sans jeton).

= Comment forcer une vérification des mises à jour ? =

Depuis l'écran Extensions, le lien « Check for updates » sous la ligne du plugin.
La vérification automatique a lieu au plus toutes les 12 heures.

== Changelog ==

= 0.3.0 =
* Action groupée « Boxtal → AST : importer le suivi » sur la liste des commandes (écran historique et
  écran HPOS) : importe le suivi des commandes sélectionnées à la demande, jusqu'à 20 par lot (réglable).
* Rattrapage automatique à l'installation : balaie les commandes des 180 derniers jours (réglable) et
  importe le suivi de celles qui n'en ont encore aucun. Ne touche jamais à une commande qui a déjà au
  moins une entrée de suivi, quelle qu'en soit l'origine. Se relance manuellement depuis Réglages →
  Suivi d'expédition → Boxtal → AST, sans risque : un balayage déjà à jour ne modifie rien.
* Le rattrapage tourne en arrière-plan par lots (Action Scheduler), avec reprise automatique en cas
  d'interruption.

= 0.2.0 =
* Nouveau module « Suivi Boxtal → Advanced Shipment Tracking » : dès qu'un bordereau est édité côté
  Boxtal (Colissimo, Chronopost, Mondial Relay, ou tout autre transporteur), le numéro et le lien de
  suivi sont importés automatiquement dans Advanced Shipment Tracking, sans action manuelle.
* Transporteur résolu par cascade (point relais Boxtal, URL de suivi, méthode de livraison, format du
  numéro) ; en dernier recours, le lien fourni par Boxtal est posé tel quel, sans transporteur reconnu.
* Jusqu'à 6 relances automatiques (toutes les 15 minutes par défaut) si l'API Boxtal n'a pas encore le
  colis au moment de l'édition du bordereau, plus une action manuelle « Boxtal → AST : importer le suivi »
  sur la fiche commande.
* Reprend le relais du snippet WPCode équivalent sans coupure : le module reste en veille tant que le
  snippet est détecté actif, avec un avertissement dans les réglages.
* Nouvel onglet de réglages « Boxtal → AST » : préférence du lien de suivi, statut « expédié »
  automatique, déduction par format de numéro, journal détaillé, tentatives et délai de relance.

= 0.1.0 =
* Version initiale : structure du plugin, registre de modules avec dépendances tierces déclarables
  (dures : WooCommerce, Advanced Shipment Tracking ; optionnelle par module : Boxtal Connect),
  onglet de réglages WooCommerce.
* Déclaration de compatibilité HPOS et blocs Panier/Commande.
* Mises à jour automatiques depuis GitHub.

== Upgrade Notice ==

= 0.3.0 =
Un rattrapage automatique démarre après cette mise à jour : il importe le suivi des commandes récentes
qui n'en ont encore aucun. Aucune commande déjà pourvue de suivi n'est modifiée. Suivi et relance depuis
Réglages → Suivi d'expédition → Boxtal → AST.

= 0.2.0 =
Nouveau module de suivi Boxtal → AST. Si le snippet WPCode équivalent est encore actif, désactivez-le
après cette mise à jour pour que le module prenne le relais — un avertissement vous le rappelle dans
Réglages → Suivi d'expédition → Boxtal → AST.

= 0.1.0 =
Première version.
