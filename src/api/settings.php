<?php

/**
 * Configuración y conexión de la cuenta.
 *
 * - admin-post `mls_save_settings`: guarda el formulario.
 * - admin-post `mls_connect` / `mls_disconnect`: inicia OAuth / borra los tokens (enlaces con nonce).
 * - AJAX `mls_test`: GET /users/me.
 */
class MLS_API_SETTINGS
{
    const ACTION = 'mls_save_settings';
    const CONNECT = 'mls_connect';
    const DISCONNECT = 'mls_disconnect';
    const NONCE = 'mls_settings_nonce';

    public static function init()
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'save']);
        add_action('admin_post_' . self::CONNECT, [self::class, 'connect']);
        add_action('admin_post_' . self::DISCONNECT, [self::class, 'disconnect']);
        add_action('wp_ajax_mls_test', [self::class, 'test']);
    }

    private static function deny()
    {
        if (!current_user_can(MLS_CAPABILITY)) {
            wp_die('No tienes permisos para cambiar esta configuración.', '', ['response' => 403]);
        }
    }

    public static function back($status, $message, $tab = '')
    {
        set_transient('mls_respond', ['status' => $status, 'message' => $message], 60);
        wp_safe_redirect(add_query_arg(array_filter(['page' => MLS_KEY, 'tab' => $tab]), admin_url('admin.php')));
        exit;
    }

    public static function save()
    {
        self::deny();
        check_admin_referer(self::ACTION);

        $before = (new MLS_USE_DATA_CONFIG())->get();
        $config = MLS_USE_DATA_CONFIG::sanitize(wp_unslash($_POST['mls'] ?? []));
        (new MLS_USE_DATA_CONFIG())->set($config);

        // Nunca guardar la Secret Key en el log (la pestaña Registro es visible en el admin).
        $logged = $config;
        $logged['client_secret'] = '' !== (string) $logged['client_secret'] ? '***' : '';
        MLS_LOG_SERVICE::add('guardar_ajustes', $logged);

        $message = 'Configuración guardada.';
        if ($before['client_id'] !== $config['client_id'] && MLS_USE_DATA_TOKEN::connected()) {
            $message .= ' Cambiaste el App ID: vuelve a conectar la cuenta.';
        }
        self::back('ok', $message, sanitize_key($_POST['mls_tab'] ?? ''));
    }

    /**
     * Redirige a MercadoLibre para autorizar la aplicación.
     */
    public static function connect()
    {
        self::deny();
        check_admin_referer(self::CONNECT);
        if (!MLS_USE_DATA_CONFIG::hasApp()) {
            self::back('error', 'Guarda primero el App ID y la Secret Key de tu aplicación de MercadoLibre.', 'conexion');
        }
        // wp_redirect (no wp_safe_redirect): el destino es el dominio de autorización de MercadoLibre.
        wp_redirect(MLS_API::authorizeUrl()); // phpcs:ignore WordPress.Security.SafeRedirect
        exit;
    }

    public static function disconnect()
    {
        self::deny();
        check_admin_referer(self::DISCONNECT);
        $account = MLS_USE_DATA_TOKEN::account();
        (new MLS_USE_DATA_TOKEN())->clear();
        MLS_LOG_SERVICE::add('desconectar', ['user_id' => $account['user_id'], 'nickname' => $account['nickname']]);
        self::back('ok', 'Cuenta desconectada. Los productos conservan su vínculo con las publicaciones.', 'conexion');
    }

    public static function test()
    {
        if (!current_user_can(MLS_CAPABILITY)) {
            wp_send_json_error('No tienes permisos para esta acción.', 403);
        }
        check_ajax_referer(self::NONCE, 'nonce');

        $result = MLS_API::me();
        if (!$result['ok']) {
            wp_send_json_error($result['error']);
        }
        $me = $result['data'];
        (new MLS_USE_DATA_TOKEN())->patch([
            'nickname' => (string) ($me['nickname'] ?? ''),
            'site_id' => (string) ($me['site_id'] ?? ''),
        ]);
        $site = MLS_USE_DATA_CONFIG::site();
        $message = 'Conexión correcta: ' . ($me['nickname'] ?? '') . ' (ID ' . ($me['id'] ?? '') . ', ' . ($me['site_id'] ?? '') . ').';
        if (!empty($me['site_id']) && $me['site_id'] !== $site['id']) {
            $message .= ' Atención: la cuenta es de ' . $me['site_id'] . ' pero el país configurado es ' . $site['id'] . '.';
        }
        MLS_LOG_SERVICE::add('test_conexion', ['ok' => 1, 'user' => $me['id'] ?? '', 'site' => $me['site_id'] ?? '']);
        wp_send_json_success(['message' => $message]);
    }
}

MLS_API_SETTINGS::init();
