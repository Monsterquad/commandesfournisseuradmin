# Module MQ Commande Fournisseur Admin

**Version :** 1.0.0  
**Auteur :** Faivre Thomas  
**Compatibilité :** PrestaShop 8.0.0+

## 📋 Description

Module d'administration pour la gestion des commandes fournisseurs MonsterQuad. Il permet de gérer les demandes de délai, copier les références produits et gérer les stocks permanents directement depuis l'interface d'administration des commandes.

## ✨ Fonctionnalités

### 🔄 Gestion des Stocks Permanents
- **Interface produit** : Case à cocher dans l'édition produit pour marquer un stock comme permanent
- **Affichage commandes** : Indicateur visuel "SP" pour les produits en stock permanent
- **Sauvegarde automatique** : Persistance des données en base lors de l'édition produit

### 📧 Demandes de Délai Fournisseurs
- **Interface interactive** : Bouton "Demande de délai" sur les pages de commande
- **Sélection produits** : Cases à cocher pour sélectionner les produits concernés
- **Envoi emails** : Système d'envoi automatique vers les fournisseurs
- **Gestion multi-fournisseurs** : Support de plusieurs fournisseurs (Deltamics, GDFrance)

### 📋 Gestion des Références
- **Copie rapide** : Boutons de copie des références produits
- **Affichage des dates** : Dates de disponibilité formatées
- **Interface optimisée** : Colonnes supplémentaires dans les tableaux de commande

## 🚀 Installation

### Prérequis
- PrestaShop 8.0.0 ou supérieur
- PHP 8.1+
- MySQL/MariaDB

### Étapes d'installation

1. **Télécharger le module**
   ```bash
   git clone [repository-url] mqcommandefournisseuradmin
   ```

2. **Placer dans PrestaShop**
   ```
   /modules/mqcommandefournisseuradmin/
   ```

3. **Installer via Back-Office**
   - Aller dans `Modules` > `Gestionnaire de modules`
   - Rechercher "MQ Commande Fournisseur Admin"
   - Cliquer sur "Installer"

4. **Vérification**
   - Le module crée automatiquement les tables nécessaires
   - Les hooks sont enregistrés automatiquement

## 📁 Structure des Fichiers

```
mqcommandefournisseuradmin/
├── mqcommandefournisseuradmin.php          # Fichier principal du module
├── src/
│   └── entity/
│       └── StockPermanent.php              # Entité pour gestion stock permanent
├── views/
│   ├── js/
│   │   ├── admin-order.js                  # Scripts page commande
│   │   ├── copyButton.js                   # Fonctionnalité copie
│   │   ├── stockPermanent.js               # Gestion stock permanent
│   │   └── mqcommandefournisseuradmin.js   # Script principal
│   ├── css/
│   │   └── admin-order.css                 # Styles interface
│   └── templates/
│       └── hook/
│           └── AdminProductsMainStepRightColumnBeforeQuantity.twig
├── sql/
│   ├── install.sql                         # Script création tables
│   └── uninstall.sql                       # Script suppression tables
└── README.md
```

## 🔧 Configuration

### Hooks Utilisés

| Hook | Description |
|------|-------------|
| `displayBackOfficeHeader` | Chargement des CSS/JS sur les pages commandes |
| `displayAdminOrderTop` | Injection des données et config JavaScript |
| `displayAdminProductsMainStepRightColumnBeforeQuantity` | Interface stock permanent |
| `actionAdminProductsControllerSaveAfter` | Sauvegarde état stock permanent |
| `adminOrdersControllerGetProducts` | Ajout données stock permanent aux produits |

### Base de Données

Le module crée une table `ps_mq_stock_permanent` :

