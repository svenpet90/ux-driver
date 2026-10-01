# svenpetersen/ux-driver

Product tours with [driver.js](https://driverjs.com/) for Symfony UX, across several pages if
need be. Whether a user has seen a tour is stored **per user**, not in the browser.

[![CI](https://github.com/svenpet90/ux-driver/actions/workflows/ci.yml/badge.svg)](https://github.com/svenpet90/ux-driver/actions/workflows/ci.yml)

Inspired by [pentiminax/ux-driver](https://github.com/pentiminax/ux-driver). The persistence
layer follows `bentools/webpush-bundle`: the bundle defines interfaces, the application maps
them onto its own user entity.

## Requirements

- PHP 8.4, Symfony 8 (SecurityBundle, StimulusBundle 3), Twig 3.8
- driver.js ^1.8, Stimulus ^3
- AssetMapper or Webpack Encore (with `@symfony/stimulus-bridge`)

## What the application provides

1. **An entity** implementing `SvenPetersen\UX\Driver\Persistence\TourViewInterface`
   (`getUser()`, `getTourId()`, `getSeenAt()`), with its mapping and migration.
2. **A manager** implementing `TourViewManagerInterface` (`find()`, `factory()`, `save()`),
   registered with `#[AsTourViewManager(userClass: User::class)]`. One manager per user class.
3. **The route** to the endpoint, once per firewall whose users see tours:

   ```php
   // config/routes/ux_driver.php
   return static function (RoutingConfigurator $routes): void {
       $routes->import('@SvenPetersenUXDriverBundle/config/routes.php')->prefix('/admin');
   };
   ```

   A second import (e.g. `->prefix('/counter')->namePrefix('counter_')`) needs that route name
   as the `seenRoute` argument of `ux_driver_tour()`.
4. **Tours**: services implementing `TourProviderInterface`. `getTourId()` is the key the
   "seen" state is stored under — a new version of a tour needs a new id.

## In a template

```twig
<button type="button" {{ ux_driver_tour('dashboard-whats-new-1.2') }}>
    Take the tour
</button>
```

The button carries the tour: on the first visit it starts by itself (if the server reports
that the user has not seen it yet), afterwards on click. Every page with a step on it renders
the same button; the controller only shows the steps of the current page.

Button and progress texts default to English and can be overridden per tour:

```twig
{{ ux_driver_tour('dashboard-whats-new-1.2', {
    next: 'Weiter',
    previous: 'Zurück',
    done: 'Fertig',
    progress: '{{current}} von {{total}}',
}) }}
```

## Installation

```bash
composer require svenpetersen/ux-driver
```

Symfony Flex registers the bundle and wires the Stimulus controller into
`assets/controllers.json`.

- **AssetMapper**: Flex also adds `driver.js` and `driver.js/dist/driver.css` to
  `importmap.php`; the bundle exposes its `assets/dist` as `@svenpetersen/ux-driver`. The
  controller is loaded lazily, together with driver.js' stylesheet.
- **Webpack Encore**: Flex adds `@svenpetersen/ux-driver` to `package.json`; run
  `yarn install --force` (or `npm install --force`) and make sure `driver.js` is installed.
  The stylesheet comes in through `autoimport`.

## Building the controller

`assets/dist/controller.js` is built from `assets/src/controller.ts` and committed:

```bash
cd assets && npm install && npm run build
```

An application consuming the bundle through a `path` repository needs `yarn install --force`
afterwards to refresh its copy in `node_modules`.

## Security notes

- The endpoint only stores ids of tours that exist, for the logged-in user, and requires a
  CSRF token (sent by the controller in the `X-CSRF-Token` header). CSRF protection with a
  session must therefore be enabled (`framework.csrf_protection`).
- Step titles and descriptions are plain text; the controller escapes them before driver.js
  writes them into the page.

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

The tests boot a minimal kernel (`tests/Fixtures/TestKernel.php`) with an in-memory user and an
in-memory `TourViewManagerInterface`, so they run without a database.

The Stimulus controller is tested with Vitest in jsdom, against the real driver.js:

```bash
cd assets
npm install
npm test
```

## License

MIT — see [LICENSE](LICENSE).
