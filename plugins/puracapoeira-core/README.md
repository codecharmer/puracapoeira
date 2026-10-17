# Pura Capoeira Core

Plugin del sitio: ajustes, tipos de contenido, formulario de contacto, registros a eventos, cuentas de
capoeiristas (Sign in with Google / Apple), traducciones y diagnóstico.

## Tipos de contenido (menú **Pura Capoeira**)

| Tipo | Slug | Público | Datos |
|---|---|---|---|
| Sede | `pura_sede` → `/sedes/<slug>/` | sí | ciudad, región, país, dirección, responsable, WhatsApp, Instagram, Facebook, horarios, costos, lat/lng |
| Profesor | `pura_profesor` → `/profesores/<slug>/` | sí | nombre completo, grado, ciudad, país, sede, antetítulo, entradilla, pie de foto, redes; la biografía es el contenido |
| Evento | `pura_evento` | no (lista en la página Eventos) | fecha, hora, ciudad/sede, lugar, estado, enlace |
| Galería | `pura_galeria` | no (cuadrícula en la página Galería) | enlace, categoría, sede (taxonomías), miniatura |

El título de la sede/profesor es el que se muestra; el extracto es el texto corto de la tarjeta
y la meta descripción. El orden de las tarjetas es el campo *Orden* (atributos de página).

## Ajustes (`pura_settings`)

`contact_to_emails`, `contact_from_email`, `contact_from_name`, `whatsapp_number`,
`whatsapp_display`, `instagram_url`, `instagram_handle`, `facebook_url`, `facebook_label`,
`youtube_url`, `icloud_album_url`, `tagline`, `motto`, `og_image_id`, `account_page_id`,
`google_client_id`, `apple_services_id`, `apple_team_id`, `apple_key_id`. Cualquier clave puede
fijarse en `wp-config.php` con una constante `PURA_<CLAVE>` (p. ej. `PURA_CONTACT_TO_EMAILS`).

Secretos (`pura_secrets`, opción sin autoload): `google_client_secret`, `apple_private_key`. Se
guardan desde **Ajustes → Cuentas** o, mejor, como constantes `PURA_GOOGLE_CLIENT_SECRET` y
`PURA_APPLE_PRIVATE_KEY` en `wp-config.php` (la constante gana y el campo aparece bloqueado).

Fuente de Block Bindings `pura/setting` (claves `tagline`, `motto`, `copyright`,
`whatsapp_display`, `instagram_handle`, `facebook_label`) para párrafos del tema.

## REST `pura/v1`

`POST /contact` — JSON `{ nombre, ciudad, telefono, email?, mensaje, website, ts }` →
`{ "ok": true }` o `{ "ok": false, "error": "…" }`. Sin nonce (las páginas se sirven desde la
caché NGINX); protegido por tipo de contenido JSON, honeypot (`website`), antigüedad mínima del
formulario (`ts`, 3 s) y límite de 5 envíos cada 10 minutos por IP. El correo sale por `wp_mail`
(instala FluentSMTP para una entrega fiable).

`POST /events/register` — JSON o `multipart/form-data` (comprobante de pago opcional) → registro
`pura_event_reg`. Si el navegador envía `X-WP-Nonce` de una sesión iniciada, el registro queda
vinculado a esa cuenta (`_pura_user_id`).

| Ruta | Método | Para qué |
|---|---|---|
| `/auth/{google\|apple}/start` | POST (formulario) | Inicia el flujo. Solo POST para que una caché nunca reutilice un `state`; `redirect_to` opcional. |
| `/auth/{google\|apple}/callback` | GET / POST | Retorno del proveedor (Apple usa `form_post`). Crea o encuentra la cuenta, inicia sesión y redirige. |
| `/account/me` | GET + `X-WP-Nonce` | La persona con sesión y sus registros a eventos. 401 sin sesión. |

## Cuentas de capoeiristas (Sign in with Google / Apple)

- Rol `pura_capoeirista` ("Capoeirista", solo `read`): sin barra de administración y sin acceso a
  `wp-admin` (se le redirige a Mi cuenta). Usuario `capoeirista-<hex>`, contraseña aleatoria.
