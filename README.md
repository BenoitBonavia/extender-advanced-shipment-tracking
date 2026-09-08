# Extender for Advanced Shipment Tracking

Plugin maison regroupant les règles et automatismes autour du **suivi d'expédition** d'une boutique
WooCommerce, à la place de snippets dispersés dans `functions.php` ou Code Snippets.

- **Version** : 0.4.0
- **Prérequis** : WordPress 6.8+, PHP 7.4+, WooCommerce 9.9+ (testé jusqu'à 11.0), Advanced Shipment
  Tracking for WooCommerce 4.0+ (testé jusqu'à 4.0.2)
- **Optionnel** : Boxtal Connect 2.0+ et WooCommerce PayPal Payments 4.1+, chacun requis uniquement par
  son module
- **Préfixe** : `east_` (options, hooks) / `EAST\` (namespace PHP)
- **Text domain** : `extender-advanced-shipment-tracking` (doit rester identique au slug du dossier)

## Arborescence

```
extender-advanced-shipment-tracking/
├── extender-advanced-shipment-tracking.php  Fichier principal : en-tête, constantes, hooks d'amorçage
├── readme.txt                               Métadonnées au format WordPress.org (lues pour la fiche de mise à jour)
├── uninstall.php                            Purge des options east_* à la suppression
├── .gitattributes                           export-ignore : fichiers exclus des archives
├── .github/workflows/release.yml            Construit et publie l'archive sur push d'un tag v*
├── bin/build-plugin-zip.sh                  Construction reproductible de l'archive d'installation
├── lib/plugin-update-checker/               Bibliothèque tierce embarquée (Plugin Update Checker 5.7)
├── stubs/                                   Déclarations pour PHPStan uniquement, jamais chargées
├── composer.json                            Autoload PSR-4 + outillage de dev (PHPCS, PHPStan)
├── phpcs.xml.dist                           Règles WordPress Coding Standards
├── phpstan.neon.dist                        Analyse statique avec stubs WordPress/WooCommerce/hôtes
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
├── languages/                               Fichiers .pot / .po / .mo
└── src/
    ├── Autoloader.php                       Autoload PSR-4 sans Composer
    ├── functions.php                        Helpers globaux : east(), east_log()
    ├── Plugin.php                           Conteneur : amorçage + registre des modules
    ├── Updater.php                          Mises à jour depuis les releases GitHub
    ├── Requirements.php                     Vérification WooCommerce + Advanced Shipment Tracking
    ├── Installer.php                        Activation / désactivation / migrations
    ├── Integration/
    │   ├── HostPlugin.php                   Contrat commun à une extension tierce dont on dépend
    │   ├── AdvancedShipmentTracking.php     Point d'isolement de l'hôte principal (dépendance DURE)
    │   ├── Boxtal.php                       Point d'isolement de Boxtal Connect (dépendance DOUCE, par module)
    │   └── PayPalPayments.php               Point d'isolement de PayPal Payments (dépendance DOUCE, par module)
    ├── Admin/
    │   ├── Admin.php                        Hooks admin, assets, lien « Réglages »
    │   ├── SettingsTab.php                  WooCommerce → Réglages → Suivi d'expédition
    │   └── PayPalTrackingPage.php           Écran dédié WooCommerce → PayPal : suivi (rattrapage AST → PayPal)
    ├── Modules/
    │   ├── ModuleInterface.php              Contrat d'un module, dépendances comprises
    │   ├── AbstractModule.php               Base : activation pilotée par option + résolution des dépendances
    │   ├── BoxtalTracking.php               Module : suivi Boxtal → AST (accroche les hooks, délègue à BoxtalTracking/)
    │   └── PayPalTracking.php               Module : suivi AST → PayPal (accroche les hooks, délègue à PayPalTracking/)
    ├── BoxtalTracking/                      Logique du module Boxtal → AST
    │   ├── Config.php                       Réglages : constante héritée MH_BXT_* → option east_boxtal_* → défaut
    │   ├── Legacy.php                       Méta et sentinelles héritées du snippet WPCode remplacé
    │   ├── SnippetGuard.php                 Détection du snippet encore actif : met le module en veille
    │   ├── CarrierResolver.php              Résolution du transporteur AST (4 signaux, du plus au moins fiable)
    │   ├── TrackingSync.php                 Import temps réel + relances (déclenché par boxtal_connect_order_shipped)
    │   ├── TrackingLink.php                 Filtre ast_tracking_link : priorité au lien Boxtal
    │   ├── OrderAction.php                  Action manuelle « importer le suivi » sur une commande
    │   ├── BulkAction.php                   Action groupée « importer le suivi » sur la liste des commandes
    │   └── Backfill.php                     Rattrapage par lots de l'historique (BatchJob)
    ├── PayPalTracking/                      Logique du module AST → PayPal
    │   ├── Config.php                       Réglages : constante héritée MH_PPT_* → option east_paypal_* → défaut
    │   ├── Legacy.php                       Méta et sentinelles héritées du snippet WPCode remplacé
    │   ├── SnippetGuard.php                 Détection du snippet encore actif : met le module en veille
    │   ├── CarrierMap.php                   Correspondance transporteur AST → code transporteur PayPal
    │   ├── OrderState.php                   Éligibilité d'une commande (passerelle ppcp, capture, suivi en attente)
    │   ├── Push.php                         Envoi vers PayPal (déclenché par update_order_status_after_adding_tracking)
    │   ├── Scan.php                         Analyse + cache (transient) pour l'écran de rattrapage
    │   └── OrderAction.php                  Action manuelle « envoyer le suivi » sur une commande
    └── Support/
        ├── Settings.php                     Lecture/écriture des options east_*
        ├── Logger.php                       Journaux WooCommerce (source extender-ast)
        ├── BatchJob.php                     Contrat d'un traitement par lots
        ├── BatchRunner.php                  Exécute un BatchJob par étapes auto-chaînées (Action Scheduler)
        ├── JobState.php                     État persistant d'un traitement par lots (option east_job_{id})
        ├── Lock.php                         Verrou d'exclusion mutuelle (INSERT IGNORE)
        └── Scheduler.php                    Unique point de contact avec Action Scheduler, repli WP-Cron
```

## Traitements en arrière-plan (`Support\BatchJob` / `BatchRunner`)

Tout travail trop long pour une requête HTTP (appel API par élément, gros volume) implémente
`Support\BatchJob` (`get_id()`, `get_hook()`, `get_batch_size()`, `process( $cursor, $limit )`) et se
fait exécuter par `new Support\BatchRunner( $job )`. Le runner découpe en étapes auto-chaînées via
Action Scheduler (budget 15 s par étape, verrou `Support\Lock`, état persistant `Support\JobState`,
reprise automatique si le processus est tué en cours de route). `BoxtalTracking\Backfill` en est
l'implémentation de référence. Point d'entrée à câbler dans le module : `east_upgrade` pour amorcer au
bon moment, `admin_init` pour relancer un travail figé (voir `Modules\BoxtalTracking::revive_backfill()`).

## Convertir un snippet en module

1. Créer `src/Modules/MonModule.php` :

```php
<?php

declare( strict_types=1 );

namespace EAST\Modules;

defined( 'ABSPATH' ) || exit;

final class MonModule extends AbstractModule {

	protected $id    = 'mon_module';
	protected $title = 'Titre affiché dans les réglages';

	// À déclarer uniquement si le module dépend de Boxtal Connect :
	// protected $dependencies = array( \EAST\Integration\Boxtal::class );

	public function register(): void {
		add_action( 'woocommerce_shipment_tracking_showdetails', array( $this, 'do_something' ) );
	}

	public function do_something( $order_id ) {
		// Le contenu du snippet vient ici.
	}
}
```

2. Le déclarer via le filtre `east_module_classes` (dans le fichier principal, ou dans un module
   existant) :

```php
add_filter(
	'east_module_classes',
	static function ( array $classes ): array {
		$classes[] = \EAST\Modules\MonModule::class;

		return $classes;
	}
);
```

Une case à cocher `east_module_mon_module_enabled` apparaît automatiquement dans
**WooCommerce → Réglages → Suivi d'expédition → Modules**. Si le module déclare une dépendance tierce
absente (Boxtal, par exemple), la case est grisée et le module n'est jamais chargé, quelle que soit sa
valeur enregistrée — voir `Plugin::register_modules()` et `AbstractModule::is_available()`.

## Points d'extension

| Hook | Type | Description |
|------|------|-------------|
| `east_loaded` | action | Le plugin est amorcé, tous les modules disponibles et actifs sont enregistrés. |
| `east_module_classes` | filtre | Ajouter ou retirer des classes de modules. |
| `east_module_is_enabled` | filtre | Forcer l'état d'un module (n'est consulté que s'il est disponible). |
| `east_setting` | filtre | Filtrer la valeur d'un réglage. |
| `east_upgrade` | action | Migrations de données à l'activation après changement de version. |
| `east_activated` / `east_deactivated` | actions | Activation / désactivation. |
| `east_batch_job_done` | action | Un `Support\BatchJob` vient de se terminer (`$job_id`, `JobState $state`). |
| `east_boxtal_carriers` | filtre | Table des transporteurs reconnus par le pont Boxtal → AST. |
| `east_boxtal_backfill_statuses` | filtre | Statuts de commande candidats au rattrapage Boxtal → AST. |
| `east_paypal_carrier_map` | filtre | Table de correspondance transporteur AST → code PayPal. |
| `east_paypal_backfill_excluded_statuses` | filtre | Statuts de commande exclus du rattrapage AST → PayPal. |

## Développement

```bash
composer install   # outillage de dev uniquement, l'autoload de prod ne dépend pas de vendor/
composer lint      # PHPCS (WordPress Coding Standards 3.4)
composer lint:fix  # PHPCBF
composer analyse   # PHPStan niveau 6 + stubs WordPress/WooCommerce/hôtes
```

L'autoloader maison (`src/Autoloader.php`) suffit en production : le plugin fonctionne sans exécuter
`composer install`.

## Compatibilité déclarée

- HPOS / `custom_order_tables`
- Blocs Cart & Checkout

Ces déclarations sont faites sur `before_woocommerce_init` dans le fichier principal ; à réévaluer si un
module venait à manipuler directement les tables de commandes historiques.

## Dépendances tierces

| Extension | Nature | Point d'isolement | Contrôlée par |
|---|---|---|---|
| WooCommerce | dure | — (API native) | `Requirements::are_met()` |
| Advanced Shipment Tracking for WooCommerce | dure | `Integration\AdvancedShipmentTracking` | `Requirements::are_met()` |
| Boxtal Connect | douce, par module | `Integration\Boxtal` | `AbstractModule::is_available()` |
| WooCommerce PayPal Payments | douce, par module | `Integration\PayPalPayments` | `AbstractModule::is_available()` |

Une dépendance dure bloque l'activation du plugin (en-tête `Requires Plugins` + `Requirements`, qui reste
le filet pour les cas que l'en-tête ne couvre pas : dossier renommé, version trop ancienne, extension
présente mais non démarrée). Une dépendance douce ne bloque que les modules qui la déclarent dans
`protected $dependencies`.

Aucune autre classe ne doit écrire en dur un slug, un nom de fichier ou un nom de classe d'une extension
tierce : tout ce que ce plugin sait d'elles vit dans `src/Integration/`.
