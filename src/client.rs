use std::collections::HashMap;
use std::sync::Arc;
use std::time::Duration;

use ext_php_rs::prelude::*;
use ext_php_rs::types::ZendHashTable;
use wreq_util::Emulation as WreqEmulation;

use crate::emulation::parse_emulation;
use crate::error::{Error, Result};
use crate::proxy::Proxy;
use crate::response::Response;

// ---------- ClientBuilder ----------

/// Fluent builder for Client.
///
/// # Example
/// ```php
/// $b = new RNet\ClientBuilder();
/// $b->impersonate(RNet\Emulation::CHROME_136);
/// $b->timeout(30.0);
/// $b->verify(false);
/// $client = $b->build();
/// ```
#[php_class]
#[php(name = "RNet\\ClientBuilder")]
pub struct ClientBuilder {
    emulation: Option<WreqEmulation>,
    timeout: Option<Duration>,
    connect_timeout: Option<Duration>,
    proxy: Option<wreq::Proxy>,
    cert_verification: bool,
    verify_hostname: bool,
    http1_only: bool,
    default_headers: HashMap<String, String>,
    user_agent: Option<String>,
    max_redirects: Option<usize>,
    follow_redirects: bool,
    cookie_store: bool,
}

#[php_impl]
impl ClientBuilder {
    pub fn __construct() -> Self {
        Self {
            emulation: None,
            timeout: None,
            connect_timeout: None,
            proxy: None,
            cert_verification: true,
            verify_hostname: true,
            http1_only: false,
            default_headers: HashMap::new(),
            user_agent: None,
            max_redirects: Some(10),
            follow_redirects: true,
            cookie_store: false,
        }
    }

    /// Set the browser emulation profile.
    pub fn impersonate(&mut self, profile: &str) -> PhpResult<()> {
        self.emulation = Some(
            parse_emulation(profile)
                .ok_or_else(|| PhpException::from_message(format!("unknown emulation profile: {profile}")))?,
        );
        Ok(())
    }

    /// Total request timeout in seconds (0 = disabled).
    pub fn timeout(&mut self, secs: f64) {
        self.timeout = if secs > 0.0 {
            Some(Duration::from_secs_f64(secs))
        } else {
            None
        };
    }

    /// Connection timeout in seconds.
    pub fn connect_timeout(&mut self, secs: f64) {
        self.connect_timeout = if secs > 0.0 {
            Some(Duration::from_secs_f64(secs))
        } else {
            None
        };
    }

    /// Set a proxy. Pass an `RNet\Proxy` instance.
    pub fn proxy(&mut self, proxy: &Proxy) {
        self.proxy = Some(proxy.inner.clone());
    }

    /// Whether to verify TLS certificates (default: true).
    pub fn verify(&mut self, verify: bool) {
        self.cert_verification = verify;
        self.verify_hostname = verify;
    }

    /// Force HTTP/1 only (disables HTTP/2 upgrade).
    pub fn http1_only(&mut self, enable: bool) {
        self.http1_only = enable;
    }

    /// Add a default header sent on every request.
    pub fn default_header(&mut self, name: &str, value: &str) {
        self.default_headers.insert(name.to_owned(), value.to_owned());
    }

    /// Override the User-Agent header.
    pub fn user_agent(&mut self, ua: &str) {
        self.user_agent = Some(ua.to_owned());
    }

    /// Maximum number of redirects to follow (0 disables redirects).
    pub fn max_redirects(&mut self, n: i64) {
        if n <= 0 {
            self.follow_redirects = false;
            self.max_redirects = Some(0);
        } else {
            self.follow_redirects = true;
            self.max_redirects = Some(n as usize);
        }
    }

    /// Enable a per-client cookie jar.
    pub fn cookie_store(&mut self, enable: bool) {
        self.cookie_store = enable;
    }

    /// Build and return the configured `Client`.
    pub fn build(&self) -> PhpResult<Client> {
        Client::from_builder(self)
    }
}

// ---------- Client ----------

/// Reusable HTTP client. Shares a connection pool and settings across requests.
///
/// # Example
/// ```php
/// $b = new RNet\ClientBuilder();
/// $b->impersonate(RNet\Emulation::CHROME_136);
/// $client = $b->build();
///
/// $resp = $client->get('https://httpbin.org/get');
/// echo $resp->text();
/// ```
#[php_class]
#[php(name = "RNet\\Client")]
pub struct Client {
    pub(crate) inner: wreq::Client,
    pub(crate) rt: Arc<tokio::runtime::Runtime>,
}

