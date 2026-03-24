<?php
// Désactiver l'affichage des erreurs pour une interface propre
error_reporting(0);
ini_set('display_errors', 0);

// Configuration de la sécurité des sessions (AVANT session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Strict');

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';

// Appeler la fonction de déconnexion
logout();
?>
