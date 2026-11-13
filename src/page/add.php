<?php

/**
 * Menú «MercadoLibre» con dos subpáginas: Configuración (MLS_KEY) y Productos (MLS_PRODUCTS_PAGE).
 */
add_action('admin_menu', function () {
    add_menu_page(
        'MercadoLibre Sync',
        'MercadoLibre',
        MLS_CAPABILITY,
        MLS_KEY,
        'MLS_PAGE_VIEW',
        'dashicons-store',
        58
    );
    add_submenu_page(MLS_KEY, 'MercadoLibre — Configuración', 'Configuración', MLS_CAPABILITY, MLS_KEY, 'MLS_PAGE_VIEW');
    add_submenu_page(MLS_KEY, 'MercadoLibre — Productos', 'Productos', MLS_CAPABILITY, MLS_PRODUCTS_PAGE, 'MLS_PAGE_PRODUCTS_VIEW');
});

function MLS_PAGE_VIEW()
{
    require_once MLS_DIR . 'src/page/page.php';
}

function MLS_PAGE_PRODUCTS_VIEW()
{
    require_once MLS_DIR . 'src/page/products.php';
}

// CSS y JS del admin solo en las subpáginas del plugin y en la edición de productos.
add_action('admin_enqueue_scripts', function ($hook) {
    $page = sanitize_text_field(wp_unslash($_GET['page'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
    $on_plugin = in_array($page, [MLS_KEY, MLS_PRODUCTS_PAGE], true);
    $on_product = false;
    if (in_array($hook, ['post.php', 'post-new.php'], true)) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $on_product = $screen && 'product' === $screen->post_type;
    }
    if (!$on_plugin && !$on_product) {
        return;
    }
    $css = MLS_DIR . 'src/css/admin.css';
    $js = MLS_DIR . 'src/js/admin.js';
    wp_enqueue_style('mls-admin', MLS_URL . 'src/css/admin.css', [], filemtime($css));
    wp_enqueue_script('mls-admin', MLS_URL . 'src/js/admin.js', ['jquery'], filemtime($js), true);
    wp_localize_script('mls-admin', 'MLS_ADMIN', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce_settings' => wp_create_nonce(MLS_API_SETTINGS::NONCE),
        'nonce_products' => wp_create_nonce(MLS_API_PRODUCTS::NONCE),
        'processing' => 'Procesando…',
        'error' => 'No se pudo completar la operación.',
        'select' => 'Selecciona al menos un producto o marca «Aplicar a todos los resultados del filtro».',
        'confirm_pause' => '¿Pausar en MercadoLibre las publicaciones seleccionadas?',
        'confirm_unlink' => '¿Desvincular? La publicación seguirá en MercadoLibre pero dejará de sincronizarse con este producto.',
        'no_category' => 'Escribe o sugiere una categoría primero.',
        'required_title' => 'Atributos obligatorios (agrégalos como ID=valor):',
        'none_required' => 'La categoría no tiene atributos obligatorios.',
    ]);
});
