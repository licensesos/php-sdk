# LicenseOS PHP SDK

A PHP SDK for integrating with the LicenseOS license management API. Provides license validation, activation, and deactivation with built-in caching and WordPress integration.

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client 7.0+

## Installation

```bash
composer require licenseos/php-sdk
```

## Quick Start

```php
use LicenseOS\LicenseOsClient;

$client = new LicenseOsClient('your_api_key');

// Validate a license
$result = $client->validate('LIC-XXXX-XXXX-XXXX-XXXX', 'example.com');

if ($result->isValid()) {
    echo "License is valid!";
    echo "Status: " . $result->status;
    echo "Expires: " . $result->expiresAt;
}
```

## Core Features

### License Validation

Validate a license key for a specific domain or device:

```php
$result = $client->validate(
    licenseKey: 'LIC-XXXX-XXXX-XXXX-XXXX',
    identifier: 'example.com',
    metadata: [
        'app_version' => '1.2.3',
        'php_version' => PHP_VERSION,
    ]
);

// Check validation status
if ($result->isValid()) {
    // License is valid for this identifier
}

if ($result->isActive()) {
    // License status is "active"
}

if ($result->isExpired()) {
    // License has expired
}

if ($result->isRevoked()) {
    // License was revoked
}

// Access entitlements
if ($result->hasEntitlement('plan')) {
    $plan = $result->getEntitlement('plan');
}

// Get remaining activations
$remaining = $result->getRemainingActivations();
```

### License Activation

Activate a license for a domain or device:

```php
$result = $client->activate(
    licenseKey: 'LIC-XXXX-XXXX-XXXX-XXXX',
    identifier: 'example.com'
);

if ($result->isActivated()) {
    echo "Successfully activated!";
    echo "Remaining activations: " . $result->getRemainingActivations();
} else {
    echo "Activation failed: " . $result->getErrorMessage();
    // Error codes: ACTIVATION_LIMIT_REACHED, LICENSE_EXPIRED, etc.
}
```

### License Deactivation

Deactivate a license from a domain or device:

```php
$result = $client->deactivate(
    licenseKey: 'LIC-XXXX-XXXX-XXXX-XXXX',
    identifier: 'example.com'
);

if ($result->isDeactivated()) {
    echo "License deactivated. Remaining: " . $result->getRemainingActivations();
}
```

### List Activations

Get all activations for a license:

```php
$result = $client->listActivations('LIC-XXXX-XXXX-XXXX-XXXX');

echo "Total activations: " . $result->total;
echo "Limit: " . $result->limit;
echo "Remaining: " . $result->remaining;

foreach ($result->getActiveActivations() as $activation) {
    echo $activation['identifier'] . " - " . $activation['activated_at'];
}

// Check if a specific identifier is activated
if ($result->hasActivation('example.com')) {
    // This domain is activated
}
```

## Domain Normalization

The SDK automatically normalizes domain identifiers:

```php
// All of these become "example.com"
$client->validate($key, 'https://www.example.com/');
$client->validate($key, 'HTTP://EXAMPLE.COM:8080/path');
$client->validate($key, 'www.example.com');
$client->validate($key, 'example.com');

// Subdomain is preserved
$client->validate($key, 'app.example.com'); // becomes "app.example.com"

// IDN domains are converted to punycode
$client->validate($key, 'münchen.de'); // becomes "xn--mnchen-3ya.de"
```

You can also normalize domains manually:

```php
$normalized = LicenseOsClient::normalizeDomain('https://www.Example.COM/page');
// Returns: "example.com"
```

## Caching

The SDK includes a caching layer for reduced API calls and offline tolerance.

### Basic Caching

```php
use LicenseOS\LicenseOsClient;
use LicenseOS\Cache\LicenseCache;
use LicenseOS\Cache\FileCache;

$client = new LicenseOsClient('your_api_key');
$cache = new FileCache('/path/to/cache/dir');
$licenseCache = new LicenseCache($client, $cache);

// Validate with caching (12-hour TTL)
$result = $licenseCache->validate($licenseKey, $domain);

// Force refresh from API
$result = $licenseCache->validate($licenseKey, $domain, [], true);

// Check if premium features should be allowed
// (uses cache with 48-hour grace period for offline tolerance)
if ($licenseCache->shouldAllowPremium($licenseKey, $domain)) {
    // Enable premium features
}
```