```sql
CREATE TABLE IF EXISTS `{prefix}mq_stock_permanent` (
    `id_product` int(11) NOT NULL,
    `state` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_product`)
);
```

## 💻 Utilisation

### 1. Configuration Stock Permanent

1. Aller dans `Catalogue` > `Produits`
2. Éditer un produit
3. Dans l'onglet "Informations de base", cocher "Stock permanent" si applicable
4. Sauvegarder

### 2. Gestion des Commandes

1. Aller dans `Commandes` > `Commandes`
2. Ouvrir une commande
3. Utiliser les fonctionnalités disponibles :
   - **Copier références** : Cliquer sur les boutons 📋
   - **Voir stock permanent** : Indicateur "SP" vert
   - **Demander délai** : Bouton "Demande de délai"

### 3. Demande de Délai

1. Cliquer sur "Demande de délai"
2. Sélectionner les produits concernés
3. Remplir le formulaire (sujet, message, fournisseur)
4. Envoyer

## 🎨 Interface Utilisateur

### Indicateurs Visuels

- **🟢 SP** : Produit en stock permanent
- **📋** : Bouton copie référence
- **📅** : Date de disponibilité (format dd/mm/yyyy)

### Colonnes Ajoutées

- **Délai** : Affichage des dates de disponibilité avec sélection
- **Stock Permanent** : Indicateur SP pour les produits concernés

## 🛠️ Développement

### Classes Principales

#### `Mqcommandefournisseuradmin`
- Classe principale du module
- Gestion des hooks et de l'interface
- Méthodes AJAX pour l'envoi d'emails

#### `StockPermanent`
- Entité pour la gestion des stocks permanents
- Méthodes `getStateForProduct()` et `setStateForProduct()`

### Méthodes Importantes

```php
// Récupération produits avec dates
public function getOrderProductsWithAvailabilityDate($order_id)

// Envoi email fournisseur
private function sendEmail($text, $subject, $receiver)

// Traitement AJAX
public function ajaxProcessSendEmail()
```

## 🐛 Debug et Logs

Le module utilise le système de logs PrestaShop :

```php
// Logs de debug (si $debug = true)
$this->addDebugLegacyLog($message);

// Logs critiques
$this->addCriticalLegacyLog($message);
```

Consulter les logs dans : `Administration` > `Paramètres avancés` > `Logs`

## 📧 Configuration Email

### Fournisseurs Supportés

```php
$suppliers = [
    'MonsterQuad' => 'service-client@monsterquad.fr',
    'Deltamics' => 'contact@deltamics.fr',
    'GDFrance' => 'commande@gdfrance.com',
];
```

### Template Email

Le module utilise le template `demande_de_delai` (à créer dans les templates email PrestaShop).

## 🔒 Sécurité

- **Tokens CSRF** : Protection des formulaires
- **Validation inputs** : Sanitisation des données
- **Permissions** : Vérification des droits d'accès
- **SQL Injection** : Requêtes préparées

## 🚨 Dépannage

### Problèmes Courants

1. **Hook non affiché**
   ```bash
   # Vérifier l'enregistrement du hook
   SELECT * FROM ps_hook_module WHERE id_module = (SELECT id_module FROM ps_module WHERE name = 'mqcommandefournisseuradmin');
   ```

2. **JavaScript non chargé**
   - Vérifier les chemins des fichiers JS
   - Contrôler la console navigateur pour erreurs

3. **Table non créée**
   ```bash
   # Vérifier la table
   SHOW TABLES LIKE '%mq_stock_permanent%';
   ```

### Réinstallation

```bash
# En cas de problème, désinstaller puis réinstaller
# Via l'interface ou en SQL :
DELETE FROM ps_module WHERE name = 'mqcommandefournisseuradmin';
```

## 📄 Licence

Module propriétaire MonsterQuad - Tous droits réservés

## 📞 Support

Pour toute question ou problème :
- **Email** : support@monsterquad.fr
- **Développeur** : faivre.thomas@monsterquad.fr

---

*Module développé spécifiquement pour MonsterQuad - Version 1.0.0*