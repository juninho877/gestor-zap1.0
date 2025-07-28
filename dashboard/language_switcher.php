<?php
require_once __DIR__ . '/../classes/Translation.php';

// Processar mudança de idioma
if (isset($_GET['lang'])) {
    $new_language = $_GET['lang'];
    $available_languages = array_keys(Translation::getAvailableLanguages());
    
    if (in_array($new_language, $available_languages)) {
        setLanguage($new_language);
    }
    
    // Redirecionar de volta para a página anterior
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    
    // Remover parâmetro lang da URL se existir
    $redirect_url = preg_replace('/[?&]lang=[^&]*/', '', $redirect_url);
    
    redirect($redirect_url);
}

// Se chegou aqui sem parâmetro lang, redirecionar para index
redirect('index.php');
?>