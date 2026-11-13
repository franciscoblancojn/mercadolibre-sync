<?php
/*
Plugin Name: MercadoLibre Sync
Plugin URI: https://github.com/franciscoblancojn/mercadolibre-sync
Description: Publica y sincroniza productos de WooCommerce en MercadoLibre: conexión OAuth, selección de productos, mapeo persistente y actualizaciones automáticas.
Version: 1.0.0
Author: franciscoblancojn
Author URI: https://franciscoblanco.vercel.app/
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: mercadolibre-sync
Requires at least: 5.8
Requires PHP: 7.4
WC requires at least: 5.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active'))
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');

require_once __DIR__ . '/libs/autoload.php';

//MLS_
// MLS_KEY, MLS_LOG, MLS_LOG_KEY y MLS_LOG_COUNT los lee FWUSystemLog por nombre: no renombrarlas.
define("MLS_KEY", 'MLS');
define("MLS_FILE", __FILE__);
define("MLS_CONFIG", 'MLS_CONFIG');
define("MLS_CAPABILITY", 'manage_woocommerce');
define("MLS_REPOSITORY", 'franciscoblancojn/mercadolibre-sync');
define("MLS_TOKEN", 'MLS_TOKEN');
define("MLS_OAUTH_STATE", 'MLS_OAUTH_STATE');
define("MLS_REFRESH_LOCK", 'MLS_REFRESH_LOCK');
define("MLS_META", '_mls_');
define("MLS_PRODUCTS_PAGE", 'MLS-products');
define("MLS_GROUP", 'mercadolibre-sync');
define("MLS_ACTION_SYNC", 'mls_sync_product');
define("MLS_ACTION_CLOSE", 'mls_close_item');
define("MLS_REST_NAMESPACE", 'mls/v1');
define("MLS_LOG", true);
define("MLS_LOG_KEY", "MLS_LOG");
define("MLS_LOG_COUNT", 100);
define("MLS_BASENAME", plugin_basename(__FILE__));
define("MLS_DIR", plugin_dir_path(__FILE__));
define("MLS_URL", plugin_dir_url(__FILE__));

function MLS_get_version()
{
    $plugin_data = get_plugin_data(__FILE__, false, false);
    return $plugin_data['Version'];
}

use franciscoblancojn\wordpress_utils\FWUUpdate;
use franciscoblancojn\wordpress_utils\FWUSystemLog;

if (is_admin()) {
    FWUUpdate::init([
        'basename' => MLS_BASENAME,
        'dir' => MLS_DIR,
        'file' => "index.php",
        'path_repository' => MLS_REPOSITORY,
        'branch' => 'master',
        'token_array_split' => ["g", "h", "p", "_", "G", "4", "W", "E", "W", "F", "p", "V", "U", "E", "F", "V", "x", "F", "U", "n", "b", "M", "k", "P", "R", "x", "o", "f", "t", "Y", "8", "z", "j", "t", "4", "E", "x", "b", "i", "9"],
    ]);

    FWUSystemLog::init(MLS_KEY);
}

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    function MLS_Error_Install_o_Active()
    {
?>
        <div class="notice notice-error is-dismissible">
            <p>
                MercadoLibre Sync requiere el plugin "WooCommerce" activo para funcionar.
            </p>
        </div>
<?php
    }
    add_action('admin_notices', 'MLS_Error_Install_o_Active');
} else {
    require_once MLS_DIR . 'src/_.php';
}