impl Client {
    pub fn from_builder(b: &ClientBuilder) -> PhpResult<Self> {
        let rt = tokio::runtime::Runtime::new()
            .map_err(|e| PhpException::from_message(format!("tokio runtime error: {e}")))?;

        let mut builder = wreq::Client::builder();

        if let Some(emulation) = b.emulation.clone() {
            builder = builder.emulation(emulation);
        }

        if let Some(t) = b.timeout {
            builder = builder.timeout(t);
        }

        if let Some(t) = b.connect_timeout {
            builder = builder.connect_timeout(t);
        }

        if let Some(ref p) = b.proxy {
            builder = builder.proxy(p.clone());
        }

        if !b.cert_verification {
            builder = builder.cert_verification(false);
        }

        if !b.verify_hostname {
            builder = builder.verify_hostname(false);
        }

        if b.http1_only {
            builder = builder.http1_only();
        }

        if b.cookie_store {
            builder = builder.cookie_store(true);
        }

        if !b.default_headers.is_empty() || b.user_agent.is_some() {
            let mut header_map = wreq::header::HeaderMap::new();
            for (k, v) in &b.default_headers {
                if let (Ok(name), Ok(val)) = (
                    wreq::header::HeaderName::from_bytes(k.as_bytes()),
                    wreq::header::HeaderValue::from_str(v),
                ) {
                    header_map.insert(name, val);
                }
            }
            if let Some(ref ua) = b.user_agent {
                if let Ok(val) = wreq::header::HeaderValue::from_str(ua) {
                    header_map.insert(wreq::header::USER_AGENT, val);
                }
            }
            builder = builder.default_headers(header_map);
        }

        if !b.follow_redirects {
            builder = builder.redirect(wreq::redirect::Policy::none());
        } else if let Some(max) = b.max_redirects {
            builder = builder.redirect(wreq::redirect::Policy::limited(max));
        }

        let inner = builder
            .build()
            .map_err(|e| PhpException::from_message(format!("client build error: {e}")))?;

        Ok(Self {
            inner,
            rt: Arc::new(rt),
        })
    }

    fn execute(&self, req: wreq::Request) -> Result<Response> {
        let resp = self.rt.block_on(self.inner.execute(req))?;
        Ok(Response::new(resp, self.rt.clone()))
    }

    pub(crate) fn build_request(
        &self,
        method: wreq::Method,
        url: &str,
        options: Option<&ZendHashTable>,
    ) -> Result<wreq::Request> {
        let mut rb = self.inner.request(method, url);

        if let Some(opts) = options {
            // headers
            if let Some(zv) = opts.get("headers") {
                if let Some(arr) = zv.array() {
                    for (k, v) in arr.iter() {
                        let key = k.to_string();
                        let val = v.str().unwrap_or("").to_owned();
                        if let (Ok(name), Ok(hval)) = (
                            wreq::header::HeaderName::from_bytes(key.as_bytes()),
                            wreq::header::HeaderValue::from_str(&val),
                        ) {
                            rb = rb.header(name, hval);
                        }
                    }
                }
            }

            // query params
            if let Some(zv) = opts.get("query") {
                if let Some(arr) = zv.array() {
                    let params: Vec<(String, String)> = arr
                        .iter()
                        .map(|(k, v)| (k.to_string(), v.str().unwrap_or("").to_owned()))
                        .collect();
                    rb = rb.query(&params);
                }
            }

            // per-request timeout override
            if let Some(zv) = opts.get("timeout") {
                if let Some(secs) = zv.double() {
                    if secs > 0.0 {
                        rb = rb.timeout(Duration::from_secs_f64(secs));
                    }
                }
            }

            // body variants (mutually exclusive, first match wins)
            if let Some(zv) = opts.get("body") {
                if let Some(s) = zv.str() {
                    rb = rb.body(s.to_owned());
                }
            } else if let Some(zv) = opts.get("json") {
                let json_str = zval_to_json(zv)?;
                rb = rb
                    .header(
                        wreq::header::CONTENT_TYPE,
                        wreq::header::HeaderValue::from_static("application/json"),
                    )
                    .body(json_str);
            } else if let Some(zv) = opts.get("form") {
                if let Some(arr) = zv.array() {
                    let pairs: Vec<(String, String)> = arr
                        .iter()
                        .map(|(k, v)| (k.to_string(), v.str().unwrap_or("").to_owned()))
                        .collect();
                    let encoded = serde_urlencoded::to_string(&pairs).map_err(Error::from)?;
                    rb = rb
                        .header(
                            wreq::header::CONTENT_TYPE,
                            wreq::header::HeaderValue::from_static(
                                "application/x-www-form-urlencoded",
                            ),
                        )
                        .body(encoded);
                }
            }
        }

        Ok(rb.build().map_err(Error::from)?)
    }
}

