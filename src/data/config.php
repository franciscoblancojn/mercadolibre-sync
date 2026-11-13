<?php

/**
 * Configuración del plugin (opción MLS_CONFIG).
 *
 * DEFAULTS es la única lista de ajustes: get() la mezcla con lo guardado y sanitize()
 * normaliza lo que llega del formulario. Un ajuste nuevo se agrega en los dos.
 */
class MLS_USE_DATA_CONFIG extends MLS_USE_DATA_BASE
{
    protected $KEY = MLS_CONFIG;

    /**
     * País (site de MercadoLibre) → dominio de autorización y moneda.
     */
    const SITES = [
        'MCO' => ['label' => 'Colombia (MCO)', 'auth' => 'https://auth.mercadolibre.com.co', 'currency' => 'COP', 'decimals' => 0],
        'MLA' => ['label' => 'Argentina (MLA)', 'auth' => 'https://auth.mercadolibre.com.ar', 'currency' => 'ARS', 'decimals' => 2],
        'MLB' => ['label' => 'Brasil (MLB)', 'auth' => 'https://auth.mercadolivre.com.br', 'currency' => 'BRL', 'decimals' => 2],
        'MLM' => ['label' => 'México (MLM)', 'auth' => 'https://auth.mercadolibre.com.mx', 'currency' => 'MXN', 'decimals' => 2],
        'MLC' => ['label' => 'Chile (MLC)', 'auth' => 'https://auth.mercadolibre.cl', 'currency' => 'CLP', 'decimals' => 0],
        'MPE' => ['label' => 'Perú (MPE)', 'auth' => 'https://auth.mercadolibre.com.pe', 'currency' => 'PEN', 'decimals' => 2],
        'MLU' => ['label' => 'Uruguay (MLU)', 'auth' => 'https://auth.mercadolibre.com.uy', 'currency' => 'UYU', 'decimals' => 2],
    ];

    const LISTING_TYPES = [
        'gold_special' => 'Clásica (gold_special)',
        'gold_pro' => 'Premium (gold_pro)',
        'free' => 'Gratuita (free)',
    ];

    const CONDITIONS = [
        'new' => 'Nuevo',
        'used' => 'Usado',
    ];

    const DEFAULTS = [
        // Conexión (aplicación creada en developers.mercadolibre.com).
        'site_id' => 'MCO',
        'client_id' => '',
        'client_secret' => '',
        'use_pkce' => 0,
        // Sincronización.
        'enabled' => 1,
        'sync_on_save' => 1,
        'sync_stock' => 1,
        'pause_unpublished' => 1,
        'close_on_delete' => 1,
        // Publicación por defecto (cada producto puede cambiarlos).
        'listing_type' => 'gold_special',
        'condition' => 'new',
        'default_category' => '',
        'auto_category' => 1,
        'price_markup' => '0',
        'fallback_stock' => 10,
        'max_pictures' => 10,
        'send_description' => 1,
        'family_name' => 0,
        'default_attributes' => '',
    ];

    public function get()
    {
        return array_merge(self::DEFAULTS, $this->DATA);
    }

    public function set($data)
    {
        $this->DATA = $data;
        $this->save();
    }

    /**
     * Decimal con punto (acepta coma).
     */
    public static function number($value, $default = 0)
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ('' === $value || !is_numeric($value)) {
            return $default;
        }

        return (float) $value;
    }

    /**
     * Normaliza lo que llega del formulario de ajustes (ya con wp_unslash).
     */
    public static function sanitize($input)
    {
        $input = (array) $input;
        $d = self::DEFAULTS;
        $check = function ($key) use ($input) {
            return empty($input[$key]) ? 0 : 1;
        };
        $pick = function ($key, $list) use ($input, $d) {
            $value = sanitize_text_field($input[$key] ?? '');
            return isset($list[$value]) ? $value : $d[$key];
        };

        // La Secret Key nunca se muestra: si el campo llega vacío se conserva la guardada.
        $secret = trim(sanitize_text_field($input['client_secret'] ?? ''));
        if ('' === $secret) {
            $secret = (string) (new self())->get()['client_secret'];
        }

        return [
            'site_id' => $pick('site_id', self::SITES),
            'client_id' => preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($input['client_id'] ?? '')),
            'client_secret' => $secret,
            'use_pkce' => $check('use_pkce'),
            'enabled' => $check('enabled'),
            'sync_on_save' => $check('sync_on_save'),
            'sync_stock' => $check('sync_stock'),
            'pause_unpublished' => $check('pause_unpublished'),
            'close_on_delete' => $check('close_on_delete'),
            'listing_type' => $pick('listing_type', self::LISTING_TYPES),
            'condition' => $pick('condition', self::CONDITIONS),
            'default_category' => strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) ($input['default_category'] ?? ''))),
            'auto_category' => $check('auto_category'),
            'price_markup' => (string) max(-90, min(500, self::number($input['price_markup'] ?? 0, 0))),
            'fallback_stock' => max(0, absint($input['fallback_stock'] ?? $d['fallback_stock'])),
            'max_pictures' => min(12, max(1, absint($input['max_pictures'] ?? $d['max_pictures']))),
            'send_description' => $check('send_description'),
            'family_name' => $check('family_name'),
            'default_attributes' => sanitize_textarea_field($input['default_attributes'] ?? ''),
        ];
    }

    /**
     * Datos del site configurado (dominio de autorización, moneda, decimales).
     */
    public static function site($config = null)
    {
        $config = null === $config ? (new self())->get() : $config;
        $site = isset(self::SITES[$config['site_id']]) ? $config['site_id'] : self::DEFAULTS['site_id'];

        return array_merge(['id' => $site], self::SITES[$site]);
    }

    /**
     * ¿Hay App ID y Secret Key?
     */
    public static function hasApp($config = null)
    {
        $config = null === $config ? (new self())->get() : $config;

        return '' !== (string) $config['client_id'] && '' !== (string) $config['client_secret'];
    }

    /**
     * ¿Se puede sincronizar? (sincronización activa + app + cuenta conectada)
     */
    public static function ready($config = null)
    {
        $config = null === $config ? (new self())->get() : $config;

        return !empty($config['enabled']) && self::hasApp($config) && MLS_USE_DATA_TOKEN::connected();
    }
}
