# BankAudit Pro - Système de Gestion Bancaire et Audit

Application PHP 8 avec PostgreSQL pour la gestion des virements bancaires et l'audit des opérations.

## 📋 Description

BankAudit Pro est une application web complète qui permet :
- **Gestion des clients bancaires** avec leurs comptes et soldes
- **Effectuer des virements** entre comptes avec validation automatique
- **Audit complet** de toutes les opérations (INSERT, UPDATE, DELETE)
- **Gestion des rôles** : Agent (gestion des virements) et Administrateur (audit)
- **Interface moderne** avec Tailwind CSS via CDN

## 🏗️ Architecture

- **Frontend** : PHP 8 avec Tailwind CSS (CDN)
- **Backend** : PostgreSQL avec triggers PL/pgSQL
- **Authentification** : Système de login sécurisé par rôle
- **Audit** : Triggers automatiques pour la traçabilité

## 📁 Structure du projet

```
bank/
├── index.php              # Page de login et redirection
├── virements.php          # Interface Agent (gestion des virements)
├── audit.php              # Interface Admin (journal d'audit)
├── logout.php             # Déconnexion
├── db.php                 # Configuration et connexion PostgreSQL
├── auth.php               # Système d'authentification
├── database_schema.sql    # Schéma de la base de données
├── triggers.sql           # Triggers PostgreSQL pour l'audit
└── README.md              # Documentation
```

## 🚀 Installation

### 1. Prérequis

- PHP 8.0 ou supérieur
- PostgreSQL 12 ou supérieur
- Serveur web (Apache, Nginx, ou serveur PHP intégré)

### 2. Configuration de la base de données

1. Créez la base de données PostgreSQL :
```sql
CREATE DATABASE bank_audit;
```

2. Exécutez le schéma de base :
```bash
psql -U postgres -d bank_audit -f database_schema.sql
```

3. Exécutez les triggers :
```bash
psql -U postgres -d bank_audit -f triggers.sql
```

### 3. Configuration de la connexion

Modifiez le fichier `db.php` avec vos paramètres PostgreSQL :
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'bank_audit');
define('DB_USER', 'postgres');
define('DB_PASSWORD', 'votre_mot_de_passe');
```

### 4. Démarrage de l'application

#### Avec serveur PHP intégré (développement) :
```bash
cd bank
php -S localhost:8000
```

#### Avec Apache (production) :
Placez les fichiers dans le répertoire web d'Apache et configurez un VirtualHost.

## 👥 Utilisateurs par défaut

| Identifiant | Mot de passe | Rôle |
|-------------|--------------|------|
| jean.martin | password | Agent |
| marie.dupont | password | Agent |
| pierre.admin | password | Administrateur |

## 🔐 Fonctionnalités de sécurité

- **Authentification par rôle** avec redirection automatique
- **Protection CSRF** avec tokens
- **Validation des entrées** et échappement XSS
- **Timeout de session** (30 minutes)
- **Protection contre fixation de session**
- **Hashage des mots de passe** avec Argon2ID

## 📊 Fonctionnalités principales

### Agent Bancaire (virements.php)
- ✅ Création de nouveaux clients
- ✅ Consultation de la liste des clients avec recherche
- ✅ Effectuer des virements entre comptes
- ✅ Historique des virements
- ✅ Modification et suppression de virements
- ✅ Statistiques en temps réel

### Administrateur (audit.php)
- ✅ Journal d'audit complet
- ✅ Filtrage par type d'action (INSERT/UPDATE/DELETE)
- ✅ Recherche par numéro de virement, utilisateur ou compte
- ✅ Export CSV des logs d'audit
- ✅ Statistiques des opérations
- ✅ Vue détaillée des montants avant/après modification

## 🔄 Triggers PostgreSQL

L'application utilise des triggers automatiques pour :

1. **Audit des opérations** : Chaque INSERT/UPDATE/DELETE sur la table `virement` est automatiquement enregistré dans `audit_virement`

2. **Mise à jour des soldes** : Les soldes des comptes sont automatiquement ajustés lors des virements

3. **Validation des opérations** : Vérification de l'existence des comptes et des soldes suffisants

### Exemple de trigger d'audit :
```sql
CREATE TRIGGER trigger_audit_virement_insert
    AFTER INSERT ON virement
    FOR EACH ROW
    EXECUTE FUNCTION audit_virement_insert();
```

## 🎨 Design et Interface

- **Design moderne** avec Tailwind CSS
- **Responsive** pour mobile et desktop
- **Icônes SVG** intégrées (pas de dépendances externes)
- **Thème clair** avec variables CSS personnalisées
- **Animations et transitions** fluides

## 📝 Tables de la base de données

### utilisateurs
- `id` (PK)
- `identifiant` (UNIQUE)
- `nom`
- `mot_de_passe` (hashé)
- `role` (agent/admin)
- `date_creation`
- `actif`

### client
- `id` (PK)
- `numero_compte` (UNIQUE)
- `nom`
- `solde`
- `date_creation`
- `actif`

### virement
- `id` (PK)
- `numero_virement` (UNIQUE)
- `compte_source`
- `compte_destination`
- `montant`
- `date_virement`
- `compte_id` (FK vers client)
- `utilisateur`
- `statut`

### audit_virement
- `id` (PK)
- `type_action` (INSERT/UPDATE/DELETE)
- `date_action`
- `numero_virement`
- `compte_source`
- `compte_destination`
- `ancien_montant`
- `nouveau_montant`
- `utilisateur`
- `compte_id`
- `details_action`

## 🔧 Personnalisation

### Ajouter de nouveaux rôles
1. Modifiez la table `utilisateurs` dans `database_schema.sql`
2. Mettez à jour la fonction `authenticate_user()` dans `auth.php`
3. Créez les pages correspondantes avec la protection `require_role()`

### Personnaliser le design
- Modifiez les variables CSS dans le `<head>` des fichiers PHP
- Ajoutez de nouvelles classes Tailwind selon vos besoins
- Les icônes sont des SVG intégrés, facilement modifiables

## 🚀 Déploiement

### En production
1. Désactivez l'affichage des erreurs PHP
2. Configurez HTTPS
3. Utilisez des mots de passe forts
4. Activez les logs d'erreurs
5. Configurez un backup régulier de la base de données

### Variables d'environnement
```php
// Dans db.php, commentez cette ligne pour la production :
// define('ENVIRONMENT', 'development');
```

## 🐛 Dépannage

### Problèmes courants
1. **Connexion PostgreSQL échouée** : Vérifiez les identifiants dans `db.php`
2. **Pages blanches** : Activez l'affichage des erreurs PHP en développement
3. **Triggers ne fonctionnent pas** : Vérifiez que `triggers.sql` a été exécuté

### Logs d'erreurs
- PHP : `error_log("Message d'erreur");`
- PostgreSQL : Vérifiez les logs PostgreSQL
- Application : Les erreurs sont loggées dans les fichiers logs du serveur

## 📞 Support

Pour toute question ou problème :
1. Vérifiez les logs d'erreurs
2. Consultez la documentation PostgreSQL
3. Testez avec les utilisateurs par défaut

## 📄 Licence

BankAudit Pro - Projet académique M2
Tous droits réservés © 2024
"# Syst-me-de-Gestion-Bancaire-avec-Audit-Automatis-par-Triggers-PostgreSQL-PHP-" 
