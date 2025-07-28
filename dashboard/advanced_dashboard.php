<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/MessageHistory.php';
require_once __DIR__ . '/../classes/ClientPayment.php';
require_once __DIR__ . '/../classes/InadimplenciaScore.php';
require_once __DIR__ . '/../classes/Translation.php';

// Inicializar traduções
initTranslations();

$database = new Database();
$db = $database->getConnection();

// Obter período do filtro (padrão: 30 dias)
$period = $_GET['period'] ?? '30';
$valid_periods = ['7', '30', '90', '365'];
if (!in_array($period, $valid_periods)) {
    $period = '30';
}

// Calcular datas
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-{$period} days"));

// Estatísticas gerais
$client = new Client($db);
$messageHistory = new MessageHistory($db);
$clientPayment = new ClientPayment($db);
$scoreCalculator = new InadimplenciaScore($db);

// Buscar dados para gráficos
$revenue_query = "SELECT 
    DATE(cp.paid_at) as date,
    SUM(cp.amount) as revenue,
    COUNT(cp.id) as payments
FROM client_payments cp 
WHERE cp.user_id = :user_id 
AND cp.status = 'approved' 
AND DATE(cp.paid_at) BETWEEN :start_date AND :end_date
GROUP BY DATE(cp.paid_at)
ORDER BY date ASC";

