<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/ClientPayment.php';
require_once __DIR__ . '/../classes/Translation.php';

// Inicializar traduções
initTranslations();

$database = new Database();
$db = $database->getConnection();

// Filtros
$period_start = $_GET['start_date'] ?? date('Y-m-01', strtotime('-12 months'));
$period_end = $_GET['end_date'] ?? date('Y-m-d');
$client_filter = $_GET['client_id'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Buscar dados de recorrência
$query = "SELECT 
    c.id,
    c.name,
    c.phone,
    c.subscription_amount,
    c.due_date,
    c.status,
    c.inadimplencia_score,
    COUNT(cp.id) as total_payments,
    SUM(CASE WHEN cp.status = 'approved' THEN 1 ELSE 0 END) as successful_payments,
    AVG(CASE 
        WHEN cp.paid_at IS NOT NULL AND c.due_date IS NOT NULL 
        THEN DATEDIFF(cp.paid_at, c.due_date) 
        ELSE 0 
    END) as avg_delay_days,
    MIN(cp.paid_at) as first_payment,
    MAX(cp.paid_at) as last_payment,
    SUM(CASE WHEN cp.status = 'approved' THEN cp.amount ELSE 0 END) as total_revenue
FROM clients c
LEFT JOIN client_payments cp ON c.id = cp.client_id 
    AND cp.created_at BETWEEN :start_date AND :end_date
WHERE c.user_id = :user_id";

$params = [
    ':user_id' => $_SESSION['user_id'],
    ':start_date' => $period_start,
    ':end_date' => $period_end
];

if ($client_filter) {
    $query .= " AND c.id = :client_id";
    $params[':client_id'] = $client_filter;
}

if ($status_filter) {
    $query .= " AND c.status = :status";
    $params[':status'] = $status_filter;
}

$query .= " GROUP BY c.id ORDER BY total_revenue DESC, c.name ASC";

$stmt = $db->prepare($query);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$recurrence_data = $stmt->fetchAll();

// Calcular métricas gerais
$total_clients = count($recurrence_data);
$total_revenue = array_sum(array_column($recurrence_data, 'total_revenue'));
$avg_payments_per_client = $total_clients > 0 ? array_sum(array_column($recurrence_data, 'total_payments')) / $total_clients : 0;

// Buscar todos os clientes para o filtro
$clients_stmt = $db->prepare("SELECT id, name FROM clients WHERE user_id = :user_id ORDER BY name");
$clients_stmt->bindParam(':user_id', $_SESSION['user_id']);
$clients_stmt->execute();
$all_clients = $clients_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?php echo Translation::getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Recorrência - <?php echo getSiteName(); ?></title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link rel="manifest" href="/public/manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link href="css/dark_mode.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Relatório de Recorrência</h1>
                            <div class="flex space-x-2">
                                <button onclick="exportToExcel()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-150">
                                    <i class="fas fa-file-excel mr-2"></i>
                                    Excel
                                </button>
                                <button onclick="exportToPDF()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-150">
                                    <i class="fas fa-file-pdf mr-2"></i>
                                    PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Filtros -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg p-6">
                            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Data Início</label>
                                    <input type="date" name="start_date" value="<?php echo $period_start; ?>" 
                                           class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Data Fim</label>
                                    <input type="date" name="end_date" value="<?php echo $period_end; ?>" 
                                           class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Cliente</label>
                                    <select name="client_id" class="w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        <option value="">Todos os clientes</option>
                                        <?php foreach ($all_clients as $client_option): ?>
                                            <option value="<?php echo $client_option['id']; ?>" <?php echo $client_filter == $client_option['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client_option['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="flex items-end">
                                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150">
                                        <i class="fas fa-filter mr-2"></i>
                                        Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Métricas Gerais -->
                        <div class="mt-8">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-users text-blue-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Total de Clientes</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $total_clients; ?></dd>
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
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Receita Total</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100">R$ <?php echo number_format($total_revenue, 2, ',', '.'); ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-sync-alt text-purple-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Média Pagamentos/Cliente</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo number_format($avg_payments_per_client, 1); ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Tendência -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Tendência de Pagamentos</h3>
                                <div class="h-64">
                                    <canvas id="trendChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Tabela Detalhada -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Análise por Cliente</h3>
                                
                                <div class="overflow-x-auto">
                                    <table id="recurrenceTable" class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                        <thead class="bg-gray-50 dark:bg-slate-700">
                                            <tr>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Cliente</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Total Pagamentos</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Pagamentos Bem-sucedidos</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">% Pontualidade</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Média Atraso (dias)</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Receita Total</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Score Inadimplência</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                            <?php foreach ($recurrence_data as $row): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                            <?php echo htmlspecialchars($row['name']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500 dark:text-slate-400">
                                                            <?php echo htmlspecialchars($row['phone']); ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-slate-100">
                                                    <?php echo $row['total_payments']; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-slate-100">
                                                    <?php echo $row['successful_payments']; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php 
                                                    $punctuality = $row['total_payments'] > 0 ? ($row['successful_payments'] / $row['total_payments']) * 100 : 0;
                                                    $punctuality_class = $punctuality >= 80 ? 'text-green-600' : ($punctuality >= 60 ? 'text-yellow-600' : 'text-red-600');
                                                    ?>
                                                    <span class="text-sm font-medium <?php echo $punctuality_class; ?>">
                                                        <?php echo number_format($punctuality, 1); ?>%
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-slate-100">
                                                    <?php echo number_format($row['avg_delay_days'], 1); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-slate-100">
                                                    R$ <?php echo number_format($row['total_revenue'], 2, ',', '.'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $score = $row['inadimplencia_score'];
                                                    $score_class = $score <= 30 ? 'bg-green-100 text-green-800' : ($score <= 70 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                                    ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $score_class; ?>">
                                                        <?php echo $score; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Dados para o gráfico de tendência
        const recurrenceData = <?php echo json_encode($recurrence_data); ?>;
        
        // Gráfico de Tendência de Pagamentos
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: recurrenceData.map(item => item.name),
                datasets: [{
                    label: 'Receita Total (R$)',
                    data: recurrenceData.map(item => parseFloat(item.total_revenue)),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1,
                    fill: true
                }, {
                    label: 'Pagamentos Bem-sucedidos',
                    data: recurrenceData.map(item => parseInt(item.successful_payments)),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Clientes'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Receita (R$)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Número de Pagamentos'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Exportar para Excel
        function exportToExcel() {
            const table = document.getElementById('recurrenceTable');
            const wb = XLSX.utils.table_to_book(table, {sheet: "Relatório de Recorrência"});
            XLSX.writeFile(wb, `relatorio_recorrencia_${new Date().toISOString().split('T')[0]}.xlsx`);
        }

        // Exportar para PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Título
            doc.setFontSize(16);
            doc.text('Relatório de Recorrência', 20, 20);
            
            // Período
            doc.setFontSize(12);
            doc.text(`Período: ${document.querySelector('input[name="start_date"]').value} a ${document.querySelector('input[name="end_date"]').value}`, 20, 30);
            
            // Métricas gerais
            doc.text(`Total de Clientes: <?php echo $total_clients; ?>`, 20, 45);
            doc.text(`Receita Total: R$ <?php echo number_format($total_revenue, 2, ',', '.'); ?>`, 20, 55);
            doc.text(`Média Pagamentos/Cliente: <?php echo number_format($avg_payments_per_client, 1); ?>`, 20, 65);
            
            // Tabela (simplificada)
            let yPosition = 80;
            doc.setFontSize(10);
            
            // Cabeçalho da tabela
            doc.text('Cliente', 20, yPosition);
            doc.text('Pagamentos', 80, yPosition);
            doc.text('Pontualidade', 120, yPosition);
            doc.text('Receita', 160, yPosition);
            
            yPosition += 10;
            
            // Dados da tabela
            recurrenceData.forEach(row => {
                if (yPosition > 270) {
                    doc.addPage();
                    yPosition = 20;
                }
                
                const punctuality = row.total_payments > 0 ? (row.successful_payments / row.total_payments * 100).toFixed(1) : '0.0';
                
                doc.text(row.name.substring(0, 25), 20, yPosition);
                doc.text(row.successful_payments + '/' + row.total_payments, 80, yPosition);
                doc.text(punctuality + '%', 120, yPosition);
                doc.text('R$ ' + parseFloat(row.total_revenue).toLocaleString('pt-BR'), 160, yPosition);
                
                yPosition += 8;
            });
            
            doc.save(`relatorio_recorrencia_${new Date().toISOString().split('T')[0]}.pdf`);
        }
    </script>
</body>
</html>