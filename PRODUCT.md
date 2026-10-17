# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Three primary audiences, confirmed by the owner on 2026-10-17:

1. **Prospective students and their parents** looking for capoeira classes. They need a sede (location), a schedule, a price and a way to make contact (WhatsApp first, contact form second). Kids, teens and adults are all served.
2. **Capoeiristas from any group** coming for events such as Vadiando na Ladeira (Guanajuato, 6–8 November 2026). They register online, pay by bank transfer to the group's Mercado Pago account, upload a proof of payment and expect the programme by email/WhatsApp. With accounts they sign in with Google or Apple and see their own registrations.
3. **Current alumnos and the wider community**: gallery, events, group culture, and their account.

Secondary: staff working in wp-admin (event registrations with status pills and CSV export, contact recipients, settings). The copy never says "estudiantes"; it says alumnos, capoeiristas, comunidad.

## Product Purpose

Public website of Centro Esportivo Cultural Pura Capoeira (C.E.C. Pura Capoeira), "escuela y comunidad internacional de capoeira", founded in 2014 by Mestre Madona (Mardonio Sales Linhares, from Fortaleza; Grupo Muzenza lineage, Mestre since 2010 in Rio). Sedes: Cuernavaca (núcleo principal, the only one with complete schedule/price data), Toluca, Guanajuato, Ceará, Angola and Austin (`public/data/sedes.json`, `public/profesor-mestre-madona.html`).

Success: a visitor finds a sede and makes contact; an event attendee registers (and pays) without staff intervention; the community sees the group's culture and activity.

## Positioning

"No es fitness, no es baile, no es solo deporte. Es cultura viva." (`themes/puracapoeira/patterns/home-intro.php`). An international school with a named lineage, six cities in four countries, and teaching across all ages. Values are named in Portuguese: Respeito, Musicalidade, Comunidade, Movimento, Estratégia.

## Operating Context

- WordPress block theme (`themes/puracapoeira`) + core plugin (`plugins/puracapoeira-core`) on a cPanel VPS; deploy on push to `master`; NGINX page cache, so **nothing user-specific may be printed in HTML** (sessions are discovered client-side).
- Content is imported from the legacy static site with `wp pura-theme import …` (manual, not part of the deploy).
- Spanish is rendered on the server; English and Portuguese are applied in the browser from generated dictionaries (`bin/i18n-convert.mjs` → `themes/puracapoeira/assets/i18n/{en,pt}.json`).
- WhatsApp +1 (805) 638-5603 (Profesor Malandro) is the primary contact channel; contact mail goes to contacto@puracapoeira.com.
- Event registrations are handled in wp-admin; payment is a Mercado Pago transfer plus an uploaded proof (image or PDF, ≤ 8 MB).
- A companion mobile app exists (Expo, Supabase, staff login by email) and is described in the privacy notice. It is a **separate system; website accounts live in WordPress only** (assumption, not confirmed by the owner).

## Capabilities and Constraints

- Pages: Inicio, El Grupo, Profesores e instructores, Sedes (Leaflet map), Galería, Eventos, Contacto, Vadiando na Ladeira 2026, Aviso de privacidad, Mi cuenta; single templates for sedes and profesores.
- Contact form: `POST pura/v1/contact` (honeypot, rate limit). Event registration: `POST pura/v1/events/register` → `pura_event_reg` record (statuses registered / confirmed / cancelled), mail to organisers with a copy to the registrant.
- Accounts (added 2026-10-17): Sign in with Google / Apple (`pura/v1/auth/*`), WordPress role `pura_capoeirista` ("Capoeirista"), "Mi cuenta" lists the visitor's own event registrations; the event form prefills name/email for signed-in visitors. No online payments, no student records, no profile form.
- Terminology: roda, jogo, batizado, troca de cordas, apelido ("apodo de capoeira"), graduación (cuerda/cordel), formatura, axé, sede/núcleo; ranks Mestre, Contramestre, Profesor/a, Instructor/a.
- Open facts: the hero eyebrow "est." carries no year; five of six sedes read "por confirmar"; the privacy notice does not yet describe web sign-in (follow-up).

## Brand Commitments

Name "Pura Capoeira"; legal name "Centro Esportivo Cultural Pura Capoeira"; motto "Capoeira · Cultura · Comunidade"; logo `themes/puracapoeira/assets/images/logo-capoeira.png`. Voice: Spanish with Portuguese capoeira vocabulary. Incumbent fonts Outfit (display) and DM Sans (body) are in use but were not pinned as a directive.

## Evidence on Hand

- Mestre Madona's biography with dates (Mexico 2005, Mestre 2010, founded 2014, first Mexican capoeira magazine 2007–08, Copas Mexicanas de Capoeira); nine teacher profiles; Instructora Palito's competition record; Profesor Malandro's grades.
- Photos in `themes/puracapoeira/assets/images` (roda, sedes, gallery, teachers), event poster `vadiando-na-ladeira-2026.jpg`, a shared iCloud album.
- **Absent, never fabricate:** testimonials, press, student counts, group awards, videos.

## Product Principles

1. Contact is the conversion: every surface leads to a sede or to WhatsApp.
2. Spanish first; English and Portuguese by enhancement, never at the cost of the Spanish page.
3. Nothing personal in cached HTML; the browser discovers the session and signs its own requests.
4. Culture over fitness: capoeira vocabulary and lineage are the product, not decoration.
5. Keep staff workload low: verified-email matching and sensible defaults over manual linking.

## Accessibility & Inclusion

Audience spans kids to adults; parents register minors (tutor fields). `prefers-reduced-motion` is respected; forms use live regions and autocomplete. No formal standard has been established (open).