### Cache Configuration

```php
$licenseCache = new LicenseCache(
    client: $client,
    cache: $cache,
    cacheTtl: 43200,    // 12 hours (default)
    gracePeriod: 172800  // 48 hours (default)
);
```

### Available Cache Adapters

**FileCache** - Stores cache in JSON files:

```php
use LicenseOS\Cache\FileCache;

$cache = new FileCache('/var/cache/myapp', 'myapp');
```

**WordPressCache** - Uses WordPress transients:

```php
use LicenseOS\Cache\WordPressCache;

$cache = new WordPressCache('my_plugin');
```

**Custom Cache** - Implement `CacheInterface`:

```php
use LicenseOS\Cache\CacheInterface;

class RedisCache implements CacheInterface
{
    public function get(string $key): mixed { /* ... */ }
    public function set(string $key, mixed $value, int $ttl): bool { /* ... */ }
    public function delete(string $key): bool { /* ... */ }
}
```

## WordPress Integration

The SDK includes ready-to-use WordPress integration classes.

### Step 1: Create Your License Manager

```php
use LicenseOS\WordPress\LicenseManager;

class MyPluginLicense extends LicenseManager
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function getApiKey(): string
    {
        return 'your_api_key_here';
    }

    protected function getOptionPrefix(): string
    {
        return 'my_plugin';
    }

    protected function getPluginVersion(): string
    {
        return MY_PLUGIN_VERSION;
    }
}
```

### Step 2: Create a Settings Page

```php
use LicenseOS\WordPress\SettingsPage;

class MyPluginSettings extends SettingsPage
{
    protected function getLicenseManager(): LicenseManager
    {
        return MyPluginLicense::getInstance();
    }

    protected function getPageTitle(): string
    {
        return 'My Plugin License';
    }

    protected function getMenuTitle(): string
    {
        return 'License';
    }

    protected function getMenuSlug(): string
    {
        return 'my-plugin-license';
    }

    protected function getPurchaseUrl(): string
    {
        return 'https://yoursite.com/pricing';
    }
}

// Register the settings page
add_action('admin_menu', function() {
    $settings = new MyPluginSettings();
    $settings->addMenuPage();
});
```

### Step 3: Gate Premium Features

```php
$license = MyPluginLicense::getInstance();

// Simple premium check
if ($license->isPremium()) {
    // Enable all premium features
}

// Check specific entitlements
if ($license->hasEntitlement('advanced_export')) {
    // Enable advanced export feature
}

// Get entitlement values
$maxItems = $license->getEntitlement('max_items', 10);
```

### Step 4: Background Validation (Optional)

Refresh license status periodically via cron:

```php
// Register cron hook
add_action('my_plugin_license_check', function() {
    $license = MyPluginLicense::getInstance();
    if ($license->needsRefresh()) {
        $license->validate(true);
    }
});

// Schedule daily check on activation
register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('my_plugin_license_check')) {
        wp_schedule_event(time(), 'daily', 'my_plugin_license_check');
    }
});

// Clear on deactivation
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('my_plugin_license_check');
});
```

## Error Handling

The SDK throws two types of exceptions:

### ApiException

Thrown when the API returns an error response:

```php
use LicenseOS\Exceptions\ApiException;

try {
    $result = $client->validate($licenseKey, $domain);
} catch (ApiException $e) {
    echo "API Error: " . $e->getMessage();
    echo "Error Code: " . $e->getErrorCode();
    echo "HTTP Status: " . $e->getStatusCode();
}
```

Common error codes:
- `LICENSE_NOT_FOUND` - License key doesn't exist
- `LICENSE_EXPIRED` - License has expired
- `LICENSE_REVOKED` - License was revoked
- `ACTIVATION_LIMIT_REACHED` - No more activations available
- `RATE_LIMITED` - Too many requests
- `AUTH_INVALID_API_KEY` - Invalid API key

### NetworkException

Thrown when unable to connect to the API:

```php
use LicenseOS\Exceptions\NetworkException;

try {
    $result = $client->validate($licenseKey, $domain);
} catch (NetworkException $e) {
    // Network error - use cached data if available
    echo "Network Error: " . $e->getMessage();
}
```

