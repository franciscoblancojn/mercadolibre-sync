<?php

/**
 * Cambios en WooCommerce → cola de sincronización.
 *
 * Aquí solo se encola (MLS_SYNC); la llamada a MercadoLibre la hace Action Scheduler
 * en segundo plano para no bloquear el guardado ni el checkout.
 */
class MLS_HOOK_PRODUCT
{
    public static function init()
    {
        add_action('woocommerce_new_product', [self::class, 'onSave'], 30, 1);
        add_action('woocommerce_update_product', [self::class, 'onSave'], 30, 1);
        add_action('woocommerce_update_product_variation', [self::class, 'onSave'], 30, 1);
        add_action('woocommerce_product_set_stock', [self::class, 'onStock'], 30, 1);
        add_action('woocommerce_variation_set_stock', [self::class, 'onStock'], 30, 1);
        add_action('trashed_post', [self::class, 'onTrash'], 10, 1);
        add_action('untrashed_post', [self::class, 'onTrash'], 10, 1);
        add_action('before_delete_post', [self::class, 'onDelete'], 10, 1);

        // Acciones programadas (Action Scheduler).
        add_action(MLS_ACTION_SYNC, [MLS_SYNC::class, 'run'], 10, 1);
        add_action(MLS_ACTION_CLOSE, [MLS_SYNC::class, 'runClose'], 10, 1);
    }

    /**
     * ¿El producto (o su padre) está marcado o vinculado con MercadoLibre?
     */
    private static function tracked($id)
    {
        $meta = MLS_PRODUCT::meta(MLS_PRODUCT::parentId($id));

        return $meta['enabled'] || '' !== $meta['item_id'];
    }

    public static function onSave($product_id)
    {
        $config = MLS_PRODUCT::config();
        if (empty($config['sync_on_save']) || !MLS_USE_DATA_CONFIG::ready($config) || !self::tracked($product_id)) {
            return;
        }
        MLS_SYNC::enqueue([$product_id], 'guardar');
    }

    /**
     * Stock cambiado por un pedido, un reembolso o la edición rápida.
     *
     * @param WC_Product $product
     */
    public static function onStock($product)
    {
        $config = MLS_PRODUCT::config();
        if (!$product || empty($config['sync_stock']) || !MLS_USE_DATA_CONFIG::ready($config) || !self::tracked($product->get_id())) {
            return;
        }
        MLS_SYNC::enqueue([$product->get_id()], 'stock');
    }

    /**
     * Papelera: la publicación se pausa (sync() ve que no está publicado); al restaurar se reactiva.
     */
    public static function onTrash($post_id)
    {
        if ('product' !== get_post_type($post_id) || !MLS_USE_DATA_CONFIG::ready() || '' === MLS_PRODUCT::meta($post_id)['item_id']) {
            return;
        }
        MLS_SYNC::enqueue([$post_id], 'papelera');
    }

    public static function onDelete($post_id)
    {
        if ('product' !== get_post_type($post_id)) {
            return;
        }
        $config = MLS_PRODUCT::config();
        $item_id = MLS_PRODUCT::meta($post_id)['item_id'];
        if ('' === $item_id || empty($config['close_on_delete']) || !MLS_USE_DATA_CONFIG::ready($config)) {
            return;
        }
        MLS_SYNC::enqueueClose($item_id);
        MLS_LOG_SERVICE::add('borrar_producto', ['product' => $post_id, 'item' => $item_id]);
    }
}

MLS_HOOK_PRODUCT::init();
