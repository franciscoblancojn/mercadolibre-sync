<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

$groups = class_exists(FWUSystemLog::class)
    ? FWUSystemLog::get(MLS_KEY)
    : json_decode((string) get_option(MLS_LOG_KEY, '[]'), true);
if (!is_array($groups)) {
    $groups = [];
}

$logs = [];
foreach ($groups as $group) {
    if (!is_array($group)) {
        continue;
    }
    foreach ($group as $entry) {
        $logs[] = is_array($entry) ? $entry : ['type' => '', 'data' => $entry];
    }
}
usort($logs, function ($a, $b) {
    return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
});

?>
<p class="description">Últimos <?= (int) MLS_LOG_COUNT ?> eventos: conexión, publicaciones, actualizaciones y errores de la API. Las acciones en segundo plano también se ven en
    <a href="<?= esc_url(admin_url('admin.php?page=wc-status&tab=action-scheduler&s=' . rawurlencode(MLS_ACTION_SYNC))) ?>">WooCommerce → Estado → Acciones programadas</a>.</p>
<?php if (!$logs) : ?>
    <p>No hay registros aún.</p>
<?php else : ?>
    <table class="wp-list-table widefat fixed striped mls-log-table">
        <thead>
            <tr>
                <th class="mls-col-date">Fecha</th>
                <th class="mls-col-type">Tipo</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $entry) : ?>
                <?php $data = $entry['data'] ?? $entry; ?>
                <tr>
                    <td class="mls-muted"><?= esc_html((string) ($entry['time'] ?? '—')) ?></td>
                    <td><code><?= esc_html((string) ($entry['type'] ?? '')) ?></code></td>
                    <td><pre class="mls-pre"><?= esc_html(is_scalar($data) ? (string) $data : (string) wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
