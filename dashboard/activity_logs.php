<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../classes/ActivityLog.php';
require_once __DIR__ . '/../classes/Translation.php';

// Verificar se é administrador
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = 'Acesso negado. Apenas administradores podem visualizar logs de atividade.';
    redirect("index.php");
}

// Inicializar traduções
initTranslations();

$database = new Database();
$db = $database->getConnection();
$activityLog = new ActivityLog($db);

// Filtros
$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';
$entity_filter = $_GET['entity_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$filters = [
    'user_id' => $user_filter,
    'action' => $action_filter,
    'entity_type' => $entity_filter,
    'date_from' => $date_from,
    'date_to' => $date_to
];

// Buscar logs
$logs_stmt = $activityLog->readAll($per_page, $offset, $filters);
$logs = $logs_stmt->fetchAll();

// Contar total de logs
$count_query = "SELECT COUNT(*) as total FROM activity_logs al WHERE 1=1";
$count_params = [];

if ($user_filter) {
    $count_query .= " AND al.user_id = :user_id";
    $count_params[':user_id'] = $user_filter;
}

if ($action_filter) {
    $count_query .= " AND al.action = :action";
    $count_params[':action'] = $action_filter;
}

if ($entity_filter) {
    $count_query .= " AND al.entity_type = :entity_type";
    $count_params[':entity_type'] = $entity_filter;
}

if ($date_from) {
    $count_query .= " AND DATE(al.created_at) >= :date_from";
    $count_params[':date_from'] = $date_from;
}

if ($date_to) {
    $count_query .= " AND DATE(al.created_at) <= :date_to";
    $count_params[':date_to'] = $date_to;
}

$count_stmt = $db->prepare($count_query);
foreach ($count_params as $param => $value) {
    $count_stmt->bindValue($param, $value);
}
$count_stmt->execute();
$total_logs = $count_stmt->fetch()['total'];

$total_pages = ceil($total_logs / $per_page);

// Buscar usuários para filtro
$users_query = "SELECT id, name FROM users ORDER BY name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll();

// Buscar ações únicas
$actions_query = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
$actions_stmt = $db->prepare($actions_query);
$actions_stmt->execute();
$actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

// Buscar tipos de entidade únicos
$entities_query = "SELECT DISTINCT entity_type FROM activity_logs ORDER BY entity_type";
$entities_stmt = $db->prepare($entities_query);
$entities_stmt->execute();
$entities = $entities_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="<?php echo Translation::getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Atividade - <?php echo getSiteName(); ?></title>
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
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Logs de Atividade</h1>
                            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
                        </div>
                    </div>
                    
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Filtros -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg p-6">
                            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Usuário</label>
                                    <select name="user_id" class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        <option value="">Todos os usuários</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Ação</label>
                                    <select name="action" class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        <option value="">Todas as ações</option>
                                        <?php foreach ($actions as $action): ?>
                                            <option value="<?php echo $action; ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                                <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Entidade</label>
                                    <select name="entity_type" class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        <option value="">Todas as entidades</option>
                                        <?php foreach ($entities as $entity): ?>
                                            <option value="<?php echo $entity; ?>" <?php echo $entity_filter === $entity ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($entity); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Data Início</label>
                                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                                           class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Data Fim</label>
                                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                                           class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                </div>
                                
                                <div class="flex items-end">
                                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150">
                                        <i class="fas fa-filter mr-2"></i>
                                        Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Lista de Logs -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100">
                                        Logs de Atividade
                                    </h3>
                                    <span class="text-sm text-gray-500 dark:text-slate-400">
                                        Total: <?php echo number_format($total_logs); ?> registros
                                    </span>
                                </div>
                                
                                <?php if (empty($logs)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-list text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Nenhum log encontrado</h3>
                                        <p class="text-lg text-gray-500 dark:text-slate-400">Ajuste os filtros para ver os logs de atividade</p>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                            <thead class="bg-gray-50 dark:bg-slate-700">
                                                <tr>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Usuário</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Ação</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Entidade</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Descrição</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">IP</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Data/Hora</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                                <?php foreach ($logs as $log): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                            <?php echo htmlspecialchars($log['user_name'] ?? 'Usuário removido'); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $action_classes = [
                                                            'login' => 'bg-green-100 text-green-800',
                                                            'logout' => 'bg-gray-100 text-gray-800',
                                                            'create' => 'bg-blue-100 text-blue-800',
                                                            'update' => 'bg-yellow-100 text-yellow-800',
                                                            'delete' => 'bg-red-100 text-red-800',
                                                            'message_sent' => 'bg-purple-100 text-purple-800',
                                                            'payment_received' => 'bg-green-100 text-green-800'
                                                        ];
                                                        $action_class = $action_classes[$log['action']] ?? 'bg-gray-100 text-gray-800';
                                                        ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $action_class; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-slate-100">
                                                        <?php echo ucfirst($log['entity_type']); ?>
                                                        <?php if ($log['entity_id']): ?>
                                                            <span class="text-gray-500 dark:text-slate-400">#<?php echo $log['entity_id']; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm text-gray-900 dark:text-slate-100 max-w-xs truncate" title="<?php echo htmlspecialchars($log['description']); ?>">
                                                            <?php echo htmlspecialchars($log['description']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                        <?php echo htmlspecialchars($log['ip_address']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                        <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Paginação -->
                                    <?php if ($total_pages > 1): ?>
                                    <div class="mt-6 flex items-center justify-between">
                                        <div class="text-sm text-gray-700 dark:text-slate-300">
                                            Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $per_page, $total_logs); ?> 
                                            de <?php echo number_format($total_logs); ?> logs
                                        </div>
                                        
                                        <div class="flex space-x-2">
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>" 
                                                   class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600">
                                                    Anterior
                                                </a>
                                            <?php endif; ?>
                                            
                                            <span class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-slate-300">
                                                Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                                            </span>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>" 
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
</body>
</html>