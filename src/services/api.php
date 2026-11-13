<?php

/**
 * Cliente de la API de MercadoLibre (https://api.mercadolibre.com).
 *
 * Única fuente de: URLs de OAuth, intercambio/renovación de tokens y peticiones HTTP.
 * Todas las respuestas se devuelven como ['ok' => bool, 'code' => int, 'data' => array, 'error' => string].
 */
class MLS_API
{
    const BASE = 'https://api.mercadolibre.com';
    const TIMEOUT = 30;

    /**
     * URL de retorno de OAuth (hay que registrarla tal cual en la aplicación de MercadoLibre).
     */
    public static function redirectUri()
    {
        return rest_url(MLS_REST_NAMESPACE . '/oauth');
    }

    /**
     * URL de autorización. Guarda state (y code_verifier si hay PKCE) durante 15 minutos.
     */
    public static function authorizeUrl()
    {
        $config = (new MLS_USE_DATA_CONFIG())->get();
        $site = MLS_USE_DATA_CONFIG::site($config);
        $state = wp_generate_password(40, false, false);
        $stored = [
            'state' => $state,
            'user' => get_current_user_id(),
            'expires' => time() + 15 * MINUTE_IN_SECONDS,
            'verifier' => '',
        ];
        $args = [
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => self::redirectUri(),
            'state' => $state,
        ];
        if (!empty($config['use_pkce'])) {
            $verifier = wp_generate_password(64, false, false);
            $stored['verifier'] = $verifier;
            $args['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $args['code_challenge_method'] = 'S256';
        }
        update_option(MLS_OAUTH_STATE, $stored, false);

        return $site['auth'] . '/authorization?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Valida el state devuelto por MercadoLibre (un solo uso).
     *
     * @return array|false Datos guardados (user, verifier) o false.
     */
    public static function consumeState($state)
    {
        $stored = get_option(MLS_OAUTH_STATE, []);
        delete_option(MLS_OAUTH_STATE);
        if (!is_array($stored) || empty($stored['state']) || !is_string($state)) {
            return false;
        }
        if ((int) $stored['expires'] < time() || !hash_equals((string) $stored['state'], $state)) {
            return false;
        }

        return $stored;
    }

    /**
     * Intercambia el code de autorización por tokens y guarda la cuenta.
     */
    public static function exchangeCode($code, $verifier = '')
    {
        $config = (new MLS_USE_DATA_CONFIG())->get();
        $body = [
            'grant_type' => 'authorization_code',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'redirect_uri' => self::redirectUri(),
        ];
        if ('' !== (string) $verifier) {
            $body['code_verifier'] = $verifier;
        }
        $result = self::tokenRequest($body);
        if (!$result['ok']) {
            return $result;
        }

        $store = new MLS_USE_DATA_TOKEN();
        $store->setFromResponse($result['data'], ['connected_at' => current_time('mysql')]);

        $me = self::request('GET', '/users/me');
        if ($me['ok']) {
            $store->patch([
                'nickname' => (string) ($me['data']['nickname'] ?? ''),
                'site_id' => (string) ($me['data']['site_id'] ?? ''),
                'user_id' => (string) ($me['data']['id'] ?? $store->get()['user_id']),
            ]);
        }

        return ['ok' => true, 'code' => 200, 'data' => MLS_USE_DATA_TOKEN::account(), 'error' => ''];
    }

    private static function tokenRequest($body)
    {
        $response = wp_remote_post(self::BASE . '/oauth/token', [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $body,
        ]);

        return self::parse($response);
    }

    /**
     * Access token vigente; lo renueva si vence en menos de 5 minutos.
     *
     * El refresh_token es de un solo uso: un bloqueo (add_option es atómico) evita que
     * dos procesos lo gasten a la vez.
     *
     * @return string '' si no hay cuenta conectada o no se pudo renovar.
     */
    public static function token($force = false)
    {
        $token = (new MLS_USE_DATA_TOKEN())->get();
        if ('' === $token['access_token']) {
            return '';
        }
        if (!$force && (int) $token['expires_at'] - 300 > time()) {
            return $token['access_token'];
        }

        if (!add_option(MLS_REFRESH_LOCK, time(), '', false)) {
            $since = (int) get_option(MLS_REFRESH_LOCK, 0);
            if ($since && time() - $since < 30) {
                // Otro proceso está renovando: esperar y releer.
                sleep(3);
                $token = (new MLS_USE_DATA_TOKEN())->get();
                return (int) $token['expires_at'] > time() ? $token['access_token'] : '';
            }
            update_option(MLS_REFRESH_LOCK, time(), false);
        }

        $config = (new MLS_USE_DATA_CONFIG())->get();
        $result = self::tokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'refresh_token' => $token['refresh_token'],
        ]);
        delete_option(MLS_REFRESH_LOCK);

        if (!$result['ok']) {
            MLS_LOG_SERVICE::add('token_error', ['code' => $result['code'], 'error' => $result['error']]);
            // invalid_grant = el usuario revocó el acceso o el refresh_token venció (6 meses).
            if (false !== strpos($result['error'], 'invalid_grant')) {
                (new MLS_USE_DATA_TOKEN())->patch(['access_token' => '', 'expires_at' => 0]);
            }
            return '';
        }

        $store = new MLS_USE_DATA_TOKEN();
        $store->setFromResponse($result['data']);
        MLS_LOG_SERVICE::add('token_renovado', ['expires_at' => date('Y-m-d H:i:s', $store->get()['expires_at'])]);

        return $store->get()['access_token'];
    }

