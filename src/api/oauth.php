<?php

/**
 * Retorno de OAuth: GET /wp-json/mls/v1/oauth?code=…&state=…
 *
 * Es una ruta pública porque llega desde MercadoLibre sin nonce de WordPress; la
 * protección es el `state` aleatorio de un solo uso que guardó MLS_API::authorizeUrl()
 * (vence en 15 minutos). Esta URL es la que se registra como «Redirect URI» en la app.
 */
class MLS_API_OAUTH
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes()
    {
        register_rest_route(MLS_REST_NAMESPACE, '/oauth', [
            'methods' => 'GET',
            'callback' => [self::class, 'callback'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @param WP_REST_Request $request
     */
    public static function callback($request)
    {
        $state = (string) $request->get_param('state');
        $code = (string) $request->get_param('code');
        $error = sanitize_text_field((string) $request->get_param('error'));

        $stored = MLS_API::consumeState($state);
        if (!$stored) {
            MLS_LOG_SERVICE::add('oauth_error', ['error' => 'state inválido o vencido']);
            self::finish('error', 'La autorización no es válida o venció. Vuelve a pulsar «Conectar con MercadoLibre».');
        }
        if ('' !== $error || '' === $code) {
            MLS_LOG_SERVICE::add('oauth_error', ['error' => $error ? $error : 'sin code']);
            self::finish('error', 'MercadoLibre no autorizó la conexión' . ($error ? ' (' . $error . ')' : '') . '.');
        }

        $result = MLS_API::exchangeCode(sanitize_text_field($code), (string) $stored['verifier']);
        if (!$result['ok']) {
            MLS_LOG_SERVICE::add('oauth_error', ['code' => $result['code'], 'error' => $result['error']]);
            self::finish('error', 'No se pudo obtener el token: ' . $result['error']);
        }

        $account = $result['data'];
        MLS_LOG_SERVICE::add('conectar', ['user_id' => $account['user_id'], 'nickname' => $account['nickname'], 'site' => $account['site_id'], 'by' => (int) $stored['user']]);
        $message = 'Cuenta conectada: ' . ($account['nickname'] ? $account['nickname'] : $account['user_id']) . '.';
        $site = MLS_USE_DATA_CONFIG::site();
        if ($account['site_id'] && $account['site_id'] !== $site['id']) {
            $message .= ' Atención: la cuenta es de ' . $account['site_id'] . ' y el país configurado es ' . $site['id'] . '.';
        }
        self::finish('ok', $message);
    }

    private static function finish($status, $message)
    {
        set_transient('mls_respond', ['status' => $status, 'message' => $message], 120);
        wp_safe_redirect(add_query_arg(['page' => MLS_KEY, 'tab' => 'conexion'], admin_url('admin.php')));
        exit;
    }
}

MLS_API_OAUTH::init();
