<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../classes/Campaign.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/MessageTemplate.php';
require_once __DIR__ . '/../classes/Translation.php';

// Inicializar traduções
initTranslations();

$database = new Database();
$db = $database->getConnection();
$campaign = new Campaign($db);
$client = new Client($db);
$template = new MessageTemplate($db);

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

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_campaign':
                    $campaign->user_id = $_SESSION['user_id'];
                    $campaign->name = trim($_POST['name']);
                    $campaign->description = trim($_POST['description']);
                    $campaign->template_id = !empty($_POST['template_id']) ? $_POST['template_id'] : null;
                    $campaign->scheduled_for = !empty($_POST['scheduled_for']) ? $_POST['scheduled_for'] : null;
                    $campaign->status = 'draft';
                    
                    // Processar critérios de segmentação
                    $target_audience = [];
                    if (!empty($_POST['target_status'])) {
                        $target_audience['status'] = $_POST['target_status'];
                    }
                    if (!empty($_POST['target_score_min']) || !empty($_POST['target_score_max'])) {
                        $target_audience['score_range'] = [
                            'min' => intval($_POST['target_score_min'] ?? 0),
                            'max' => intval($_POST['target_score_max'] ?? 100)
                        ];
                    }
                    $campaign->target_audience = json_encode($target_audience);
                    
                    $validation_errors = $campaign->validate();
                    if (!empty($validation_errors)) {
                        $_SESSION['error'] = implode(', ', $validation_errors);
                        redirect("campaigns.php");
                    }
                    
                    if ($campaign->create()) {
                        // Adicionar destinatários baseado na segmentação
                        $recipients = $this->getTargetedClients($target_audience, $_SESSION['user_id'], $db);
                        if (!empty($recipients)) {
                            $campaign->addRecipients($recipients);
                        }
                        
                        $_SESSION['message'] = "Campanha criada com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao criar campanha.";
                    }
                    
                    redirect("campaigns.php");
                    break;
                    
                case 'execute_campaign':
                    $campaign->id = $_POST['campaign_id'];
                    $campaign->user_id = $_SESSION['user_id'];
                    
                    if ($campaign->readOne()) {
                        if ($campaign->execute()) {
                            $_SESSION['message'] = "Campanha executada com sucesso!";
                        } else {
                            $_SESSION['error'] = "Erro ao executar campanha.";
                        }
                    } else {
                        $_SESSION['error'] = "Campanha não encontrada.";
                    }
                    
                    redirect("campaigns.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("campaigns.php");
    }
}

// Buscar campanhas
$campaigns_stmt = $campaign->readByUser($_SESSION['user_id'], 50, 0);
$campaigns = $campaigns_stmt->fetchAll();

// Buscar templates ativos
$templates_stmt = $template->readAll($_SESSION['user_id']);
$templates = [];
while ($row = $templates_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['active']) {
        $templates[] = $row;
    }
}

// Buscar estatísticas
$stats = $campaign->getStatistics($_SESSION['user_id']);

/**
 * Função para obter clientes baseado na segmentação
 */
