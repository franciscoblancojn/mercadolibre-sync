<?php

/**
 * Tokens OAuth de MercadoLibre (opción MLS_TOKEN, sin autoload).
 *
 * access_token dura ~6 h; refresh_token es de un solo uso y se reemplaza en cada
 * renovación (MLS_API::token() es el único que renueva). Nunca se muestran ni se
 * guardan en el log.
 */
class MLS_USE_DATA_TOKEN extends MLS_USE_DATA_BASE
{
    protected $KEY = MLS_TOKEN;

    const DEFAULTS = [
        'access_token' => '',
        'refresh_token' => '',
        'expires_at' => 0,
        'user_id' => '',
        'nickname' => '',
        'site_id' => '',
        'scope' => '',
        'connected_at' => '',
    ];

    public function get()
    {
        return array_merge(self::DEFAULTS, $this->DATA);
    }

    /**
     * Guarda la respuesta de /oauth/token (y datos de /users/me si se pasan).
     */
    public function setFromResponse($response, $extra = [])
    {
        $current = $this->get();
        $this->DATA = array_merge($current, [
            'access_token' => (string) ($response['access_token'] ?? ''),
            // Si la respuesta no trae refresh_token (scope sin offline_access) se conserva el anterior.
            'refresh_token' => (string) ($response['refresh_token'] ?? $current['refresh_token']),
            'expires_at' => time() + max(60, (int) ($response['expires_in'] ?? 21600)),
            'user_id' => (string) ($response['user_id'] ?? $current['user_id']),
            'scope' => (string) ($response['scope'] ?? $current['scope']),
        ], (array) $extra);
        $this->save();
    }

    public function patch($data)
    {
        $this->DATA = array_merge($this->get(), (array) $data);
        $this->save();
    }

    public function clear()
    {
        $this->DATA = [];
        delete_option($this->KEY);
    }

    public static function connected()
    {
        $token = (new self())->get();

        return '' !== $token['access_token'] && '' !== $token['refresh_token'];
    }

    /**
     * Datos visibles de la cuenta conectada (sin tokens).
     */
    public static function account()
    {
        $token = (new self())->get();
        unset($token['access_token'], $token['refresh_token']);

        return $token;
    }
}
