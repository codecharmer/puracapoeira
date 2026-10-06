# Pura Capoeira Core

Plugin del sitio: ajustes, tipos de contenido, formulario de contacto, traducciones y diagnóstico.

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
`youtube_url`, `icloud_album_url`, `tagline`, `motto`, `og_image_id`. Cualquier clave puede
fijarse en `wp-config.php` con una constante `PURA_<CLAVE>` (p. ej. `PURA_CONTACT_TO_EMAILS`).

Fuente de Block Bindings `pura/setting` (claves `tagline`, `motto`, `copyright`,
`whatsapp_display`, `instagram_handle`, `facebook_label`) para párrafos del tema.

## REST `pura/v1`

`POST /contact` — JSON `{ nombre, ciudad, telefono, email?, mensaje, website, ts }` →
`{ "ok": true }` o `{ "ok": false, "error": "…" }`. Sin nonce (las páginas se sirven desde la
caché NGINX); protegido por tipo de contenido JSON, honeypot (`website`), antigüedad mínima del
formulario (`ts`, 3 s) y límite de 5 envíos cada 10 minutos por IP. El correo sale por `wp_mail`
(instala FluentSMTP para una entrega fiable).

## Traducciones

Meta `_pura_i18n` en páginas, sedes y profesores: `{ "en": { "clave": "texto" | {"html": "…"} }, "pt": {…} }`.
Caja **Traducciones (EN / PT)** en el editor; el JSON inválido se rechaza y se conserva el anterior.
El tema imprime el JSON en la vista de la entrada y lo aplica en el navegador.

## WP-CLI

```bash
wp pura doctor               # permalinks, tema, páginas, contenidos, menú, logo, correo, SMTP, DISALLOW_FILE_EDIT
wp pura-theme import all     # (comando del tema) importa todo desde el sitio estático
```

## Desarrollo

```bash
composer install
composer run lint            # PHPCS (WordPress-Extra + Docs)
composer run lint:fix
composer run test:unit       # PHPUnit sin WordPress (Contact_Message, Settings, Mailer::build_contact_body)
```
