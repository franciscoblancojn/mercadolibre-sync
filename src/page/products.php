<?php

/**
 * Subpágina «Productos»: lista de WooCommerce con su estado en MercadoLibre y acciones masivas.
 */

$f = MLS_PRODUCT::filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$result = MLS_PRODUCT::page($f);
$ready = MLS_USE_DATA_CONFIG::ready();
$base = ['page' => MLS_PRODUCTS_PAGE];
$build_url = function ($extra) use ($base, $f) {
    return add_query_arg(array_filter(array_merge($base, $f, $extra), function ($v) {
        return '' !== $v && null !== $v && 0 !== $v;
    }), admin_url('admin.php'));
};
$categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
$ml_filters = [
    '' => 'Todos (MercadoLibre)',
    'enabled' => 'Marcados para publicar',
    'linked' => 'Vinculados',
    'not_linked' => 'Sin publicar',
    'active' => 'Activos',
    'paused' => 'Pausados',
    'queued' => 'En cola',
    'error' => 'Con error',
];

?>
<div class="wrap mls-page mls-products-page">
    <h1>MercadoLibre — Productos</h1>
    <p class="description">Selecciona los productos de la tienda que quieres publicar en MercadoLibre. Los variables se publican con sus variaciones. Las acciones se procesan en segundo plano; recarga la página para ver el estado.</p>

    <?php if (!$ready) : ?>
        <div class="notice notice-warning inline"><p>La cuenta de MercadoLibre no está conectada o la sincronización está desactivada. <a href="<?= esc_url(admin_url('admin.php?page=' . MLS_KEY)) ?>">Ir a Configuración</a>.</p></div>
    <?php endif; ?>

    <form method="get" action="<?= esc_url(admin_url('admin.php')) ?>" class="mls-filters">
        <input type="hidden" name="page" value="<?= esc_attr(MLS_PRODUCTS_PAGE) ?>">
        <input type="search" name="s" value="<?= esc_attr($f['s']) ?>" placeholder="Nombre o SKU" class="regular-text">
        <select name="type">
            <option value="">Todos los tipos</option>
            <option value="simple" <?= selected($f['type'], 'simple', false) ?>>Simples</option>
            <option value="variable" <?= selected($f['type'], 'variable', false) ?>>Variables</option>
        </select>
        <select name="cat">
            <option value="0">Todas las categorías</option>
            <?php if (!is_wp_error($categories)) : ?>
                <?php foreach ($categories as $term) : ?>
                    <option value="<?= (int) $term->term_id ?>" <?= selected($f['cat'], (int) $term->term_id, false) ?>><?= esc_html($term->name) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <select name="ml">
            <?php foreach ($ml_filters as $value => $label) : ?>
                <option value="<?= esc_attr($value) ?>" <?= selected($f['ml'], $value, false) ?>><?= esc_html($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="per_page">
            <?php foreach (MLS_PRODUCT::PER_PAGE as $pp) : ?>
                <option value="<?= (int) $pp ?>" <?= selected($f['per_page'], $pp, false) ?>><?= (int) $pp ?> por página</option>
            <?php endforeach; ?>
        </select>
        <?php submit_button('Filtrar', 'secondary', '', false); ?>
        <a href="<?= esc_url(admin_url('admin.php?page=' . MLS_PRODUCTS_PAGE)) ?>" class="button">Limpiar</a>
    </form>

    <div class="mls-bulk-actions">
        <strong>Con la selección:</strong>
        <button type="button" class="button button-primary mls-bulk" data-do="publish" <?= disabled($ready, false, false) ?>>Publicar / actualizar</button>
        <button type="button" class="button mls-bulk" data-do="sync" <?= disabled($ready, false, false) ?>>Solo actualizar vinculados</button>
        <button type="button" class="button mls-bulk" data-do="pause" <?= disabled($ready, false, false) ?>>Pausar</button>
        <button type="button" class="button mls-bulk" data-do="unlink">Desvincular</button>
        <label class="mls-inline">
            <input type="checkbox" id="mls-scope-filtered" value="1">
            Aplicar a los <?= (int) $result['total'] ?> resultados del filtro (no solo a esta página)
        </label>
    </div>
    <p id="mls-products-message" class="mls-inline-message"></p>
    <p class="mls-muted">Resultados: <?= (int) $result['total'] ?> · En cola: <strong class="mls-pending"><?= (int) MLS_SYNC::pending() ?></strong></p>

    <table class="wp-list-table widefat fixed striped mls-products-table">
        <thead>
            <tr>
                <td class="manage-column column-cb check-column"><input type="checkbox" id="mls-check-all"></td>
                <th class="mls-col-name">Producto</th>
                <th>Tipo</th>
                <th>SKU</th>
                <th>Precio / stock</th>
                <th>MercadoLibre</th>
                <th>Última sync</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$result['items']) : ?>
                <tr><td colspan="7">No hay productos con esos filtros.</td></tr>
            <?php endif; ?>
            <?php foreach ($result['items'] as $item) : ?>
                <tr>
                    <th scope="row" class="check-column"><input type="checkbox" class="mls-check" value="<?= (int) $item['id'] ?>"></th>
                    <td>
                        <?php if ($item['image']) : ?>
                            <img src="<?= esc_url($item['image']) ?>" alt="" class="mls-thumb" loading="lazy">
                        <?php endif; ?>
                        <a href="<?= esc_url($item['edit']) ?>"><strong><?= esc_html($item['name']) ?></strong></a>
                        <div class="mls-muted">Tienda: <?= esc_html($item['woo_status']) ?><?= $item['variations'] ? ' · ' . (int) $item['variations'] . ' variaciones' : '' ?></div>
                    </td>
                    <td><?= esc_html($item['type']) ?></td>
                    <td><?= esc_html($item['sku'] ? $item['sku'] : '—') ?></td>
                    <td>
                        <div><?= wp_kses_post($item['price']) ?></div>
                        <div class="mls-muted">Stock: <?= null === $item['stock'] ? esc_html($item['stock_status']) : (int) $item['stock'] ?></div>
                    </td>
                    <td>
                        <span class="mls-badge mls-badge--<?= esc_attr($item['status'] ? $item['status'] : 'none') ?>"><?= esc_html($item['status_label']) ?></span>
                        <?php if ($item['item_id']) : ?>
                            <div>
                                <?php if ($item['permalink']) : ?>
                                    <a href="<?= esc_url($item['permalink']) ?>" target="_blank" rel="noopener noreferrer"><code><?= esc_html($item['item_id']) ?></code></a>
                                <?php else : ?>
                                    <code><?= esc_html($item['item_id']) ?></code>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($item['enabled']) : ?>
                            <div class="mls-muted">Marcado para publicar</div>
                        <?php endif; ?>
                        <?php if ($item['last_error']) : ?>
                            <div class="mls-error"><?= esc_html($item['last_error']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="mls-muted"><?= esc_html($item['last_sync'] ? $item['last_sync'] : '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($result['pages'] > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo wp_kses_post(paginate_links([
                    'base' => $build_url(['paged' => '%#%']),
                    'format' => '',
                    'current' => (int) $f['paged'],
                    'total' => (int) $result['pages'],
                ]));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
