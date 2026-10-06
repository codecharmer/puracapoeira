# Pura Capoeira

Sitio de [puracapoeira.com](https://puracapoeira.com) en WordPress: un **block theme**
(`themes/puracapoeira`) y un **plugin** (`plugins/puracapoeira-core`) con los ajustes, las sedes,
los profesores, los eventos, la galería, el formulario de contacto (API REST) y las traducciones.

> El sitio estático anterior vive en `public/` hasta que termine el cambio a WordPress (es la
> fuente del importador en local). Ver [`docs/cutover-runbook.md`](docs/cutover-runbook.md).

## Estructura

```
themes/puracapoeira/        theme.json, plantillas, partes, patrones, bloques (src/ → build/), importador WP-CLI,
                            idiomas (assets/i18n/*.json, assets/js/i18n.js)
plugins/puracapoeira-core/  ajustes, tipos de contenido (sede, profesor, evento, galería), REST (pura/v1),
                            correo del formulario, traducciones por entrada, WP-CLI, tests
docs/                       runbook de cambio, plantilla de .htaccess de producción
bin/wp-env-seed.sh          semilla del entorno local
bin/check-patterns.php      valida el marcado de patrones, partes y plantillas (CI)
bin/i18n-convert.mjs        genera los diccionarios en/pt a partir de public/assets/js/main.js
.wp-env.json                entorno local (Docker) con @wordpress/env
```

## Desarrollo local

Requisitos: Node 20+, Docker Desktop, Composer (solo para lint/tests de PHP).

```bash
npm install                 # instala wp-env y wp-scripts (el tema es el único workspace con JS)
npm run build               # compila los bloques del tema
npx wp-env start            # http://localhost:8888  (admin / password)
```

`bin/wp-env-seed.sh` corre solo tras `wp-env start`: idioma, permalinks, tema, plugins y
`wp pura-theme import all`. El importador lee `public/` (montado como `wp-content/pura-source`):
`data/*.json`, las páginas de perfil y `assets/images/`. Es re-ejecutable: las entradas editadas en
el admin se saltan; `PURA_SEED_FORCE=1 bash bin/wp-env-seed.sh` las sobrescribe.

Comandos útiles:

```bash
npm run start                                  # wp-scripts en modo watch
npm run lint && npm run test:unit              # ESLint + Stylelint + Jest (módulo de idiomas, diccionarios)
php bin/check-patterns.php                     # marcado de bloques en patrones/partes/plantillas
cd plugins/puracapoeira-core && composer install && composer run lint && composer run test:unit
npx wp-env run cli wp pura doctor
npx wp-env run cli wp pura-theme import all --force
```

## Contenido editable

Todo se edita en el admin: copia de las páginas (bloques y patrones), **Pura Capoeira → Sedes /
Profesores / Eventos / Galería** (cada uno con su caja de datos), **Pura Capoeira → Ajustes**
(destinatarios del formulario, redes, lema, imagen para compartir). La biografía larga de cada
profesor es el contenido de su entrada (bloques, con el bloque *Línea de tiempo*).

## Idiomas

El contenido se escribe en español. Inglés y portugués se aplican en el navegador (selector de
banderas en la cabecera; se recuerda en `localStorage`, también `?lang=en`). Las traducciones de
la interfaz y de las páginas viven en `themes/puracapoeira/assets/i18n/{en,pt}.json`; las de cada
profesor/sede en la caja **Traducciones (EN / PT)** de su entrada (meta `_pura_i18n`). Las claves
son las clases `i18n-*` del contenido. `bin/i18n-convert.mjs` regenera los JSON desde el sitio
estático (solo durante la migración).

## Despliegue

`.github/workflows/deploy-wordpress.yml` compila, pasa PHPCS y PHPUnit y sube `themes/` y
`plugins/` al `wp-content` del sitio en producción por rsync, activa tema y plugin y limpia la
caché NGINX de cPanel. Se ejecuta en cada push a `master` (o a mano con *Run workflow*) **cuando
la variable del repositorio `WP_DEPLOY_ENABLED` vale `true`**. Usa los mismos secretos de siempre:
`SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS` y `DEPLOY_PATH` (la raíz
del sitio, donde vive `wp-config.php`). Antes de desplegar comprueba que ahí hay un WordPress.

`.github/workflows/server-check.yml` (manual) inspecciona el servidor con esas credenciales:
WP-CLI, sudo, PHP, ea-nginx y el contenido de `DEPLOY_PATH`.

El mapa de sedes usa Leaflet (BSD-2, incluido en `assets/vendor/leaflet`) con teselas de
OpenStreetMap (© colaboradores de OpenStreetMap).
