/**
 * PWA Installation Handler
 * Gerencia a instalação do PWA e registro do Service Worker
 */

let deferredPrompt;
let installButton;

// Registrar Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/public/sw.js')
            .then((registration) => {
                console.log('SW registered: ', registration);
                
                // Verificar por atualizações
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // Nova versão disponível
                            showUpdateNotification();
                        }
                    });
                });
            })
            .catch((registrationError) => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

// Capturar evento de instalação PWA
window.addEventListener('beforeinstallprompt', (e) => {
    console.log('PWA install prompt captured');
    
    // Prevenir o prompt automático
    e.preventDefault();
    
    // Armazenar o evento para uso posterior
    deferredPrompt = e;
    
    // Mostrar botão de instalação personalizado
    showInstallButton();
});

// Detectar quando o PWA foi instalado
window.addEventListener('appinstalled', (evt) => {
    console.log('PWA was installed');
    hideInstallButton();
    
    // Opcional: enviar analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'pwa_installed', {
            event_category: 'PWA',
            event_label: 'App Installed'
        });
    }
});

// Mostrar botão de instalação
function showInstallButton() {
    // Criar botão se não existir
    if (!installButton) {
        installButton = document.createElement('button');
        installButton.id = 'pwa-install-btn';
        installButton.innerHTML = `
            <i class="fas fa-download mr-2"></i>
            Instalar App
        `;
        installButton.className = 'fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg hover:bg-blue-700 transition duration-150 z-50';
        installButton.addEventListener('click', installPWA);
        
        document.body.appendChild(installButton);
    }
    
    installButton.style.display = 'block';
}

// Ocultar botão de instalação
function hideInstallButton() {
    if (installButton) {
        installButton.style.display = 'none';
    }
}

// Instalar PWA
async function installPWA() {
    if (!deferredPrompt) {
        return;
    }
    
    // Mostrar prompt de instalação
    deferredPrompt.prompt();
    
    // Aguardar escolha do usuário
    const { outcome } = await deferredPrompt.userChoice;
    
    console.log(`User response to the install prompt: ${outcome}`);
    
    // Limpar o prompt
    deferredPrompt = null;
    
    // Ocultar botão
    hideInstallButton();
    
    // Opcional: enviar analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'pwa_install_prompt', {
            event_category: 'PWA',
            event_label: outcome
        });
    }
}

// Mostrar notificação de atualização
function showUpdateNotification() {
    // Criar notificação de atualização
    const updateNotification = document.createElement('div');
    updateNotification.id = 'update-notification';
    updateNotification.innerHTML = `
        <div class="fixed top-4 right-4 bg-blue-600 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold">Nova versão disponível!</h4>
                    <p class="text-sm">Clique para atualizar o app.</p>
                </div>
                <button onclick="updateApp()" class="ml-4 bg-white text-blue-600 px-3 py-1 rounded text-sm font-medium hover:bg-gray-100">
                    Atualizar
                </button>
            </div>
            <button onclick="dismissUpdate()" class="absolute top-1 right-1 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(updateNotification);
}

// Atualizar app
function updateApp() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then((registration) => {
            if (registration && registration.waiting) {
                // Enviar mensagem para o service worker ativar a nova versão
                registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                
                // Recarregar página após ativação
                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    window.location.reload();
                });
            }
        });
    }
}

// Dispensar notificação de atualização
function dismissUpdate() {
    const notification = document.getElementById('update-notification');
    if (notification) {
        notification.remove();
    }
}

// Verificar se está rodando como PWA
function isPWA() {
    return window.matchMedia('(display-mode: standalone)').matches || 
           window.navigator.standalone === true;
}

// Configurar comportamento específico para PWA
if (isPWA()) {
    console.log('Running as PWA');
    
    // Adicionar classe CSS para PWA
    document.documentElement.classList.add('pwa-mode');
    
    // Opcional: ocultar elementos específicos do browser
    const browserElements = document.querySelectorAll('.browser-only');
    browserElements.forEach(el => el.style.display = 'none');
}

// Solicitar permissão para notificações
function requestNotificationPermission() {
    if ('Notification' in window && 'serviceWorker' in navigator) {
        Notification.requestPermission().then((permission) => {
            console.log('Notification permission:', permission);
            
            if (permission === 'granted') {
                // Opcional: registrar para push notifications
                registerForPushNotifications();
            }
        });
    }
}

// Registrar para push notifications (Firebase)
function registerForPushNotifications() {
    // Implementar integração com Firebase Cloud Messaging
    // Este é um exemplo básico - você precisará configurar o Firebase
    
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.ready.then((registration) => {
            // Aqui você integraria com Firebase Cloud Messaging
            console.log('Ready for push notifications');
        });
    }
}

// Inicializar PWA features quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    // Verificar se deve solicitar permissão para notificações
    if (isPWA() && Notification.permission === 'default') {
        // Aguardar um pouco antes de solicitar permissão
        setTimeout(() => {
            requestNotificationPermission();
        }, 5000);
    }
    
    // Adicionar meta tag para iOS
    if (!document.querySelector('meta[name="apple-mobile-web-app-capable"]')) {
        const appleMeta = document.createElement('meta');
        appleMeta.name = 'apple-mobile-web-app-capable';
        appleMeta.content = 'yes';
        document.head.appendChild(appleMeta);
    }
    
    // Adicionar meta tag para status bar do iOS
    if (!document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]')) {
        const statusBarMeta = document.createElement('meta');
        statusBarMeta.name = 'apple-mobile-web-app-status-bar-style';
        statusBarMeta.content = 'default';
        document.head.appendChild(statusBarMeta);
    }
});

// Exportar funções para uso global
window.PWAInstaller = {
    install: installPWA,
    isPWA: isPWA,
    requestNotifications: requestNotificationPermission
};