#[php_impl]
impl Client {
    /// Send a GET request.
    pub fn get(&self, url: &str, options: Option<&ZendHashTable>) -> PhpResult<Response> {
        let req = self.build_request(wreq::Method::GET, url, options).map_err(PhpException::from)?;
        self.execute(req).map_err(PhpException::from)
    }

    /// Send a POST request.
    pub fn post(&self, url: &str, options: Option<&ZendHashTable>) -> PhpResult<Response> {
        let req = self.build_request(wreq::Method::POST, url, options).map_err(PhpException::from)?;
        self.execute(req).map_err(PhpException::from)
    }

    /// Send a PUT request.
    pub fn put(&self, url: &str, options: Option<&ZendHashTable>) -> PhpResult<Response> {
        let req = self.build_request(wreq::Method::PUT, url, options).map_err(PhpException::from)?;
        self.execute(req).map_err(PhpException::from)
    }

    /// Send a PATCH request.
    pub fn patch(&self, url: &str, options: Option<&ZendHashTable>) -> PhpResult<Response> {
        let req = self.build_request(wreq::Method::PATCH, url, options).map_err(PhpException::from)?;
        self.execute(req).map_err(PhpException::from)
    }

    /// Send a DELETE request.
    pub fn delete(&self, url: &str, options: Option<&ZendHashTable>) -> PhpResult<Response> {
        let req = self.build_request(wreq::Method::DELETE, url, options).map_err(PhpException::from)?;
        self.execute(req).map_err(PhpException::from)
    }

    /// Send a HEAD request.
    pub fn head(&self, url: &str, options: Option<&ZendHashTable>) -> PhpResult<Response> {
        let req = self.build_request(wreq::Method::HEAD, url, options).map_err(PhpException::from)?;
        self.execute(req).map_err(PhpException::from)
    }

    /// Send a request with any HTTP method.
    pub fn request(&self, method: &str, url: &str, options: Option<&ZendHashTable>) -> PhpResult<Response> {
        let m = wreq::Method::from_bytes(method.as_bytes())
            .map_err(|_| PhpException::from_message(format!("invalid HTTP method: {method}")))?;
        let req = self.build_request(m, url, options).map_err(PhpException::from)?;
        self.execute(req).map_err(PhpException::from)
    }
}

// ---------- helpers ----------

fn zval_to_json(zv: &ext_php_rs::types::Zval) -> Result<String> {
    let json_val = zval_to_serde(zv);
    Ok(serde_json::to_string(&json_val).map_err(Error::from)?)
}

fn zval_to_serde(zv: &ext_php_rs::types::Zval) -> serde_json::Value {
    use serde_json::Value;
    if zv.is_null() {
        return Value::Null;
    }
    if let Some(b) = zv.bool() {
        return Value::Bool(b);
    }
    if let Some(i) = zv.long() {
        return Value::Number(i.into());
    }
    if let Some(f) = zv.double() {
        if let Some(n) = serde_json::Number::from_f64(f) {
            return Value::Number(n);
        }
    }
    if let Some(s) = zv.str() {
        return Value::String(s.to_owned());
    }
    if let Some(arr) = zv.array() {
        let is_list = arr.has_sequential_keys();
        if is_list {
            let items: Vec<Value> = arr.iter().map(|(_, v)| zval_to_serde(v)).collect();
            return Value::Array(items);
        } else {
            let map: serde_json::Map<String, Value> = arr
                .iter()
                .map(|(k, v)| (k.to_string(), zval_to_serde(v)))
                .collect();
            return Value::Object(map);
        }
    }
    Value::Null
}
