# Plugin for statamic logging missing pages

![Statamic 3.0+](https://img.shields.io/badge/Statamic-3.0+-FF269E?style=for-the-badge&link=https://statamic.com)
![Statamic 4.0+](https://img.shields.io/badge/Statamic-4.0+-FF269E?style=for-the-badge&link=https://statamic.com)
![Statamic 5.0+](https://img.shields.io/badge/Statamic-5.0+-FF269E?style=for-the-badge&link=https://statamic.com)

Pecotamic Missing Pages Logger is a Statamic addon which logs missing pages (404 errors)

## How to Install

You can search for this addon in the `Tools > Addons` section of the Statamic control panel and click **install**, or run the following command from your project root:

``` bash
composer require pecotamic/missing-pages-logger
```

The package requires PHP 8+. It will auto register.

## Configuration (optional)

You can override the default options by publishing the configuration:

```sh
php artisan vendor:publish --provider="Pecotamic\MissingPagesLogger\ServiceProvider" --tag=config
```

This will copy the default config file to `config/pecotamic/missing-pages-logger.php`.

**Enable/Disable Logging:**

In your `.env` file:
```env
PECOTAMIC_MISSING_PAGES_LOGGER_ENABLED=true
```

Or in the config file `config/missing-pages-logger.php`:
```php
'log_missing_pages' => env('PECOTAMIC_MISSING_PAGES_LOGGER_ENABLED', false),
```

**Log Files Location:**

When logging is enabled, missing pages are logged to:
- Index file: `storage/pecotamic/missing-pages-logger/missing_pages.yaml`
- Individual log files: `storage/pecotamic/missing-pages-logger/missing_pages/{id}.yaml`

Each log entry contains:
- Request URI
- Date and time
- Remote address (IP)
- Referer (if available)
- User agent (if available)
