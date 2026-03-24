<?php
// Interface Agent - Sujet 11 d'examen
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

// Vérifier si l'utilisateur est connecté et est un agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    header('Location: index.php');
    exit;
}

// Traitement des formulaires
$message = '';
$error = '';

// Création d'un nouveau client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_client') {
    try {
        $stmt = $pdo->prepare("INSERT INTO client (n°compte, nomclient, solde) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['numero_compte'], $_POST['nom_client'], $_POST['solde_initial']]);
        $message = "Client créé avec succès !";
    } catch (PDOException $e) {
        $error = "Erreur lors de la création du client : " . $e->getMessage();
    }
}

// Récupérer les messages de l'URL
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_virement') {
            $compte = $_POST['compte'];
            $montant = $_POST['montant'];
            $date_virement = $_POST['date_virement'];
            $numero_virement = 'VIR-' . date('Y-m-d-H-i-s');
            
            $stmt = $pdo->prepare("INSERT INTO virement (\"n°virement\", \"n°compte\", Montant, datevirement) VALUES (?, ?, ?, ?)");
            $stmt->execute([$numero_virement, $compte, $montant, $date_virement]);
            
            $message = "Virement de " . number_format($montant, 0, ',', ' ') . " Ar effectué avec succès vers le compte $compte.";
            
            // Rediriger pour éviter la double soumission
            header("Location: virements_examen.php?message=" . urlencode($message));
            exit;
        }
        
        if ($action === 'create_client') {
            // Debug : Afficher ce qui est reçu
            error_log("POST reçu: " . print_r($_POST, true));
            
            // Validation des champs
            $n_compte = trim($_POST['n_compte'] ?? '');
            $nom_client = trim($_POST['nom_client'] ?? '');
            $solde_initial = $_POST['solde_initial'] ?? 0;
            
            error_log("N°Compte: '$n_compte', Nom: '$nom_client', Solde: '$solde_initial'");
            
            if (empty($n_compte)) {
                $error = "Le numéro de compte est obligatoire";
                error_log("ERREUR: N° Compte vide");
            } elseif (empty($nom_client)) {
                $error = "Le nom du client est obligatoire";
                error_log("ERREUR: Nom client vide");
            } else {
                try {
                    // Vérifier si le numéro de compte existe déjà
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM client WHERE \"n°compte\" = ?");
                    $check_stmt->execute([$n_compte]);
                    $exists = $check_stmt->fetch()['count'] > 0;
                    
                    if ($exists) {
                        $error = "Ce numéro de compte existe déjà. Veuillez en choisir un autre.";
                        error_log("ERREUR: N° Compte '$n_compte' déjà existant");
                        // Rediriger avec l'erreur pour l'afficher dans le pop-up
                        header("Location: virements_examen.php?error=" . urlencode($error) . "&show_form=1");
                        exit;
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO client (\"n°compte\", nomclient, solde) VALUES (?, ?, ?)");
                        $result = $stmt->execute([$n_compte, $nom_client, $solde_initial]);
                        error_log("Insertion réussie: " . ($result ? 'OK' : 'ERREUR'));
                        
                        // Rediriger pour éviter la double soumission
                        $message = "Client créé avec succès ! Compte: $n_compte";
                        header("Location: virements_examen.php?message=" . urlencode($message));
                        exit;
                    }
                    
                } catch (PDOException $e) {
                    error_log("ERREUR SQL: " . $e->getMessage());
                    
                    // Gérer spécifiquement l'erreur de clé dupliquée
                    if (strpos($e->getMessage(), 'Unique violation') !== false || strpos($e->getMessage(), 'client_pkey') !== false) {
                        $error = "Ce numéro de compte existe déjà. Veuillez en choisir un autre.";
                    } else {
                        $error = "Erreur lors de la création du client : " . $e->getMessage();
                    }
                }
            }
        }
        
        // Modification d'un virement
        if ($action === 'update_virement') {
            $stmt = $pdo->prepare("UPDATE virement SET \"n°compte\" = ?, Montant = ? WHERE \"n°virement\" = ?");
            $stmt->execute([$_POST['compte'], $_POST['montant'], $_POST['numero_virement']]);
            $message = "Virement modifié avec succès !";
        }
        
        // Suppression d'un virement
        if ($action === 'delete_virement') {
            $stmt = $pdo->prepare("DELETE FROM virement WHERE \"n°virement\" = ?");
            $stmt->execute([$_POST['numero_virement']]);
            $message = "Virement supprimé avec succès !";
        }
        
    } catch (PDOException $e) {
        $error = "Erreur lors de l'opération : " . $e->getMessage();
    }
}

// Récupérer les données
$clients = $pdo->query("SELECT * FROM client ORDER BY \"n°compte\" DESC")->fetchAll();
$virements = $pdo->query("SELECT v.*, c.nomclient FROM virement v LEFT JOIN client c ON v.\"n°compte\" = c.\"n°compte\" ORDER BY v.\"n°virement\" DESC")->fetchAll();

