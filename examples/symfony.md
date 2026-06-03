# Symfony integration

The PHP SDK ships a Symfony bundle that registers the `Traffical\Client` as an autowirable service.

## Install

```bash
composer require traffical/sdk symfony/http-client nyholm/psr7
```

## Enable the bundle

Add it to `config/bundles.php`:

```php
return [
    // ...
    Traffical\Symfony\TrafficalBundle::class => ['all' => true],
];
```

## Configure

Create `config/packages/traffical.yaml`:

```yaml
traffical:
    org_id: '%env(TRAFFICAL_ORG_ID)%'
    project_id: '%env(TRAFFICAL_PROJECT_ID)%'
    env: '%env(TRAFFICAL_ENV)%'
    api_key: '%env(TRAFFICAL_API_KEY)%'
    evaluation_mode: 'bundle'   # or 'server'
    disable_cloud_events: false
    deduplicate_assignment_logger: true
```

The bundle wires a PSR-3 logger and PSR-16 cache from the container when available (null otherwise).

## Use

Autowire the client:

```php
use Traffical\Client;

final class CheckoutController
{
    public function __construct(private readonly Client $traffical)
    {
    }

    public function show(): Response
    {
        $params = $this->traffical->getParams(
            ['userId' => $this->getUser()?->getUserIdentifier()],
            ['checkout_button_color' => 'blue'],
        );

        // ...
    }
}
```

The service is also available under the `traffical.client` alias for manual `$container->get()` access.
