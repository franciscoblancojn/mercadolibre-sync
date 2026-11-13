<?php

/**
 * Cola de sincronización en segundo plano.
 *
 * Usa Action Scheduler (incluido en WooCommerce, se ve en Herramientas → Acciones
 * programadas, grupo MLS_GROUP). Un producto solo tiene una acción pendiente a la vez:
 * varios guardados seguidos se resuelven en una sola llamada a MercadoLibre.
 */
class MLS_SYNC
{
    private static function hasPending($hook, $args)
    {
        if (function_exists('as_get_scheduled_actions')) {
            return (bool) as_get_scheduled_actions([
                'hook' => $hook,
                'args' => $args,
                'group' => MLS_GROUP,
                'status' => 'pending',
                'per_page' => 1,
            ], 'ids');
        }

        return (bool) wp_next_scheduled($hook, $args);
    }

    private static function schedule($hook, $args)
    {
        if (self::hasPending($hook, $args)) {
            return;
        }
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action($hook, $args, MLS_GROUP);
        } else {
            wp_schedule_single_event(time(), $hook, $args);
        }
    }

    /**
     * Encola la sincronización de productos (las variaciones se resuelven a su padre).
     *
     * @param int[]  $ids
     * @param string $reason Solo para el log.
     * @return int Productos encolados.
     */
    public static function enqueue($ids, $reason = 'manual')
    {
        $done = [];
        foreach ((array) $ids as $id) {
            $id = MLS_PRODUCT::parentId((int) $id);
            if (!$id || isset($done[$id])) {
                continue;
            }
            $done[$id] = true;
            MLS_PRODUCT::patch($id, ['queued' => 1]);
            self::schedule(MLS_ACTION_SYNC, [$id]);
        }
        if ($done) {
            MLS_LOG_SERVICE::add('encolar', ['reason' => $reason, 'ids' => array_keys($done)]);
        }

        return count($done);
    }

    /**
     * Finaliza una publicación cuyo producto se borró (ya no hay meta donde guardar nada).
     */
    public static function enqueueClose($item_id)
    {
        self::schedule(MLS_ACTION_CLOSE, [(string) $item_id]);
    }

    public static function pending()
    {
        if (!function_exists('as_get_scheduled_actions')) {
            return 0;
        }

        return count(as_get_scheduled_actions([
            'hook' => MLS_ACTION_SYNC,
            'group' => MLS_GROUP,
            'status' => 'pending',
            'per_page' => -1,
        ], 'ids'));
    }

    /**
     * Ejecuta la acción programada de un producto.
     */
    public static function run($product_id)
    {
        $result = MLS_PRODUCT::sync((int) $product_id);
        if (!$result['ok'] && function_exists('as_enqueue_async_action')) {
            // Action Scheduler marca la acción como fallida y guarda el mensaje.
            throw new Exception($result['message']);
        }
    }

    public static function runClose($item_id)
    {
        $result = MLS_API::setStatus((string) $item_id, 'closed');
        MLS_LOG_SERVICE::add('finalizar', ['item' => $item_id, 'ok' => $result['ok'] ? 1 : 0, 'error' => $result['error']]);
    }
}
