<?php
// Configuration de la base de données PostgreSQL
// Fichier de connexion à la base de données

// Paramètres de connexion
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'projet');
define('DB_USER', 'postgres');
define('DB_PASSWORD', 'marcelo');

// Chaîne de connexion DSN
$dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;

// Options PDO
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => true
];

try {
    // Création de la connexion PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    
    // Configuration du charset
    $pdo->exec("SET NAMES 'UTF8'");
    $pdo->exec("SET TIME ZONE 'Europe/Paris'");
    
} catch (PDOException $e) {
    // En cas d'erreur de connexion, afficher un message d'erreur
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    
    // En développement, afficher l'erreur
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Erreur de connexion à la base de données: " . $e->getMessage());
    } else {
        // En production, afficher un message générique
        die("Une erreur technique est survenue. Veuillez réessayer ultérieurement.");
    }
}

// Fonction pour exécuter une requête avec gestion d'erreur
function execute_query($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erreur SQL: " . $e->getMessage() . " - SQL: " . $sql);
        
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            throw $e;
        } else {
            throw new Exception("Une erreur technique est survenue lors de l'exécution de la requête.");
        }
    }
}

// Fonction pour commencer une transaction
function begin_transaction($pdo) {
    try {
        $pdo->beginTransaction();
    } catch (PDOException $e) {
        error_log("Erreur lors du début de la transaction: " . $e->getMessage());
        throw new Exception("Impossible de démarrer la transaction.");
    }
}

// Fonction pour valider une transaction
function commit_transaction($pdo) {
    try {
        $pdo->commit();
    } catch (PDOException $e) {
        error_log("Erreur lors de la validation de la transaction: " . $e->getMessage());
        throw new Exception("Impossible de valider la transaction.");
    }
}

// Fonction pour annuler une transaction
function rollback_transaction($pdo) {
    try {
        $pdo->rollBack();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'annulation de la transaction: " . $e->getMessage());
        // Ne pas lancer d'exception ici car on est déjà dans une gestion d'erreur
    }
}

// Fonction pour vérifier si une table existe
function table_exists($pdo, $table_name) {
    $sql = "SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name = ?
    )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table_name]);
    return $stmt->fetchColumn() == '1';
}

// Fonction pour obtenir la dernière erreur de la base de données
function get_db_error($pdo) {
    $error_info = $pdo->errorInfo();
    if ($error_info[0] !== '00000') {
        return $error_info[2]; // Message d'erreur
    }
    return null;
}

// Configuration pour le développement (à commenter en production)
define('ENVIRONMENT', 'development');

// Test de connexion au démarrage (uniquement en développement)
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    try {
        $test_query = $pdo->query("SELECT 1");
        if ($test_query->fetchColumn() != '1') {
            throw new Exception("La connexion à la base de données semble fonctionner mais le test a échoué.");
        }
    } catch (Exception $e) {
        error_log("Test de connexion à la base de données: " . $e->getMessage());
    }
}
?>
