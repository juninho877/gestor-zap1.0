<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../classes/Ticket.php';
require_once __DIR__ . '/../classes/TicketResponse.php';
require_once __DIR__ . '/../classes/Translation.php';

// Inicializar traduções
initTranslations();

$database = new Database();
$db = $database->getConnection();
$ticket = new Ticket($db);
$ticketResponse = new TicketResponse($db);

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

// Verificar se é administrador
$is_admin = ($_SESSION['user_role'] === 'admin');

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_ticket':
                    $ticket->user_id = $_SESSION['user_id'];
                    $ticket->title = trim($_POST['title']);
                    $ticket->description = trim($_POST['description']);
                    $ticket->priority = $_POST['priority'];
                    $ticket->category = trim($_POST['category']);
                    $ticket->status = 'open';
                    
                    $validation_errors = $ticket->validate();
                    if (!empty($validation_errors)) {
                        $_SESSION['error'] = implode(', ', $validation_errors);
                        redirect("tickets.php");
                    }
                    
                    if ($ticket->create()) {
                        $_SESSION['message'] = "Ticket criado com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao criar ticket.";
                    }
                    
                    redirect("tickets.php");
                    break;
                    
                case 'add_response':
                    $ticketResponse->ticket_id = $_POST['ticket_id'];
                    $ticketResponse->user_id = $_SESSION['user_id'];
                    $ticketResponse->message = trim($_POST['message']);
                    $ticketResponse->is_internal = isset($_POST['is_internal']) && $is_admin;
                    
                    if (empty($ticketResponse->message)) {
                        $_SESSION['error'] = "Mensagem é obrigatória.";
                        redirect("tickets.php?view=" . $_POST['ticket_id']);
                    }
                    
                    if ($ticketResponse->create()) {
                        $_SESSION['message'] = "Resposta adicionada com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao adicionar resposta.";
                    }
                    
                    redirect("tickets.php?view=" . $_POST['ticket_id']);
                    break;
                    
                case 'update_ticket':
                    if (!$is_admin) {
                        $_SESSION['error'] = "Acesso negado.";
                        redirect("tickets.php");
                    }
                    
                    $ticket->id = $_POST['ticket_id'];
                    $ticket->status = $_POST['status'];
                    $ticket->priority = $_POST['priority'];
                    $ticket->assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
                    
                    if ($ticket->update()) {
                        $_SESSION['message'] = "Ticket atualizado com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao atualizar ticket.";
                    }
                    
                    redirect("tickets.php?view=" . $_POST['ticket_id']);
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("tickets.php");
    }
}

// Verificar se está visualizando um ticket específico
$viewing_ticket = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $ticket->id = $_GET['view'];
    $viewing_ticket = $ticket->readOne();
    
    if ($viewing_ticket && !$is_admin && $viewing_ticket['user_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = "Acesso negado a este ticket.";
        redirect("tickets.php");
    }
}

// Buscar tickets
if ($is_admin) {
    $tickets_stmt = $ticket->readAll(50, 0);
} else {
    $tickets_stmt = $ticket->readByUser($_SESSION['user_id'], 50, 0);
}
$tickets = $tickets_stmt->fetchAll();

// Buscar estatísticas
$stats = $ticket->getStatistics($is_admin ? null : $_SESSION['user_id']);

