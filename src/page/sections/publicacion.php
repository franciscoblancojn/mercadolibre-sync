<?php

$site = MLS_USE_DATA_CONFIG::site($config);

?>
<h2>Sincronización</h2>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row">Activar</th>
        <td>
            <label><input type="checkbox" name="mls[enabled]" value="1" <?= checked($config['enabled'], 1, false) ?>> Activar la sincronización con MercadoLibre</label>
            <p class="description">Desactivada, no se envía nada (la conexión y los vínculos se conservan).</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Automática</th>
        <td>
            <label><input type="checkbox" name="mls[sync_on_save]" value="1" <?= checked($config['sync_on_save'], 1, false) ?>> Actualizar la publicación al guardar el producto (precio, stock, título, imágenes, descripción, atributos)</label><br>
            <label><input type="checkbox" name="mls[sync_stock]" value="1" <?= checked($config['sync_stock'], 1, false) ?>> Actualizar el stock cuando cambia por pedidos o reembolsos</label><br>
            <label><input type="checkbox" name="mls[pause_unpublished]" value="1" <?= checked($config['pause_unpublished'], 1, false) ?>> Pausar la publicación si el producto deja de estar publicado en la tienda (borrador, privado, papelera)</label><br>
            <label><input type="checkbox" name="mls[close_on_delete]" value="1" <?= checked($config['close_on_delete'], 1, false) ?>> Finalizar la publicación al borrar el producto definitivamente</label>
            <p class="description">Los envíos se hacen en segundo plano con las acciones programadas de WooCommerce (grupo <code><?= esc_html(MLS_GROUP) ?></code>).</p>
        </td>
    </tr>
</table>

<h2>Valores por defecto</h2>
<p class="description">Cada producto puede cambiarlos en su caja «MercadoLibre».</p>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><label for="mls-listing-type">Tipo de publicación</label></th>
        <td>
            <select id="mls-listing-type" name="mls[listing_type]">
                <?php foreach (MLS_USE_DATA_CONFIG::LISTING_TYPES as $value => $label) : ?>
                    <option value="<?= esc_attr($value) ?>" <?= selected($config['listing_type'], $value, false) ?>><?= esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-condition-default">Condición</label></th>
        <td>
            <select id="mls-condition-default" name="mls[condition]">
                <?php foreach (MLS_USE_DATA_CONFIG::CONDITIONS as $value => $label) : ?>
                    <option value="<?= esc_attr($value) ?>" <?= selected($config['condition'], $value, false) ?>><?= esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-default-category">Categoría</label></th>
        <td>
            <input type="text" id="mls-default-category" name="mls[default_category]" value="<?= esc_attr($config['default_category']) ?>" class="regular-text" placeholder="Ej. <?= esc_attr($site['id']) ?>1234">
            <p><label><input type="checkbox" name="mls[auto_category]" value="1" <?= checked($config['auto_category'], 1, false) ?>> Si el producto no tiene categoría, usar la que sugiera MercadoLibre por el título</label></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-markup">Ajuste de precio (%)</label></th>
        <td>
            <input type="text" id="mls-markup" name="mls[price_markup]" value="<?= esc_attr($config['price_markup']) ?>" class="small-text">
            <p class="description">Se aplica al precio activo de WooCommerce (con oferta). Ej. <code>12</code> para cubrir la comisión; <code>0</code> = mismo precio. Moneda: <?= esc_html($site['currency']) ?>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-fallback-stock">Stock si no se gestiona</label></th>
        <td>
            <input type="number" min="0" step="1" id="mls-fallback-stock" name="mls[fallback_stock]" value="<?= esc_attr($config['fallback_stock']) ?>" class="small-text">
            <p class="description">Cantidad publicada cuando el producto está «En existencia» sin gestionar inventario.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-max-pictures">Imágenes</label></th>
        <td>
            <input type="number" min="1" max="12" id="mls-max-pictures" name="mls[max_pictures]" value="<?= esc_attr($config['max_pictures']) ?>" class="small-text">
            <p class="description">Máximo de imágenes (principal + galería). Deben ser públicas: MercadoLibre las descarga desde el sitio.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Contenido</th>
        <td>
            <label><input type="checkbox" name="mls[send_description]" value="1" <?= checked($config['send_description'], 1, false) ?>> Enviar la descripción (como texto plano)</label><br>
            <label><input type="checkbox" name="mls[family_name]" value="1" <?= checked($config['family_name'], 1, false) ?>> Usar <code>family_name</code> en lugar de <code>title</code> (cuentas con el modelo «User Products»)</label>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-default-attributes">Atributos por defecto</label></th>
        <td>
            <textarea id="mls-default-attributes" name="mls[default_attributes]" rows="4" class="large-text code" placeholder="BRAND=Genérica&#10;EMPTY_GTIN_REASON=El producto no tiene código registrado"><?= esc_textarea($config['default_attributes']) ?></textarea>
            <p class="description">Una línea por atributo: <code>ID=valor</code>. Se envían en todos los productos (el producto puede sobrescribirlos). Los obligatorios dependen de la categoría: consúltalos con «Ver atributos obligatorios» en la ficha.</p>
        </td>
    </tr>
</table>
