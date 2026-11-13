# 🧩 MercadoLibre Sync

> Publica y sincroniza productos de WooCommerce en MercadoLibre: conexión OAuth, selección de productos, mapeo persistente y actualizaciones automáticas.

**Versión:** 1.0.0 · **WordPress:** 5.8+ · **PHP:** 7.4+ · **WooCommerce:** requerido · **Licencia:** GPLv2+

📋 [Registro de cambios](CHANGELOG.md) · 🤖 [Reglas para IAs](AGENTS.md) · 📦 [Releases](https://github.com/franciscoblancojn/mercadolibre-sync/releases)

---

## 💡 Cómo funciona

_Describir aquí qué hace el plugin, con un ejemplo._

## ⚙️ Configuración

En el menú **MercadoLibre** del admin:

| Pestaña | Ajustes |
|---|---|
| General | _Completar._ |

## 📋 Requisitos

- WordPress 5.8+
- PHP 7.4+
- WooCommerce: requerido
- Permiso `manage_woocommerce`

## ⚙️ Instalación

1. Descarga el zip de la rama `master`: https://github.com/franciscoblancojn/mercadolibre-sync/archive/refs/heads/master.zip
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin** y actívalo.
3. Ve a **MercadoLibre**, revisa los ajustes y guarda.

## 🔄 Actualizaciones

- Usa `FWUUpdate` (librería `wordpress_utils`), configurado en `index.php` (repositorio, rama `master` y token), como en los demás plugins.
- En **Plugins** aparece «Actualizar» cuando hay una release más nueva en GitHub. Para eso, cada versión necesita su release: la crea el workflow al subir el tag.
- `FWUUpdate` instala el zip de la rama `master`, que se descomprime en la carpeta `mercadolibre-sync-master`. Conviene hacer la **primera instalación también desde ese zip**, para que la carpeta no cambie con la primera actualización: si cambia, WordPress desactiva el plugin.

## 🧑‍💻 Desarrollo

```bash
npm run install          # composer install → libs/ (franciscoblancojn/wordpress_utils)
npm run check            # validaciones: php -l, JS ES5, require_once, secretos y dependencias
npm run push-v -- patch  # o minor | major → publica una versión
```

`npm run push-v`:

1. exige una sección `## [Sin publicar]` en [`CHANGELOG.md`](CHANGELOG.md) y que `npm run check` pase;
2. sube la versión en `index.php`, `package.json` y este README;
3. cambia `## [Sin publicar]` por `## [X.Y.Z] - fecha`;
4. hace commit «Release X.Y.Z», crea el tag `X.Y.Z` y hace push.

Después, GitHub Actions crea la release con `mercadolibre-sync.zip`.

## 🗂️ Estructura

```
mercadolibre-sync/
├── index.php                 # Cabecera del plugin, constantes MLS_*, FWUUpdate, carga
├── composer.json             # wordpress_utils, autoloader-suffix MLS
├── libs/                     # Composer → libs (se versiona; no editar)
├── bin/harness.sh            # Validaciones (npm run check)
├── scripts/bump-version.sh   # npm run push-v
├── .github/workflows/        # Release con el zip al subir un tag
└── src/
    ├── _.php                 # Cargador maestro
    ├── data/                 # MLS_USE_DATA_BASE / MLS_USE_DATA_CONFIG (wp_options)
    ├── services/             # Lógica; MLS_LOG_SERVICE
    ├── hooks/                # Integración con WordPress / WooCommerce
    ├── api/                  # Guardado de ajustes (admin-post) y AJAX
    ├── page/                 # Menú «MercadoLibre» y secciones (FWUPage)
    ├── js/admin.js           # ES5 + jQuery
    └── css/admin.css
```
