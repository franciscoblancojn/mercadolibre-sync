<?php

/**
 * Producto de WooCommerce ↔ publicación de MercadoLibre.
 *
 * Única fuente del mapeo: meta `_mls_*` del producto (item_id, estado, ajustes por
 * producto) y `_mls_variation_id` en cada variación. sync() decide si crear, actualizar,
 * pausar o reactivar, y es lo único que llama a MLS_API para un producto.
 *
 * Nunca llama a $product->save(): solo update_post_meta(), para no disparar los hooks
 * de guardado y volver a encolar el producto.
 */
class MLS_PRODUCT
{
    const TYPES = ['simple', 'variable'];
    const PER_PAGE = [25, 50, 100];
    const TITLE_MAX = 60;

    const DEFAULTS = [
        // Ajustes del producto (metabox).
        'enabled' => 0,
        'category' => '',
        'listing_type' => '',
        'condition' => '',
        'gtin' => '',
        'attributes' => '',
        'variation_map' => '',
        // Estado del mapeo.
        'item_id' => '',
        'permalink' => '',
        'status' => '',
        'last_sync' => '',
        'last_error' => '',
        'queued' => 0,
        'pictures_hash' => '',
        'description_hash' => '',
    ];

    const STATUS_LABELS = [
        '' => 'Sin publicar',
        'queued' => 'En cola',
        'active' => 'Activa',
        'paused' => 'Pausada',
        'closed' => 'Finalizada',
        'under_review' => 'En revisión',
        'inactive' => 'Inactiva',
        'error' => 'Error',
    ];

    public static function key($name)
    {
        return MLS_META . $name;
    }

