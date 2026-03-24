<?php
// Interface Admin - Sujet 11 d'examen
error_reporting(0);
ini_set('display_errors', 0);

// Configuration de la sécurité des sessions
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

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Récupérer les logs d'audit avec les colonnes exactes
$audit_logs = $pdo->query("
    SELECT * FROM audit_virement 
    ORDER BY \"date d'operation\" DESC
")->fetchAll();

// Calculer les statistiques
$stats = $pdo->query("
    SELECT 
        SUM(CASE WHEN \"type d'action\" = 'INSERT' THEN 1 ELSE 0 END) as insertions,
        SUM(CASE WHEN \"type d'action\" = 'UPDATE' THEN 1 ELSE 0 END) as modifications,
        SUM(CASE WHEN \"type d'action\" = 'DELETE' THEN 1 ELSE 0 END) as suppressions
    FROM audit_virement
")->fetch();

// Filtrage
$search_term = $_GET['search'] ?? '';
$filter_action = $_GET['filter_action'] ?? 'all';

$filtered_logs = $pdo->query("
    SELECT * FROM audit_virement 
    WHERE (
        LOWER(\"n°virement\"::text) LIKE LOWER('%$search_term%') OR
        LOWER(\"n°compte\"::text) LIKE LOWER('%$search_term%') OR
        LOWER(nomclient::text) LIKE LOWER('%$search_term%') OR
        LOWER(utilisateur::text) LIKE LOWER('%$search_term%')
    )
    " . ($filter_action !== 'all' ? "AND \"type d'action\" = '$filter_action'" : "") . "
    ORDER BY id DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal d'Audit - Administrateur</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-600 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">BankAudit Pro</h1>
                        <p class="text-sm text-gray-600">Journal d'Audit</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">
                        Connecté: <strong><?php echo $_SESSION['user_name']; ?></strong>
                    </span>
                    <a href="logout.php" class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                        Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <!-- Filtres -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Filtres de Recherche</h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" 
                           placeholder="N° virement, utilisateur, compte..." 
                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type d'action</label>
                    <select name="filter_action" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="all" <?php echo $filter_action === 'all' ? 'selected' : ''; ?>>Toutes</option>
                        <option value="INSERT" <?php echo $filter_action === 'INSERT' ? 'selected' : ''; ?>>Insertions</option>
                        <option value="UPDATE" <?php echo $filter_action === 'UPDATE' ? 'selected' : ''; ?>>Modifications</option>
                        <option value="DELETE" <?php echo $filter_action === 'DELETE' ? 'selected' : ''; ?>>Suppressions</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                        Filtrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Tableau d'Audit - Colonnes exactes selon sujet -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Journal d'Audit</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left p-2 text-sm font-medium text-gray-700">Type d'action</th>
                            <th class="text-left p-2 text-sm font-medium text-gray-700">Date d'opération</th>
                            <th class="text-left p-2 text-sm font-medium text-gray-700">Date de Virement</th>
                            <th class="text-left p-2 text-sm font-medium text-gray-700">N° Virement</th>
                            <th class="text-left p-2 text-sm font-medium text-gray-700">N° Compte</th>
                            <th class="text-left p-2 text-sm font-medium text-gray-700">Nom Client</th>
                            <th class="text-right p-2 text-sm font-medium text-gray-700">Montant Ancien</th>
                            <th class="text-right p-2 text-sm font-medium text-gray-700">Montant Nouveau</th>
                            <th class="text-left p-2 text-sm font-medium text-gray-700">Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_logs as $log): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="p-2 text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        $action = $log['type d\'action'];
                                        if ($action === 'INSERT') {
                                            echo 'bg-green-100 text-green-800';
                                        } elseif ($action === 'UPDATE') {
                                            echo 'bg-yellow-100 text-yellow-800';
                                        } else {
                                            echo 'bg-red-100 text-red-800';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($log['type d\'action']); ?>
                                    </span>
                                </td>
                                <td class="p-2 text-sm"><?php echo date('d/m/Y H:i:s', strtotime($log['date d\'operation'])); ?></td>
                                <td class="p-2 text-sm"><?php 
                                    if ($log['date virement']) {
                                        echo date('d/m/Y', strtotime($log['date virement']));
                                    } else {
                                        echo '-';
                                    }
                                ?></td>
                                <td class="p-2 text-sm"><?php echo htmlspecialchars($log['n°virement'] ?? '-'); ?></td>
                                <td class="p-2 text-sm"><?php echo htmlspecialchars($log['n°compte'] ?? '-'); ?></td>
                                <td class="p-2 text-sm"><?php echo htmlspecialchars($log['nomclient'] ?? '-'); ?></td>
                                <td class="p-2 text-sm text-right">
                                    <?php 
                                    if ($log['montant_ancien'] !== null) {
                                        echo number_format($log['montant_ancien'], 0, ',', ' ') . ' Ar';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td class="p-2 text-sm text-right">
                                    <?php 
                                    if ($log['montant_nouv'] !== null) {
                                        echo number_format($log['montant_nouv'], 0, ',', ' ') . ' Ar';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td class="p-2 text-sm"><?php echo htmlspecialchars($log['utilisateur']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($filtered_logs)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p>Aucun enregistrement d'audit trouvé</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Statistiques des Opérations</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-green-600">Insertions</p>
                            <p class="text-2xl font-bold text-green-800"><?php echo $stats['insertions']; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-green-200 flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-yellow-600">Modifications</p>
                            <p class="text-2xl font-bold text-yellow-800"><?php echo $stats['modifications']; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-yellow-200 flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-red-600">Suppressions</p>
                            <p class="text-2xl font-bold text-red-800"><?php echo $stats['suppressions']; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-red-200 flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
