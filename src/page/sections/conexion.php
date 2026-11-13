<?php

$account = MLS_USE_DATA_TOKEN::account();
$connected = MLS_USE_DATA_TOKEN::connected();
$token = (new MLS_USE_DATA_TOKEN())->get();
$has_access = '' !== $token['access_token'];
$redirect = MLS_API::redirectUri();
$connect_url = wp_nonce_url(admin_url('admin-post.php?action=' . MLS_API_SETTINGS::CONNECT), MLS_API_SETTINGS::CONNECT);
$disconnect_url = wp_nonce_url(admin_url('admin-post.php?action=' . MLS_API_SETTINGS::DISCONNECT), MLS_API_SETTINGS::DISCONNECT);

?>
<p class="description">
    MercadoLibre usa <strong>OAuth 2.0</strong>: crea una aplicación en el
    <a href="https://developers.mercadolibre.com/devcenter" target="_blank" rel="noopener noreferrer">DevCenter de MercadoLibre</a>
    con la cuenta vendedora, copia aquí su <strong>App ID</strong> y su <strong>Secret Key</strong>, guarda y pulsa
    «Conectar con MercadoLibre». El token se renueva solo cada 6 horas.
</p>

<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><label for="mls-site">País</label></th>
        <td>
            <select id="mls-site" name="mls[site_id]">
                <?php foreach (MLS_USE_DATA_CONFIG::SITES as $code => $site) : ?>
                    <option value="<?= esc_attr($code) ?>" <?= selected($config['site_id'], $code, false) ?>><?= esc_html($site['label'] . ' — ' . $site['currency']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">Define el dominio de autorización y la moneda de las publicaciones.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Redirect URI</th>
        <td>
            <input type="text" readonly class="large-text code mls-copy" value="<?= esc_attr($redirect) ?>" onclick="this.select()">
            <p class="description">Regístrala tal cual en la aplicación (campo «URI de redirect»). MercadoLibre exige HTTPS.</p>
            <?php if (0 !== strpos($redirect, 'https://')) : ?>
                <p class="mls-error">El sitio no usa HTTPS: MercadoLibre no aceptará esta URL.</p>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-client-id">App ID (client_id)</label></th>
        <td>
            <input type="text" id="mls-client-id" name="mls[client_id]" value="<?= esc_attr($config['client_id']) ?>" class="regular-text" autocomplete="off">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="mls-client-secret">Secret Key (client_secret)</label></th>
        <td>
            <input type="password" id="mls-client-secret" name="mls[client_secret]" value="" class="regular-text" autocomplete="new-password" placeholder="<?= $config['client_secret'] ? 'Guardada (deja en blanco para conservarla)' : 'Secret Key de la aplicación' ?>">
            <p class="description">No se vuelve a mostrar. Deja el campo vacío para conservar la guardada.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">PKCE</th>
        <td>
            <label><input type="checkbox" name="mls[use_pkce]" value="1" <?= checked($config['use_pkce'], 1, false) ?>> Mi aplicación tiene activado PKCE</label>
            <p class="description">Márcalo solo si activaste PKCE al crear la aplicación (si está activo es obligatorio).</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Cuenta</th>
        <td>
            <?php if ($connected) : ?>
                <p>
                    <span class="mls-badge mls-badge--<?= $has_access ? 'active' : 'error' ?>"><?= $has_access ? 'Conectada' : 'Token vencido' ?></span>
                    <strong><?= esc_html($account['nickname'] ? $account['nickname'] : 'Usuario ' . $account['user_id']) ?></strong>
                    <span class="mls-muted">ID <?= esc_html($account['user_id']) ?><?= $account['site_id'] ? ' · ' . esc_html($account['site_id']) : '' ?></span>
                </p>
                <p class="mls-muted">
                    Conectada el <?= esc_html($account['connected_at']) ?>.
                    <?php if ($has_access && $account['expires_at']) : ?>
                        El token vence el <?= esc_html(date_i18n('Y-m-d H:i', (int) $account['expires_at'] + (int) (get_option('gmt_offset') * HOUR_IN_SECONDS))) ?> y se renueva solo.
                    <?php endif; ?>
                </p>
                <?php if (!$has_access) : ?>
                    <p class="mls-error">No se pudo renovar el token (permiso revocado o refresh token vencido). Vuelve a conectar.</p>
                <?php endif; ?>
                <p>
                    <button type="button" class="button" id="mls-test">Probar conexión</button>
                    <a class="button" href="<?= esc_url($connect_url) ?>">Reconectar</a>
                    <a class="button-link mls-danger" href="<?= esc_url($disconnect_url) ?>" onclick="return confirm('¿Desconectar la cuenta? Se borran los tokens; los productos conservan su vínculo.');">Desconectar</a>
                </p>
            <?php elseif (MLS_USE_DATA_CONFIG::hasApp($config)) : ?>
                <p><a class="button button-primary" href="<?= esc_url($connect_url) ?>">Conectar con MercadoLibre</a></p>
                <p class="description">Inicia sesión con la cuenta vendedora y acepta los permisos. Volverás a esta página.</p>
            <?php else : ?>
                <p class="description">Guarda el App ID y la Secret Key para poder conectar la cuenta.</p>
            <?php endif; ?>
            <p id="mls-connection-message" class="mls-inline-message"></p>
        </td>
    </tr>
</table>