function getTargetedClients($target_audience, $user_id, $db) {
    $where_conditions = ["user_id = :user_id"];
    $params = [':user_id' => $user_id];
    
    if (!empty($target_audience['status'])) {
        $where_conditions[] = "status = :status";
        $params[':status'] = $target_audience['status'];
    }
    
    if (!empty($target_audience['score_range'])) {
        $where_conditions[] = "inadimplencia_score BETWEEN :score_min AND :score_max";
        $params[':score_min'] = $target_audience['score_range']['min'];
        $params[':score_max'] = $target_audience['score_range']['max'];
    }
    
    $query = "SELECT id FROM clients WHERE " . implode(' AND ', $where_conditions);
    $stmt = $db->prepare($query);
    
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="<?php echo Translation::getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campanhas de Marketing - <?php echo getSiteName(); ?></title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link rel="manifest" href="/public/manifest.json">
    <?php include 'pwa_init.php'; ?>
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
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Campanhas de Marketing</h1>
                            <button onclick="openCreateModal()" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md">
                                <i class="fas fa-bullhorn mr-2"></i>
                                Nova Campanha
                            </button>
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
                                                <i class="fas fa-bullhorn text-purple-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Total de Campanhas</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['total_campaigns'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-play text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Em Execução</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['running_campaigns'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-paper-plane text-blue-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Mensagens Enviadas</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['total_sent'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-users text-orange-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Total Destinatários</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['total_recipients'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de Campanhas -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Minhas Campanhas</h3>
                                
                                <?php if (empty($campaigns)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-bullhorn text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Nenhuma campanha criada</h3>
                                        <p class="text-lg text-gray-500 dark:text-slate-400 mb-4">Crie sua primeira campanha de marketing</p>
                                        <button onclick="openCreateModal()" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md">
                                            <i class="fas fa-bullhorn mr-2"></i>
                                            Criar Primeira Campanha
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <?php foreach ($campaigns as $campaign_row): ?>
                                        <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-6 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                            <div class="flex justify-between items-start mb-3">
                                                <h4 class="font-semibold text-gray-900 dark:text-slate-100"><?php echo htmlspecialchars($campaign_row['name']); ?></h4>
                                                <div class="flex space-x-2">
                                                    <?php
                                                    $status_classes = [
                                                        'draft' => 'bg-gray-100 text-gray-800',
                                                        'scheduled' => 'bg-blue-100 text-blue-800',
                                                        'running' => 'bg-yellow-100 text-yellow-800',
                                                        'completed' => 'bg-green-100 text-green-800',
                                                        'cancelled' => 'bg-red-100 text-red-800'
                                                    ];
                                                    $status_labels = [
                                                        'draft' => 'Rascunho',
                                                        'scheduled' => 'Agendada',
                                                        'running' => 'Executando',
                                                        'completed' => 'Concluída',
                                                        'cancelled' => 'Cancelada'
                                                    ];
                                                    ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_classes[$campaign_row['status']]; ?>">
                                                        <?php echo $status_labels[$campaign_row['status']]; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($campaign_row['description']): ?>
                                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                                                <?php echo htmlspecialchars($campaign_row['description']); ?>
                                            </p>
                                            <?php endif; ?>
                                            
                                            <div class="space-y-2 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600 dark:text-slate-400">Destinatários:</span>
                                                    <span class="font-medium text-gray-900 dark:text-slate-100"><?php echo $campaign_row['total_recipients']; ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600 dark:text-slate-400">Enviadas:</span>
                                                    <span class="font-medium text-green-600"><?php echo $campaign_row['sent_count']; ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600 dark:text-slate-400">Entregues:</span>
                                                    <span class="font-medium text-blue-600"><?php echo $campaign_row['delivered_count']; ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600 dark:text-slate-400">Falhas:</span>
                                                    <span class="font-medium text-red-600"><?php echo $campaign_row['failed_count']; ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4 pt-4 border-t dark:border-slate-600">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs text-gray-500 dark:text-slate-400">
                                                        <?php echo date('d/m/Y', strtotime($campaign_row['created_at'])); ?>
                                                    </span>
                                                    <div class="flex space-x-2">
                                                        <?php if ($campaign_row['status'] === 'draft'): ?>
                                                            <button onclick="executeCampaign(<?php echo $campaign_row['id']; ?>, '<?php echo htmlspecialchars($campaign_row['name']); ?>')" 
                                                                    class="text-green-600 hover:text-green-900 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                    title="Executar campanha">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button onclick="viewCampaign(<?php echo $campaign_row['id']; ?>)" 
                                                                class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                title="Ver detalhes">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para criar campanha -->
    <div id="createCampaignModal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-3xl shadow-lg rounded-md bg-white dark:bg-slate-800 border-t-4 border-purple-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Criar Nova Campanha</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_campaign">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Informações Básicas -->
                        <div class="space-y-4">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 border-b dark:border-slate-600 pb-2">Informações Básicas</h4>
                            
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome da Campanha *</label>
                                <input type="text" name="name" id="name" required 
                                       class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                            </div>
                            
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Descrição</label>
                                <textarea name="description" id="description" rows="3" 
                                          class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"></textarea>
                            </div>
                            
                            <div>
                                <label for="template_id" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Template *</label>
                                <select name="template_id" id="template_id" required 
                                        class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                    <option value="">Selecione um template</option>
                                    <?php foreach ($templates as $template_row): ?>
                                        <option value="<?php echo $template_row['id']; ?>">
                                            <?php echo htmlspecialchars($template_row['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="scheduled_for" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Agendar para (opcional)</label>
                                <input type="datetime-local" name="scheduled_for" id="scheduled_for" 
                                       min="<?php echo date('Y-m-d\TH:i'); ?>"
                                       class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                    Deixe em branco para executar imediatamente
                                </p>
                            </div>
                        </div>
                        
                        <!-- Segmentação -->
                        <div class="space-y-4">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 border-b dark:border-slate-600 pb-2">Segmentação de Clientes</h4>
                            
                            <div>
                                <label for="target_status" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Status dos Clientes</label>
                                <select name="target_status" id="target_status" 
                                        class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                    <option value="">Todos os status</option>
                                    <option value="active">Apenas ativos</option>
                                    <option value="inactive">Apenas inativos</option>
                                    <option value="pending">Apenas pendentes</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Score de Inadimplência</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <input type="number" name="target_score_min" id="target_score_min" 
                                               min="0" max="100" placeholder="Mín"
                                               class="block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                    </div>
                                    <div>
                                        <input type="number" name="target_score_max" id="target_score_max" 
                                               min="0" max="100" placeholder="Máx"
                                               class="block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                    Deixe em branco para incluir todos os scores
                                </p>
                            </div>
                            
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                <h5 class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">Prévia da Segmentação</h5>
                                <p class="text-sm text-blue-700 dark:text-blue-300" id="segmentationPreview">
                                    Configure os filtros acima para ver quantos clientes serão incluídos
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeCreateModal()" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-5 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md">
                            Criar Campanha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createCampaignModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createCampaignModal').classList.add('hidden');
        }

        function executeCampaign(id, name) {
            if (confirm('Tem certeza que deseja executar a campanha "' + name + '"? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="execute_campaign">
                    <input type="hidden" name="campaign_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewCampaign(id) {
            // Implementar visualização detalhada da campanha
            window.location.href = 'campaign_details.php?id=' + id;
        }

        // Fechar modal ao clicar fora
        document.getElementById('createCampaignModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });

        // Atualizar prévia da segmentação
        function updateSegmentationPreview() {
            // Implementar lógica para mostrar quantos clientes serão incluídos
            // baseado nos filtros selecionados
        }

        // Event listeners para atualizar prévia
        document.getElementById('target_status').addEventListener('change', updateSegmentationPreview);
        document.getElementById('target_score_min').addEventListener('input', updateSegmentationPreview);
        document.getElementById('target_score_max').addEventListener('input', updateSegmentationPreview);
    </script>
</body>
</html>