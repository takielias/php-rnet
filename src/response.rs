use bytes::Bytes;
use ext_php_rs::prelude::*;
use std::collections::HashMap;
use std::sync::{Arc, Mutex};
use wreq::Response as WreqResponse;

use crate::cookie::Cookie;
use crate::error::Error;

/// HTTP response returned by all request methods.
///
/// # Example
/// ```php
/// $resp = RNet\get('https://httpbin.org/get');
/// echo $resp->status();        // 200
/// echo $resp->text();          // response body as string
/// $data = $resp->json();       // decoded JSON as PHP array
/// ```
#[php_class]
#[php(name = "RNet\\Response")]
pub struct Response {
    status: u16,
    version: String,
    url: String,
    headers: HashMap<String, String>,
    cookies: Vec<Cookie>,
    remote_addr: Option<String>,
    // body cached on first read — allows multiple calls
    body: Arc<Mutex<Option<Bytes>>>,
    // raw response for lazy body consumption
    raw: Arc<Mutex<Option<WreqResponse>>>,
    rt: Arc<tokio::runtime::Runtime>,
}

impl Response {
    pub fn new(resp: WreqResponse, rt: Arc<tokio::runtime::Runtime>) -> Self {
        let status = resp.status().as_u16();
        let version = format!("{:?}", resp.version());
        let url = resp.uri().to_string();

        let mut headers = HashMap::new();
        for (k, v) in resp.headers() {
            if let Ok(val) = v.to_str() {
                // Keep last value for duplicate headers
                headers.insert(k.as_str().to_owned(), val.to_owned());
            }
        }

        let cookies: Vec<Cookie> = resp
            .cookies()
            .map(|c| Cookie::from_wreq(&c))
            .collect();

        let remote_addr = resp.remote_addr().map(|a| a.to_string());

        Self {
            status,
            version,
            url,
            headers,
            cookies,
            remote_addr,
            body: Arc::new(Mutex::new(None)),
            raw: Arc::new(Mutex::new(Some(resp))),
            rt,
        }
    }

    fn read_body(&self) -> crate::error::Result<Bytes> {
        // Return cached bytes if already read
        {
            let cache = self.body.lock().unwrap();
            if let Some(ref b) = *cache {
                return Ok(b.clone());
            }
        }

        // Consume the raw response body
        let raw_opt = {
            let mut guard = self.raw.lock().unwrap();
            guard.take()
        };

        let bytes = match raw_opt {
            Some(resp) => self.rt.block_on(resp.bytes()).map_err(Error::from)?,
            None => return Err(Error::other("response body already consumed")),
        };

        *self.body.lock().unwrap() = Some(bytes.clone());
        Ok(bytes)
    }
}

#[php_impl]
impl Response {
    /// HTTP status code (e.g. 200, 404).
    pub fn status(&self) -> u16 {
        self.status
    }

    /// HTTP protocol version string (e.g. "HTTP/2.0").
    pub fn version(&self) -> String {
        self.version.clone()
    }

    /// Final URL after redirects.
    pub fn url(&self) -> String {
        self.url.clone()
    }

    /// All response headers as an associative array.
    pub fn headers(&self) -> HashMap<String, String> {
        self.headers.clone()
    }

    /// A single header value by name (case-insensitive lookup).
    pub fn header(&self, name: &str) -> Option<String> {
        let name_lc = name.to_lowercase();
        self.headers
            .iter()
            .find(|(k, _)| k.to_lowercase() == name_lc)
            .map(|(_, v)| v.clone())
    }

    /// Cookies set by the response as an array of Cookie objects.
    pub fn cookies(&self) -> Vec<Cookie> {
        self.cookies.clone()
    }

    /// Remote server IP address and port.
    pub fn remote_addr(&self) -> Option<String> {
        self.remote_addr.clone()
    }

    /// Response body decoded as a UTF-8 string.
    pub fn text(&self) -> PhpResult<String> {
        let bytes = self.read_body().map_err(PhpException::from)?;
        String::from_utf8(bytes.to_vec())
            .map_err(|e| PhpException::from_message(format!("UTF-8 decode error: {e}")))
    }

    /// Response body decoded as JSON. Returns a PHP array or scalar.
    pub fn json(&self) -> PhpResult<ext_php_rs::types::Zval> {
        let bytes = self.read_body().map_err(PhpException::from)?;
        let val: serde_json::Value = serde_json::from_slice(&bytes)
            .map_err(|e| PhpException::from_message(format!("JSON decode error: {e}")))?;
        json_to_zval(val)
    }

    /// Response body as raw bytes (returned as PHP string).
    pub fn bytes(&self) -> PhpResult<Vec<u8>> {
        Ok(self.read_body().map_err(PhpException::from)?.to_vec())
    }

    /// Returns true if the status code is 2xx.
    pub fn ok(&self) -> bool {
        self.status >= 200 && self.status < 300
    }

    /// Throws a `StatusException` if the status code is 4xx or 5xx.
    pub fn raise_for_status(&self) -> PhpResult<()> {
        if self.status >= 400 {
            return Err(PhpException::from_class::<crate::error::StatusException>(
                format!("HTTP error: status {}", self.status),
            ));
        }
        Ok(())
    }
}

// ---------- JSON → Zval conversion ----------

fn json_to_zval(val: serde_json::Value) -> PhpResult<ext_php_rs::types::Zval> {
    use ext_php_rs::convert::IntoZval;
    use ext_php_rs::types::Zval;
    use serde_json::Value;

    match val {
        Value::Null => {
            let mut zval = Zval::new();
            zval.set_null();
            Ok(zval)
        }
        Value::Bool(b) => {
            let mut zval = Zval::new();
            zval.set_bool(b);
            Ok(zval)
        }
        Value::Number(n) => {
            let mut zval = Zval::new();
            if let Some(i) = n.as_i64() {
                zval.set_long(i);
            } else if let Some(f) = n.as_f64() {
                zval.set_double(f);
            }
            Ok(zval)
        }
        Value::String(s) => s
            .into_zval(false)
            .map_err(|e| PhpException::from_message(format!("string conversion error: {e}"))),
        Value::Array(arr) => {
            let mut php_arr = ext_php_rs::types::ZendHashTable::new();
            for item in arr {
                let item_zval = json_to_zval(item)?;
                php_arr
                    .push(item_zval)
                    .map_err(|e| PhpException::from_message(format!("array push error: {e}")))?;
            }
            php_arr
                .into_zval(false)
                .map_err(|e| PhpException::from_message(format!("array conversion error: {e}")))
        }
        Value::Object(map) => {
            let mut php_arr = ext_php_rs::types::ZendHashTable::new();
            for (k, v) in map {
                let val_zval = json_to_zval(v)?;
                php_arr
                    .insert(k.as_str(), val_zval)
                    .map_err(|e| PhpException::from_message(format!("array insert error: {e}")))?;
            }
            php_arr
                .into_zval(false)
                .map_err(|e| PhpException::from_message(format!("array conversion error: {e}")))
        }
    }
}