$stmt = $db->prepare($revenue_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$revenue_data = $stmt->fetchAll();

// Dados de clientes por status
$clients_status_query = "SELECT 
    status,
    COUNT(*) as count
FROM clients 
WHERE user_id = :user_id 
GROUP BY status";

$stmt = $db->prepare($clients_status_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$clients_status = $stmt->fetchAll();

// Dados de inadimplência
$inadimplencia_stats = $scoreCalculator->getStatistics($_SESSION['user_id']);

// Receita total do período
$total_revenue = array_sum(array_column($revenue_data, 'revenue'));

// Receita do período anterior para comparação
$prev_start = date('Y-m-d', strtotime("-" . ($period * 2) . " days"));
$prev_end = date('Y-m-d', strtotime("-{$period} days"));

$prev_revenue_query = "SELECT SUM(amount) as revenue 
FROM client_payments 
WHERE user_id = :user_id 
AND status = 'approved' 
AND DATE(paid_at) BETWEEN :start_date AND :end_date";

$stmt = $db->prepare($prev_revenue_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':start_date', $prev_start);
$stmt->bindParam(':end_date', $prev_end);
$stmt->execute();
$prev_revenue = $stmt->fetch()['revenue'] ?? 0;

// Calcular crescimento
$growth_percentage = 0;
if ($prev_revenue > 0) {
    $growth_percentage = (($total_revenue - $prev_revenue) / $prev_revenue) * 100;
}

// Mensagens do período
$messages_stats = $messageHistory->getStatistics($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?php echo Translation::getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('dashboard', 'dashboard'); ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link href="css/dark_mode.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
                                <?php echo __('dashboard', 'dashboard'); ?>
                            </h1>
                            
                            <!-- Filtros de período -->
                            <div class="flex space-x-2">
                                <select id="periodFilter" onchange="changePeriod()" 
                                        class="bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md px-3 py-2 text-sm">
                                    <option value="7" <?php echo $period == '7' ? 'selected' : ''; ?>>7 dias</option>
                                    <option value="30" <?php echo $period == '30' ? 'selected' : ''; ?>>30 dias</option>
                                    <option value="90" <?php echo $period == '90' ? 'selected' : ''; ?>>90 dias</option>
                                    <option value="365" <?php echo $period == '365' ? 'selected' : ''; ?>>12 meses</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Cards de métricas principais -->
                        <div class="mt-8">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                <!-- Receita Mensal -->
                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-dollar-sign text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">
                                                        <?php echo __('monthly_revenue', 'dashboard'); ?>
                                                    </dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100">
                                                        R$ <?php echo number_format($total_revenue, 2, ',', '.'); ?>
                                                    </dd>
                                                    <dd class="text-sm <?php echo $growth_percentage >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                        <i class="fas fa-arrow-<?php echo $growth_percentage >= 0 ? 'up' : 'down'; ?> mr-1"></i>
                                                        <?php echo abs(round($growth_percentage, 1)); ?>% vs período anterior
                                                    </dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Clientes Ativos -->
                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-users text-blue-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">
                                                        <?php echo __('active_clients', 'dashboard'); ?>
                                                    </dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100">
                                                        <?php 
                                                        $active_count = 0;
                                                        foreach ($clients_status as $status) {
                                                            if ($status['status'] === 'active') {
                                                                $active_count = $status['count'];
                                                                break;
                                                            }
                                                        }
                                                        echo $active_count;
                                                        ?>
                                                    </dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Percentual de Inadimplência -->
                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle text-red-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">
                                                        Inadimplência
                                                    </dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100">
                                                        <?php 
                                                        $total_clients = $inadimplencia_stats['total_clients'] ?? 1;
                                                        $high_risk = $inadimplencia_stats['high_risk'] ?? 0;
                                                        $inadimplencia_percent = ($high_risk / $total_clients) * 100;
                                                        echo round($inadimplencia_percent, 1); 
                                                        ?>%
                                                    </dd>
                                                    <dd class="text-sm text-gray-500 dark:text-slate-400">
                                                        <?php echo $high_risk; ?> clientes alto risco
                                                    </dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mensagens Enviadas -->
                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fab fa-whatsapp text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">
                                                        <?php echo __('messages_sent', 'dashboard'); ?>
                                                    </dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100">
                                                        <?php echo $messages_stats['total_messages'] ?? 0; ?>
                                                    </dd>
                                                    <dd class="text-sm text-green-600">
                                                        <?php 
                                                        $success_rate = 0;
                                                        if ($messages_stats['total_messages'] > 0) {
                                                            $success_rate = ($messages_stats['sent_count'] / $messages_stats['total_messages']) * 100;
                                                        }
                                                        echo round($success_rate, 1); 
                                                        ?>% taxa de sucesso
                                                    </dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráficos -->
                        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Gráfico de Receita -->
                            <div class="bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                <div class="px-6 py-6 sm:p-8">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">
                                        <?php echo __('revenue_comparison', 'dashboard'); ?>
                                    </h3>
                                    <div class="h-64">
                                        <canvas id="revenueChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Gráfico de Status dos Clientes -->
                            <div class="bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                <div class="px-6 py-6 sm:p-8">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">
                                        Status dos Clientes
                                    </h3>
                                    <div class="h-64">
                                        <canvas id="clientsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Score de Inadimplência -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">
                                    Distribuição de Score de Inadimplência
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                    <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                        <div class="text-2xl font-bold text-green-600"><?php echo $inadimplencia_stats['low_risk'] ?? 0; ?></div>
                                        <div class="text-sm text-gray-600 dark:text-slate-400">Baixo Risco (0-30)</div>
                                    </div>
                                    <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                        <div class="text-2xl font-bold text-yellow-600"><?php echo $inadimplencia_stats['medium_risk'] ?? 0; ?></div>
                                        <div class="text-sm text-gray-600 dark:text-slate-400">Médio Risco (31-70)</div>
                                    </div>
                                    <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                        <div class="text-2xl font-bold text-red-600"><?php echo $inadimplencia_stats['high_risk'] ?? 0; ?></div>
                                        <div class="text-sm text-gray-600 dark:text-slate-400">Alto Risco (71-100)</div>
                                    </div>
                                </div>
                                <div class="h-64">
                                    <canvas id="scoreChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Dados para os gráficos
        const revenueData = <?php echo json_encode($revenue_data); ?>;
        const clientsStatusData = <?php echo json_encode($clients_status); ?>;
        const inadimplenciaStats = <?php echo json_encode($inadimplencia_stats); ?>;

        // Gráfico de Receita
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                }),
                datasets: [{
                    label: 'Receita Diária',
                    data: revenueData.map(item => parseFloat(item.revenue)),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Receita: R$ ' + context.parsed.y.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de Status dos Clientes
        const clientsCtx = document.getElementById('clientsChart').getContext('2d');
        new Chart(clientsCtx, {
            type: 'doughnut',
            data: {
                labels: clientsStatusData.map(item => {
                    const statusMap = {
                        'active': 'Ativos',
                        'inactive': 'Inativos',
                        'pending': 'Pendentes'
                    };
                    return statusMap[item.status] || item.status;
                }),
                datasets: [{
                    data: clientsStatusData.map(item => parseInt(item.count)),
                    backgroundColor: [
                        'rgb(34, 197, 94)',
                        'rgb(239, 68, 68)',
                        'rgb(251, 191, 36)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de Score de Inadimplência
        const scoreCtx = document.getElementById('scoreChart').getContext('2d');
        new Chart(scoreCtx, {
            type: 'bar',
            data: {
                labels: ['Baixo Risco', 'Médio Risco', 'Alto Risco'],
                datasets: [{
                    label: 'Número de Clientes',
                    data: [
                        inadimplenciaStats.low_risk || 0,
                        inadimplenciaStats.medium_risk || 0,
                        inadimplenciaStats.high_risk || 0
                    ],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Função para alterar período
        function changePeriod() {
            const period = document.getElementById('periodFilter').value;
            window.location.href = '?period=' + period;
        }
    </script>
</body>
</html>