// Buscar usuários para atribuição (apenas admin)
$users = [];
if ($is_admin) {
    $users_query = "SELECT id, name FROM users WHERE role = 'admin' ORDER BY name";
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="<?php echo Translation::getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets de Suporte - <?php echo getSiteName(); ?></title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
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
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">
                                <?php if ($viewing_ticket): ?>
                                    Ticket #<?php echo $viewing_ticket['id']; ?>
                                    <a href="tickets.php" class="ml-4 text-lg text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-arrow-left"></i> Voltar
                                    </a>
                                <?php else: ?>
                                    Tickets de Suporte
                                <?php endif; ?>
                            </h1>
                            
                            <?php if (!$viewing_ticket): ?>
                            <button onclick="openCreateModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                <i class="fas fa-plus mr-2"></i>
                                Novo Ticket
                            </button>
                            <?php endif; ?>
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

                        <?php if ($viewing_ticket): ?>
                            <!-- Visualização de Ticket Específico -->
                            <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
                                <!-- Detalhes do Ticket -->
                                <div class="lg:col-span-2">
                                    <div class="bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                        <div class="px-6 py-6 sm:p-8">
                                            <div class="flex justify-between items-start mb-6">
                                                <div>
                                                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">
                                                        <?php echo htmlspecialchars($viewing_ticket['title']); ?>
                                                    </h2>
                                                    <p class="text-gray-600 dark:text-slate-400 mt-2">
                                                        Criado por: <?php echo htmlspecialchars($viewing_ticket['user_name']); ?> • 
                                                        <?php echo date('d/m/Y H:i', strtotime($viewing_ticket['created_at'])); ?>
                                                    </p>
                                                </div>
                                                
                                                <div class="flex space-x-2">
                                                    <?php
                                                    $status_classes = [
                                                        'open' => 'bg-blue-100 text-blue-800',
                                                        'in_progress' => 'bg-yellow-100 text-yellow-800',
                                                        'resolved' => 'bg-green-100 text-green-800',
                                                        'closed' => 'bg-gray-100 text-gray-800'
                                                    ];
                                                    $priority_classes = [
                                                        'low' => 'bg-gray-100 text-gray-800',
                                                        'medium' => 'bg-blue-100 text-blue-800',
                                                        'high' => 'bg-orange-100 text-orange-800',
                                                        'urgent' => 'bg-red-100 text-red-800'
                                                    ];
                                                    ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_classes[$viewing_ticket['status']]; ?>">
                                                        <?php echo ucfirst($viewing_ticket['status']); ?>
                                                    </span>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $priority_classes[$viewing_ticket['priority']]; ?>">
                                                        <?php echo ucfirst($viewing_ticket['priority']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="prose dark:prose-invert max-w-none">
                                                <p><?php echo nl2br(htmlspecialchars($viewing_ticket['description'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Respostas -->
                                    <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                        <div class="px-6 py-6 sm:p-8">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-6">Respostas</h3>
                                            
                                            <?php
                                            $responses_stmt = $ticketResponse->readByTicket($viewing_ticket['id'], $is_admin);
                                            $responses = $responses_stmt->fetchAll();
                                            ?>
                                            
                                            <div class="space-y-6">
                                                <?php foreach ($responses as $response): ?>
                                                <div class="border-l-4 <?php echo $response['is_internal'] ? 'border-yellow-400 bg-yellow-50 dark:bg-yellow-900/20' : 'border-blue-400 bg-blue-50 dark:bg-blue-900/20'; ?> pl-4 py-3">
                                                    <div class="flex justify-between items-start mb-2">
                                                        <div class="font-medium text-gray-900 dark:text-slate-100">
                                                            <?php echo htmlspecialchars($response['user_name']); ?>
                                                            <?php if ($response['is_internal']): ?>
                                                                <span class="ml-2 text-xs bg-yellow-200 text-yellow-800 px-2 py-1 rounded">Interno</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="text-sm text-gray-500 dark:text-slate-400">
                                                            <?php echo date('d/m/Y H:i', strtotime($response['created_at'])); ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-gray-700 dark:text-slate-300">
                                                        <?php echo nl2br(htmlspecialchars($response['message'])); ?>
                                                    </p>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <!-- Formulário de Resposta -->
                                            <div class="mt-8 border-t dark:border-slate-600 pt-6">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="add_response">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $viewing_ticket['id']; ?>">
                                                    
                                                    <div class="mb-4">
                                                        <label for="message" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
                                                            Sua resposta
                                                        </label>
                                                        <textarea name="message" id="message" rows="4" required
                                                                  class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                                                  placeholder="Digite sua resposta..."></textarea>
                                                    </div>
                                                    
                                                    <?php if ($is_admin): ?>
                                                    <div class="mb-4">
                                                        <label class="flex items-center">
                                                            <input type="checkbox" name="is_internal" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                            <span class="ml-2 text-sm text-gray-700 dark:text-slate-300">Resposta interna (não visível para o usuário)</span>
                                                        </label>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150">
                                                        <i class="fas fa-reply mr-2"></i>
                                                        Enviar Resposta
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sidebar com informações do ticket -->
                                <div class="lg:col-span-1">
                                    <?php if ($is_admin): ?>
                                    <!-- Painel de Administração -->
                                    <div class="bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                        <div class="px-6 py-6 sm:p-8">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">Administração</h3>
                                            
                                            <form method="POST">
                                                <input type="hidden" name="action" value="update_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo $viewing_ticket['id']; ?>">
                                                
                                                <div class="space-y-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Status</label>
                                                        <select name="status" class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                                            <option value="open" <?php echo $viewing_ticket['status'] === 'open' ? 'selected' : ''; ?>>Aberto</option>
                                                            <option value="in_progress" <?php echo $viewing_ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>Em Andamento</option>
                                                            <option value="resolved" <?php echo $viewing_ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolvido</option>
                                                            <option value="closed" <?php echo $viewing_ticket['status'] === 'closed' ? 'selected' : ''; ?>>Fechado</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Prioridade</label>
                                                        <select name="priority" class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                                            <option value="low" <?php echo $viewing_ticket['priority'] === 'low' ? 'selected' : ''; ?>>Baixa</option>
                                                            <option value="medium" <?php echo $viewing_ticket['priority'] === 'medium' ? 'selected' : ''; ?>>Média</option>
                                                            <option value="high" <?php echo $viewing_ticket['priority'] === 'high' ? 'selected' : ''; ?>>Alta</option>
                                                            <option value="urgent" <?php echo $viewing_ticket['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgente</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Atribuído a</label>
                                                        <select name="assigned_to" class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                                            <option value="">Não atribuído</option>
                                                            <?php foreach ($users as $user): ?>
                                                                <option value="<?php echo $user['id']; ?>" <?php echo $viewing_ticket['assigned_to'] == $user['id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-150">
                                                        <i class="fas fa-save mr-2"></i>
                                                        Atualizar Ticket
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Informações do Ticket -->
                                    <div class="<?php echo $is_admin ? 'mt-6' : ''; ?> bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                        <div class="px-6 py-6 sm:p-8">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">Informações</h3>
                                            
                                            <dl class="space-y-3">
                                                <div>
                                                    <dt class="text-sm font-medium text-gray-500 dark:text-slate-400">ID</dt>
                                                    <dd class="text-sm text-gray-900 dark:text-slate-100">#<?php echo $viewing_ticket['id']; ?></dd>
                                                </div>
                                                <div>
                                                    <dt class="text-sm font-medium text-gray-500 dark:text-slate-400">Categoria</dt>
                                                    <dd class="text-sm text-gray-900 dark:text-slate-100"><?php echo htmlspecialchars($viewing_ticket['category'] ?: 'Não especificada'); ?></dd>
                                                </div>
                                                <div>
                                                    <dt class="text-sm font-medium text-gray-500 dark:text-slate-400">Criado em</dt>
                                                    <dd class="text-sm text-gray-900 dark:text-slate-100"><?php echo date('d/m/Y H:i', strtotime($viewing_ticket['created_at'])); ?></dd>
                                                </div>
                                                <div>
                                                    <dt class="text-sm font-medium text-gray-500 dark:text-slate-400">Última atualização</dt>
                                                    <dd class="text-sm text-gray-900 dark:text-slate-100"><?php echo date('d/m/Y H:i', strtotime($viewing_ticket['updated_at'])); ?></dd>
                                                </div>
                                                <?php if ($viewing_ticket['resolved_at']): ?>
                                                <div>
                                                    <dt class="text-sm font-medium text-gray-500 dark:text-slate-400">Resolvido em</dt>
                                                    <dd class="text-sm text-gray-900 dark:text-slate-100"><?php echo date('d/m/Y H:i', strtotime($viewing_ticket['resolved_at'])); ?></dd>
                                                </div>
                                                <?php endif; ?>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Lista de Tickets -->
                            
                            <!-- Estatísticas -->
                            <div class="mt-8">
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                        <div class="p-6">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-ticket-alt text-blue-400 text-3xl"></i>
                                                </div>
                                                <div class="ml-5 w-0 flex-1">
                                                    <dl>
                                                        <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Total de Tickets</dt>
                                                        <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['total_tickets'] ?? 0; ?></dd>
                                                    </dl>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                        <div class="p-6">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-folder-open text-green-400 text-3xl"></i>
                                                </div>
                                                <div class="ml-5 w-0 flex-1">
                                                    <dl>
                                                        <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Abertos</dt>
                                                        <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['open_tickets'] ?? 0; ?></dd>
                                                    </dl>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                        <div class="p-6">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-cog text-yellow-400 text-3xl"></i>
                                                </div>
                                                <div class="ml-5 w-0 flex-1">
                                                    <dl>
                                                        <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Em Andamento</dt>
                                                        <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['in_progress_tickets'] ?? 0; ?></dd>
                                                    </dl>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                        <div class="p-6">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-exclamation-triangle text-red-400 text-3xl"></i>
                                                </div>
                                                <div class="ml-5 w-0 flex-1">
                                                    <dl>
                                                        <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Urgentes</dt>
                                                        <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['urgent_tickets'] ?? 0; ?></dd>
                                                    </dl>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Lista de Tickets -->
                            <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                <div class="px-6 py-6 sm:p-8">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">
                                        <?php echo $is_admin ? 'Todos os Tickets' : 'Meus Tickets'; ?>
                                    </h3>
                                    
                                    <?php if (empty($tickets)): ?>
                                        <div class="text-center py-12">
                                            <i class="fas fa-ticket-alt text-gray-300 text-7xl mb-4"></i>
                                            <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Nenhum ticket encontrado</h3>
                                            <p class="text-lg text-gray-500 dark:text-slate-400 mb-4">Crie seu primeiro ticket de suporte</p>
                                            <button onclick="openCreateModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                                <i class="fas fa-plus mr-2"></i>
                                                Criar Primeiro Ticket
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                                <thead class="bg-gray-50 dark:bg-slate-700">
                                                    <tr>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">ID</th>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Título</th>
                                                        <?php if ($is_admin): ?>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Usuário</th>
                                                        <?php endif; ?>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Status</th>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Prioridade</th>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Criado em</th>
                                                        <th class="px-6 py-4 text-right text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                                    <?php foreach ($tickets as $ticket_row): ?>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-slate-100">
                                                            #<?php echo $ticket_row['id']; ?>
                                                        </td>
                                                        <td class="px-6 py-4">
                                                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                                <?php echo htmlspecialchars($ticket_row['title']); ?>
                                                            </div>
                                                            <?php if ($ticket_row['category']): ?>
                                                            <div class="text-sm text-gray-500 dark:text-slate-400">
                                                                <?php echo htmlspecialchars($ticket_row['category']); ?>
                                                            </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <?php if ($is_admin): ?>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900 dark:text-slate-100">
                                                                <?php echo htmlspecialchars($ticket_row['user_name'] ?? 'Usuário removido'); ?>
                                                            </div>
                                                        </td>
                                                        <?php endif; ?>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_classes[$ticket_row['status']]; ?>">
                                                                <?php echo ucfirst($ticket_row['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $priority_classes[$ticket_row['priority']]; ?>">
                                                                <?php echo ucfirst($ticket_row['priority']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                            <?php echo date('d/m/Y H:i', strtotime($ticket_row['created_at'])); ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <a href="tickets.php?view=<?php echo $ticket_row['id']; ?>" 
                                                               class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para criar ticket -->
    <div id="createTicketModal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white dark:bg-slate-800 border-t-4 border-blue-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Criar Novo Ticket</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_ticket">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Título *</label>
                            <input type="text" name="title" id="title" required 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                        </div>
                        
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Categoria</label>
                            <input type="text" name="category" id="category" 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                   placeholder="Ex: Técnico, Financeiro, Dúvida">
                        </div>
                        
                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Prioridade</label>
                            <select name="priority" id="priority" 
                                    class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                <option value="low">Baixa</option>
                                <option value="medium" selected>Média</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Descrição *</label>
                            <textarea name="description" id="description" rows="6" required 
                                      class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                      placeholder="Descreva detalhadamente o problema ou solicitação..."></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeCreateModal()" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-5 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                            Criar Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createTicketModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createTicketModal').classList.add('hidden');
        }

        // Fechar modal ao clicar fora
        document.getElementById('createTicketModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });
    </script>
</body>
</html>