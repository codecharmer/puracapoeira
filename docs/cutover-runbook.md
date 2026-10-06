# Cutover runbook: static site → WordPress on the same cPanel VPS

Same server, same domain (`puracapoeira.com`). The switch is a document-root swap; DNS does
not change. Rollback is a directory rename. No staging: WordPress is installed straight into
the live docroot, so follow the order below.

## 0. Before anything else

- [ ] **Order matters.** The legacy workflow `.github/workflows/deploy.yml` runs
      `rsync --delete public/ → DEPLOY_PATH` on every push to `master`. If WordPress lives in
      that docroot while that workflow still exists, the next push wipes it. The retirement
      commit (step 1) must be on `master` **before** WordPress is installed (step 2).
- [ ] Run **Actions → Server check (pre-cutover) → Run workflow** and read the log: `wp` is
      installed, `su -s /bin/bash puracapoeirasite -c 'wp --info'` works, `ea-nginx` is present, the
      vhost PHP version is ≥ 8.1, and `DEPLOY_PATH` is the vhost document root (the setup script's
      default was `/home/<owner>/public_html/puracapoeirasite`, a sub-folder; the deploy target
      must be the folder Apache/NGINX serves for the domain).
- [ ] If the vhost PHP version differs from `.wp-env.json` (`phpVersion`), align them.
- [ ] cPanel → Email Accounts: create `contacto@puracapoeira.com` (the contact form's recipient
      and SMTP account). cPanel → Email Deliverability: enable DKIM and SPF for the domain.
- [ ] Move the raw photo dumps out of the working tree (`public/assets/images/IMG_*`,
      `PHOTO-*`, `capoeira_2_*`, `_photo-index.jpg`); they are gitignored but 200 MB of noise.
- [ ] Make sure `themes/puracapoeira/assets/images/og-image.jpg` is the image you want shared
      on social networks (1200 × 630).

## 1. Content freeze and retirement commit

1. Content freeze on the static site.
2. On `master`, commit the deletion of `.github/workflows/deploy.yml` and `setup-vps-deploy.sh`
   (keep `public/`; it is still the import source for local dev). Push. The static deploy is
   now gone; the live site is untouched.

## 2. Install WordPress in the live docroot

Over SSH as root (replace `<docroot>` with the real `DEPLOY_PATH`):

```bash
mv <docroot> <docroot>_static_backup && chmod 700 <docroot>_static_backup
mkdir -p <docroot> && chown puracapoeirasite:puracapoeirasite <docroot>
```

cPanel → **WP Toolkit → Install** into `<docroot>` for `puracapoeira.com`: language Español
(México), a new admin user with a strong password, HTTPS on. Fallback without Toolkit, as the
cPanel user: `wp core download --locale=es_MX && wp config create … && wp core install …`.

Add to `wp-config.php` (above "That's all, stop editing"):

```php
define( 'DISALLOW_FILE_EDIT', true );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'WP_ENVIRONMENT_TYPE', 'production' );
// Optional: pin any plugin setting, e.g. define( 'PURA_CONTACT_TO_EMAILS', 'contacto@puracapoeira.com' );
```

The site now shows the default WordPress theme for a few minutes; that is expected.

## 3. First deploy

1. GitHub → Settings → Secrets and variables → Actions → **Variables** → new repository variable
   `WP_DEPLOY_ENABLED` = `true` (or `gh variable set WP_DEPLOY_ENABLED --body true`).
2. Actions → **Deploy WordPress theme and plugin → Run workflow**. It checks that
   `DEPLOY_PATH/wp-config.php` exists, rsyncs `themes/puracapoeira` and
   `plugins/puracapoeira-core` into `wp-content`, activates both and clears the NGINX cache.
   Every later push to `master` deploys the same way.

## 4. Seed the content

Over SSH as root, become the cPanel user with `su -s /bin/bash puracapoeirasite` (the account has
no login shell, so `sudo -u … -i` does not work), then `cd <docroot>`:

```bash
wp option update blog_public 1
wp option update timezone_string America/Mexico_City
wp option update blogdescription "Escuela internacional de capoeira"
wp rewrite structure '/%postname%/' --hard
wp plugin install fluent-smtp --activate
wp plugin delete akismet hello
wp pura-theme import all --source=<docroot>_static_backup
wp pura doctor
```

`import all` creates the media (logo, OG image), the 6 sedes, 9 teacher profiles (biographies
parsed from the old HTML, translations attached), events, gallery items, the 7 pages from the
theme patterns, the main menu and the settings. It is re-runnable; posts edited in the admin
afterwards are skipped unless `--force` is given.

## 5. Mail, redirects, cache

1. WP admin → **Pura Capoeira → Ajustes**: recipients, sender, social links; save; click
   "Enviar correo de prueba".
2. Settings → **FluentSMTP**: host `mail.puracapoeira.com`, port 465 (SSL), user
   `contacto@puracapoeira.com`, password from cPanel. Send the test mail.
3. Install `docs/htaccess.production` as `<docroot>/.htaccess` (merge with whatever WP Toolkit
   wrote; keep the WordPress block at the end). Then `wp rewrite flush --hard`.
4. `/usr/local/cpanel/scripts/ea-nginx clear_cache puracapoeirasite` (as root).

## 6. Production checklist

- [ ] `curl -sI` → 200 for `/`, `/grupo/`, `/profesores/`, `/sedes/`, `/galeria/`, `/eventos/`,
      `/contacto/`, the 6 `/sedes/<slug>/` and the 9 `/profesores/<slug>/`.
- [ ] Every old URL → 301 to its slug: `/index.html`, `/grupo.html` … `/contacto.html`,
      `/sedes/cuernavaca.html` …, `/profesor-mestre-madona.html` …; `/assets/css/styles.css` → 410;
      `/.git/` → 404.
- [ ] `curl -s -X POST https://puracapoeira.com/wp-json/pura/v1/contact -H 'content-type: application/json' -d '{"nombre":"Prueba","mensaje":"Hola","ts":1}'`
      → `{"ok":true}` and the mail arrives at `contacto@puracapoeira.com`.
- [ ] Sedes page: the map shows 6 markers; hovering a card pans the map.
- [ ] Gallery filters work; events list renders; teacher profiles show bios, timeline and social links.
- [ ] Language switch (header and mobile menu) to English and Portuguese on the home page, a sede
      page and the Madona profile; the choice survives a reload; `?lang=pt` is honoured and stripped.
- [ ] `wp pura doctor` is green; FluentSMTP log shows the sends.
- [ ] Social share preview (e.g. https://developers.facebook.com/tools/debug/) shows the OG image.

## 7. Rollback (≤ 5 minutes)

As root:

```bash
mv <docroot> <docroot>_wp_failed
mv <docroot>_static_backup <docroot>
/usr/local/cpanel/scripts/ea-nginx clear_cache puracapoeirasite
```

Then revert the retirement commit on `master` (or re-add `deploy.yml`) to resume static deploys.
The WordPress copy stays in `<docroot>_wp_failed` for a second attempt.

## 8. After a stable week

- [ ] Delete `<docroot>_static_backup` (or keep it 30 days).
- [ ] Cleanup commit: remove `public/`, the `mappings` entry in `.wp-env.json`,
      `bin/i18n-convert.mjs` and update the README. From then on the local importer only needs
      the theme (pages, menu, settings); sedes/profesores/eventos/gallery come from the live
      database export if ever needed again.
