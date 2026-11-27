<?php

/**
 * php-rnet integration tests.
 *
 * Run with:
 *   php -d extension=./target/release/librnet.so tests/run_tests.php
 *
 * Requires network access (uses httpbin.org).
 * Exit code 0 = all passed, 1 = failure.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Minimal test harness
// ---------------------------------------------------------------------------

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  PASS  $name\n";
        $passed++;
    } catch (Throwable $e) {
        echo "  FAIL  $name\n";
        echo "        " . $e->getMessage() . "\n";
        $failed++;
    }
}

function assert_eq(mixed $actual, mixed $expected, string $msg = ''): void {
    if ($actual !== $expected) {
        $a = var_export($actual, true);
        $e = var_export($expected, true);
        throw new RuntimeException("assert_eq failed{$msg}: got $a, expected $e");
    }
}

function assert_true(mixed $value, string $msg = ''): void {
    if (!$value) {
        throw new RuntimeException("assert_true failed: $msg");
    }
}

function assert_false(mixed $value, string $msg = ''): void {
    if ($value) {
        throw new RuntimeException("assert_false failed: $msg");
    }
}

function assert_throws(string $class, callable $fn): void {
    try {
        $fn();
        throw new RuntimeException("expected $class to be thrown, but nothing was thrown");
    } catch (Throwable $e) {
        if (!($e instanceof $class)) {
            throw new RuntimeException(
                "expected $class, got " . get_class($e) . ': ' . $e->getMessage()
            );
        }
    }
}

// ---------------------------------------------------------------------------
// Guard: extension must be loaded
// ---------------------------------------------------------------------------

if (!extension_loaded('php-rnet')) {
    echo "ERROR: php-rnet extension is not loaded.\n";
    echo "Run: php -d extension=./target/release/librnet.so tests/run_tests.php\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// 1. Extension / class / function existence
// ---------------------------------------------------------------------------

echo "\n--- Extension presence ---\n";

test('extension_loaded returns true', function () {
    assert_true(extension_loaded('php-rnet'));
});

test('RNet\\ClientBuilder class exists', function () {
    assert_true(class_exists('RNet\\ClientBuilder'));
});

test('RNet\\Client class exists', function () {
    assert_true(class_exists('RNet\\Client'));
});

test('RNet\\Response class exists', function () {
    assert_true(class_exists('RNet\\Response'));
});

test('RNet\\Cookie class exists', function () {
    assert_true(class_exists('RNet\\Cookie'));
});

test('RNet\\Proxy class exists', function () {
    assert_true(class_exists('RNet\\Proxy'));
});

test('RNet\\Emulation class exists', function () {
    assert_true(class_exists('RNet\\Emulation'));
});

test('RNet\\get function exists', function () {
    assert_true(function_exists('RNet\\get'));
});

test('RNet\\post function exists', function () {
    assert_true(function_exists('RNet\\post'));
});

test('RNet\\put function exists', function () {
    assert_true(function_exists('RNet\\put'));
});

test('RNet\\patch function exists', function () {
    assert_true(function_exists('RNet\\patch'));
});

test('RNet\\delete function exists', function () {
    assert_true(function_exists('RNet\\delete'));
});

test('RNet\\head function exists', function () {
    assert_true(function_exists('RNet\\head'));
});

test('RNet\\request function exists', function () {
    assert_true(function_exists('RNet\\request'));
});

// ---------------------------------------------------------------------------
// 2. Emulation constants
// ---------------------------------------------------------------------------

echo "\n--- Emulation constants ---\n";

test('CHROME_136 constant value', function () {
    assert_eq(RNet\Emulation::CHROME_136, 'Chrome136');
});

test('FIREFOX_139 constant value', function () {
    assert_eq(RNet\Emulation::FIREFOX_139, 'Firefox139');
});

test('SAFARI_26 constant value', function () {
    assert_eq(RNet\Emulation::SAFARI_26, 'Safari26');
});

test('EDGE_137 constant value', function () {
    assert_eq(RNet\Emulation::EDGE_137, 'Edge137');
});

test('OPERA_119 constant value', function () {
    assert_eq(RNet\Emulation::OPERA_119, 'Opera119');
});

test('OK_HTTP_5 constant value', function () {
    assert_eq(RNet\Emulation::OK_HTTP_5, 'OkHttp5');
});

test('SAFARI_IOS_26 constant value', function () {
    assert_eq(RNet\Emulation::SAFARI_IOS_26, 'SafariIos26');
});

// ---------------------------------------------------------------------------
// 3. ClientBuilder — construction
// ---------------------------------------------------------------------------

echo "\n--- ClientBuilder ---\n";

test('ClientBuilder constructs without error', function () {
    $b = new RNet\ClientBuilder();
    assert_true($b instanceof RNet\ClientBuilder);
});

test('build() returns a Client', function () {
    $client = (new RNet\ClientBuilder())->build();
    assert_true($client instanceof RNet\Client);
});

test('impersonate() with valid profile builds', function () {
    $b = new RNet\ClientBuilder();
    $b->impersonate(RNet\Emulation::CHROME_136);
    assert_true($b->build() instanceof RNet\Client);
});

test('impersonate() with unknown profile throws', function () {
    assert_throws(Exception::class, function () {
        $b = new RNet\ClientBuilder();
        $b->impersonate('NotABrowser999');
    });
});

test('verify(false) builds', function () {
    $b = new RNet\ClientBuilder();
    $b->verify(false);
    assert_true($b->build() instanceof RNet\Client);
});

test('timeout(30.0) builds', function () {
    $b = new RNet\ClientBuilder();
    $b->timeout(30.0);
    assert_true($b->build() instanceof RNet\Client);
});

test('timeout(0.0) disables timeout and builds', function () {
    $b = new RNet\ClientBuilder();
    $b->timeout(0.0);
    assert_true($b->build() instanceof RNet\Client);
});

test('connectTimeout(5.0) builds', function () {
    $b = new RNet\ClientBuilder();
    $b->connectTimeout(5.0);
    assert_true($b->build() instanceof RNet\Client);
});

test('maxRedirects(0) builds', function () {
    $b = new RNet\ClientBuilder();
    $b->maxRedirects(0);
    assert_true($b->build() instanceof RNet\Client);
});

test('maxRedirects(5) builds', function () {
    $b = new RNet\ClientBuilder();
    $b->maxRedirects(5);
    assert_true($b->build() instanceof RNet\Client);
});

test('cookieStore(true) builds', function () {
    $b = new RNet\ClientBuilder();
    $b->cookieStore(true);
    assert_true($b->build() instanceof RNet\Client);
});

test('http1Only(true) builds', function () {
    $b = new RNet\ClientBuilder();
    $b->http1Only(true);
    assert_true($b->build() instanceof RNet\Client);
});

test('defaultHeader() builds', function () {
    $b = new RNet\ClientBuilder();
    $b->defaultHeader('X-Test', 'value');
    assert_true($b->build() instanceof RNet\Client);
});

test('userAgent() builds', function () {
    $b = new RNet\ClientBuilder();
    $b->userAgent('TestAgent/1.0');
    assert_true($b->build() instanceof RNet\Client);
});

// ---------------------------------------------------------------------------
// 4. Proxy construction
// ---------------------------------------------------------------------------

echo "\n--- Proxy ---\n";

test('Proxy::all() with socks5 URL', function () {
    assert_true(RNet\Proxy::all('socks5://127.0.0.1:1080') instanceof RNet\Proxy);
});

test('Proxy::http() with HTTP URL', function () {
    assert_true(RNet\Proxy::http('http://127.0.0.1:8080') instanceof RNet\Proxy);
});

test('Proxy::https() with HTTPS URL', function () {
    assert_true(RNet\Proxy::https('https://127.0.0.1:8080') instanceof RNet\Proxy);
});

test('Proxy::all() with invalid URL throws', function () {
    assert_throws(Exception::class, function () {
        RNet\Proxy::all('not-a-url');
    });
});

test('Proxy::http() with invalid URL throws', function () {
    assert_throws(Exception::class, function () {
        RNet\Proxy::http('not-a-url');
    });
});

// ---------------------------------------------------------------------------
// 5. HTTP request methods
// ---------------------------------------------------------------------------

echo "\n--- HTTP request methods ---\n";

test('RNet\\get() returns 200', function () {
    $resp = RNet\get('https://httpbin.org/get');
    assert_eq($resp->status(), 200);
});

test('RNet\\post() returns 200', function () {
    $resp = RNet\post('https://httpbin.org/post');
    assert_eq($resp->status(), 200);
});

test('RNet\\put() returns 200', function () {
    $resp = RNet\put('https://httpbin.org/put');
    assert_eq($resp->status(), 200);
});

test('RNet\\patch() returns 200', function () {
    $resp = RNet\patch('https://httpbin.org/patch');
    assert_eq($resp->status(), 200);
});

test('RNet\\delete() returns 200', function () {
    $resp = RNet\delete('https://httpbin.org/delete');
    assert_eq($resp->status(), 200);
});

test('RNet\\head() returns 200 with empty body', function () {
    $resp = RNet\head('https://httpbin.org/get');
    assert_eq($resp->status(), 200);
    assert_eq($resp->text(), '');
});

test('RNet\\request() with GET method returns 200', function () {
    $resp = RNet\request('GET', 'https://httpbin.org/get');
    assert_eq($resp->status(), 200);
});

test('RNet\\request() with invalid method throws', function () {
    assert_throws(Exception::class, function () {
        RNet\request('NOT VALID METHOD', 'https://httpbin.org/get');
    });
});

// ---------------------------------------------------------------------------
// 6. Request options
// ---------------------------------------------------------------------------

echo "\n--- Request options ---\n";

test('query params are appended to URL', function () {
    $resp = RNet\get('https://httpbin.org/get', [
        'query' => ['foo' => 'bar', 'page' => '2'],
    ]);
    $data = $resp->json();
    assert_eq($data['args']['foo'] ?? null, 'bar');
    assert_eq($data['args']['page'] ?? null, '2');
});

function resp_info(RNet\Response $resp): string {
    $body = preg_replace('/\s+/', ' ', $resp->text());
    return sprintf(' (status %d, body: %s)', $resp->status(), substr($body, 0, 200));
}

// httpbin.org rate-limits body-echo requests per client IP and answers 429.
function post_echo(array $options): RNet\Response {
    for ($attempt = 1; ; $attempt++) {
        $resp = RNet\post('https://httpbin.org/post', $options);
        if ($resp->status() !== 429 || $attempt > 3) {
            return $resp;
        }
        sleep(1);
    }
}

test('json option sets body and Content-Type', function () {
    $resp = post_echo([
        'json' => ['hello' => 'world', 'num' => 42],
    ]);
    $data = $resp->json();
    assert_eq($data['json']['hello'] ?? null, 'world', resp_info($resp));
    assert_eq($data['json']['num'] ?? null, 42, resp_info($resp));
});

test('form option sets body and Content-Type', function () {
    $resp = post_echo([
        'form' => ['field' => 'value', 'other' => 'data'],
    ]);
    $data = $resp->json();
    assert_eq($data['form']['field'] ?? null, 'value', resp_info($resp));
    assert_eq($data['form']['other'] ?? null, 'data', resp_info($resp));
});

test('body option sends raw string body', function () {
    $resp = post_echo([
        'headers' => ['Content-Type' => 'text/plain'],
        'body'    => 'raw payload here',
    ]);
    $data = $resp->json();
    assert_eq($data['data'] ?? null, 'raw payload here', resp_info($resp));
});

test('per-request headers are sent', function () {
    $resp = RNet\get('https://httpbin.org/headers', [
        'headers' => ['X-Custom-Header' => 'test-value'],
    ]);
    $data = $resp->json();
    assert_eq($data['headers']['X-Custom-Header'] ?? null, 'test-value');
});

test('per-request timeout option is accepted', function () {
    // Just verify it doesn't throw; actual timeout behaviour requires a slow server
    $resp = RNet\get('https://httpbin.org/get', ['timeout' => 30.0]);
    assert_eq($resp->status(), 200);
});

// ---------------------------------------------------------------------------
// 7. Response methods
// ---------------------------------------------------------------------------

echo "\n--- Response methods ---\n";

test('status() returns int', function () {
    $resp = RNet\get('https://httpbin.org/get');
    assert_true(is_int($resp->status()));
    assert_eq($resp->status(), 200);
});

test('ok() true for 2xx', function () {
    assert_true(RNet\get('https://httpbin.org/status/200')->ok());
    assert_true(RNet\get('https://httpbin.org/status/201')->ok());
});

test('ok() false for non-2xx', function () {
    // Use a no-redirect client so 3xx is not followed
    $b = new RNet\ClientBuilder();
    $b->maxRedirects(0);
    $client = $b->build();
    assert_false($client->get('https://httpbin.org/status/301')->ok());
    assert_false(RNet\get('https://httpbin.org/status/404')->ok());
    assert_false(RNet\get('https://httpbin.org/status/500')->ok());
});

test('version() returns HTTP version string', function () {
    $v = RNet\get('https://httpbin.org/get')->version();
    assert_true(is_string($v) && strlen($v) > 0, "expected version string, got: $v");
    assert_true(str_starts_with($v, 'HTTP/'), "expected HTTP/ prefix, got: $v");
});

test('url() returns a non-empty string', function () {
    $url = RNet\get('https://httpbin.org/get')->url();
    assert_true(is_string($url) && strlen($url) > 0);
});

test('text() returns body as string', function () {
    $text = RNet\get('https://httpbin.org/get')->text();
    assert_true(is_string($text) && strlen($text) > 0);
});

test('text() can be called multiple times (cached)', function () {
    $resp = RNet\get('https://httpbin.org/get');
    $first  = $resp->text();
    $second = $resp->text();
    assert_eq($first, $second, 'second call should return same cached body');
});

test('json() decodes body as PHP array', function () {
    $data = RNet\get('https://httpbin.org/get')->json();
    assert_true(is_array($data));
    assert_true(isset($data['url']));
});

test('bytes() returns raw byte array', function () {
    $bytes = RNet\get('https://httpbin.org/get')->bytes();
    assert_true(is_array($bytes) && count($bytes) > 0);
    // Every element should be an integer 0–255
    assert_true(is_int($bytes[0]));
});

test('headers() returns non-empty associative array', function () {
    $headers = RNet\get('https://httpbin.org/get')->headers();
    assert_true(is_array($headers) && count($headers) > 0);
});

test('header() case-insensitive lookup', function () {
    $resp = RNet\get('https://httpbin.org/get');
    $lower = $resp->header('content-type');
    $upper = $resp->header('Content-Type');
    $mixed = $resp->header('CONTENT-TYPE');
    assert_true($lower !== null, 'lowercase lookup failed');
    assert_eq($lower, $upper);
    assert_eq($lower, $mixed);
});

test('header() returns null for missing header', function () {
    $resp = RNet\get('https://httpbin.org/get');
    assert_eq($resp->header('x-does-not-exist-xyz'), null);
});

test('remoteAddr() returns a string or null', function () {
    $addr = RNet\get('https://httpbin.org/get')->remoteAddr();
    // May be null depending on the environment, but must be string if set
    assert_true($addr === null || is_string($addr));
});

// ---------------------------------------------------------------------------
// 8. Response cookies
// ---------------------------------------------------------------------------

echo "\n--- Response cookies ---\n";

test('cookies() returns array', function () {
    // httpbin /cookies/set redirects and sets cookies; follow with cookieStore
    $b = new RNet\ClientBuilder();
    $b->cookieStore(true);
    $client = $b->build();
    $client->get('https://httpbin.org/cookies/set/testname/testvalue');
    $resp = $client->get('https://httpbin.org/cookies');
    $data = $resp->json();
    assert_eq($data['cookies']['testname'] ?? null, 'testvalue');
});

test('response cookies() from Set-Cookie header', function () {
    // httpbin /cookies/set without following redirects returns Set-Cookie
    $b = new RNet\ClientBuilder();
    $b->maxRedirects(0);
    $client = $b->build();
    $resp = $client->get('https://httpbin.org/cookies/set/mykey/myval');
    $cookies = $resp->cookies();
    assert_true(is_array($cookies));
    if (count($cookies) > 0) {
        $c = $cookies[0];
        assert_true($c instanceof RNet\Cookie);
        assert_true(is_string($c->getName()) && strlen($c->getName()) > 0);
        assert_true(is_string($c->getValue()));
        // domain/path may or may not be set
        assert_true($c->getDomain() === null || is_string($c->getDomain()));
        assert_true($c->getPath() === null || is_string($c->getPath()));
        assert_true(is_bool($c->isHttpOnly()));
        assert_true(is_bool($c->isSecure()));
        // __toString
        $str = (string) $c;
        assert_true(str_contains($str, '='), '__toString should be name=value');
    }
});

// ---------------------------------------------------------------------------
// 9. Status helpers
// ---------------------------------------------------------------------------

echo "\n--- Status helpers ---\n";

test('raiseForStatus() passes on 200', function () {
    RNet\get('https://httpbin.org/status/200')->raiseForStatus();
    assert_true(true);
});

test('raiseForStatus() throws StatusException on 400', function () {
    assert_throws(RNet\StatusException::class, function () {
        RNet\get('https://httpbin.org/status/400')->raiseForStatus();
    });
});

test('raiseForStatus() throws StatusException on 404', function () {
    assert_throws(RNet\StatusException::class, function () {
        RNet\get('https://httpbin.org/status/404')->raiseForStatus();
    });
});

test('raiseForStatus() throws StatusException on 500', function () {
    assert_throws(RNet\StatusException::class, function () {
        RNet\get('https://httpbin.org/status/500')->raiseForStatus();
    });
});

// ---------------------------------------------------------------------------
// 10. Exception hierarchy (all 9 classes)
// ---------------------------------------------------------------------------

echo "\n--- Exception hierarchy ---\n";

$exceptionClasses = [
    'RNet\\RequestException',
    'RNet\\ConnectionException',
    'RNet\\TlsException',
    'RNet\\TimeoutException',
    'RNet\\StatusException',
    'RNet\\BodyException',
    'RNet\\DecodingException',
    'RNet\\RedirectException',
    'RNet\\WebSocketException',
];

foreach ($exceptionClasses as $class) {
    test("$class extends \\Exception", function () use ($class) {
        assert_true(class_exists($class), "$class does not exist");
        $r = new ReflectionClass($class);
        assert_true($r->isSubclassOf(Exception::class), "$class does not extend Exception");
    });
}

// ---------------------------------------------------------------------------
// 11. defaultHeader and userAgent are actually sent
// ---------------------------------------------------------------------------

echo "\n--- Client-level headers ---\n";

test('defaultHeader() is sent on every request', function () {
    $b = new RNet\ClientBuilder();
    $b->defaultHeader('X-Default-Header', 'default-val');
    $client = $b->build();

    $resp = $client->get('https://httpbin.org/headers');
    $data = $resp->json();
    assert_eq($data['headers']['X-Default-Header'] ?? null, 'default-val');
});

test('userAgent() is sent as User-Agent header', function () {
    $b = new RNet\ClientBuilder();
    $b->userAgent('MyTestBot/2.0');
    $client = $b->build();

    $resp = $client->get('https://httpbin.org/headers');
    $data = $resp->json();
    assert_eq($data['headers']['User-Agent'] ?? null, 'MyTestBot/2.0');
});

test('per-request header overrides defaultHeader', function () {
    $b = new RNet\ClientBuilder();
    $b->defaultHeader('X-Key', 'default');
    $client = $b->build();

    $resp = $client->get('https://httpbin.org/headers', [
        'headers' => ['X-Key' => 'override'],
    ]);
    $data = $resp->json();
    assert_eq($data['headers']['X-Key'] ?? null, 'override');
});

// ---------------------------------------------------------------------------
// 12. Redirects
// ---------------------------------------------------------------------------

echo "\n--- Redirects ---\n";

test('redirects are followed by default', function () {
    // httpbin /redirect/1 issues one 302 redirect to /get
    $resp = RNet\get('https://httpbin.org/redirect/1');
    assert_eq($resp->status(), 200);
});

test('maxRedirects(0) does not follow redirects', function () {
    $b = new RNet\ClientBuilder();
    $b->maxRedirects(0);
    $client = $b->build();

    $resp = $client->get('https://httpbin.org/redirect/1');
    // Should get the 302 itself, not the final 200
    assert_true($resp->status() >= 300 && $resp->status() < 400);
});

// ---------------------------------------------------------------------------
// 13. Cookie persistence across requests
// ---------------------------------------------------------------------------

echo "\n--- Cookie persistence ---\n";

test('cookieStore persists cookies across requests', function () {
    $b = new RNet\ClientBuilder();
    $b->cookieStore(true);
    $client = $b->build();

    // First request sets a cookie via redirect; follow it
    $client->get('https://httpbin.org/cookies/set/session/abc123');

    // Second request should include the cookie
    $resp = $client->get('https://httpbin.org/cookies');
    $data = $resp->json();
    assert_eq($data['cookies']['session'] ?? null, 'abc123');
});

test('without cookieStore cookies are not persisted', function () {
    $client = (new RNet\ClientBuilder())->build();

    $client->get('https://httpbin.org/cookies/set/session/abc123');

    $resp = $client->get('https://httpbin.org/cookies');
    $data = $resp->json();
    // Without a cookie jar the session cookie should not be present
    assert_true(!isset($data['cookies']['session']));
});

// ---------------------------------------------------------------------------
// 14. Browser emulation
// ---------------------------------------------------------------------------

echo "\n--- Browser emulation ---\n";

test('Chrome136 emulation request succeeds', function () {
    $b = new RNet\ClientBuilder();
    $b->impersonate(RNet\Emulation::CHROME_136);
    $b->timeout(30.0);
    $resp = $b->build()->get('https://httpbin.org/get');
    assert_eq($resp->status(), 200);
});

test('Firefox139 emulation request succeeds', function () {
    $b = new RNet\ClientBuilder();
    $b->impersonate(RNet\Emulation::FIREFOX_139);
    $b->timeout(30.0);
    $resp = $b->build()->get('https://httpbin.org/get');
    assert_eq($resp->status(), 200);
});

test('Safari26 emulation request succeeds', function () {
    $b = new RNet\ClientBuilder();
    $b->impersonate(RNet\Emulation::SAFARI_26);
    $b->timeout(30.0);
    $resp = $b->build()->get('https://httpbin.org/get');
    assert_eq($resp->status(), 200);
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "\n";
echo str_repeat('-', 40) . "\n";
echo "Results: $passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
