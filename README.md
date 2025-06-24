# NOTE IMPORTANTE
Ce module charge jQuery via CDN dans le back-office pour assurer la compatibilité des scripts (modale, AJAX, etc.) sur PrestaShop 8.2.

# Module MQ Commande Fournisseur

## Description
Module PrestaShop 8.2 permettant d'envoyer des demandes de délai aux fournisseurs directement depuis les commandes en back-office.

## Fonctionnalités

### Interface utilisateur
- **Cases à cocher** pour sélectionner les produits
- **Sélection multiple** avec case "Tout sélectionner"
- **Affichage des dernières demandes** par produit
- **Interface responsive** adaptée au back-office PS 8.2

### Gestion des emails
- **Envoi automatique** d'emails personnalisés aux fournisseurs
- **Groupement par fournisseur** (un email par fournisseur)
- **Templates HTML et texte** pour les emails
- **Historique** des demandes en base de données

### Compatibilité
- **PrestaShop 8.0 à 8.2**
- **PHP 7.4+**
- **JavaScript vanilla** (pas de dépendance jQuery)
- **Hooks modernes** (pas d'overrides)

## Installation

1. Placer le module dans `/modules/mqcommandefournisseur/`
2. Installer depuis le back-office PrestaShop
3. Le module créera automatiquement :
   - La table `mq_supplier_requests` pour l'historique
   - L'onglet admin caché `AdminMqSupplierRequest`

## Utilisation

1. Aller dans **Commandes > Voir** une commande
2. Faire défiler vers le bas jusqu'à la section "Demandes de délai fournisseur"
3. Sélectionner les produits souhaités
4. Cliquer sur "Envoyer les demandes fournisseur"

## Structure des fichiers

```
mqcommandefournisseur/
├── mqcommandefournisseur.php          # Classe principale du module
├── controllers/
│   └── admin/
│       └── AdminMqSupplierRequestController.php  # Contrôleur AJAX
├── views/
│   ├── templates/admin/
│   │   └── supplier_request_interface.tpl       # Interface principale
│   └── css/
│       └── admin.css                            # Styles CSS
├── mails/
│   └── fr/
│       ├── supplier_request.html               # Template email HTML
│       └── supplier_request.txt                # Template email texte
├── sql/
│   ├── install.sql                             # Script d'installation DB
│   └── uninstall.sql                           # Script de désinstallation DB
└── translations/                               # Dossier des traductions
```

## Hooks utilisés

- `displayBackOfficeHeader` : Ajout CSS/JS dans le back-office
- `displayAdminOrderMainBottom` : Affichage de l'interface dans les commandes
- `actionMailAlterMessageBeforeSend` : Personnalisation des emails

## Base de données

### Table `mq_supplier_requests`
- `id_request` : ID auto-incrémenté
- `reference` : Référence du produit
- `id_product` : ID du produit
- `id_product_attribute` : ID de la déclinaison (0 si aucune)
- `date_request` : Date/heure de la demande

## Configuration requise

### Fournisseurs
Les produits doivent avoir un fournisseur configuré avec :
- **Nom du fournisseur**
- **Adresse email du fournisseur**

### Emails
Le système utilise la configuration email PrestaShop :
- `PS_SHOP_EMAIL` : Email expéditeur
- `PS_SHOP_NAME` : Nom expéditeur

## Développement

### Architecture moderne
- Utilise les hooks PrestaShop 8.2
- JavaScript vanilla (compatible ES6+)
- Fetch API pour AJAX
- Classes PHP avec typage strict
- Templates Smarty 3

### Sécurité
- Validation des données AJAX
- Échappement des variables templates
- Protection contre les injections SQL
- Contrôles d'accès admin

## Support

Version 1.0.0 - Compatible PrestaShop 8.0 à 8.2
Développé par MonsterQuad 