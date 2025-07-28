<?php

class Translation {
    private static $translations = [];
    private static $current_locale = 'pt-BR';
    private static $fallback_locale = 'pt-BR';
    
    /**
     * Carregar traduções para um idioma
     */
    public static function loadTranslations($locale, $module = 'common') {
        $file_path = __DIR__ . "/../lang/{$locale}/{$module}.json";
        
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            $translations = json_decode($content, true);
            
            if ($translations) {
                if (!isset(self::$translations[$locale])) {
                    self::$translations[$locale] = [];
                }
                self::$translations[$locale][$module] = $translations;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Definir idioma atual
     */
    public static function setLocale($locale) {
        self::$current_locale = $locale;
        
        // Carregar traduções básicas
        self::loadTranslations($locale, 'common');
        self::loadTranslations($locale, 'dashboard');
        self::loadTranslations($locale, 'clients');
        self::loadTranslations($locale, 'messages');
    }
    
    /**
     * Obter idioma atual
     */
    public static function getLocale() {
        return self::$current_locale;
    }
    
    /**
     * Traduzir uma chave
     */
    public static function translate($key, $module = 'common', $params = []) {
        $locale = self::$current_locale;
        
        // Tentar carregar o módulo se não estiver carregado
        if (!isset(self::$translations[$locale][$module])) {
            self::loadTranslations($locale, $module);
        }
        
        // Buscar tradução no idioma atual
        if (isset(self::$translations[$locale][$module][$key])) {
            $translation = self::$translations[$locale][$module][$key];
        } 
        // Fallback para idioma padrão
        elseif ($locale !== self::$fallback_locale) {
            if (!isset(self::$translations[self::$fallback_locale][$module])) {
                self::loadTranslations(self::$fallback_locale, $module);
            }
            
            $translation = self::$translations[self::$fallback_locale][$module][$key] ?? $key;
        } 
        // Se não encontrar, retornar a própria chave
        else {
            $translation = $key;
        }
        
        // Substituir parâmetros
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $translation = str_replace("{{$param}}", $value, $translation);
            }
        }
        
        return $translation;
    }
    
    /**
     * Detectar idioma do navegador
     */
    public static function detectBrowserLanguage() {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return self::$fallback_locale;
        }
        
        $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $supported_languages = ['pt-BR', 'en', 'es'];
        
        foreach ($languages as $language) {
            $lang = trim(explode(';', $language)[0]);
            
            // Mapear códigos de idioma
            $lang_map = [
                'pt' => 'pt-BR',
                'pt-BR' => 'pt-BR',
                'en' => 'en',
                'en-US' => 'en',
                'es' => 'es',
                'es-ES' => 'es'
            ];
            
            $mapped_lang = $lang_map[$lang] ?? null;
            
            if ($mapped_lang && in_array($mapped_lang, $supported_languages)) {
                return $mapped_lang;
            }
        }
        
        return self::$fallback_locale;
    }
    
    /**
     * Obter idiomas disponíveis
     */
    public static function getAvailableLanguages() {
        return [
            'pt-BR' => ['name' => 'Português (Brasil)', 'flag' => '🇧🇷'],
            'en' => ['name' => 'English', 'flag' => '🇺🇸'],
            'es' => ['name' => 'Español', 'flag' => '🇪🇸']
        ];
    }
}

/**
 * Função helper para traduções
 */
function __($key, $module = 'common', $params = []) {
    return Translation::translate($key, $module, $params);
}

/**
 * Função helper para definir idioma
 */
function setLanguage($locale) {
    Translation::setLocale($locale);
    $_SESSION['locale'] = $locale;
}

/**
 * Inicializar sistema de traduções
 */
function initTranslations() {
    // Verificar se há idioma na sessão
    if (isset($_SESSION['locale'])) {
        $locale = $_SESSION['locale'];
    } 
    // Verificar se há idioma na URL
    elseif (isset($_GET['lang'])) {
        $locale = $_GET['lang'];
        $_SESSION['locale'] = $locale;
    } 
    // Detectar idioma do navegador
    else {
        $locale = Translation::detectBrowserLanguage();
        $_SESSION['locale'] = $locale;
    }
    
    Translation::setLocale($locale);
}
?>