- Identidades en meta de usuario `_pura_identity_{google|apple}` (= `sub`); correo verificado por el
  proveedor en `_pura_verified_email`; `_pura_apple_private_email` si usó "Ocultar mi correo".
- Un correo afirmado por el proveedor solo se adjunta a una cuenta cuyo único rol sea Capoeirista:
  nunca abre una cuenta del equipo (`auth_error=admin_account`).
- Registros a eventos: al iniciar sesión se marcan con `_pura_user_id` los registros hechos con el
  correo verificado (`claim`); Mi cuenta lista los propios y los que coinciden por correo. Los correos
  relay de Apple no se emparejan: solo ven lo registrado desde la cuenta.
- Las páginas se sirven desde caché: el HTML no imprime nada personal. `assets/js/pura-auth.js`
  (`window.puraAuth`) pide el nonce a `admin-ajax.php?action=rest-nonce` y firma las llamadas.
- Estado OAuth en transients (`pura_auth_state_*`, 10 min, un solo uso), JWKS en caché 6 h, tokens
  verificados con `openssl` (RS256; el secreto de Apple es un JWT ES256 firmado con la llave .p8).

### Configuración

URLs de retorno (también en **Ajustes → Cuentas**):

- `https://puracapoeira.com/wp-json/pura/v1/auth/google/callback`
- `https://puracapoeira.com/wp-json/pura/v1/auth/apple/callback`

**Google** — Google Cloud Console → APIs & Services → Credentials → *OAuth client ID* (tipo *Web
application*). *Authorized redirect URIs*: la URL de Google de arriba (y la de `http://localhost:8787`
si quieres probar en local). Pantalla de consentimiento: externa, scopes `openid`, `email`,
`profile`, enlace al aviso de privacidad `https://puracapoeira.com/privacidad/`. El *Client ID* va en
Ajustes; el *Client secret* en `PURA_GOOGLE_CLIENT_SECRET`.

**Apple** — Apple Developer → Certificates, Identifiers & Profiles:

1. *Identifiers → App IDs*: un App ID con la capacidad *Sign in with Apple* (puede ser el mismo que
   usa el sitio de Cuernavaca si el equipo es el mismo).
2. *Identifiers → Services IDs*: p. ej. `com.puracapoeira.web` (= `apple_services_id`) con *Sign in
   with Apple* → *Configure*: Primary App ID el de arriba; *Domains* `puracapoeira.com`; *Return
   URLs* la URL de Apple de arriba. Apple exige HTTPS y dominio verificado: no se puede probar en local.
3. *Keys*: una llave con *Sign in with Apple* (la del equipo sirve para varios Services ID; si ya
   existe para Cuernavaca, reutiliza su Key ID y su `.p8`). Key ID → `apple_key_id`; Team ID (arriba a
   la derecha de la cuenta) → `apple_team_id`; el contenido del `.p8` → `PURA_APPLE_PRIVATE_KEY`
   (PEM con saltos de línea reales, con `\n` escapados o el archivo entero en base64).

Después de desplegar una versión que añade la página: `wp pura-theme import pages && wp pura-theme
import menu && wp pura-theme import settings` y `wp pura doctor` (comprueba página, rol y proveedores).

## Traducciones

Meta `_pura_i18n` en páginas, sedes y profesores: `{ "en": { "clave": "texto" | {"html": "…"} }, "pt": {…} }`.
Caja **Traducciones (EN / PT)** en el editor; el JSON inválido se rechaza y se conserva el anterior.
El tema imprime el JSON en la vista de la entrada y lo aplica en el navegador.

## WP-CLI

```bash
wp pura doctor               # permalinks, tema, páginas, contenidos, menú, logo, correo, SMTP, DISALLOW_FILE_EDIT, Mi cuenta, rol, Google/Apple
wp pura-theme import all     # (comando del tema) importa todo desde el sitio estático
```

## Desarrollo

```bash
composer install
composer run lint            # PHPCS (WordPress-Extra + Docs)
composer run lint:fix
composer run test:unit       # PHPUnit sin WordPress (Contact_Message, Settings, Mailer, registros, JWT/ES256, Apple client secret, State_Store)
```
