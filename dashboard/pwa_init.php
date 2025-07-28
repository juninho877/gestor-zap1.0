<?php
/**
 * Inicializador PWA
 * Inclui scripts e estilos necessários para PWA
 */

// Verificar se é uma requisição HTTPS (necessário para PWA)
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);

// PWA só funciona em HTTPS ou localhost
$pwa_enabled = $is_https || $is_localhost;
?>

<?php if ($pwa_enabled): ?>
<!-- PWA Meta Tags -->
<meta name="theme-color" content="#3B82F6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?php echo getSiteName(); ?>">
<meta name="msapplication-TileColor" content="#3B82F6">
<meta name="msapplication-config" content="/public/browserconfig.xml">

<!-- Apple Touch Icons -->
<link rel="apple-touch-icon" sizes="180x180" href="/public/icons/icon-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="/public/icons/icon-192x192.png">
<link rel="icon" type="image/png" sizes="16x16" href="/public/icons/icon-192x192.png">

<!-- PWA Styles -->
<link href="/dashboard/css/pwa.css" rel="stylesheet">

<!-- PWA Scripts -->
<script>
// Configurações PWA
window.PWA_CONFIG = {
    name: '<?php echo getSiteName(); ?>',
    version: '1.0.0',
    debug: <?php echo (defined('DEBUG') && DEBUG) ? 'true' : 'false'; ?>,
    offline_enabled: true,
    push_notifications: true
};

// Detectar se está offline
window.addEventListener('online', function() {
    document.body.classList.remove('offline-mode');
    showConnectionStatus('online', 'Conectado');
});

window.addEventListener('offline', function() {
    document.body.classList.add('offline-mode');
    showConnectionStatus('offline', 'Sem conexão');
});

// Mostrar status de conexão
function showConnectionStatus(status, message) {
    // Remover status anterior
    const existingStatus = document.querySelector('.connection-status');
    if (existingStatus) {
        existingStatus.remove();
    }
    
    // Criar novo indicador
    const statusDiv = document.createElement('div');
    statusDiv.className = `connection-status ${status}`;
    statusDiv.textContent = message;
    
    document.body.appendChild(statusDiv);
    
    // Remover após 3 segundos se estiver online
    if (status === 'online') {
        setTimeout(() => {
            statusDiv.remove();
        }, 3000);
    }
}

// Verificar status inicial
if (!navigator.onLine) {
    document.addEventListener('DOMContentLoaded', function() {
        showConnectionStatus('offline', 'Sem conexão');
    });
}
</script>

<script src="/dashboard/pwa_installer.js" defer></script>
<?php endif; ?>

<?php if (!$pwa_enabled): ?>
<!-- Aviso sobre HTTPS -->
<script>
console.warn('PWA features disabled: HTTPS required for production');
</script>
<?php endif; ?>