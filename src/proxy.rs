use ext_php_rs::prelude::*;
use wreq::Proxy as WreqProxy;

/// HTTP/HTTPS/SOCKS proxy configuration.
///
/// # Example
/// ```php
/// $proxy = RNet\Proxy::all('socks5://127.0.0.1:1080');
/// $proxy = RNet\Proxy::http('http://user:pass@proxy:8080');
/// ```
#[php_class]
#[php(name = "RNet\\Proxy")]
pub struct Proxy {
    pub(crate) inner: WreqProxy,
}

#[php_impl]
impl Proxy {
    /// Route all traffic through this proxy.
    pub fn all(url: &str) -> PhpResult<Self> {
        let inner = WreqProxy::all(url)
            .map_err(|e| PhpException::from_message(format!("invalid proxy URL: {e}")))?;
        Ok(Self { inner })
    }

    /// Route only HTTP traffic through this proxy.
    pub fn http(url: &str) -> PhpResult<Self> {
        let inner = WreqProxy::http(url)
            .map_err(|e| PhpException::from_message(format!("invalid proxy URL: {e}")))?;
        Ok(Self { inner })
    }

    /// Route only HTTPS traffic through this proxy.
    pub fn https(url: &str) -> PhpResult<Self> {
        let inner = WreqProxy::https(url)
            .map_err(|e| PhpException::from_message(format!("invalid proxy URL: {e}")))?;
        Ok(Self { inner })
    }

    /// Return the proxy URL as a string.
    pub fn __to_string(&self) -> String {
        format!("{:?}", self.inner)
    }
}
