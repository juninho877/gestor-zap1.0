<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../classes/ScheduledMessage.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/MessageTemplate.php';
require_once __DIR__ . '/../classes/Translation.php';

// Inicializar traduções
initTranslations();

$database = new Database();
$db = $database->getConnection();
$scheduledMessage = new ScheduledMessage($db);
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
                case 'schedule_message':
                    $scheduledMessage->user_id = $_SESSION['user_id'];
                    $scheduledMessage->client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
                    $scheduledMessage->template_id = !empty($_POST['template_id']) ? $_POST['template_id'] : null;
                    $scheduledMessage->message = trim($_POST['message']);
                    $scheduledMessage->phone = trim($_POST['phone']);
                    $scheduledMessage->scheduled_for = $_POST['scheduled_date'] . ' ' . $_POST['scheduled_time'];
                    $scheduledMessage->status = 'pending';
                    
                    // Se foi selecionado um cliente, usar os dados dele
                    if ($scheduledMessage->client_id) {
                        $client->id = $scheduledMessage->client_id;
                        $client->user_id = $_SESSION['user_id'];
                        if ($client->readOne()) {
                            $scheduledMessage->phone = $client->phone;
                            
                            // Personalizar mensagem se for template
                            if ($scheduledMessage->template_id) {
                                $scheduledMessage->message = str_replace('{nome}', $client->name, $scheduledMessage->message);
                                $scheduledMessage->message = str_replace('{valor}', 'R$ ' . number_format($client->subscription_amount, 2, ',', '.'), $scheduledMessage->message);
                                $scheduledMessage->message = str_replace('{vencimento}', date('d/m/Y', strtotime($client->due_date)), $scheduledMessage->message);
                            }
                        }
                    }
                    
                    $validation_errors = $scheduledMessage->validate();
                    if (!empty($validation_errors)) {
                        $_SESSION['error'] = implode(', ', $validation_errors);
                        redirect("scheduled_messages.php");
                    }
                    
                    if ($scheduledMessage->create()) {
                        $_SESSION['message'] = "Mensagem agendada com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao agendar mensagem.";
                    }
                    
                    redirect("scheduled_messages.php");
                    break;
                    
                case 'cancel_message':
                    $scheduledMessage->id = $_POST['message_id'];
                    if ($scheduledMessage->cancel()) {
                        $_SESSION['message'] = "Mensagem cancelada com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao cancelar mensagem.";
                    }
                    
                    redirect("scheduled_messages.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("scheduled_messages.php");
    }
}

// Buscar mensagens agendadas
$scheduled_messages_stmt = $scheduledMessage->readByUser($_SESSION['user_id'], 50, 0);
$scheduled_messages = $scheduled_messages_stmt->fetchAll();

// Buscar clientes ativos
$clients_stmt = $client->readAll($_SESSION['user_id']);
$clients = [];
while ($row = $clients_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['status'] == 'active') {
        $clients[] = $row;
    }
}

// Buscar templates ativos
$templates_stmt = $template->readAll($_SESSION['user_id']);
$templates = [];
while ($row = $templates_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['active']) {
        $templates[] = $row;
    }
}

