# MercadoLibre Sync — Reglas para IAs

## Código
- **PHP 7.4+**, estándares de WordPress. Clases y funciones con prefijo `MLS_`.
- **JavaScript ES5 + jQuery** (sin arrow functions, `let`/`const` ni template literals). Funciones con prefijo `mls_`.
- **CSS** con prefijo `mls-` (las clases `fwue-` son de `wordpress_utils`).
- Escapar toda salida (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) y sanitizar toda entrada.

## Seguridad
- Guardar ajustes: `check_admin_referer(MLS_API_SETTINGS::ACTION)` + `current_user_can(MLS_CAPABILITY)`.
- AJAX (si se agrega): `check_ajax_referer()` + `current_user_can()` en un `guard()` de la clase `MLS_API_*`.
- El token de GitHub de `FWUUpdate` está en `index.php` (`token_array_split`), como en los demás plugins del autor. No agregar otras claves o secretos al código: `npm run check` falla si encuentra un token escrito de corrido.

## Arquitectura
- `index.php`: cabecera y constantes `MLS_*`. Sin lógica. WooCommerce es **obligatorio**: sin él solo se muestra un aviso en el admin.
- `src/_.php`: cargador maestro (data → services → hooks → api → page). Cada carpeta tiene su `_.php`; un archivo nuevo se agrega a ese `_.php` con `require_once MLS_DIR . 'src/…'`.
- `src/data/`: opciones de `wp_options` (`MLS_USE_DATA_BASE` y sus hijas). `MLS_USE_DATA_CONFIG::DEFAULTS` es la única lista de ajustes; `sanitize()` la normaliza.
- `src/services/`: lógica (clases `MLS_*` estáticas). Una sola fuente por regla de negocio.
- `src/hooks/`: integración con WordPress / WooCommerce (`add_action` / `add_filter`).
- `src/api/`: entradas `admin-post` y AJAX.
- `src/page/`: menú (`add.php`), página con pestañas `FWUPage` (`page.php`) y una sección por pestaña (`sections/<key>.php`).
- Plugin genérico: **no** usar nombres ni textos de un sitio concreto (el contenido por defecto es solo un ejemplo editable).
- Rendimiento: CSS/JS del admin solo en la página del plugin; en el sitio, solo donde se usa.
- Toda operación relevante queda en el log: `MLS_LOG_SERVICE::add('tipo', [...])`.
- `libs/` es Composer (`npm run install`) y se versiona: el zip de `master` que instala `FWUUpdate` lo necesita. No editar a mano.

## Publicar
- Anotar los cambios en `CHANGELOG.md` bajo `## [Sin publicar]`.
- `npm run push-v -- patch|minor|major`.
