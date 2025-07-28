<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../classes/Translation.php';

// Inicializar traduções
initTranslations();

$database = new Database();
$db = $database->getConnection();
$notification = new Notification($db);

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
                case 'mark_read':
                    $notification_id = $_POST['notification_id'];
                    if ($notification->markAsRead($notification_id, $_SESSION['user_id'])) {
                        $_SESSION['message'] = "Notificação marcada como lida!";
                    } else {
                        $_SESSION['error'] = "Erro ao marcar notificação como lida.";
                    }
                    redirect("notifications.php");
                    break;
                    
                case 'mark_all_read':
                    if ($notification->markAllAsRead($_SESSION['user_id'])) {
                        $_SESSION['message'] = "Todas as notificações foram marcadas como lidas!";
                    } else {
                        $_SESSION['error'] = "Erro ao marcar notificações como lidas.";
                    }
                    redirect("notifications.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("notifications.php");
    }
}

// Filtros
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Buscar notificações
$notifications_stmt = $notification->readByUser($_SESSION['user_id'], $per_page, $offset, $status_filter === 'unread');
$notifications = $notifications_stmt->fetchAll();

// Contar total de notificações
$total_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = :user_id";
if ($status_filter === 'unread') {
    $total_query .= " AND read_at IS NULL";
}

$total_stmt = $db->prepare($total_query);
$total_stmt->bindParam(':user_id', $_SESSION['user_id']);
$total_stmt->execute();
$total_notifications = $total_stmt->fetch()['total'];

$total_pages = ceil($total_notifications / $per_page);

// Contar não lidas
$unread_count = $notification->countUnread($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?php echo Translation::getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('notifications', 'dashboard'); ?> - <?php echo getSiteName(); ?></title>
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
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">
                                <?php echo __('notifications', 'dashboard'); ?>
                                <?php if ($unread_count > 0): ?>
                                    <span class="ml-2 bg-red-500 text-white text-sm px-2 py-1 rounded-full">
                                        <?php echo $unread_count; ?>
                                    </span>
                                <?php endif; ?>
                            </h1>
                            
                            <?php if ($unread_count > 0): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150">
                                    <i class="fas fa-check-double mr-2"></i>
                                    <?php echo __('mark_all_read', 'dashboard'); ?>
                                </button>
                            </form>
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

                        <!-- Filtros -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg p-6">
                            <div class="flex flex-col sm:flex-row gap-4">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
                                        Filtrar por status
                                    </label>
                                    <select id="statusFilter" onchange="applyFilters()" 
                                            class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        <option value="">Todas</option>
                                        <option value="unread" <?php echo $status_filter === 'unread' ? 'selected' : ''; ?>>Não lidas</option>
                                        <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Lidas</option>
                                    </select>
                                </div>
                                
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
                                        Filtrar por tipo
                                    </label>
                                    <select id="typeFilter" onchange="applyFilters()" 
                                            class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        <option value="">Todos os tipos</option>
                                        <option value="info" <?php echo $type_filter === 'info' ? 'selected' : ''; ?>>Informação</option>
                                        <option value="success" <?php echo $type_filter === 'success' ? 'selected' : ''; ?>>Sucesso</option>
                                        <option value="warning" <?php echo $type_filter === 'warning' ? 'selected' : ''; ?>>Aviso</option>
                                        <option value="error" <?php echo $type_filter === 'error' ? 'selected' : ''; ?>>Erro</option>
                                        <option value="system" <?php echo $type_filter === 'system' ? 'selected' : ''; ?>>Sistema</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de Notificações -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <?php if (empty($notifications)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-bell text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">
                                            <?php echo __('no_notifications', 'dashboard'); ?>
                                        </h3>
                                        <p class="text-lg text-gray-500 dark:text-slate-400">
                                            Você não tem notificações no momento
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($notifications as $notif): ?>
                                        <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 <?php echo $notif['read_at'] ? 'bg-gray-50 dark:bg-slate-700' : 'bg-white dark:bg-slate-800'; ?>">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center">
                                                        <?php
                                                        $type_icons = [
                                                            'info' => 'fas fa-info-circle text-blue-500',
                                                            'success' => 'fas fa-check-circle text-green-500',
                                                            'warning' => 'fas fa-exclamation-triangle text-yellow-500',
                                                            'error' => 'fas fa-times-circle text-red-500',
                                                            'system' => 'fas fa-cog text-gray-500'
                                                        ];
                                                        $icon_class = $type_icons[$notif['type']] ?? 'fas fa-bell text-gray-500';
                                                        ?>
                                                        <i class="<?php echo $icon_class; ?> mr-3"></i>
                                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100">
                                                            <?php echo htmlspecialchars($notif['title']); ?>
                                                        </h4>
                                                        <?php if (!$notif['read_at']): ?>
                                                            <span class="ml-2 w-2 h-2 bg-blue-500 rounded-full"></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="mt-2 text-gray-600 dark:text-slate-400">
                                                        <?php echo htmlspecialchars($notif['message']); ?>
                                                    </p>
                                                    <p class="mt-2 text-sm text-gray-500 dark:text-slate-500">
                                                        <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                                                        <?php if ($notif['read_at']): ?>
                                                            • Lida em <?php echo date('d/m/Y H:i', strtotime($notif['read_at'])); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                
                                                <?php if (!$notif['read_at']): ?>
                                                <form method="POST" class="ml-4">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                                    <button type="submit" class="text-blue-600 hover:text-blue-800 p-2 rounded-full hover:bg-gray-200 dark:hover:bg-slate-600 transition duration-150" title="Marcar como lida">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Paginação -->
                                    <?php if ($total_pages > 1): ?>
                                    <div class="mt-6 flex items-center justify-between">
                                        <div class="text-sm text-gray-700 dark:text-slate-300">
                                            Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $per_page, $total_notifications); ?> 
                                            de <?php echo $total_notifications; ?> notificações
                                        </div>
                                        
                                        <div class="flex space-x-2">
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>" 
                                                   class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600">
                                                    Anterior
                                                </a>
                                            <?php endif; ?>
                                            
                                            <span class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-slate-300">
                                                Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                                            </span>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>" 
                                                   class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600">
                                                    Próxima
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const type = document.getElementById('typeFilter').value;
            
            const params = new URLSearchParams();
            if (status) params.set('status', status);
            if (type) params.set('type', type);
            
            window.location.href = 'notifications.php?' + params.toString();
        }
    </script>
</body>
</html>