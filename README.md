OBB app Service Provider
---------------------------------------

Install
-------
```bash
composer require goldenplanetdk/obb-app-service-provider "dev-master"
```

```php
use GP\App\Provider\Service\AuthorizeServiceProvider;

$app->register(new AuthorizeServiceProvider(), $parameters);
```

Events:

`app.installation.success`

License
-------
MIT
