<?php

/**
 * AJAX de productos (subpágina Productos y caja de la ficha).
 *
 * - mls_bulk: acciones masivas → cola (publish, sync, pause, unlink).
 * - mls_product: acción inmediata sobre un producto (sync, pause, activate, unlink, link).
 * - mls_predict / mls_required: predictor de categoría y atributos obligatorios.
 */
class MLS_API_PRODUCTS
{
    const NONCE = 'mls_products_nonce';

    public static function init()
    {
        add_action('wp_ajax_mls_bulk', [self::class, 'bulk']);
        add_action('wp_ajax_mls_product', [self::class, 'product']);
        add_action('wp_ajax_mls_predict', [self::class, 'predict']);
        add_action('wp_ajax_mls_required', [self::class, 'required']);
    }

    private static function guard()
    {
        if (!current_user_can(MLS_CAPABILITY)) {
            wp_send_json_error('No tienes permisos para esta acción.', 403);
        }
        check_ajax_referer(self::NONCE, 'nonce');
    }

    private static function ready()
    {
        if (!MLS_USE_DATA_CONFIG::ready()) {
            wp_send_json_error('Conecta la cuenta de MercadoLibre y activa la sincronización en Configuración.');
        }
    }

    /**
     * IDs de la selección o, con scope=filtered, de todos los resultados del filtro.
     *
     * @return int[]
     */
    private static function ids()
    {
        $ids = isset($_POST['ids']) ? (array) wp_unslash($_POST['ids']) : [];
        $ids = array_values(array_filter(array_map('absint', $ids)));
        if ($ids) {
            return $ids;
        }
        if ('filtered' !== sanitize_key($_POST['scope'] ?? '')) {
            return [];
        }

        return MLS_PRODUCT::idsByFilters(MLS_PRODUCT::filters($_POST)); // filters() sanitiza cada campo.
    }

    public static function bulk()
    {
        self::guard();
        $do = sanitize_key($_POST['do'] ?? '');
        $ids = self::ids();
        if (!$ids) {
            wp_send_json_error('No hay productos seleccionados.');
        }
        if ('unlink' !== $do) {
            self::ready();
        }

        $count = 0;
        switch ($do) {
            case 'publish':
                foreach ($ids as $id) {
                    MLS_PRODUCT::patch($id, ['enabled' => 1]);
                }
                $count = MLS_SYNC::enqueue($ids, 'masivo_publicar');
                $message = $count . ' producto(s) marcados y en cola para publicar/actualizar.';
                break;
            case 'sync':
                $linked = array_values(array_filter($ids, function ($id) {
                    $meta = MLS_PRODUCT::meta($id);
                    return $meta['enabled'] || '' !== $meta['item_id'];
                }));
                $count = MLS_SYNC::enqueue($linked, 'masivo_actualizar');
                $message = $count . ' producto(s) en cola para actualizar (se omiten los no marcados).';
                break;
            case 'pause':
                $linked = [];
                foreach ($ids as $id) {
                    if ('' !== MLS_PRODUCT::meta($id)['item_id']) {
                        MLS_PRODUCT::patch($id, ['enabled' => 0]);
                        $linked[] = $id;
                    }
                }
                $count = MLS_SYNC::enqueue($linked, 'masivo_pausar');
                $message = $count . ' publicación(es) en cola para pausar.';
                break;
            case 'unlink':
                foreach ($ids as $id) {
                    if ('' !== MLS_PRODUCT::meta($id)['item_id'] || MLS_PRODUCT::meta($id)['enabled']) {
                        MLS_PRODUCT::unlink($id);
                        $count++;
                    }
                }
                MLS_LOG_SERVICE::add('masivo_desvincular', ['ids' => $ids]);
                $message = $count . ' producto(s) desvinculados. Las publicaciones siguen en MercadoLibre.';
                break;
            default:
                wp_send_json_error('Acción no válida.');
        }

        wp_send_json_success([
            'message' => $message,
            'pending' => MLS_SYNC::pending(),
        ]);
    }

    public static function product()
    {
        self::guard();
        $id = absint($_POST['id'] ?? 0);
        $do = sanitize_key($_POST['do'] ?? '');
        if (!$id || !current_user_can('edit_post', $id)) {
            wp_send_json_error('Producto no válido.');
        }
        if ('unlink' === $do) {
            MLS_PRODUCT::unlink($id);
            MLS_LOG_SERVICE::add('desvincular', ['product' => $id]);
            wp_send_json_success(['message' => 'Producto desvinculado. La publicación sigue en MercadoLibre.']);
        }
        self::ready();

        switch ($do) {
            case 'sync':
                MLS_PRODUCT::patch($id, ['enabled' => 1]);
                $result = MLS_PRODUCT::sync($id);
                break;
            case 'pause':
                $result = MLS_PRODUCT::changeStatus($id, 'paused');
                break;
            case 'activate':
                MLS_PRODUCT::patch($id, ['enabled' => 1]);
                $result = MLS_PRODUCT::changeStatus($id, 'active');
                break;
            case 'link':
                $result = MLS_PRODUCT::link($id, sanitize_text_field(wp_unslash($_POST['item'] ?? '')));
                break;
            default:
                wp_send_json_error('Acción no válida.');
        }
        if (!$result['ok']) {
            wp_send_json_error($result['message']);
        }
        wp_send_json_success(['message' => $result['message']]);
    }

    public static function predict()
    {
        self::guard();
        self::ready();
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        if ('' === $title) {
            $product = wc_get_product(absint($_POST['id'] ?? 0));
            $title = $product ? MLS_PRODUCT::title($product) : '';
        }
        if ('' === $title) {
            wp_send_json_error('El producto no tiene título.');
        }
        $rows = MLS_API::predictCategory($title, 5);
        if (!$rows) {
            wp_send_json_error('MercadoLibre no sugirió categorías para ese título.');
        }
        wp_send_json_success(['categories' => $rows]);
    }

    public static function required()
    {
        self::guard();
        self::ready();
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $rows = MLS_API::requiredAttributes($category);
        if (null === $rows) {
            wp_send_json_error('No se pudieron leer los atributos de la categoría ' . $category . '.');
        }
        wp_send_json_success(['attributes' => $rows]);
    }
}

MLS_API_PRODUCTS::init();
