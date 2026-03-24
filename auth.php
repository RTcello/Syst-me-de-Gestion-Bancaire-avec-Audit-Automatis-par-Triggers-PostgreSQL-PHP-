<?php
// Système d'authentification pour BankAudit Pro
// Gère la connexion, déconnexion et validation des sessions

// Configuration de la sécurité des sessions (AVANT session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Strict');

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

// Fonction d'authentification des utilisateurs
function authenticate_user($identifier, $password, $role) {
    global $pdo;
    
    try {
        // Recherche de l'utilisateur dans la base de données avec les nouveaux noms de colonnes
        $sql = "SELECT id, login, mot_de_passe, role, actif 
                FROM utilisateurs 
                WHERE login = ? AND role = ? AND actif = TRUE";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$identifier, $role]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("Tentative de connexion échouée: utilisateur non trouvé ou inactif - $identifier");
            return false;
        }
        
        // Vérification du mot de passe
        if (password_verify($password, $user['mot_de_passe'])) {
            // Authentification réussie - Utiliser les variables de session correctes
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_login'] = $user['login'];
            $_SESSION['user_name'] = $user['login']; // Utiliser login comme nom
            $_SESSION['user_role'] = $user['role'];
            
            error_log("Connexion réussie: {$user['login']} ({$user['role']})");
            return true;
        } else {
            error_log("Tentative de connexion échouée: mot de passe incorrect - $identifier");
            return false;
        }
        
    } catch (PDOException $e) {
        error_log("Erreur lors de l'authentification: " . $e->getMessage());
        return false;
    }
}

// Fonction pour vérifier si l'utilisateur est connecté
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Fonction pour vérifier si l'utilisateur a un rôle spécifique
function has_role($required_role) {
    return is_logged_in() && $_SESSION['user_role'] === $required_role;
}

// Fonction pour rediriger selon le rôle
function redirect_by_role() {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
    
    switch ($_SESSION['user_role']) {
        case 'agent':
            header('Location: virements_examen.php');
            break;
        case 'admin':
            header('Location: audit_examen.php');
            break;
        default:
            header('Location: index.php');
            break;
    }
    exit;
}

// Fonction pour forcer la connexion (protéger les pages)
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: index.php');
        exit;
    }
}

// Fonction pour forcer un rôle spécifique
function require_role($required_role) {
    require_login();
    
    if (!has_role($required_role)) {
        error_log("Accès refusé: rôle requis '$required_role', rôle actuel '{$_SESSION['user_role']}'");
        
        // Rediriger vers la page appropriée selon le rôle actuel
        redirect_by_role();
    }
}

// Fonction pour déconnexion
function logout() {
    // Enregistrer la déconnexion dans les logs
    if (is_logged_in()) {
        error_log("Déconnexion: {$_SESSION['user_name']} ({$_SESSION['user_role']})");
    }
    
    // Détruire la session
    session_unset();
    session_destroy();
    
    // Supprimer le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    header('Location: index.php');
    exit;
}

// Fonction pour obtenir les informations de l'utilisateur actuel
function get_current_user_info() {
    if (is_logged_in()) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null;
}

// Fonction pour générer un token CSRF
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour vérifier le token CSRF
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fonction pour sécuriser les entrées
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Fonction pour valider un identifiant
function validate_identifier($identifier) {
    return !empty($identifier) && 
           strlen($identifier) >= 3 && 
           strlen($identifier) <= 50 &&
           preg_match('/^[a-zA-Z0-9._-]+$/', $identifier);
}

// Fonction pour valider un mot de passe
function validate_password($password) {
    return !empty($password) && 
           strlen($password) >= 8 &&
           preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password);
}

// Fonction pour créer un hash de mot de passe sécurisé
function hash_password($password) {
    return password_hash($password, PASSWORD_ARGON2ID);
}

// Fonction pour vérifier un mot de passe
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Régénération de l'ID de session pour la sécurité
if (!isset($_SESSION['session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = true;
}

// Timeout de session (30 minutes)
$session_timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    logout();
}
$_SESSION['last_activity'] = time();

// Protection contre les attaques de fixation de session
if (!isset($_SESSION['user_ip'])) {
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
} elseif ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
    logout();
}

// Protection contre les attaques User-Agent
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} elseif ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    logout();
}
?>
