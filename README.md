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

## Cuentas (Mi cuenta)

Las personas pueden entrar con **Google o Apple** (`/cuenta/`) para ver sus registros a eventos y
llenar el formulario de registro más rápido. Las cuentas son usuarios de WordPress con el rol
*Capoeirista* (sin acceso al admin); los secretos de los proveedores viven en `wp-config.php`
(`PURA_GOOGLE_CLIENT_SECRET`, `PURA_APPLE_PRIVATE_KEY`) y el resto en **Pura Capoeira → Ajustes →
Cuentas**. Pasos de configuración en Google Cloud y Apple Developer:
[`plugins/puracapoeira-core/README.md`](plugins/puracapoeira-core/README.md#cuentas-de-capoeiristas-sign-in-with-google--apple).
Apple solo funciona con HTTPS y dominio verificado (producción); Google también en
`http://localhost:8888` si añades esa URL de retorno al cliente OAuth. Para wp-env copia
`docs/wp-env.override.example.json` a `.wp-env.override.json`.

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

El despliegue no ejecuta el importador. Cuando una versión añade páginas o entradas de menú (p. ej.
Mi cuenta), en el servidor: `wp pura-theme import pages && wp pura-theme import menu && wp pura-theme
import settings` (como el usuario de cPanel) y después `wp pura doctor`.

`.github/workflows/server-check.yml` (manual) inspecciona el servidor con esas credenciales:
WP-CLI, sudo, PHP, ea-nginx y el contenido de `DEPLOY_PATH`.

`.github/workflows/configure-smtp.yml` (manual) revisa el correo saliente (conexiones de
FluentSMTP, constantes en `wp-config.php`, buzones, certificado TLS del puerto 465, SPF/DKIM/DMARC)
y, con *apply* marcado, lo deja configurado: buzón en cPanel, credenciales en `wp-config.php`,
conexión SSL en FluentSMTP, remitente del plugin y un correo de prueba. Los scripts viven en
`.github/scripts/smtp/`; la contraseña del buzón se genera en el servidor y nunca se imprime.

`.github/workflows/configure-dns.yml` (manual) revisa y, con *apply*, corrige los registros TXT de
autenticación de correo (SPF y DMARC) de la zona `puracapoeira.com`. La zona vive en este mismo VPS:
el registro delega el dominio a `ns1/ns2.codecharmer.io` (ambos apuntan al servidor), así que se
edita con la API de WHM, no en un registrador.

El mapa de sedes usa Leaflet (BSD-2, incluido en `assets/vendor/leaflet`) con teselas de
OpenStreetMap (© colaboradores de OpenStreetMap).