// Calculer les statistiques pour le style de l'audit
$solde_total = array_sum(array_column($clients, 'solde'));
$total_clients = count($clients);
$total_virements = count($virements);
$total_montant_virements = array_sum(array_column($virements, 'Montant'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Virements - Agent</title>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">BankAudit Pro</h1>
                        <p class="text-sm text-gray-600">Gestion des Virements</p>
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
        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="text-sm text-green-800"><?php echo $message; ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="text-sm text-red-800"><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Statistiques</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-green-600">Total Clients</p>
                            <p class="text-2xl font-bold text-green-800"><?php echo $total_clients; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-green-200 flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656-.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-yellow-600">Total Virements</p>
                            <p class="text-2xl font-bold text-yellow-800"><?php echo $total_virements; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-yellow-200 flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-red-600">Solde Total</p>
                            <p class="text-2xl font-bold text-red-800"><?php echo number_format($solde_total, 0, ',', ' '); ?> Ar</p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-red-200 flex items-center justify-center">
                            <span class="text-red-600 font-bold text-xl">Ar</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Colonne gauche: Liste des clients -->
            <div class="space-y-6">
                <!-- Liste des clients -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Liste des Clients</h2>
                        <button onclick="toggleClientForm()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                            Nouveau Client
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left p-2 text-sm font-medium text-gray-700">N° Compte</th>
                                    <th class="text-left p-2 text-sm font-medium text-gray-700">Nom Client</th>
                                    <th class="text-right p-2 text-sm font-medium text-gray-700">Solde (Ar)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="p-2 text-sm"><?php echo htmlspecialchars($client['n°compte']); ?></td>
                                        <td class="p-2 text-sm"><?php echo htmlspecialchars($client['nomclient']); ?></td>
                                        <td class="p-2 text-sm text-right"><?php echo number_format($client['solde'], 0, ',', ' '); ?> Ar</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Formulaire Nouveau Client (pop-up centré) -->
                <div id="clientForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-all duration-300" style="display: none;">
                    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4 transform transition-all duration-300 scale-95">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Créer un Nouveau Client</h2>
                            <button onclick="toggleClientForm()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <form method="POST" class="space-y-4" onsubmit="return validateClientForm()">
                            <input type="hidden" name="action" value="create_client">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">N° Compte</label>
                                <input type="text" name="n_compte" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ex: FR76-0001" id="n_compte_field">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nom du Client</label>
                                <input type="text" name="nom_client" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Solde Initial (Ar)</label>
                                <input type="number" name="solde_initial" step="0.01" min="0" required 
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex space-x-3 pt-4">
                                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors transform hover:scale-105">
                                    Créer le Client
                                </button>
                                <button type="button" onclick="toggleClientForm()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-4 rounded-lg transition-colors transform hover:scale-105">
                                    Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Colonne droite: Nouveau virement -->
            <div class="space-y-6">
                <!-- Nouveau virement -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Nouveau Virement</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_virement">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Compte</label>
                            <select name="compte" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo htmlspecialchars($client['n°compte']); ?>">
                                        <?php echo htmlspecialchars($client['n°compte'] . ' - ' . $client['nomclient']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date de Virement</label>
                            <input type="date" name="date_virement" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Montant (Ar)</label>
                            <input type="number" name="montant" step="0.01" min="0.01" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                            Effectuer le Virement
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Historique des virements -->
        <div class="mt-6 bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Historique des Virements</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left p-2 text-sm font-medium text-gray-700">N° Virement</th>
                            <th class="text-left p-2 text-sm font-medium text-gray-700">Compte</th>
                            <th class="text-left p-2 text-sm font-medium text-gray-700">Nom Client</th>
                            <th class="text-right p-2 text-sm font-medium text-gray-700">Montant Virement (Ar)</th>
                            <th class="text-left p-2 text-sm font-medium text-gray-700">Date</th>
                            <th class="text-center p-2 text-sm font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($virements as $virement): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="p-2 text-sm"><?php echo htmlspecialchars($virement['n°virement']); ?></td>
                                <td class="p-2 text-sm"><?php echo htmlspecialchars($virement['n°compte']); ?></td>
                                <td class="p-2 text-sm"><?php echo htmlspecialchars($virement['nomclient'] ?? 'N/A'); ?></td>
                                <td class="p-2 text-sm text-right font-mono">
                                    <?php 
                                    $montant = $virement['montant'];
                                    echo number_format($montant, 0, ',', ' ') . ' Ar';
                                    ?>
                                </td>
                                <td class="p-2 text-sm"><?php echo date('d/m/Y', strtotime($virement['datevirement'])); ?></td>
                                <td class="p-2 text-sm">
                                    <div class="flex justify-center space-x-2">
                                        <button onclick="editVirement('<?php echo htmlspecialchars($virement['n°virement']); ?>', '<?php echo htmlspecialchars($virement['n°compte']); ?>', '<?php echo htmlspecialchars($virement['montant']); ?>')" 
                                                class="text-blue-600 hover:text-blue-800">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_virement">
                                            <input type="hidden" name="numero_virement" value="<?php echo htmlspecialchars($virement['n°virement']); ?>">
                                            <button type="submit" onclick="return confirm('Supprimer ce virement ?')" 
                                                    class="text-red-600 hover:text-red-800">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Formulaire de modification caché -->
    <form id="editForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_virement">
        <input type="hidden" name="numero_virement" id="edit_numero_virement">
        <input type="hidden" name="compte" id="edit_compte">
        <input type="hidden" name="montant" id="edit_montant">
    </form>

    <script>
        function editVirement(numero, compte, montant) {
            document.getElementById('edit_numero_virement').value = numero;
            document.getElementById('edit_compte').value = compte;
            document.getElementById('edit_montant').value = montant;
            document.getElementById('editForm').submit();
        }
        
        function validateClientForm() {
            const nCompte = document.getElementById('n_compte_field').value.trim();
            const nomClient = document.querySelector('input[name="nom_client"]').value.trim();
            
            console.log('Validation JavaScript - N°Compte:', nCompte, 'Nom:', nomClient);
            
            if (!nCompte) {
                alert('Le numéro de compte est obligatoire');
                document.getElementById('n_compte_field').focus();
                return false;
            }
            
            if (!nomClient) {
                alert('Le nom du client est obligatoire');
                document.querySelector('input[name="nom_client"]').focus();
                return false;
            }
            
            return true;
        }
        
        function toggleClientForm() {
            console.log('🔘 Bouton Nouveau Client cliqué');
            
            const form = document.getElementById('clientForm');
            const formContent = form.querySelector('div > div');
            
            if (!form) {
                console.error('❌ ERREUR: Élément #clientForm non trouvé');
                alert('Erreur: Formulaire non trouvé');
                return;
            }
            
            console.log('✅ Formulaire trouvé, display actuel:', form.style.display);
            
            if (form.style.display === 'none' || form.style.display === '') {
                // Afficher avec animation
                form.style.display = 'flex';
                setTimeout(() => {
                    form.classList.remove('bg-opacity-0');
                    form.classList.add('bg-opacity-50');
                    formContent.classList.remove('scale-95');
                    formContent.classList.add('scale-100');
                }, 10);
                console.log('👁️ Pop-up AFFICHÉ avec animation');
            } else {
                // Masquer avec animation
                formContent.classList.remove('scale-100');
                formContent.classList.add('scale-95');
                form.classList.remove('bg-opacity-50');
                form.classList.add('bg-opacity-0');
                setTimeout(() => {
                    form.style.display = 'none';
                }, 300);
                console.log('🙈 Pop-up MASQUÉ avec animation');
            }
        }
        
        // Fermer avec la touche Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const form = document.getElementById('clientForm');
                if (form && form.style.display === 'flex') {
                    toggleClientForm();
                }
            }
        });
        
        // Test au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('clientForm');
            if (form) {
                console.log('🚀 Pop-up client prêt au chargement');
                
                // Vérifier s'il faut afficher le pop-up (erreur ou paramètre show_form)
                const urlParams = new URLSearchParams(window.location.search);
                const showError = urlParams.get('error');
                const showMessage = urlParams.get('message');
                const showForm = urlParams.get('show_form');
                
                if (showError) {
                    // Afficher le pop-up avec l'erreur
                    toggleClientForm();
                    // Afficher l'erreur dans le pop-up
                    setTimeout(() => {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4';
                        errorDiv.innerHTML = `<strong>Erreur:</strong> ${showError}`;
                        form.querySelector('form').prepend(errorDiv);
                    }, 300);
                } else if (showMessage) {
                    // Afficher le message de succès sur la page principale
                    const successDiv = document.createElement('div');
                    successDiv.className = 'bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6';
                    successDiv.innerHTML = `<strong>Succès:</strong> ${showMessage}`;
                    
                    // Insérer après les statistiques
                    const statsDiv = document.querySelector('.bg-white.border.border-gray-200.rounded-lg.p-6.shadow-sm');
                    if (statsDiv) {
                        statsDiv.parentNode.insertBefore(successDiv, statsDiv.nextSibling);
                    }
                    
                    // Nettoyer l'URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                } else if (showForm === '1') {
                    // Afficher le pop-up si demandé
                    toggleClientForm();
                }
            } else {
                console.error('❌ Pop-up client NON PRÊT au chargement');
            }
        });
    </script>
</body>
</html>
