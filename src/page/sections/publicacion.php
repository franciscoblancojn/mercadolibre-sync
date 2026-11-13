<?php

$site = MLS_USE_DATA_CONFIG::site($config);

?>
<h2>Sincronización</h2>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row">Activar<?php MLS_PAGE_TIP('Interruptor general. Desmárcalo para detener todos los envíos a MercadoLibre (por ejemplo, mientras haces cambios masivos de precios) sin perder la conexión ni los vínculos de productos. No hay que conseguir ningún dato.'); ?></th>
        <td>
            <label><input type="checkbox" name="mls[enabled]" value="1" <?= checked($config['enabled'], 1, false) ?>> Activar la sincronización con MercadoLibre</label>
            <p class="description">Desactivada, no se envía nada (la conexión y los vínculos se conservan).</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Automática<?php MLS_PAGE_TIP('Qué cambios de la tienda se envían solos:<ol><li><strong>Al guardar</strong>: precio, stock, título, imágenes, descripción y atributos de los productos marcados.</li><li><strong>Stock por pedidos</strong>: descuenta en MercadoLibre lo vendido en la tienda (evita vender sin existencias).</li><li><strong>Pausar</strong>: si el producto pasa a borrador, privado o papelera.</li><li><strong>Finalizar</strong>: al borrar el producto definitivamente (no se puede deshacer en MercadoLibre).</li></ol>El progreso se ve en WooCommerce → Estado → Acciones programadas.'); ?></th>
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
        <th scope="row"><label for="mls-listing-type">Tipo de publicación</label><?php MLS_PAGE_TIP('Define la comisión y la exposición en MercadoLibre:<ol><li><strong>Clásica</strong>: comisión menor, sin cuotas sin interés.</li><li><strong>Premium</strong>: comisión mayor, cuotas sin interés y más exposición.</li><li><strong>Gratuita</strong>: sin comisión, poca exposición y cupo limitado.</li></ol>Consulta los porcentajes en tu cuenta: Mi cuenta → Ventas → «Costos de vender» (o la página «Tarifas» de MercadoLibre de tu país).'); ?></th>
        <td>
            <select id="mls-listing-type" name="mls[listing_type]">
                <?php foreach (MLS_USE_DATA_CONFIG::LISTING_TYPES as $value => $label) : ?>
                    <option value="<?= esc_attr($value) ?>" <?= selected($config['listing_type'], $value, false) ?>><?= esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-condition-default">Condición</label><?php MLS_PAGE_TIP('Estado del artículo que verá el comprador. Usa «Nuevo» salvo que vendas productos usados o reacondicionados; cada producto puede cambiarlo en su ficha.'); ?></th>
        <td>
            <select id="mls-condition-default" name="mls[condition]">
                <?php foreach (MLS_USE_DATA_CONFIG::CONDITIONS as $value => $label) : ?>
                    <option value="<?= esc_attr($value) ?>" <?= selected($config['condition'], $value, false) ?>><?= esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-default-category">Categoría</label><?php MLS_PAGE_TIP('ID de una categoría <strong>hoja</strong> (la última del árbol), por ejemplo <code>MCO1234</code>. Formas de conseguirlo:<ol><li>En la ficha de un producto, botón <strong>«Sugerir»</strong> (lo predice MercadoLibre por el título).</li><li>Navegando el árbol en <code>https://api.mercadolibre.com/sites/&lt;PAÍS&gt;/categories</code> y luego <code>/categories/&lt;ID&gt;</code> hasta una sin <code>children_categories</code>.</li></ol>Lo más práctico es dejarlo vacío y marcar la predicción automática.'); ?></th>
        <td>
            <input type="text" id="mls-default-category" name="mls[default_category]" value="<?= esc_attr($config['default_category']) ?>" class="regular-text" placeholder="Ej. <?= esc_attr($site['id']) ?>1234">
            <p><label><input type="checkbox" name="mls[auto_category]" value="1" <?= checked($config['auto_category'], 1, false) ?>> Si el producto no tiene categoría, usar la que sugiera MercadoLibre por el título</label></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-markup">Ajuste de precio (%)</label><?php MLS_PAGE_TIP('Porcentaje que se suma (o resta, con negativo) al precio de la tienda. Sirve para cubrir la comisión de MercadoLibre.<br>Para calcularlo, mira la comisión de tu categoría y tipo de publicación en Mi cuenta → Ventas → «Costos de vender» y súmale el costo de envío si lo asumes. Ej.: comisión 13% → <code>15</code>.'); ?></th>
        <td>
            <input type="text" id="mls-markup" name="mls[price_markup]" value="<?= esc_attr($config['price_markup']) ?>" class="small-text">
            <p class="description">Se aplica al precio activo de WooCommerce (con oferta). Ej. <code>12</code> para cubrir la comisión; <code>0</code> = mismo precio. Moneda: <?= esc_html($site['currency']) ?>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-fallback-stock">Stock si no se gestiona</label><?php MLS_PAGE_TIP('Solo para productos sin «Gestionar inventario» en WooCommerce (pestaña Inventario del producto). Como la tienda no tiene una cantidad real, se publica este número. Si el producto está «Agotado» se envía 0 y MercadoLibre pausa la publicación.<br>Recomendado: activar la gestión de inventario en los productos que publiques.'); ?></th>
        <td>
            <input type="number" min="0" step="1" id="mls-fallback-stock" name="mls[fallback_stock]" value="<?= esc_attr($config['fallback_stock']) ?>" class="small-text">
            <p class="description">Cantidad publicada cuando el producto está «En existencia» sin gestionar inventario.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-max-pictures">Imágenes</label><?php MLS_PAGE_TIP('Se toman de la imagen principal y la galería del producto en WooCommerce (y la imagen de cada variación).<br>MercadoLibre las descarga desde el sitio, así que deben ser públicas. Recomendaciones de MercadoLibre: mínimo 500 px (ideal 1200×1200), fondo blanco en la principal, sin logos ni textos.'); ?></th>
        <td>
            <input type="number" min="1" max="12" id="mls-max-pictures" name="mls[max_pictures]" value="<?= esc_attr($config['max_pictures']) ?>" class="small-text">
            <p class="description">Máximo de imágenes (principal + galería). Deben ser públicas: MercadoLibre las descarga desde el sitio.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Contenido<?php MLS_PAGE_TIP('<strong>Descripción</strong>: se envía la descripción larga del producto (o la corta si no hay) como texto plano; MercadoLibre no admite HTML ni enlaces.<br><strong>family_name</strong>: márcalo solo si al publicar MercadoLibre responde que falta <code>family_name</code> o que <code>title</code> no está permitido (cuentas migradas al modelo «User Products»). Ve el error en la pestaña Registro.'); ?></th>
        <td>
            <label><input type="checkbox" name="mls[send_description]" value="1" <?= checked($config['send_description'], 1, false) ?>> Enviar la descripción (como texto plano)</label><br>
            <label><input type="checkbox" name="mls[family_name]" value="1" <?= checked($config['family_name'], 1, false) ?>> Usar <code>family_name</code> en lugar de <code>title</code> (cuentas con el modelo «User Products»)</label>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-default-attributes">Atributos por defecto</label><?php MLS_PAGE_TIP('Ficha técnica que exige MercadoLibre según la categoría (marca, modelo, código de barras…). Formato <code>ID=valor</code>, una línea por atributo.<br>Para ver qué IDs pide una categoría:<ol><li>En la ficha de un producto, pon la categoría y pulsa <strong>«Ver atributos obligatorios»</strong>.</li><li>O abre <code>https://api.mercadolibre.com/categories/&lt;ID&gt;/attributes</code>.</li></ol>Si no tienes código de barras: <code>EMPTY_GTIN_REASON=El producto no tiene código registrado</code>. Lo que pongas aquí aplica a todos los productos; cada ficha puede sobrescribirlo.'); ?></th>
        <td>
            <textarea id="mls-default-attributes" name="mls[default_attributes]" rows="4" class="large-text code" placeholder="BRAND=Genérica&#10;EMPTY_GTIN_REASON=El producto no tiene código registrado"><?= esc_textarea($config['default_attributes']) ?></textarea>
            <p class="description">Una línea por atributo: <code>ID=valor</code>. Se envían en todos los productos (el producto puede sobrescribirlos). Los obligatorios dependen de la categoría: consúltalos con «Ver atributos obligatorios» en la ficha.</p>
        </td>
    </tr>
</table>