    public static function meta($id)
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = get_post_meta($id, self::key($key), true);
            $out[$key] = '' === $value || null === $value ? $default : $value;
        }
        $out['enabled'] = (int) $out['enabled'];
        $out['queued'] = (int) $out['queued'];

        return $out;
    }

    public static function patch($id, $data)
    {
        foreach ((array) $data as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            if ('' === $value || null === $value) {
                delete_post_meta($id, self::key($key));
            } else {
                update_post_meta($id, self::key($key), $value);
            }
        }
    }

    /**
     * Quita el vínculo con MercadoLibre (no toca la publicación remota).
     */
    public static function unlink($id)
    {
        self::patch($id, [
            'enabled' => 0,
            'item_id' => '',
            'permalink' => '',
            'status' => '',
            'last_error' => '',
            'queued' => 0,
            'pictures_hash' => '',
            'description_hash' => '',
        ]);
        $product = wc_get_product($id);
        if ($product && $product->is_type('variable')) {
            foreach ($product->get_children() as $child_id) {
                delete_post_meta($child_id, self::key('variation_id'));
            }
        }
    }

    public static function parentId($id)
    {
        $parent = wp_get_post_parent_id($id);

        return 'product_variation' === get_post_type($id) && $parent ? (int) $parent : (int) $id;
    }

    public static function config()
    {
        return (new MLS_USE_DATA_CONFIG())->get();
    }

    // ── Datos que se envían ────────────────────────────────────────────────

    public static function sellerSku($product)
    {
        $sku = (string) $product->get_sku();

        return '' !== $sku ? $sku : 'WC-' . $product->get_id();
    }

    public static function title($product)
    {
        $title = trim(wp_strip_all_tags(html_entity_decode($product->get_name(), ENT_QUOTES, 'UTF-8')));
        if (function_exists('mb_substr')) {
            return mb_strlen($title) > self::TITLE_MAX ? rtrim(mb_substr($title, 0, self::TITLE_MAX)) : $title;
        }

        return substr($title, 0, self::TITLE_MAX);
    }

    /**
     * Precio activo de WooCommerce (con oferta) + ajuste % de la configuración.
     */
    public static function price($product, $config)
    {
        $price = (float) $product->get_price();
        $markup = (float) $config['price_markup'];
        if ($markup) {
            $price = $price * (1 + $markup / 100);
        }
        $site = MLS_USE_DATA_CONFIG::site($config);

        return round($price, (int) $site['decimals']);
    }

    public static function stock($product, $config)
    {
        if ($product->managing_stock()) {
            return max(0, (int) $product->get_stock_quantity());
        }

        return 'outofstock' === $product->get_stock_status() ? 0 : (int) $config['fallback_stock'];
    }

    /**
     * URLs de imagen principal + galería (deben ser públicas: MercadoLibre las descarga).
     */
    public static function imageUrls($product, $max)
    {
        $ids = array_merge([(int) $product->get_image_id()], array_map('intval', $product->get_gallery_image_ids()));
        $urls = [];
        foreach (array_unique(array_filter($ids)) as $attachment_id) {
            $url = wp_get_attachment_url($attachment_id);
            if ($url) {
                $urls[] = $url;
            }
        }

        return array_slice($urls, 0, $max);
    }

    public static function description($product)
    {
        $html = (string) $product->get_description();
        if ('' === trim($html)) {
            $html = (string) $product->get_short_description();
        }
        $html = preg_replace('#<(br|/p|/li|/h[1-6]|/div)\b[^>]*>#i', "\n", strip_shortcodes($html));
        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Líneas `CLAVE=valor` → ['CLAVE' => 'valor'].
     */
    public static function pairs($text)
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            $line = trim($line);
            if ('' === $line || false === strpos($line, '=')) {
                continue;
            }
            list($key, $value) = array_map('trim', explode('=', $line, 2));
            if ('' !== $key && '' !== $value) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Atributos del ítem: los por defecto de la configuración, los del producto, SELLER_SKU y GTIN.
     */
    public static function attributes($product, $meta, $config)
    {
        $pairs = array_merge(self::pairs($config['default_attributes']), self::pairs($meta['attributes']));
        $gtin = (string) $meta['gtin'];
        if ('' === $gtin && method_exists($product, 'get_global_unique_id')) {
            $gtin = (string) $product->get_global_unique_id();
        }
        if ('' !== $gtin) {
            $pairs['GTIN'] = $gtin;
        }
        if (!$product->is_type('variable')) {
            $pairs['SELLER_SKU'] = self::sellerSku($product);
        }
        $out = [];
        foreach ($pairs as $id => $value) {
            $out[] = ['id' => strtoupper($id), 'value_name' => $value];
        }

        return $out;
    }

    /**
     * Combinación de atributos de una variación (pa_color=COLOR en el mapeo → id de MercadoLibre).
     */
    public static function combination($variation, $parent, $map)
    {
        $out = [];
        foreach ($variation->get_variation_attributes(false) as $taxonomy => $slug) {
            if ('' === (string) $slug) {
                continue; // «Cualquiera»: no forma parte de la combinación.
            }
            $value = $slug;
            if (taxonomy_exists($taxonomy)) {
                $term = get_term_by('slug', $slug, $taxonomy);
                $value = $term ? $term->name : $slug;
            }
            $label = wc_attribute_label($taxonomy, $parent);
            if (isset($map[$taxonomy])) {
                $out[] = ['id' => strtoupper($map[$taxonomy]), 'value_name' => $value];
            } elseif (isset($map[$label])) {
                $out[] = ['id' => strtoupper($map[$label]), 'value_name' => $value];
            } else {
                $out[] = ['name' => $label, 'value_name' => $value];
            }
        }

        return $out;
    }

    private static function signature($combination)
    {
        $values = [];
        foreach ((array) $combination as $row) {
            $values[] = strtolower(remove_accents(trim((string) ($row['value_name'] ?? ''))));
        }
        sort($values);

        return implode('|', $values);
    }

    /**
     * Variaciones publicables (publicadas y con precio).
     *
     * @return WC_Product_Variation[]
     */
    public static function variations($product)
    {
        $out = [];
        foreach ($product->get_children() as $child_id) {
            $child = wc_get_product($child_id);
            if ($child && 'publish' === $child->get_status() && (float) $child->get_price() > 0) {
                $out[] = $child;
            }
        }

        return $out;
    }

    /**
     * Arma el cuerpo de POST /items (crear) o PUT /items/{id} (actualizar).
     *
     * @return array ['payload' => array, 'pictures_hash' => string, 'variations' => WC_Product_Variation[]]
     */
    public static function payload($product, $meta, $config, $update)
    {
        $max = (int) $config['max_pictures'];
        $pictures = self::imageUrls($product, $max);
        $variations = $product->is_type('variable') ? self::variations($product) : [];
        $map = self::pairs($meta['variation_map']);
        $has_new_variation = false;

        $variation_rows = [];
        foreach ($variations as $variation) {
            $image = $variation->get_image_id() ? wp_get_attachment_url($variation->get_image_id()) : '';
            if ($image && !in_array($image, $pictures, true)) {
                $pictures[] = $image;
            }
            $remote_id = (string) get_post_meta($variation->get_id(), self::key('variation_id'), true);
            $row = [
                'price' => self::price($variation, $config),
                'available_quantity' => self::stock($variation, $config),
                'attributes' => [['id' => 'SELLER_SKU', 'value_name' => self::sellerSku($variation)]],
            ];
            if ($update && '' !== $remote_id) {
                $row = array_merge(['id' => is_numeric($remote_id) ? (int) $remote_id : $remote_id], $row);
            } else {
                $has_new_variation = true;
                $row['attribute_combinations'] = self::combination($variation, $product, $map);
            }
            $row['_picture'] = $image ? $image : ($pictures ? $pictures[0] : '');
            $variation_rows[] = $row;
        }
        $pictures = array_slice(array_values(array_unique($pictures)), 0, max($max, 1) + count($variations));
        $pictures_hash = md5(wp_json_encode($pictures));
        $send_pictures = !$update || $has_new_variation || $pictures_hash !== (string) $meta['pictures_hash'];

        foreach ($variation_rows as $i => $row) {
            if ($send_pictures && '' !== $row['_picture']) {
                $variation_rows[$i]['picture_ids'] = [$row['_picture']];
            }
            unset($variation_rows[$i]['_picture']);
        }

        $payload = [];
        if (!$update) {
            $site = MLS_USE_DATA_CONFIG::site($config);
            $payload = [
                'category_id' => $meta['category'],
                'currency_id' => $site['currency'],
                'buying_mode' => 'buy_it_now',
                'listing_type_id' => $meta['listing_type'] ? $meta['listing_type'] : $config['listing_type'],
                'condition' => $meta['condition'] ? $meta['condition'] : $config['condition'],
            ];
            if (!empty($config['family_name'])) {
                // Modelo «User Products»: el título lo genera MercadoLibre a partir de family_name.
                $payload['family_name'] = self::title($product);
            } else {
                $payload['title'] = self::title($product);
            }
        } elseif (empty($config['family_name'])) {
            $payload['title'] = self::title($product);
        }

        if ($variation_rows) {
            $payload['variations'] = $variation_rows;
            if (!$update) {
                $payload['price'] = $variation_rows[0]['price'];
            }
        } else {
            $payload['price'] = self::price($product, $config);
            $payload['available_quantity'] = self::stock($product, $config);
        }
        if ($send_pictures && $pictures) {
            $payload['pictures'] = array_map(function ($url) {
                return ['source' => $url];
            }, $pictures);
        }
        $attributes = self::attributes($product, $meta, $config);
        if ($attributes) {
            $payload['attributes'] = $attributes;
        }

        return [
            'payload' => $payload,
            'send_pictures' => $send_pictures,
            'pictures_hash' => $pictures_hash,
            'variations' => $variations,
        ];
    }

    // ── Sincronización ─────────────────────────────────────────────────────

    /**
     * Crea, actualiza, pausa o reactiva la publicación de un producto.
     *
     * @return array ['ok' => bool, 'message' => string]
     */
    public static function sync($id)
    {
        $id = self::parentId($id);
        $product = wc_get_product($id);
        self::patch($id, ['queued' => 0]);
        if (!$product || !in_array($product->get_type(), self::TYPES, true)) {
            return ['ok' => false, 'message' => 'Solo se publican productos simples y variables.'];
        }
        $config = self::config();
        if (!MLS_USE_DATA_CONFIG::ready($config)) {
            return self::fail($id, 'La sincronización está desactivada o la cuenta de MercadoLibre no está conectada.');
        }
        $meta = self::meta($id);
        $wc_live = 'publish' === $product->get_status();
        $want_active = $meta['enabled'] && ($wc_live || empty($config['pause_unpublished']));

        if ('' === $meta['item_id']) {
            if (!$meta['enabled']) {
                return ['ok' => true, 'message' => 'El producto no está marcado para MercadoLibre.'];
            }
            if (!$wc_live) {
                return self::fail($id, 'El producto no está publicado en WooCommerce.');
            }

            return self::create($product, $meta, $config);
        }

        if (!$meta['enabled'] || !$want_active) {
            // Desmarcado o no publicado en WooCommerce: solo pausar.
            if ('paused' === $meta['status'] || 'closed' === $meta['status']) {
                return ['ok' => true, 'message' => 'La publicación ya estaba pausada.'];
            }
            return self::changeStatus($id, 'paused');
        }

        return self::update($product, $meta, $config);
    }

    private static function fail($id, $message, $context = [])
    {
        self::patch($id, ['last_error' => $message, 'last_sync' => current_time('mysql')]);
        MLS_LOG_SERVICE::add('sync_error', array_merge(['product' => $id, 'error' => $message], $context));

        return ['ok' => false, 'message' => $message];
    }

    private static function create($product, $meta, $config)
    {
        $id = $product->get_id();
        if ('' === $meta['category']) {
            $meta['category'] = $config['default_category'];
        }
        if ('' === $meta['category'] && !empty($config['auto_category'])) {
            $predicted = MLS_API::predictCategory(self::title($product), 1);
            if ($predicted) {
                $meta['category'] = $predicted[0]['category_id'];
                self::patch($id, ['category' => $meta['category']]);
            }
        }
        if ('' === $meta['category']) {
            return self::fail($id, 'Falta la categoría de MercadoLibre (defínela en el producto o activa la predicción automática).');
        }

        $built = self::payload($product, $meta, $config, false);
        if (empty($built['payload']['pictures'])) {
            return self::fail($id, 'MercadoLibre exige al menos una imagen.');
        }
        $result = MLS_API::createItem($built['payload']);
        if (!$result['ok']) {
            return self::fail($id, 'No se pudo publicar: ' . $result['error'], ['payload' => self::forLog($built['payload'])]);
        }

        $item = $result['data'];
        self::applyItem($id, $item, $built);
        if (!empty($config['send_description'])) {
            self::sendDescription($product, (string) $item['id'], false, '');
        }
        MLS_LOG_SERVICE::add('publicar', ['product' => $id, 'item' => $item['id'], 'status' => $item['status'] ?? '']);

        return ['ok' => true, 'message' => 'Publicado en MercadoLibre: ' . $item['id'] . '.'];
    }

    private static function update($product, $meta, $config)
    {
        $id = $product->get_id();
        $item_id = $meta['item_id'];
        $built = self::payload($product, $meta, $config, true);
        $result = MLS_API::updateItem($item_id, $built['payload']);

        // El título no se puede cambiar si la publicación ya tiene ventas: reintentar sin él.
        if (!$result['ok'] && isset($built['payload']['title']) && false !== stripos($result['error'], 'title')) {
            unset($built['payload']['title']);
            $result = MLS_API::updateItem($item_id, $built['payload']);
        }
        if (!$result['ok']) {
            if (404 === $result['code'] || false !== stripos($result['error'], 'closed')) {
                self::patch($id, ['status' => 'closed']);
                return self::fail($id, 'La publicación ' . $item_id . ' está finalizada o no existe. Desvincúlala para crear una nueva. (' . $result['error'] . ')');
            }
            return self::fail($id, 'No se pudo actualizar: ' . $result['error'], ['item' => $item_id, 'payload' => self::forLog($built['payload'])]);
        }

        $item = $result['data'];
        self::applyItem($id, $item, $built);
        if (!empty($config['send_description'])) {
            self::sendDescription($product, $item_id, true, (string) $meta['description_hash']);
        }

        // Reactivar si estaba pausada a mano y vuelve a estar habilitada con stock.
        $status = (string) ($item['status'] ?? '');
        $sub = isset($item['sub_status']) ? (array) $item['sub_status'] : [];
        if ('paused' === $status && !in_array('out_of_stock', $sub, true) && (int) ($item['available_quantity'] ?? 0) > 0) {
            self::changeStatus($id, 'active');
        }
        MLS_LOG_SERVICE::add('actualizar', ['product' => $id, 'item' => $item_id, 'status' => $status]);

        return ['ok' => true, 'message' => 'Publicación ' . $item_id . ' actualizada.'];
    }

    private static function sendDescription($product, $item_id, $exists, $previous_hash)
    {
        $text = self::description($product);
        if ('' === $text) {
            return;
        }
        $hash = md5($text);
        if ($exists && $hash === $previous_hash) {
            return;
        }
        $result = MLS_API::setDescription($item_id, $text, $exists);
        if ($result['ok']) {
            self::patch($product->get_id(), ['description_hash' => $hash]);
        } else {
            MLS_LOG_SERVICE::add('descripcion_error', ['product' => $product->get_id(), 'item' => $item_id, 'error' => $result['error']]);
        }
    }

    /**
     * Guarda en el producto lo que devolvió MercadoLibre (id, enlace, estado, ids de variaciones).
     */
    private static function applyItem($id, $item, $built)
    {
        $patch = [
            'item_id' => (string) ($item['id'] ?? ''),
            'permalink' => esc_url_raw((string) ($item['permalink'] ?? '')),
            'status' => (string) ($item['status'] ?? ''),
            'last_sync' => current_time('mysql'),
            'last_error' => '',
        ];
        if ($built['send_pictures']) {
            $patch['pictures_hash'] = $built['pictures_hash'];
        }
        self::patch($id, $patch);

        if (empty($item['variations']) || empty($built['variations'])) {
            return;
        }
        $remote = [];
        foreach ($item['variations'] as $index => $row) {
            $remote[self::signature($row['attribute_combinations'] ?? [])] = $row['id'];
        }
        $parent = wc_get_product($id);
        $map = self::pairs(self::meta($id)['variation_map']);
        foreach ($built['variations'] as $index => $variation) {
            $signature = self::signature(self::combination($variation, $parent, $map));
            if (isset($remote[$signature])) {
                update_post_meta($variation->get_id(), self::key('variation_id'), (string) $remote[$signature]);
            } elseif (isset($item['variations'][$index]['id'])) {
                update_post_meta($variation->get_id(), self::key('variation_id'), (string) $item['variations'][$index]['id']);
            }
        }
    }

    /**
     * Pausa / activa / finaliza la publicación vinculada.
     */
    public static function changeStatus($id, $status)
    {
        $id = self::parentId($id);
        $meta = self::meta($id);
        if ('' === $meta['item_id']) {
            return ['ok' => false, 'message' => 'El producto no está vinculado a una publicación.'];
        }
        $result = MLS_API::setStatus($meta['item_id'], $status);
        if (!$result['ok']) {
            return self::fail($id, 'No se pudo cambiar el estado a ' . $status . ': ' . $result['error']);
        }
        self::patch($id, [
            'status' => (string) ($result['data']['status'] ?? $status),
            'last_sync' => current_time('mysql'),
            'last_error' => '',
        ]);
        MLS_LOG_SERVICE::add('estado', ['product' => $id, 'item' => $meta['item_id'], 'status' => $status]);

        return ['ok' => true, 'message' => 'Estado en MercadoLibre: ' . (self::STATUS_LABELS[$status] ?? $status) . '.'];
    }

    /**
     * Vincula manualmente un producto con una publicación existente (comprueba que sea de la cuenta).
     */
    public static function link($id, $item_id)
    {
        $id = self::parentId($id);
        $item_id = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $item_id));
        if ('' === $item_id) {
            return ['ok' => false, 'message' => 'ID de publicación vacío.'];
        }
        $result = MLS_API::item($item_id);
        if (!$result['ok']) {
            return ['ok' => false, 'message' => 'No se encontró la publicación ' . $item_id . ': ' . $result['error']];
        }
        $account = MLS_USE_DATA_TOKEN::account();
        if ((string) ($result['data']['seller_id'] ?? '') !== (string) $account['user_id']) {
            return ['ok' => false, 'message' => 'La publicación ' . $item_id . ' no pertenece a la cuenta conectada.'];
        }
        $item = $result['data'];
        self::patch($id, [
            'enabled' => 1,
            'item_id' => $item_id,
            'permalink' => esc_url_raw((string) ($item['permalink'] ?? '')),
            'status' => (string) ($item['status'] ?? ''),
            'category' => (string) ($item['category_id'] ?? ''),
            'last_error' => '',
            'pictures_hash' => '',
            'description_hash' => '',
        ]);
        // Variaciones: vincular por SELLER_SKU o por combinación de valores.
        $product = wc_get_product($id);
        if ($product && $product->is_type('variable') && !empty($item['variations'])) {
            $map = self::pairs(self::meta($id)['variation_map']);
            foreach (self::variations($product) as $variation) {
                $sku = self::sellerSku($variation);
                $signature = self::signature(self::combination($variation, $product, $map));
                foreach ($item['variations'] as $row) {
                    $row_sku = '';
                    foreach ((array) ($row['attributes'] ?? []) as $attr) {
                        if ('SELLER_SKU' === ($attr['id'] ?? '')) {
                            $row_sku = (string) ($attr['value_name'] ?? '');
                        }
                    }
                    if ($row_sku === $sku || self::signature($row['attribute_combinations'] ?? []) === $signature) {
                        update_post_meta($variation->get_id(), self::key('variation_id'), (string) $row['id']);
                        break;
                    }
                }
            }
        }
        MLS_LOG_SERVICE::add('vincular', ['product' => $id, 'item' => $item_id]);

        return ['ok' => true, 'message' => 'Producto vinculado con ' . $item_id . '.'];
    }

    /**
     * Payload para el log sin descripciones largas.
     */
    private static function forLog($payload)
    {
        if (isset($payload['pictures'])) {
            $payload['pictures'] = count($payload['pictures']) . ' imagen(es)';
        }

        return $payload;
    }

    // ── Listado (subpágina Productos) ──────────────────────────────────────

    public static function summary($id)
    {
        $meta = self::meta($id);
        $status = $meta['queued'] ? 'queued' : (string) $meta['status'];
        if ('' === $status && '' !== $meta['last_error']) {
            $status = 'error';
        }

        return [
            'status' => $status,
            'label' => self::STATUS_LABELS[$status] ?? $status,
            'meta' => $meta,
        ];
    }

    public static function filters($source)
    {
        $source = (array) $source;
        $ml = sanitize_key($source['ml'] ?? '');
        if (!in_array($ml, ['enabled', 'linked', 'not_linked', 'active', 'paused', 'queued', 'error'], true)) {
            $ml = '';
        }
        $type = sanitize_key($source['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            $type = '';
        }
        $per_page = absint($source['per_page'] ?? 50);

        return [
            's' => sanitize_text_field(wp_unslash($source['s'] ?? '')),
            'type' => $type,
            'ml' => $ml,
            'cat' => absint($source['cat'] ?? 0),
            'per_page' => in_array($per_page, self::PER_PAGE, true) ? $per_page : 50,
            'paged' => max(1, absint($source['paged'] ?? 1)),
        ];
    }

    private static function metaQuery($ml)
    {
        $item = self::key('item_id');
        switch ($ml) {
            case 'enabled':
                return [['key' => self::key('enabled'), 'value' => '1']];
            case 'linked':
                return [['key' => $item, 'compare' => 'EXISTS']];
            case 'not_linked':
                return [['key' => $item, 'compare' => 'NOT EXISTS']];
            case 'active':
            case 'paused':
                return [['key' => self::key('status'), 'value' => $ml]];
            case 'queued':
                return [['key' => self::key('queued'), 'value' => '1']];
            case 'error':
                return [['key' => self::key('last_error'), 'compare' => 'EXISTS']];
        }

        return [];
    }

    private static function queryArgs($f)
    {
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
            'orderby' => 'date',
            'order' => 'DESC',
            'tax_query' => [[
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => $f['type'] ? [$f['type']] : self::TYPES,
            ]],
        ];
        if ($f['cat']) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => [$f['cat']],
            ];
        }
        if ('' !== $f['s']) {
            // Búsqueda por SKU exacto o por título.
            $by_sku = wc_get_product_id_by_sku($f['s']);
            if ($by_sku) {
                $args['post__in'] = [self::parentId($by_sku)];
            } else {
                $args['s'] = $f['s'];
            }
        }
        $meta = self::metaQuery($f['ml']);
        if ($meta) {
            $args['meta_query'] = $meta;
        }

        return $args;
    }

    public static function page($f)
    {
        $query = new WP_Query(array_merge(self::queryArgs($f), [
            'posts_per_page' => $f['per_page'],
            'paged' => $f['paged'],
        ]));
        $items = [];
        foreach ($query->posts as $post) {
            $product = wc_get_product($post);
            if ($product) {
                $items[] = self::row($product);
            }
        }

        return [
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'items' => $items,
        ];
    }

    public static function row($product)
    {
        $id = $product->get_id();
        $summary = self::summary($id);
        $meta = $summary['meta'];
        $image = $product->get_image_id() ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '';

        return [
            'id' => $id,
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'sku' => (string) $product->get_sku(),
            'price' => $product->get_price_html(),
            'stock' => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
            'stock_status' => $product->get_stock_status(),
            'woo_status' => get_post_status($id),
            'image' => $image ? $image : '',
            'variations' => $product->is_type('variable') ? count($product->get_children()) : 0,
            'enabled' => $meta['enabled'],
            'item_id' => $meta['item_id'],
            'permalink' => $meta['permalink'],
            'status' => $summary['status'],
            'status_label' => $summary['label'],
            'last_sync' => $meta['last_sync'],
            'last_error' => $meta['last_error'],
            'edit' => get_edit_post_link($id, 'raw'),
        ];
    }

    public static function idsByFilters($f)
    {
        return array_map('intval', get_posts(array_merge(self::queryArgs($f), [
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ])));
    }
}
