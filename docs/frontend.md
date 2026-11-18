# Admin front end (Tailwind + Vite)

The admin pages (everything behind login) are built with **Tailwind CSS** and **Alpine**
(the Alpine bundled with Livewire), compiled by **Vite**. The old Vuexy theme is no longer
loaded by the admin. `public/assets` stays because the public homepage still uses it.

## What a page loads

| File | Size (gzip) | When |
|------|-------------|------|
| `app-*.css` | ~12 kB | every admin page |
| `app-*.js` | ~3 kB | every admin page |
| Inter font (latin) | ~48 kB | once, then cached |
| `apexcharts-*.js` | ~140 kB | only on Social Media → Overview (loaded on demand) |

Icons are inline SVGs from [Tabler](https://tabler.io/icons), copied into `resources/icons/`
for just the icons the views use. There is no icon font.

## Day to day

```bash
npm install          # once
npm run dev          # while editing: hot reload (keep it running)
npm run build        # before deploying: writes public/build
```

`npm run dev` and `npm run build` first run `npm run icons`, which copies any newly used
Tabler icon into `resources/icons/`. Commit that folder.

If a page shows a blank space where an icon should be: the name is wrong or was built at
runtime. Check `<!-- missing icon: … -->` in the page source (debug mode), fix the name, or
add it to `SAFELIST` in `scripts/sync-icons.mjs`.

> Stop `npm run dev` with Ctrl+C. If it is killed another way, delete `public/hot`,
> otherwise the app keeps loading assets from the stopped dev server.

## Deploying

`public/build` is ignored by git (Laravel's default). Pick one:

1. **Build on the server** (needs Node 20+): `npm ci && npm run build` after pulling.
2. **Build locally and upload**: run `npm run build`, then upload the `public/build` folder
   with the rest of the code. Use this if the host has no Node (typical cPanel).
3. **Commit the build**: remove `/public/build` from `.gitignore` and commit it after every
   `npm run build`. Simplest for git-based deploys without Node on the server.

## Building blocks

- **Layout:** `resources/views/components/layouts/base.blade.php` (HTML shell, theme script,
  toasts, confirm dialog) and `app.blade.php` (sidebar + top bar).
- **CSS components** in `resources/css/app.css`: `card`, `btn btn-primary|secondary|ghost|danger`,
  `input`, `select`, `label`, `badge badge-*`, `table`, `alert alert-*`, `tab`, `segmented`,
  `dropdown-panel`, `meter`.
- **Blade components** in `resources/views/components/ui/`: `page-header`, `empty`, `modal`,
  `delta`, plus `<x-icon name="…">`.
- **Toasts:** Livewire `$this->dispatch('swal:success', ['title' => '…', 'text' => '…'])`
  (also `swal:warning`, `swal:error`, `swal:info`) and session flashes (`->with('success', …)`).
- **Confirmations:** `x-on:click="$dispatch('confirm', { title, text, confirm: 'Delete', onConfirm: () => $wire.delete(id) })"`.
- **Dark mode:** light / dark / system from the top bar. The choice lives in `localStorage`, and a
  `theme` cookie lets the server render the right theme on the next page.
- **Brand colour:** `brand` in `tailwind.config.js` (#009DFF). Use `brand-600` or darker behind
  white text to keep 4.5:1 contrast.
