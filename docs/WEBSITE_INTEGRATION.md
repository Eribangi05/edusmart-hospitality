# Linking from edusmartconsult.com

The main site is static HTML and already links to separate apps in sub-folders. The hospitality platform runs on its own sub-domain, so the static site only needs a page, a menu entry and buttons. Nothing on the static host has to run PHP.

1. **DNS**: add an `A` record `hospitality` → your VPS IP (see DEPLOYMENT.md) and obtain a TLS certificate for `hospitality.edusmartconsult.com`.
2. **Menu entry**: add to your products menu/mega-menu (copy the markup style of the existing `ikimina`/`fmis` items):
   ```html
   <a href="/hospitality/" class="menu-item">Hospitality (hotels, restaurants, tours)</a>
   ```
3. **Product page**: copy `docs/website/hospitality/index.html` to `hospitality/index.html` on the static site, then restyle it with your site's CSS (the file uses neutral classes and links to your `assets/` folder structure; adjust the `<link>` and header/footer includes to match an existing page such as `ikimina/index.html`).
4. **Buttons**: "Log in" → `https://hospitality.edusmartconsult.com/login`, "Start free trial" → `https://hospitality.edusmartconsult.com/register`.
5. **Android**: once published on Google Play, add the store badge/link to the product page.

`docs/website/hospitality/index.html` is a self-contained starting point (English, with French/Kinyarwanda headings) — replace copy and screenshots with your own.
