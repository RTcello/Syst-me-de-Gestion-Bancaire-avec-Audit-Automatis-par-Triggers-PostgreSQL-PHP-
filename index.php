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

require_once 'db.php';
require_once 'auth.php';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize_input($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? '');
    
    if (validate_identifier($identifier) && !empty($password) && !empty($role)) {
        // Utiliser les nouveaux identifiants pour l'examen
        if (authenticate_user($identifier, $password, $role)) {
            // Rediriger selon le rôle vers les nouvelles pages d'examen
            if ($role === 'agent') {
                header('Location: virements_examen.php');
                exit;
            } elseif ($role === 'admin') {
                header('Location: audit_examen.php');
                exit;
            }
        }
    }
    $error = "Identifiant, mot de passe ou rôle incorrect";
}

// If user is already logged in, redirect to appropriate page
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'agent') {
        header('Location: virements_examen.php');
        exit;
    } elseif ($_SESSION['user_role'] === 'admin') {
        header('Location: audit_examen.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BankAudit Pro - Connexion</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        border: "hsl(var(--border))",
                        background: "hsl(var(--background))",
                        foreground: "hsl(var(--foreground))",
                        card: "hsl(var(--card))",
                        "card-foreground": "hsl(var(--card-foreground))",
                        primary: "hsl(var(--primary))",
                        "primary-foreground": "hsl(var(--primary-foreground))",
                        secondary: "hsl(var(--secondary))",
                        "secondary-foreground": "hsl(var(--secondary-foreground))",
                        muted: "hsl(var(--muted))",
                        "muted-foreground": "hsl(var(--muted-foreground))",
                        accent: "hsl(var(--accent))",
                        "accent-foreground": "hsl(var(--accent-foreground))",
                        destructive: "hsl(var(--destructive))",
                        "destructive-foreground": "hsl(var(--destructive-foreground))",
                        warning: "hsl(var(--warning))",
                        success: "hsl(var(--success))",
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --background: 0 0% 100%;
            --foreground: 222.2 84% 4.9%;
            --card: 0 0% 100%;
            --card-foreground: 222.2 84% 4.9%;
            --border: 214.3 31.8% 91.4%;
            --primary: 222.2 47.4% 11.2%;
            --primary-foreground: 210 40% 98%;
            --secondary: 210 40% 96%;
            --secondary-foreground: 222.2 47.4% 11.2%;
            --muted: 210 40% 96%;
            --muted-foreground: 215.4 16.3% 46.9%;
            --accent: 210 40% 96%;
            --accent-foreground: 222.2 47.4% 11.2%;
            --destructive: 0 84.2% 60.2%;
            --destructive-foreground: 210 40% 98%;
            --warning: 38 92% 50%;
            --success: 87 100% 37%;
        }
        .dark {
            --background: 222.2 84% 4.9%;
            --foreground: 210 40% 98%;
            --card: 222.2 84% 4.9%;
            --card-foreground: 210 40% 98%;
            --border: 217.2 32.6% 17.5%;
            --primary: 210 40% 98%;
            --primary-foreground: 222.2 47.4% 11.2%;
            --secondary: 217.2 32.6% 17.5%;
            --secondary-foreground: 210 40% 98%;
            --muted: 217.2 32.6% 17.5%;
            --muted-foreground: 215 20.2% 65.1%;
            --accent: 217.2 32.6% 17.5%;
            --accent-foreground: 210 40% 98%;
            --destructive: 0 62.8% 30.6%;
            --destructive-foreground: 210 40% 98%;
            --warning: 48 96% 53%;
            --success: 142 71% 45%;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-background p-4">
    <!-- Background pattern -->
    <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(99,102,241,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(99,102,241,0.03)_1px,transparent_1px)] bg-[size:64px_64px]"></div>
    
    <div class="relative w-full max-w-md">
        <!-- Logo and title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 mb-4">
                <!-- Building2 icon SVG -->
                <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-semibold text-foreground">BankAudit Pro</h1>
            <p class="text-muted-foreground mt-1">Système de Gestion Bancaire et Audit</p>
        </div>

        <!-- Login card -->
        <div class="bg-card border border-border rounded-xl p-8 shadow-xl shadow-primary/5">
            <div class="mb-6">
                <h2 class="text-lg font-medium text-foreground">Connexion</h2>
                <p class="text-sm text-muted-foreground mt-1">
                    Entrez vos identifiants pour accéder au système
                </p>
            </div>

            <?php if (isset($error)): ?>
                <div class="mb-4 p-3 bg-destructive/10 border border-destructive/20 rounded-lg">
                    <p class="text-sm text-destructive"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <!-- Identifier field -->
                <div class="space-y-2">
                    <label for="identifier" class="text-sm font-medium text-foreground">
                        Identifiant
                    </label>
                    <div class="relative">
                        <!-- User icon SVG -->
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <input
                            type="text"
                            id="identifier"
                            name="identifier"
                            placeholder="Votre identifiant"
                            class="pl-10 w-full px-3 py-2 bg-secondary border border-border text-foreground placeholder:text-muted-foreground rounded-md focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors"
                            required
                        />
                    </div>
                </div>

                <!-- Password field -->
                <div class="space-y-2">
                    <label for="password" class="text-sm font-medium text-foreground">
                        Mot de passe
                    </label>
                    <div class="relative">
                        <!-- Lock icon SVG -->
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Votre mot de passe"
                            class="pl-10 w-full px-3 py-2 bg-secondary border border-border text-foreground placeholder:text-muted-foreground rounded-md focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors"
                            required
                        />
                    </div>
                </div>

                <!-- Role select -->
                <div class="space-y-2">
                    <p class="text-sm text-muted-foreground">Accès pour l'examen :</p>
                    <div class="text-xs space-y-1 bg-secondary/50 p-2 rounded">
                        <p><strong>Agent:</strong> marcelo / marcelo29</p>
                        <p><strong>Admin:</strong> admin / admin123</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="role" class="text-sm font-medium text-foreground">
                        Rôle
                    </label>
                    <select
                        id="role"
                        name="role"
                        class="w-full px-3 py-2 bg-secondary border border-border text-foreground rounded-md focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors"
                        required
                    >
                        <option value="">Sélectionnez votre rôle</option>
                        <option value="agent">Agent</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>

                <!-- Submit button -->
                <button
                    type="submit"
                    class="w-full bg-primary hover:bg-primary/90 text-primary-foreground font-medium py-2 px-4 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Se connecter
                </button>
            </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-xs text-muted-foreground mt-6">
            BankAudit Pro v1.0 - Tous droits réservés
        </p>
    </div>
</body>
</html>
