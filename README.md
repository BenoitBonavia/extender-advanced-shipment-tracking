# Extender for Advanced Shipment Tracking

Plugin maison regroupant les règles et automatismes autour du **suivi d'expédition** d'une boutique
WooCommerce, à la place de snippets dispersés dans `functions.php` ou Code Snippets.

- **Version** : 0.1.0
- **Prérequis** : WordPress 6.8+, PHP 7.4+, WooCommerce 9.9+ (testé jusqu'à 11.0), Advanced Shipment
  Tracking for WooCommerce 4.0+ (testé jusqu'à 4.0.2)
- **Optionnel** : Boxtal Connect 2.0+, requis uniquement par certains modules
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
    │   └── Boxtal.php                       Point d'isolement de Boxtal Connect (dépendance DOUCE, par module)
    ├── Admin/
    │   ├── Admin.php                        Hooks admin, assets, lien « Réglages »
    │   └── SettingsTab.php                  WooCommerce → Réglages → Suivi d'expédition
    ├── Modules/
    │   ├── ModuleInterface.php              Contrat d'un module, dépendances comprises
    │   └── AbstractModule.php               Base : activation pilotée par option + résolution des dépendances
    └── Support/
        ├── Settings.php                     Lecture/écriture des options east_*
        └── Logger.php                       Journaux WooCommerce (source extender-ast)
```

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

Une dépendance dure bloque l'activation du plugin (en-tête `Requires Plugins` + `Requirements`, qui reste
le filet pour les cas que l'en-tête ne couvre pas : dossier renommé, version trop ancienne, extension
présente mais non démarrée). Une dépendance douce ne bloque que les modules qui la déclarent dans
`protected $dependencies`.

Aucune autre classe ne doit écrire en dur un slug, un nom de fichier ou un nom de classe d'une extension
tierce : tout ce que ce plugin sait d'elles vit dans `src/Integration/`.
