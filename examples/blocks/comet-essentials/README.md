# Comet Essentials

The CometCMS builder extension: Callout, Card, FAQ, Feature, Maps, Pricing plan, Slideshow and Stats. Package ID: `cometcms/essentials`.

Validate and package from the repository root:

```sh
npm run blocks -- validate examples/blocks/comet-essentials
npm run blocks -- pack examples/blocks/comet-essentials examples/blocks/comet-essentials.zip
```

Install the ZIP in **Page Builder → Custom blocks**. Maps, Slideshow and Stats include JavaScript, which an administrator must allow during installation.

Maps accepts a location as decimal `latitude, longitude`, a display name or address, and a zoom slider (1–19). Coordinates locate the pin; the display name is not a search query. The published map uses OpenStreetMap raster tiles, shows attribution, and loads only the visible area. No API key is required. Previews keep scripts disabled and show the location with a placeholder. See the [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/) when choosing a provider for production traffic.

This replaces the development example `acme/marketing`. Its `block:acme/*` kinds have been renamed to `block:cometcms/*`; existing layouts using the old example need to be updated before removing that package. Comet Essentials does not rewrite saved layouts.
