# Laravel integration

The PHP SDK ships a Laravel service provider and facade that are **auto-discovered** on Laravel 5.5+ via
composer's `extra.laravel` block — no manual registration required.

## Install

```bash
composer require traffical/sdk guzzlehttp/guzzle nyholm/psr7
```

## Configure

Publish the config file:

```bash
php artisan vendor:publish --tag=traffical-config
```

This writes `config/traffical.php`. Set the values via your `.env`:

```dotenv
TRAFFICAL_ORG_ID=org_...
TRAFFICAL_PROJECT_ID=prj_...
TRAFFICAL_ENV=production
TRAFFICAL_API_KEY=traffical_sk_…
TRAFFICAL_EVALUATION_MODE=bundle
TRAFFICAL_DISABLE_CLOUD_EVENTS=false
```

The provider resolves a PSR-3 logger and a PSR-16 cache from the container automatically when bound, so
PHP-FPM workers can share a single cached config bundle (bind `Psr\SimpleCache\CacheInterface`, e.g. to
`symfony/cache` or a Laravel cache PSR-16 bridge).

## Use

Resolve the client from the container:

```php
use Traffical\Client;

public function show(Client $traffical)
{
    $params = $traffical->getParams(
        ['userId' => auth()->id()],
        ['checkout_button_color' => 'blue'],
    );

    return view('checkout', ['color' => $params['checkout_button_color']]);
}
```

…or via the facade:

```php
use Traffical\Laravel\Traffical;

$decision = Traffical::decide(['userId' => auth()->id()], ['hero_variant' => 'control']);
Traffical::trackExposure($decision);
```

The `Client` is registered as a singleton, so the cached bundle and event batch are shared for the request.