    /**
     * Petición autenticada a la API. Reintenta una vez renovando el token si responde 401.
     *
     * @param string     $method GET|POST|PUT|DELETE
     * @param string     $path   '/items' o URL completa de la API
     * @param array|null $body   Se envía como JSON
     * @param array      $query
     */
    public static function request($method, $path, $body = null, $query = [], $retry = true)
    {
        $token = self::token();
        if ('' === $token) {
            return ['ok' => false, 'code' => 0, 'data' => [], 'error' => 'No hay una cuenta de MercadoLibre conectada (o el token no se pudo renovar). Vuelve a conectar en Configuración.'];
        }
        $url = 0 === strpos($path, 'http') ? $path : self::BASE . $path;
        if ($query) {
            $url = add_query_arg(array_map('rawurlencode', $query), $url);
        }
        $args = [
            'method' => $method,
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ];
        if (null !== $body) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $result = self::parse(wp_remote_request($url, $args));
        if ($retry && 401 === $result['code']) {
            if ('' !== self::token(true)) {
                return self::request($method, $path, $body, $query, false);
            }
        }

        return $result;
    }

    /**
     * Normaliza la respuesta HTTP y arma un mensaje de error legible (message + cause[]).
     */
    private static function parse($response)
    {
        if (is_wp_error($response)) {
            return ['ok' => false, 'code' => 0, 'data' => [], 'error' => $response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'code' => $code, 'data' => $data, 'error' => ''];
        }

        $parts = [];
        if (!empty($data['error'])) {
            $parts[] = is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']);
        }
        if (!empty($data['message']) && is_string($data['message'])) {
            $parts[] = $data['message'];
        }
        if (!empty($data['cause']) && is_array($data['cause'])) {
            foreach ($data['cause'] as $cause) {
                if (is_array($cause) && !empty($cause['message'])) {
                    // Las advertencias (type=warning) no impiden publicar; solo se listan los errores.
                    if (isset($cause['type']) && 'warning' === $cause['type']) {
                        continue;
                    }
                    $parts[] = (isset($cause['code']) ? $cause['code'] . ': ' : '') . $cause['message'];
                } elseif (is_string($cause)) {
                    $parts[] = $cause;
                }
            }
        }
        $error = $parts ? implode(' | ', array_unique($parts)) : ('HTTP ' . $code . ' ' . substr($raw, 0, 300));

        return ['ok' => false, 'code' => $code, 'data' => $data, 'error' => $error];
    }

    // ── Endpoints usados por el plugin ─────────────────────────────────────

    public static function me()
    {
        return self::request('GET', '/users/me');
    }

    public static function item($item_id)
    {
        return self::request('GET', '/items/' . rawurlencode($item_id));
    }

    public static function createItem($payload)
    {
        return self::request('POST', '/items', $payload);
    }

    public static function updateItem($item_id, $payload)
    {
        return self::request('PUT', '/items/' . rawurlencode($item_id), $payload);
    }

    public static function setStatus($item_id, $status)
    {
        return self::updateItem($item_id, ['status' => $status]);
    }

    /**
     * Crea o reemplaza la descripción (texto plano; MercadoLibre no admite HTML).
     */
    public static function setDescription($item_id, $text, $exists = true)
    {
        $path = '/items/' . rawurlencode($item_id) . '/description';
        if (!$exists) {
            $result = self::request('POST', $path, ['plain_text' => $text]);
            if ($result['ok']) {
                return $result;
            }
        }

        return self::request('PUT', $path, ['plain_text' => $text], ['api_version' => '2']);
    }

    /**
     * Predictor de categorías por título.
     *
     * @return array [['category_id' => '', 'category_name' => '', 'domain_name' => '']]
     */
    public static function predictCategory($title, $limit = 3)
    {
        $site = MLS_USE_DATA_CONFIG::site();
        $result = self::request('GET', '/sites/' . $site['id'] . '/domain_discovery/search', null, [
            'limit' => (string) (int) $limit,
            'q' => (string) $title,
        ]);
        if (!$result['ok']) {
            return [];
        }
        $out = [];
        foreach ($result['data'] as $row) {
            if (!empty($row['category_id'])) {
                $out[] = [
                    'category_id' => (string) $row['category_id'],
                    'category_name' => (string) ($row['category_name'] ?? ''),
                    'domain_name' => (string) ($row['domain_name'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * Atributos obligatorios de una categoría (caché 1 día).
     *
     * @return array|null [['id' => 'BRAND', 'name' => 'Marca', 'values' => ['…']]] o null si falla.
     */
    public static function requiredAttributes($category_id)
    {
        $category_id = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $category_id));
        if ('' === $category_id) {
            return null;
        }
        $cache_key = 'mls_attrs_' . $category_id;
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }
        $result = self::request('GET', '/categories/' . $category_id . '/attributes');
        if (!$result['ok']) {
            return null;
        }
        $out = [];
        foreach ($result['data'] as $attr) {
            $tags = isset($attr['tags']) && is_array($attr['tags']) ? $attr['tags'] : [];
            if (empty($tags['required']) && empty($tags['catalog_required']) && empty($tags['conditional_required'])) {
                continue;
            }
            $values = [];
            if (!empty($attr['values']) && is_array($attr['values'])) {
                foreach (array_slice($attr['values'], 0, 15) as $value) {
                    $values[] = (string) ($value['name'] ?? '');
                }
            }
            $out[] = [
                'id' => (string) $attr['id'],
                'name' => (string) ($attr['name'] ?? ''),
                'conditional' => empty($tags['required']) && empty($tags['catalog_required']),
                'values' => array_values(array_filter($values)),
            ];
        }
        set_transient($cache_key, $out, DAY_IN_SECONDS);

        return $out;
    }

    public static function categoryName($category_id)
    {
        $result = self::request('GET', '/categories/' . rawurlencode($category_id));

        return $result['ok'] ? (string) ($result['data']['name'] ?? '') : '';
    }
}
