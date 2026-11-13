<?php

/**
 * Caja «MercadoLibre» en la edición del producto.
 *
 * Marca si el producto se publica, guarda los ajustes por producto (categoría, tipo de
 * publicación, atributos, mapeo de variaciones) y muestra el vínculo con la publicación.
 * Los botones (publicar ahora, pausar, vincular…) van por AJAX a MLS_API_PRODUCTS.
 */
class MLS_HOOK_METABOX
{
    const NONCE = 'mls_metabox_nonce';

    public static function init()
    {
        add_action('add_meta_boxes', [self::class, 'add']);
        add_action('save_post_product', [self::class, 'save'], 20, 1);
    }

    public static function add()
    {
        add_meta_box('mls-mercadolibre', 'MercadoLibre', [self::class, 'render'], 'product', 'side', 'default');
    }

    /**
     * @param WP_Post $post
     */
    public static function render($post)
    {
        $product = wc_get_product($post->ID);
        if (!$product) {
            echo '<p>Guarda el producto para configurarlo.</p>';
            return;
        }
        $config = MLS_PRODUCT::config();
        $ready = MLS_USE_DATA_CONFIG::ready($config);
        $summary = MLS_PRODUCT::summary($post->ID);
        $meta = $summary['meta'];
        $supported = in_array($product->get_type(), MLS_PRODUCT::TYPES, true);
        $settings_url = admin_url('admin.php?page=' . MLS_KEY);
        wp_nonce_field(self::NONCE, self::NONCE);
        ?>
        <div class="mls-metabox" data-id="<?= (int) $post->ID ?>">
            <?php if (!$ready) : ?>
                <p class="mls-error">La cuenta de MercadoLibre no está conectada o la sincronización está desactivada. <a href="<?= esc_url($settings_url) ?>">Configuración</a>.</p>
            <?php endif; ?>
            <?php if (!$supported) : ?>
                <p class="mls-error">Solo se publican productos simples y variables.</p>
            <?php endif; ?>

            <p>
                <label>
                    <input type="checkbox" name="mls[enabled]" value="1" <?= checked($meta['enabled'], 1, false) ?>>
                    <strong>Publicar en MercadoLibre</strong>
                </label>
            </p>

            <p class="mls-state">
                Estado: <span class="mls-badge mls-badge--<?= esc_attr($summary['status'] ? $summary['status'] : 'none') ?>"><?= esc_html($summary['label']) ?></span>
            </p>
            <?php if ('' !== $meta['item_id']) : ?>
                <p>
                    Publicación: <code><?= esc_html($meta['item_id']) ?></code>
                    <?php if ($meta['permalink']) : ?>
                        · <a href="<?= esc_url($meta['permalink']) ?>" target="_blank" rel="noopener noreferrer">Ver</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if ($meta['last_sync']) : ?>
                <p class="mls-muted">Última sincronización: <?= esc_html($meta['last_sync']) ?></p>
            <?php endif; ?>
            <?php if ($meta['last_error']) : ?>
                <p class="mls-error"><?= esc_html($meta['last_error']) ?></p>
            <?php endif; ?>

            <p>
                <label for="mls-category"><strong>Categoría</strong></label>
                <span class="mls-row">
                    <input type="text" id="mls-category" name="mls[category]" value="<?= esc_attr($meta['category']) ?>" placeholder="<?= esc_attr($config['default_category'] ? $config['default_category'] : 'Ej. MCO1234') ?>">
                    <button type="button" class="button mls-predict" <?= disabled($ready, false, false) ?>>Sugerir</button>
                </span>
                <span class="mls-muted">Vacío = la de la configuración<?= !empty($config['auto_category']) ? ' o la que sugiera MercadoLibre por el título' : '' ?>. No se puede cambiar después de publicar.</span>
            </p>
            <div class="mls-predictions"></div>
            <p>
                <button type="button" class="button button-small mls-required" <?= disabled($ready, false, false) ?>>Ver atributos obligatorios</button>
            </p>
            <div class="mls-required-list"></div>

            <div class="mls-grid">
                <p>
                    <label for="mls-listing"><strong>Tipo</strong></label>
                    <select id="mls-listing" name="mls[listing_type]" class="widefat">
                        <option value="">Por defecto</option>
                        <?php foreach (MLS_USE_DATA_CONFIG::LISTING_TYPES as $value => $label) : ?>
                            <option value="<?= esc_attr($value) ?>" <?= selected($meta['listing_type'], $value, false) ?>><?= esc_html($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="mls-condition"><strong>Condición</strong></label>
                    <select id="mls-condition" name="mls[condition]" class="widefat">
                        <option value="">Por defecto</option>
                        <?php foreach (MLS_USE_DATA_CONFIG::CONDITIONS as $value => $label) : ?>
                            <option value="<?= esc_attr($value) ?>" <?= selected($meta['condition'], $value, false) ?>><?= esc_html($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </div>

            <p>
                <label for="mls-gtin"><strong>Código universal (GTIN/EAN)</strong></label>
                <input type="text" id="mls-gtin" class="widefat" name="mls[gtin]" value="<?= esc_attr($meta['gtin']) ?>" placeholder="Opcional">
            </p>

            <p>
                <label for="mls-attributes"><strong>Atributos</strong></label>
                <textarea id="mls-attributes" class="widefat" name="mls[attributes]" rows="3" placeholder="BRAND=Marca&#10;MODEL=Modelo"><?= esc_textarea($meta['attributes']) ?></textarea>
                <span class="mls-muted">Una línea por atributo: <code>ID=valor</code>. Se suman a los de la configuración; SELLER_SKU se envía solo.</span>
            </p>

            <?php if ($product->is_type('variable')) : ?>
                <p>
                    <label for="mls-variation-map"><strong>Atributos de variación</strong></label>
                    <textarea id="mls-variation-map" class="widefat" name="mls[variation_map]" rows="2" placeholder="pa_color=COLOR&#10;pa_talla=SIZE"><?= esc_textarea($meta['variation_map']) ?></textarea>
                    <span class="mls-muted">Atributo de WooCommerce = ID en MercadoLibre. Sin mapeo se envía con su nombre.</span>
                </p>
            <?php endif; ?>

            <?php if ('' === $meta['item_id']) : ?>
                <p>
                    <label for="mls-link"><strong>Vincular publicación existente</strong></label>
                    <span class="mls-row">
                        <input type="text" id="mls-link" placeholder="<?= esc_attr(MLS_USE_DATA_CONFIG::site($config)['id']) ?>123456789">
                        <button type="button" class="button mls-action" data-do="link" <?= disabled($ready, false, false) ?>>Vincular</button>
                    </span>
                </p>
            <?php endif; ?>

            <p class="mls-muted">Al guardar el producto, los cambios se envían en segundo plano.</p>

            <?php if ($ready && $supported) : ?>
                <p class="mls-actions">
                    <button type="button" class="button button-primary mls-action" data-do="sync">
                        <?= '' === $meta['item_id'] ? 'Publicar ahora' : 'Actualizar ahora' ?>
                    </button>
                    <?php if ('' !== $meta['item_id']) : ?>
                        <?php if ('paused' === $meta['status']) : ?>
                            <button type="button" class="button mls-action" data-do="activate">Activar</button>
                        <?php elseif ('active' === $meta['status']) : ?>
                            <button type="button" class="button mls-action" data-do="pause">Pausar</button>
                        <?php endif; ?>
                        <button type="button" class="button-link mls-action mls-danger" data-do="unlink">Desvincular</button>
                    <?php endif; ?>
                </p>
                <p class="mls-message"></p>
                <p class="mls-muted">«Publicar ahora» usa los datos guardados: guarda el producto antes si cambiaste algo.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function save($post_id)
    {
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE)) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
            return;
        }
        $input = wp_unslash($_POST['mls'] ?? []);
        if (!is_array($input)) {
            return;
        }
        $before = MLS_PRODUCT::meta($post_id);
        $listing = sanitize_key($input['listing_type'] ?? '');
        $condition = sanitize_key($input['condition'] ?? '');
        $enabled = empty($input['enabled']) ? 0 : 1;

        MLS_PRODUCT::patch($post_id, [
            'enabled' => $enabled,
            'category' => strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) ($input['category'] ?? ''))),
            'listing_type' => isset(MLS_USE_DATA_CONFIG::LISTING_TYPES[$listing]) ? $listing : '',
            'condition' => isset(MLS_USE_DATA_CONFIG::CONDITIONS[$condition]) ? $condition : '',
            'gtin' => preg_replace('/[^0-9A-Za-z]/', '', (string) ($input['gtin'] ?? '')),
            'attributes' => sanitize_textarea_field($input['attributes'] ?? ''),
            'variation_map' => sanitize_textarea_field($input['variation_map'] ?? ''),
        ]);

        // WooCommerce ya disparó woocommerce_update_product con el meta anterior:
        // aquí se encolan los cambios que ese hook no pudo ver (marcar / desmarcar).
        $config = MLS_PRODUCT::config();
        if (!MLS_USE_DATA_CONFIG::ready($config)) {
            return;
        }
        $changed = $enabled !== (int) $before['enabled'];
        if ($changed || ($enabled && !empty($config['sync_on_save']))) {
            if ($enabled || '' !== $before['item_id']) {
                MLS_SYNC::enqueue([$post_id], 'ficha');
            }
        }
    }
}

MLS_HOOK_METABOX::init();
