<?php

use franciscoblancojn\wordpress_utils\FWUPage;
use franciscoblancojn\wordpress_utils\FWURespond;

echo FWUPage::css();

// Una pestaña por archivo en src/page/sections/<key>.php. 'form' => true: va dentro del formulario de ajustes.
$TAGS = [
    ['key' => 'conexion', 'title' => 'Conexión', 'form' => true],
    ['key' => 'publicacion', 'title' => 'Publicación', 'form' => true],
    ['key' => 'registro', 'title' => 'Registro', 'form' => false],
];
$keys = wp_list_pluck($TAGS, 'key');
$defaultTag = in_array($_GET['tab'] ?? '', $keys, true) ? $_GET['tab'] : $TAGS[0]['key']; // phpcs:ignore WordPress.Security.NonceVerification
$config = (new MLS_USE_DATA_CONFIG())->get();
$respond = get_transient('mls_respond');

?>
<div id="page-<?= esc_attr(MLS_KEY) ?>" class="wrap mls-page">
    <h1>MercadoLibre Sync <span class="mls-version">v<?= esc_html(MLS_get_version()) ?></span></h1>
    <p class="mls-muted">Conecta la tienda con MercadoLibre. Los productos se eligen en <a href="<?= esc_url(admin_url('admin.php?page=' . MLS_PRODUCTS_PAGE)) ?>">MercadoLibre → Productos</a> o en la ficha de cada producto.</p>
    <?php
    if ($respond) {
        delete_transient('mls_respond');
        FWURespond::render($respond);
    }
    ?>
    <?php FWUPage::tabs($TAGS, $defaultTag); ?>
    <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" class="mls-form">
        <input type="hidden" name="action" value="<?= esc_attr(MLS_API_SETTINGS::ACTION) ?>">
        <input type="hidden" name="mls_tab" value="<?= esc_attr($defaultTag) ?>" id="mls-tab">
        <?php wp_nonce_field(MLS_API_SETTINGS::ACTION); ?>
        <?php foreach ($TAGS as $value) : ?>
            <?php if ($value['form']) : ?>
                <div class="tab-content <?= $value['key'] === $defaultTag ? 'nav-tab-active' : '' ?>" id="<?= esc_attr($value['key']) ?>">
                    <?php require MLS_DIR . 'src/page/sections/' . $value['key'] . '.php'; ?>
                    <?php submit_button('Guardar cambios'); ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </form>
    <?php foreach ($TAGS as $value) : ?>
        <?php if (!$value['form']) : ?>
            <div class="tab-content <?= $value['key'] === $defaultTag ? 'nav-tab-active' : '' ?>" id="<?= esc_attr($value['key']) ?>">
                <?php require MLS_DIR . 'src/page/sections/' . $value['key'] . '.php'; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php

echo FWUPage::js(MLS_KEY);