### Graceful Degradation Pattern

```php
use LicenseOS\Exceptions\ApiException;
use LicenseOS\Exceptions\NetworkException;

function checkLicense(LicenseCache $cache, string $key, string $domain): bool
{
    try {
        $result = $cache->validate($key, $domain);
        return $result->isValid();
    } catch (NetworkException $e) {
        // Network error - fall back to grace period
        return $cache->shouldAllowPremium($key, $domain);
    } catch (ApiException $e) {
        // API error - license is invalid
        return false;
    }
}
```

## Best Practices

### 1. Cache API Responses

Always use the `LicenseCache` class to avoid excessive API calls:

```php
// Good
$cache = new LicenseCache($client, new FileCache('/tmp/cache'));
$result = $cache->validate($key, $domain);

// Bad - calls API every time
$result = $client->validate($key, $domain);
```

### 2. Handle Network Errors Gracefully

Don't break your app when the API is unreachable:

```php
// Good - uses grace period
if ($licenseCache->shouldAllowPremium($key, $domain)) {
    enablePremiumFeatures();
}

// Bad - might throw on network error
$result = $client->validate($key, $domain);
if ($result->isValid()) {
    enablePremiumFeatures();
}
```

### 3. Store License Key Securely

For WordPress, use options (they're in the database):

```php
// Good
update_option('my_plugin_license_key', $licenseKey);

// Bad - don't store in plain files
file_put_contents('license.txt', $licenseKey);
```

### 4. Validate on Reasonable Schedule

Don't validate on every page load:

```php
// Good - validate if cache is stale
if ($licenseCache->needsRefresh($key, $domain)) {
    $licenseCache->validate($key, $domain, [], true);
}

// Bad - validates every time
$client->validate($key, $domain);
```

### 5. Soft Gate Features

Disable UI/features rather than breaking functionality:

```php
// Good - soft gating
function renderExportButton() {
    $license = MyPluginLicense::getInstance();
    if ($license->isPremium()) {
        echo '<button>Export</button>';
    } else {
        echo '<button disabled>Export (Pro)</button>';
        echo '<a href="...">Upgrade to Pro</a>';
    }
}

// Bad - hard gating that breaks UX
function exportData() {
    if (!MyPluginLicense::getInstance()->isPremium()) {
        die('License required');
    }
}
```

## API Reference

### LicenseOsClient

| Method | Description |
|--------|-------------|
| `validate(string $licenseKey, string $identifier, array $metadata = [])` | Validate a license |
| `activate(string $licenseKey, string $identifier, array $metadata = [])` | Activate a license |
| `deactivate(string $licenseKey, string $identifier)` | Deactivate a license |
| `listActivations(string $licenseKey)` | List all activations |
| `normalizeDomain(string $input)` | Normalize a domain (static) |
| `shouldAllowPremium(array $cachedState, int $graceTtl = 172800)` | Check cached state (static) |

### LicenseCache

| Method | Description |
|--------|-------------|
| `validate(...)` | Validate with caching |
| `shouldAllowPremium(string $licenseKey, ?string $identifier = null)` | Check premium status |
| `getCachedState(string $licenseKey, ?string $identifier = null)` | Get cached state |
| `clearCache(string $licenseKey, ?string $identifier = null)` | Clear cached data |
| `needsRefresh(string $licenseKey, ?string $identifier = null)` | Check if refresh needed |

### WordPress\LicenseManager

| Method | Description |
|--------|-------------|
| `getLicenseKey()` | Get stored license key |
| `setLicenseKey(string $key)` | Store license key |
| `clearLicenseKey()` | Clear license key and cache |
| `getStatus()` | Get cached status |
| `getLicenseData()` | Get cached license data |
| `isPremium()` | Check if premium features allowed |
| `hasEntitlement(string $key)` | Check for entitlement |
| `getEntitlement(string $key, mixed $default = null)` | Get entitlement value |
| `validate(bool $forceRefresh = false)` | Validate license |
| `activate(string $licenseKey)` | Activate license |
| `deactivate()` | Deactivate license |
| `needsRefresh()` | Check if refresh needed |

## License

MIT
