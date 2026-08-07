# WooCommerce GoCardless Payments — Brand & Asset System

Everything in `.wordpress-org/` is generated. `icon.svg` and
`resources/brand/banner.html` are the only files edited by hand — never touch
the PNGs directly, they are overwritten on every render.

## Asset inventory

| File | Dimensions | Purpose |
|---|---|---|
| `icon.svg` | vector | Canonical icon; the directory consumes this directly when present |
| `icon-256x256.png` · `icon-128x128.png` | 256 · 128 | Directory hero and grid |
| `icon-512x512.png` | 512 | Channels outside the directory |
| `banner-1544x500.png` · `banner-772x250.png` | — | Desktop and mobile directory banners |
| `banner-1024x512.png` | 1024×512 | Square-ish crop for other channels |
| `screenshot-*.png` | 1200×900 | Listing screenshots, once there are any |

## Palette

**It lives in `tests/assets/brand.ts`, and only there.** `icon.svg` repeats the
values because SVG cannot import, and its header says so.

| Token | Hex |
|---|---|
| `ink` | `#082f49` |
| `inkMid` | `#0c4a6e` |
| `inkLift` | `#075985` |
| `royal` | `#0284c7` |
| `royalLight` | `#0ea5e9` |
| `sky` | `#38bdf8` |
| `accent` | `#7dd3fc` |
| `glyphMid` | `#f0f9ff` |
| `glyphBase` | `#bae6fd` |

Tailwind sky. Sky blue is banking without being any particular bank, and it separates this from the darker blue the WooCommerce set uses for admin tooling.

## The mark

A bank: pediment, columns, plinth. Direct Debit is the one payment method where the money moves bank to bank rather than card to processor, and the building says that in a way a card glyph cannot.

## Regenerating

```bash
yarn install        # once
yarn shots:banners  # icon + banner PNGs; no site needed
```

Screenshots need a running site and a `shots` project; this plugin has the
banner half of the pipeline only, until there are screens worth capturing.