// Buscar estatísticas
$stats = $scheduledMessage->getStatistics($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?php echo Translation::getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensagens Agendadas - <?php echo getSiteName(); ?></title>
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
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Mensagens Agendadas</h1>
                            <button onclick="openScheduleModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                <i class="fas fa-clock mr-2"></i>
                                Agendar Mensagem
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
                                                <i class="fas fa-calendar text-blue-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Total Agendadas</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['total_scheduled'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-clock text-yellow-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Pendentes</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['pending_count'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Enviadas</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['sent_count'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-times text-red-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Falharam</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['failed_count'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de Mensagens Agendadas -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Mensagens Agendadas</h3>
                                
                                <?php if (empty($scheduled_messages)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-clock text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Nenhuma mensagem agendada</h3>
                                        <p class="text-lg text-gray-500 dark:text-slate-400 mb-4">Agende sua primeira mensagem</p>
                                        <button onclick="openScheduleModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                            <i class="fas fa-clock mr-2"></i>
                                            Agendar Primeira Mensagem
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                            <thead class="bg-gray-50 dark:bg-slate-700">
                                                <tr>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Cliente/Telefone</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Mensagem</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Agendado para</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Status</th>
                                                    <th class="px-6 py-4 text-right text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                                <?php foreach ($scheduled_messages as $msg): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                                <?php echo htmlspecialchars($msg['client_name'] ?? 'Número personalizado'); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500 dark:text-slate-400">
                                                                <?php echo htmlspecialchars($msg['phone']); ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm text-gray-900 dark:text-slate-100 max-w-xs truncate" title="<?php echo htmlspecialchars($msg['message']); ?>">
                                                            <?php echo htmlspecialchars(substr($msg['message'], 0, 50)) . (strlen($msg['message']) > 50 ? '...' : ''); ?>
                                                        </div>
                                                        <?php if ($msg['template_name']): ?>
                                                        <div class="text-sm text-gray-500 dark:text-slate-400">
                                                            Template: <?php echo htmlspecialchars($msg['template_name']); ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900 dark:text-slate-100">
                                                            <?php echo date('d/m/Y H:i', strtotime($msg['scheduled_for'])); ?>
                                                        </div>
                                                        <?php
                                                        $now = time();
                                                        $scheduled_time = strtotime($msg['scheduled_for']);
                                                        $time_diff = $scheduled_time - $now;
                                                        
                                                        if ($time_diff > 0) {
                                                            $hours = floor($time_diff / 3600);
                                                            $minutes = floor(($time_diff % 3600) / 60);
                                                            echo '<div class="text-xs text-blue-600">Em ' . $hours . 'h ' . $minutes . 'm</div>';
                                                        } elseif ($msg['status'] === 'pending') {
                                                            echo '<div class="text-xs text-red-600">Atrasada</div>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $status_classes = [
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'sent' => 'bg-green-100 text-green-800',
                                                            'failed' => 'bg-red-100 text-red-800',
                                                            'cancelled' => 'bg-gray-100 text-gray-800'
                                                        ];
                                                        $status_labels = [
                                                            'pending' => 'Pendente',
                                                            'sent' => 'Enviada',
                                                            'failed' => 'Falhou',
                                                            'cancelled' => 'Cancelada'
                                                        ];
                                                        ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_classes[$msg['status']]; ?>">
                                                            <?php echo $status_labels[$msg['status']]; ?>
                                                        </span>
                                                        
                                                        <?php if ($msg['sent_at']): ?>
                                                        <div class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                                                            Enviada: <?php echo date('d/m H:i', strtotime($msg['sent_at'])); ?>
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($msg['error_message']): ?>
                                                        <div class="text-xs text-red-500 mt-1" title="<?php echo htmlspecialchars($msg['error_message']); ?>">
                                                            Erro: <?php echo htmlspecialchars(substr($msg['error_message'], 0, 30)); ?>...
                                                        </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <?php if ($msg['status'] === 'pending'): ?>
                                                            <button onclick="cancelMessage(<?php echo $msg['id']; ?>)" 
                                                                    class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                    title="Cancelar mensagem">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para agendar mensagem -->
    <div id="scheduleModal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white dark:bg-slate-800 border-t-4 border-blue-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Agendar Mensagem</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="schedule_message">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Cliente (opcional)</label>
                            <select name="client_id" id="client_id" onchange="updatePhoneFromClient()"
                                    class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                <option value="">Selecione um cliente ou digite o telefone manualmente</option>
                                <?php foreach ($clients as $client_row): ?>
                                    <option value="<?php echo $client_row['id']; ?>" data-phone="<?php echo $client_row['phone']; ?>">
                                        <?php echo htmlspecialchars($client_row['name']) . ' - ' . htmlspecialchars($client_row['phone']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Telefone *</label>
                            <input type="tel" name="phone" id="phone" required 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                   placeholder="(11) 99999-9999">
                        </div>
                        
                        <div>
                            <label for="template_id" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Template (opcional)</label>
                            <select name="template_id" id="template_id" onchange="loadTemplate()"
                                    class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                <option value="">Selecione um template ou digite mensagem personalizada</option>
                                <?php foreach ($templates as $template_row): ?>
                                    <option value="<?php echo $template_row['id']; ?>" data-message="<?php echo htmlspecialchars($template_row['message']); ?>">
                                        <?php echo htmlspecialchars($template_row['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Mensagem *</label>
                            <textarea name="message" id="message" rows="4" required
                                      class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                      placeholder="Digite sua mensagem aqui..."></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                Variáveis disponíveis: {nome}, {valor}, {vencimento}
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="scheduled_date" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Data *</label>
                                <input type="date" name="scheduled_date" id="scheduled_date" required 
                                       min="<?php echo date('Y-m-d'); ?>"
                                       class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                            </div>
                            
                            <div>
                                <label for="scheduled_time" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Hora *</label>
                                <input type="time" name="scheduled_time" id="scheduled_time" required 
                                       class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeScheduleModal()" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-5 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                            <i class="fas fa-clock mr-2"></i>
                            Agendar Mensagem
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openScheduleModal() {
            document.getElementById('scheduleModal').classList.remove('hidden');
            
            // Definir data mínima como hoje
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('scheduled_date').value = today;
            
            // Definir hora padrão como próxima hora
            const now = new Date();
            now.setHours(now.getHours() + 1);
            const timeString = now.toTimeString().slice(0, 5);
            document.getElementById('scheduled_time').value = timeString;
        }

        function closeScheduleModal() {
            document.getElementById('scheduleModal').classList.add('hidden');
            document.getElementById('message').value = '';
            document.getElementById('template_id').value = '';
            document.getElementById('client_id').value = '';
            document.getElementById('phone').value = '';
        }

        function updatePhoneFromClient() {
            const select = document.getElementById('client_id');
            const phoneInput = document.getElementById('phone');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.dataset.phone) {
                phoneInput.value = selectedOption.dataset.phone;
            }
        }

        function loadTemplate() {
            const select = document.getElementById('template_id');
            const textarea = document.getElementById('message');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.dataset.message) {
                textarea.value = selectedOption.dataset.message;
            }
        }

        function cancelMessage(id) {
            if (confirm('Tem certeza que deseja cancelar esta mensagem agendada?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_message">
                    <input type="hidden" name="message_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Fechar modal ao clicar fora
        document.getElementById('scheduleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeScheduleModal();
            }
        });
    </script>
</body>
</html>