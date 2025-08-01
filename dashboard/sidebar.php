<?php
// Verificar se é administrador
$is_admin = ($_SESSION['user_role'] === 'admin');

// Inicializar traduções se não estiver inicializado
if (!class_exists('Translation')) {
    require_once __DIR__ . '/../classes/Translation.php';
    initTranslations();
}

// Contar notificações não lidas
$unread_notifications = 0;
if (class_exists('Notification')) {
    require_once __DIR__ . '/../classes/Notification.php';
    $notification = new Notification($database->getConnection());
    $unread_notifications = $notification->countUnread($_SESSION['user_id']);
}

// Determinar qual página está ativa
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Mobile header - Visível apenas em telas pequenas -->

<!-- Overlay para o menu mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden" onclick="toggleSidebar()"></div>

<!-- Botão flutuante para abrir o menu em dispositivos móveis -->
<button onclick="toggleSidebar()" class="md:hidden fixed top-4 left-4 bg-blue-600 text-white p-3 rounded-full shadow-lg z-50 focus:outline-none transition duration-150 ease-in-out">
    <i class="fas fa-bars text-xl"></i>
</button>

<!-- Sidebar para desktop (sempre visível em md+) e mobile (toggle) -->
<div id="sidebar" class="sidebar fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out z-40 md:static md:z-0 md:h-screen flex flex-col w-64 bg-gray-800 dark:bg-slate-800 text-gray-100 border-r border-gray-700 dark:border-slate-600">
    <div class="flex items-center justify-between p-4 border-b border-gray-700">
        <div class="flex-shrink-0">
            <?php if (!empty(SITE_LOGO_PATH)): ?>
                <img src="<?php echo SITE_LOGO_PATH; ?>" alt="<?php echo getSiteName(); ?>" class="h-8 w-auto">
            <?php else: ?>
                <h1 class="text-xl font-bold text-white dark:text-slate-100"><?php echo getSiteName(); ?></h1>
            <?php endif; ?>
        </div>
        <!-- Language Switcher and Dark Mode Toggle -->
        <div class="flex items-center space-x-2">
            <!-- Language Switcher -->
            <div class="relative">
                <button id="languageToggle" class="text-white hover:text-gray-300 p-1 rounded" title="Alterar idioma">
                    <?php 
                    $current_lang = Translation::getLocale();
                    $languages = Translation::getAvailableLanguages();
                    echo $languages[$current_lang]['flag'] ?? '🌐';
                    ?>
                </button>
                <div id="languageMenu" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-700 rounded-md shadow-lg z-50 hidden">
                    <?php foreach (Translation::getAvailableLanguages() as $code => $lang): ?>
                    <a href="language_switcher.php?lang=<?php echo $code; ?>" 
                       class="block px-4 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-600 <?php echo $current_lang === $code ? 'bg-blue-50 dark:bg-blue-900/20' : ''; ?>">
                        <?php echo $lang['flag']; ?> <?php echo $lang['name']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Dark Mode Toggle -->
            <button id="darkModeToggle" class="dark-mode-toggle" title="Alternar modo escuro">
                <span class="sr-only">Alternar modo escuro</span>
            </button>
        </div>
        <button onclick="toggleSidebar()" class="text-white md:hidden focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="flex-grow overflow-y-auto">
        <nav class="px-4 py-4 space-y-2">
            <a href="index.php" class="sidebar-link <?php echo $current_page == 'index.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-home mr-3"></i>
                <?php echo __('dashboard'); ?>
            </a>
            <a href="advanced_dashboard.php" class="sidebar-link <?php echo $current_page == 'advanced_dashboard.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-chart-line mr-3"></i>
                Dashboard Avançado
            </a>
            <a href="clients.php" class="sidebar-link <?php echo $current_page == 'clients.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-users mr-3"></i>
                <?php echo __('clients'); ?>
            </a>
            <a href="messages.php" class="sidebar-link <?php echo $current_page == 'messages.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fab fa-whatsapp mr-3"></i>
                <?php echo __('messages'); ?>
            </a>
            <a href="scheduled_messages.php" class="sidebar-link <?php echo $current_page == 'scheduled_messages.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-clock mr-3"></i>
                Mensagens Agendadas
            </a>
            <a href="templates.php" class="sidebar-link <?php echo $current_page == 'templates.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-file-alt mr-3"></i>
                <?php echo __('templates'); ?>
            </a>
            <a href="campaigns.php" class="sidebar-link <?php echo $current_page == 'campaigns.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-bullhorn mr-3"></i>
                Campanhas
            </a>
            
            <a href="client_payments.php" class="sidebar-link <?php echo $current_page == 'client_payments.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-receipt mr-3"></i>
                Pagamentos de Clientes
            </a>
            <a href="whatsapp.php" class="sidebar-link <?php echo $current_page == 'whatsapp.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-qrcode mr-3"></i>
                WhatsApp
            </a>
            <a href="reports.php" class="sidebar-link <?php echo $current_page == 'reports.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-chart-bar mr-3"></i>
                <?php echo __('reports'); ?>
            </a>
            <a href="recurrence_report.php" class="sidebar-link <?php echo $current_page == 'recurrence_report.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-sync-alt mr-3"></i>
                Relatório de Recorrência
            </a>
            <a href="notifications.php" class="sidebar-link <?php echo $current_page == 'notifications.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-bell mr-3"></i>
                Notificações
                <?php if ($unread_notifications > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $unread_notifications; ?></span>
                <?php endif; ?>
            </a>
            <a href="user_settings.php" class="sidebar-link <?php echo $current_page == 'user_settings.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-user-cog mr-3"></i>
                Notificações
            </a>
            
            <a href="payment_settings.php" class="sidebar-link <?php echo $current_page == 'payment_settings.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-money-bill-wave mr-3"></i>
                Configurações de Pagamento
            </a>
            <a href="tickets.php" class="sidebar-link <?php echo $current_page == 'tickets.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-ticket-alt mr-3"></i>
                Suporte
            </a>
            
            <a href="profile.php" class="sidebar-link <?php echo $current_page == 'profile.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-user mr-3"></i>
                Meu Perfil
            </a>
            
            <?php if (!$is_admin): ?>
                <?php 
                // Verificar se precisa mostrar opção de assinatura
                $show_subscription_link = false;
                if (isset($_SESSION['subscription_status'])) {
                    $show_subscription_link = ($_SESSION['subscription_status'] === 'trial' || $_SESSION['subscription_status'] === 'expired');
                }
                ?>
                <?php if ($show_subscription_link): ?>
                <a href="../payment.php?plan_id=<?php echo htmlspecialchars($_SESSION['plan_id'] ?? '1'); ?>" class="sidebar-link text-yellow-300 hover:bg-yellow-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md border border-yellow-500">
                    <i class="fas fa-crown mr-3"></i>
                    <?php if ($_SESSION['subscription_status'] === 'trial'): ?>
                        Assinar Agora
                    <?php else: ?>
                        Renovar Assinatura
                    <?php endif; ?>
                    <?php if ($_SESSION['subscription_status'] === 'trial'): ?>
                        <span class="ml-auto bg-yellow-500 text-yellow-900 text-xs px-2 py-1 rounded-full font-bold">Teste</span>
                    <?php else: ?>
                        <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Expirado</span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($is_admin): ?>
            <!-- Separador para seção administrativa -->
            <div class="border-t border-gray-700 my-2"></div>
            <div class="px-2 py-1">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Administração</span>
            </div>
            
            <a href="users.php" class="sidebar-link <?php echo $current_page == 'users.php' ? 'bg-red-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-users-cog mr-3"></i>
                Gerenciar Usuários
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
            </a>
            
            <a href="plans.php" class="sidebar-link <?php echo $current_page == 'plans.php' ? 'bg-red-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-tags mr-3"></i>
                Gerenciar Planos
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
            </a>
            
            <a href="payments.php" class="sidebar-link <?php echo $current_page == 'payments.php' ? 'bg-red-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-credit-card mr-3"></i>
                Gerenciar Pagamentos
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
            </a>
            
            <a href="settings.php" class="sidebar-link <?php echo $current_page == 'settings.php' ? 'bg-red-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-cog mr-3"></i>
                Configurações Sistema
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
            </a>
            
            <a href="activity_logs.php" class="sidebar-link <?php echo $current_page == 'activity_logs.php' ? 'bg-red-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-list mr-3"></i>
                Logs de Atividade
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <div class="sidebar-user-info border-t border-gray-700 dark:border-slate-600 p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center">
                <i class="fas fa-user text-gray-300"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-gray-200"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <?php if ($is_admin): ?>
                    <span class="text-xs font-medium text-yellow-400">Administrador</span>
                <?php else: ?>
                    <span class="text-xs font-medium text-gray-400">Usuário</span>
                <?php endif; ?>
                <a href="../logout.php" class="text-xs font-medium text-gray-400 hover:text-white block">Sair</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Função para alternar a visibilidade do menu lateral em dispositivos móveis
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
        
        // Impedir rolagem do body quando o menu está aberto
        document.body.classList.toggle('overflow-hidden', !overlay.classList.contains('hidden'));
    }
    
    // Fechar menu ao redimensionar para desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) { // 768px é o breakpoint md do Tailwind
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (!sidebar.classList.contains('-translate-x-full') && overlay) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }
    });
    
    // Dark Mode Toggle Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const darkModeToggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;
        
        // Check for saved dark mode preference or default to light mode
        const savedTheme = localStorage.getItem('darkMode');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'enabled' || (!savedTheme && prefersDark)) {
            html.classList.add('dark');
            darkModeToggle.classList.add('active');
        }
        
        // Toggle dark mode
        darkModeToggle.addEventListener('click', function() {
            html.classList.toggle('dark');
            darkModeToggle.classList.toggle('active');
            
            // Save preference
            if (html.classList.contains('dark')) {
                localStorage.setItem('darkMode', 'enabled');
            } else {
                localStorage.setItem('darkMode', 'disabled');
            }
        });
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (!localStorage.getItem('darkMode')) {
                if (e.matches) {
                    html.classList.add('dark');
                    darkModeToggle.classList.add('active');
                } else {
                    html.classList.remove('dark');
                    darkModeToggle.classList.remove('active');
                }
            }
        });
        
        // Language switcher functionality
        document.getElementById('languageToggle').addEventListener('click', function() {
            const menu = document.getElementById('languageMenu');
            menu.classList.toggle('hidden');
        });
        
        // Close language menu when clicking outside
        document.addEventListener('click', function(event) {
            const toggle = document.getElementById('languageToggle');
            const menu = document.getElementById('languageMenu');
            
            if (!toggle.contains(event.target) && !menu.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });
    });
</script>