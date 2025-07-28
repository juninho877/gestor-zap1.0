<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../classes/ClientInteraction.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/Translation.php';

// Inicializar traduções
initTranslations();

$database = new Database();
$db = $database->getConnection();
$clientInteraction = new ClientInteraction($db);
$client = new Client($db);

$message = '';
$error = '';

// Verificar se há mensagens na sessão
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Verificar se foi especificado um cliente
if (!isset($_GET['client_id']) || !is_numeric($_GET['client_id'])) {
    $_SESSION['error'] = "Cliente não especificado.";
    redirect("clients.php");
}

$client_id = $_GET['client_id'];

// Carregar dados do cliente
$client->id = $client_id;
$client->user_id = $_SESSION['user_id'];

if (!$client->readOne()) {
    $_SESSION['error'] = "Cliente não encontrado.";
    redirect("clients.php");
}

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_note':
                    $title = trim($_POST['title']);
                    $note = trim($_POST['note']);
                    
                    if (empty($title) || empty($note)) {
                        $_SESSION['error'] = "Título e nota são obrigatórios.";
                        redirect("client_interactions.php?client_id=" . $client_id);
                    }
                    
                    if (ClientInteraction::logNote($db, $_SESSION['user_id'], $client_id, $title, $note)) {
                        $_SESSION['message'] = "Nota adicionada com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao adicionar nota.";
                    }
                    
                    redirect("client_interactions.php?client_id=" . $client_id);
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("client_interactions.php?client_id=" . $client_id);
    }
}

// Buscar interações
$interactions_stmt = $clientInteraction->readByClient($client_id, $_SESSION['user_id'], 50, 0);
$interactions = $interactions_stmt->fetchAll();

// Buscar estatísticas
$stats = $clientInteraction->getStatistics($client_id, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?php echo Translation::getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interações - <?php echo htmlspecialchars($client->name); ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link rel="manifest" href="/public/manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link href="css/dark_mode.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-slate-900">
    <div class="flex h-screen bg-gray-100 dark:bg-slate-900">
        <?php include 'sidebar.php'; ?>

        <!-- Main content -->
        <div class="flex flex-col w-full md:w-0 md:flex-1 overflow-hidden">
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <div class="flex justify-between items-center">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">
                                    Interações - <?php echo htmlspecialchars($client->name); ?>
                                </h1>
                                <p class="text-gray-600 dark:text-slate-400 mt-1">
                                    <?php echo htmlspecialchars($client->phone); ?>
                                    <?php if ($client->email): ?>
                                        • <?php echo htmlspecialchars($client->email); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex space-x-3">
                                <button onclick="openNoteModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150">
                                    <i class="fas fa-plus mr-2"></i>
                                    Adicionar Nota
                                </button>
                                <a href="clients.php" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-4 py-2 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Voltar
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Mensagens de feedback -->
                        <?php if ($message): ?>
                            <div class="mt-4 bg-green-100 border-green-400 text-green-800 p-4 rounded-lg shadow-sm">
                                <div class="flex">
                                    <i class="fas fa-check-circle mr-3 mt-0.5"></i>
                                    <span><?php echo htmlspecialchars($message); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="mt-4 bg-red-100 border-red-400 text-red-800 p-4 rounded-lg shadow-sm">
                                <div class="flex">
                                    <i class="fas fa-exclamation-circle mr-3 mt-0.5"></i>
                                    <span><?php echo htmlspecialchars($error); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Estatísticas -->
                        <div class="mt-8">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-comments text-blue-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Total Interações</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['total_interactions'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fab fa-whatsapp text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Mensagens</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['message_count'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-dollar-sign text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Pagamentos</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['payment_count'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-sticky-note text-yellow-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Notas</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['note_count'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline de Interações -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-6">Timeline de Interações</h3>
                                
                                <?php if (empty($interactions)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-comments text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Nenhuma interação registrada</h3>
                                        <p class="text-lg text-gray-500 dark:text-slate-400 mb-4">Adicione a primeira nota ou interação</p>
                                        <button onclick="openNoteModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                            <i class="fas fa-plus mr-2"></i>
                                            Adicionar Primeira Nota
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="flow-root">
                                        <ul class="-mb-8">
                                            <?php foreach ($interactions as $index => $interaction): ?>
                                            <li>
                                                <div class="relative pb-8">
                                                    <?php if ($index < count($interactions) - 1): ?>
                                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-slate-600" aria-hidden="true"></span>
                                                    <?php endif; ?>
                                                    <div class="relative flex space-x-3">
                                                        <div>
                                                            <?php
                                                            $type_icons = [
                                                                'message' => 'fab fa-whatsapp text-green-500',
                                                                'payment' => 'fas fa-dollar-sign text-green-500',
                                                                'note' => 'fas fa-sticky-note text-yellow-500',
                                                                'status_change' => 'fas fa-exchange-alt text-blue-500',
                                                                'call' => 'fas fa-phone text-purple-500',
                                                                'meeting' => 'fas fa-calendar text-indigo-500'
                                                            ];
                                                            $icon_class = $type_icons[$interaction['type']] ?? 'fas fa-circle text-gray-500';
                                                            ?>
                                                            <span class="h-8 w-8 rounded-full bg-white dark:bg-slate-700 flex items-center justify-center ring-8 ring-white dark:ring-slate-800">
                                                                <i class="<?php echo $icon_class; ?>"></i>
                                                            </span>
                                                        </div>
                                                        <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                            <div>
                                                                <p class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                                    <?php echo htmlspecialchars($interaction['title']); ?>
                                                                </p>
                                                                <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                                                                    <?php echo nl2br(htmlspecialchars($interaction['description'])); ?>
                                                                </p>
                                                                
                                                                <?php if ($interaction['metadata']): ?>
                                                                <?php $metadata = json_decode($interaction['metadata'], true); ?>
                                                                <?php if ($metadata): ?>
                                                                <div class="mt-2 text-xs text-gray-500 dark:text-slate-500">
                                                                    <?php foreach ($metadata as $key => $value): ?>
                                                                        <?php if ($key !== 'full_message'): ?>
                                                                        <span class="inline-block mr-3">
                                                                            <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong> 
                                                                            <?php echo htmlspecialchars($value); ?>
                                                                        </span>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                                <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-right text-sm whitespace-nowrap text-gray-500 dark:text-slate-400">
                                                                <time datetime="<?php echo $interaction['created_at']; ?>">
                                                                    <?php echo date('d/m/Y H:i', strtotime($interaction['created_at'])); ?>
                                                                </time>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para adicionar nota -->
    <div id="noteModal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white dark:bg-slate-800 border-t-4 border-blue-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Adicionar Nota</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_note">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Título *</label>
                            <input type="text" name="title" id="title" required 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                   placeholder="Ex: Reunião de alinhamento, Feedback do cliente">
                        </div>
                        
                        <div>
                            <label for="note" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nota *</label>
                            <textarea name="note" id="note" rows="6" required 
                                      class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                      placeholder="Descreva os detalhes da interação, observações importantes, próximos passos, etc."></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeNoteModal()" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-5 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                            <i class="fas fa-save mr-2"></i>
                            Salvar Nota
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openNoteModal() {
            document.getElementById('noteModal').classList.remove('hidden');
        }

        function closeNoteModal() {
            document.getElementById('noteModal').classList.add('hidden');
            document.getElementById('title').value = '';
            document.getElementById('note').value = '';
        }

        // Fechar modal ao clicar fora
        document.getElementById('noteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNoteModal();
            }
        });
    </script>
</body>
</html>