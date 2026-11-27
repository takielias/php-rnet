# php-rnet

HTTP client for PHP with browser TLS/H2 fingerprinting. Built in Rust on top of [wreq](https://github.com/0x676e67/wreq) (BoringSSL).

Lets you make HTTP requests that look like they came from a real browser at the TLS/JA3/H2 level — useful when dealing with sites that fingerprint clients.

---

## Details of the Extension

- Fully statically linked — only `glibc` and `libgcc_s.so.1` required at runtime
- BoringSSL baked in — no OpenSSL dependency
- 100+ browser emulation profiles: Chrome, Firefox, Safari, Edge, Opera, OkHttp
- Every `.so` is bound to a specific PHP API version at compile time (PHP 8.4 build will not load in PHP 8.3)

---

## Usage

### Installation

Download the pre-built `.so` for your PHP version from the [Releases](https://github.com/takielias/php-rnet/releases) page, then load it.

Supported PHP versions:

| PHP | Pre-built since | Minimum patch release |
|---|---|---|
| 8.2 | v0.0.1 | 8.2.35 |
| 8.3 | v0.0.1 | 8.3.36 |
| 8.4 | v0.0.1 | 8.4.27 |
| 8.5 | v0.0.3 | 8.5.12 |

PHP 8.1 is end of life. The last pre-built 8.1 file is in v0.0.2 and stays available there.

The PHP security releases of 21 October 2026 fix PHP itself, so upgrade your PHP runtime to the minimum patch release above or newer. Replacing the extension file alone does not apply those fixes.

```bash
# Check your PHP version
php --version

# Verify it loaded
php -d extension=/path/to/librnet.so -r "var_dump(extension_loaded('php-rnet'));"
# bool(true)
```

Add to `php.ini` permanently:

```ini
extension=/absolute/path/to/librnet.so
```

Or load per-script:

```bash
php -d extension=/absolute/path/to/librnet.so your_script.php
```

---

### Quick one-liners

The simplest way to use php-rnet — no client setup needed:

```php
// GET request
$resp = RNet\get('https://httpbin.org/get');
echo $resp->status();   // 200
echo $resp->text();     // response body as string

// POST with JSON body
$resp = RNet\post('https://httpbin.org/post', [
    'json' => ['name' => 'Alice', 'role' => 'admin'],
]);
$data = $resp->json();  // decoded as PHP array
echo $data['json']['name'];  // Alice

// POST with form data
$resp = RNet\post('https://httpbin.org/post', [
    'form' => ['username' => 'alice', 'password' => 'secret'],
]);

// Other methods
$resp = RNet\put('https://httpbin.org/put');
$resp = RNet\patch('https://httpbin.org/patch');
$resp = RNet\delete('https://httpbin.org/delete');
$resp = RNet\head('https://httpbin.org/get');

// Custom HTTP method
$resp = RNet\request('OPTIONS', 'https://httpbin.org/get');
```

---

### Reusable client

Create a client once and reuse it across requests. Shares a connection pool for better performance.

> Builder methods return `void` and **cannot be chained**. Call them separately then call `build()`.

```php
$b = new RNet\ClientBuilder();
$b->timeout(30.0);
$client = $b->build();

$resp = $client->get('https://httpbin.org/get');
echo $resp->status();   // 200
```

---

### Request options

All request methods accept an optional array as the second argument:

```php
$resp = $client->post('https://httpbin.org/post', [
    // Custom headers
    'headers' => [
        'X-Api-Key'      => 'secret',
        'Accept-Language' => 'en-US',
    ],

    // Query string  →  ?page=2&sort=asc
    'query' => [
        'page' => '2',
        'sort' => 'asc',
    ],

    // JSON body (sets Content-Type: application/json automatically)
    'json' => ['name' => 'Alice', 'active' => true],

    // Form body (sets Content-Type: application/x-www-form-urlencoded)
    'form' => ['field' => 'value'],

    // Raw string body
    'body' => 'raw payload here',

    // Per-request timeout override (seconds)
    'timeout' => 10.0,
]);
```

Only one of `json`, `form`, or `body` should be set per request. The first one found wins.

---

### Working with responses

```php
$resp = RNet\get('https://httpbin.org/get');

// Status
$resp->status();         // int — e.g. 200, 404
$resp->ok();             // bool — true if 2xx
$resp->version();        // string — e.g. "HTTP/2.0"
$resp->url();            // string — final URL after redirects

// Body (can be called multiple times — cached after first read)
$resp->text();           // string — body decoded as UTF-8
$resp->json();           // mixed  — body decoded as PHP array/scalar
$resp->bytes();          // array  — raw bytes

// Headers
$resp->headers();                     // array  — all headers as key→value
$resp->header('content-type');        // ?string — single header, case-insensitive
$resp->header('Content-Type');        // same result

// Cookies
$cookies = $resp->cookies();          // RNet\Cookie[]
foreach ($cookies as $c) {
    echo $c->getName();               // string
    echo $c->getValue();              // string
    echo $c->getDomain() ?? '';       // ?string
    echo $c->getPath() ?? '';         // ?string
    var_dump($c->isHttpOnly());       // bool
    var_dump($c->isSecure());         // bool
    echo $c;                          // "name=value"
}

// Server address
$resp->remoteAddr();     // ?string — e.g. "93.184.216.34:443"

// Throw on 4xx / 5xx
$resp->raiseForStatus(); // throws RNet\StatusException if status >= 400
```

---

### Browser emulation

Make your request look like it came from a real browser at the TLS/JA3/HTTP2 fingerprint level:

```php
$b = new RNet\ClientBuilder();
$b->impersonate(RNet\Emulation::CHROME_136);
$client = $b->build();

$resp = $client->get('https://tls.browserleaks.com/json');
echo $resp->text();
```

**Available profiles:**

| Family | Constants |
|--------|-----------|
| Chrome | `CHROME_100` through `CHROME_138` |
| Edge | `EDGE_101`, `EDGE_122`, `EDGE_127`, `EDGE_131`, `EDGE_134`–`EDGE_137` |
| Firefox | `FIREFOX_109`, `FIREFOX_117`, `FIREFOX_128`, `FIREFOX_133`, `FIREFOX_135`, `FIREFOX_136`, `FIREFOX_139`, `FIREFOX_PRIVATE_135`, `FIREFOX_ANDROID_135` |
| Safari | `SAFARI_15_3` through `SAFARI_26`, `SAFARI_IPAD_18`, `SAFARI_IOS_16_5` through `SAFARI_IOS_26` |
| Opera | `OPERA_116`–`OPERA_119` |
| OkHttp | `OK_HTTP_3_9`, `OK_HTTP_3_11`, `OK_HTTP_3_13`, `OK_HTTP_3_14`, `OK_HTTP_4_9`, `OK_HTTP_4_10`, `OK_HTTP_4_12`, `OK_HTTP_5` |

---

### TLS / certificate verification

Skip certificate verification (useful for internal services or development):

```php
$b = new RNet\ClientBuilder();
$b->verify(false);       // disables both cert validation and hostname check
$client = $b->build();
```

---

### Proxy

Route traffic through HTTP, HTTPS, or SOCKS5 proxies:

```php
// Route all traffic (HTTP + HTTPS) through a SOCKS5 proxy
$proxy = RNet\Proxy::all('socks5://127.0.0.1:1080');

// HTTP traffic only
$proxy = RNet\Proxy::http('http://127.0.0.1:8080');

// HTTPS traffic only
$proxy = RNet\Proxy::https('https://127.0.0.1:8080');

// Proxy with authentication
$proxy = RNet\Proxy::all('socks5://user:pass@127.0.0.1:1080');

$b = new RNet\ClientBuilder();
$b->proxy($proxy);
$client = $b->build();

$resp = $client->get('https://httpbin.org/ip');
echo $resp->json()['origin'];   // your proxy's IP
```

---

### Cookies

Enable a per-client cookie jar so cookies persist across requests (like a real browser session):

```php
$b = new RNet\ClientBuilder();
$b->cookieStore(true);
$client = $b->build();

// Login — server sets a session cookie
$client->post('https://example.com/login', [
    'form' => ['username' => 'alice', 'password' => 'secret'],
]);

// Subsequent requests automatically send the session cookie
$resp = $client->get('https://example.com/dashboard');
```

---

### Timeouts and redirects

```php
$b = new RNet\ClientBuilder();
$b->timeout(30.0);           // total request timeout in seconds (0 = no limit)
$b->connectTimeout(5.0);     // max time to establish the connection
$b->maxRedirects(5);         // follow up to 5 redirects (0 = disable redirects)
$client = $b->build();
```

---

### Headers and User-Agent

Set default headers sent on every request from this client:

```php
$b = new RNet\ClientBuilder();
$b->defaultHeader('Accept-Language', 'en-US,en;q=0.9');
$b->defaultHeader('X-Api-Key', 'my-secret');
$b->userAgent('MyBot/1.0');
$client = $b->build();
```

Per-request headers override client defaults:

```php
$resp = $client->get('https://httpbin.org/headers', [
    'headers' => ['X-Api-Key' => 'override-for-this-request'],
]);
```

---

### HTTP/1 only

Disable HTTP/2 and force HTTP/1.1:

```php
$b = new RNet\ClientBuilder();
$b->http1Only(true);
$client = $b->build();
```

---

### Error handling

All exceptions extend `\Exception` and can be caught individually or together:

```php
try {
    $resp = RNet\get('https://example.com');
    $resp->raiseForStatus();
    $data = $resp->json();
} catch (RNet\TimeoutException $e) {
    // Request exceeded the timeout
    error_log('Timed out: ' . $e->getMessage());
} catch (RNet\ConnectionException $e) {
    // Could not connect (DNS failure, refused, etc.)
    error_log('Connection failed: ' . $e->getMessage());
} catch (RNet\TlsException $e) {
    // TLS handshake failed
    error_log('TLS error: ' . $e->getMessage());
} catch (RNet\StatusException $e) {
    // Server returned 4xx or 5xx
    error_log('HTTP error: ' . $e->getMessage());
} catch (RNet\RedirectException $e) {
    // Too many redirects or redirect loop
    error_log('Redirect error: ' . $e->getMessage());
} catch (RNet\BodyException $e) {
    // Error reading the response body
    error_log('Body error: ' . $e->getMessage());
} catch (RNet\RequestException $e) {
    // Any other request error
    error_log('Request error: ' . $e->getMessage());
}
```

**Exception class hierarchy:**

```
\Exception
 ├── RNet\RequestException      — generic request failure
 ├── RNet\ConnectionException   — could not connect
 ├── RNet\TlsException          — TLS handshake failure
 ├── RNet\TimeoutException      — request timed out
 ├── RNet\StatusException       — 4xx / 5xx response
 ├── RNet\BodyException         — error reading body
 ├── RNet\DecodingException     — response decoding failure
 ├── RNet\RedirectException     — too many / bad redirects
 └── RNet\WebSocketException    — WebSocket error
```

---

## Building from source

### System requirements

- Linux x86_64
- PHP 8.2 or newer (with development headers)
- Rust 1.85 or newer
- cmake 3.14 or newer (to compile BoringSSL)
- libclang 18 (used at build time only by bindgen — not needed at runtime)
- A C compiler: gcc or clang

> **Note:** libclang is only needed during `cargo build`. The compiled `.so` file has no dependency on it.

---

### Step 1 — Install Rust

```bash
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source ~/.cargo/env
```

Verify:

```bash
rustc --version   # should be 1.85 or newer
cargo --version
```

---

### Step 2 — Install system dependencies

**Debian / Ubuntu:**

```bash
sudo apt update
sudo apt install build-essential cmake pkg-config libclang-18-dev
```

If `libclang-18-dev` is not available in your repo (e.g. Ubuntu 22.04), add the LLVM apt repository first:

```bash
wget -qO- https://apt.llvm.org/llvm-snapshot.gpg.key | sudo tee /etc/apt/trusted.gpg.d/apt.llvm.org.asc
echo "deb http://apt.llvm.org/jammy/ llvm-toolchain-jammy-18 main" | sudo tee /etc/apt/sources.list.d/llvm-18.list
sudo apt update
sudo apt install libclang-18-dev
```

Replace `jammy` with your Ubuntu codename (`focal`, `noble`, etc.) if needed.

**Fedora / RHEL:**

```bash
sudo dnf install gcc cmake clang-devel
```

**Arch Linux:**

```bash
sudo pacman -S base-devel cmake clang
```

---

### Step 3 — Install PHP development headers

**Debian / Ubuntu:**

```bash
# PHP 8.4
sudo apt install php8.4-dev

# or if you only have a generic package available
sudo apt install php-dev
```

**Fedora / RHEL:**

```bash
sudo dnf install php-devel
```

**Arch Linux:**

```bash
sudo pacman -S php
```

Verify that `php-config` works and points to your intended PHP version:

```bash
php-config --version
```

If you have multiple PHP versions installed and `php-config` points to the wrong one, set it explicitly:

```bash
export PHP_CONFIG=/usr/bin/php-config8.4
```

---

### Step 4 — Set environment variables

ext-php-rs uses bindgen to parse PHP headers at build time. You need to tell it where libclang is:

```bash
# Adjust the path to match your system
export LIBCLANG_PATH=/usr/lib/llvm-18/lib

# Only needed if libclang is not in a standard location
export LD_LIBRARY_PATH="$LIBCLANG_PATH:$LD_LIBRARY_PATH"
```

Check where libclang was installed:

```bash
find /usr -name "libclang.so*" 2>/dev/null
```

If cmake is not in your PATH (e.g. installed to a custom location), add it:

```bash
export PATH="/path/to/cmake/bin:$PATH"
```

---

### Step 5 — Build

```bash
git clone https://github.com/takielias/php-rnet.git
cd php-rnet
cargo build --release
```

The first build will take a few minutes because it compiles BoringSSL from source. Subsequent builds are much faster.

Output:

```
target/release/librnet.so
```

---

### Step 6 — Load the extension

**Permanently (add to php.ini):**

```bash
php --ini   # find your php.ini
```

```ini
extension=/absolute/path/to/librnet.so
```

**Per script:**

```bash
php -d extension=/absolute/path/to/librnet.so your_script.php
```

**Verify:**

```bash
php -d extension=/absolute/path/to/librnet.so -r "var_dump(extension_loaded('php-rnet'));"
# bool(true)
```

---

### Troubleshooting

**`error: could not find tool "cc"`**

```bash
sudo apt install gcc
export PATH="/usr/bin:$PATH"
```

**`could not find libclang`**

```bash
find / -name "libclang.so*" 2>/dev/null
export LIBCLANG_PATH=/usr/lib/llvm-18/lib
```

**`Error: Could not find PHP executable`**

```bash
which php
# if missing:
sudo apt install php-cli
```

**`php-config: not found`**

```bash
sudo apt install php-dev
# or set it manually:
export PHP_CONFIG=/usr/bin/php-config8.4
```

**cmake version too old**

BoringSSL requires cmake 3.14+:

```bash
cmake --version
# install newer via cmake.org or mise:
mise install cmake@latest
export PATH="$HOME/.local/share/mise/installs/cmake/latest/bin:$PATH"
```

---

## License

MIT
