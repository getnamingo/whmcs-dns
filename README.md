# DNS hosting module for WHMCS

[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

[![SWUbanner](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner2-direct.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

DNS hosting module for WHMCS

## Supported Providers

WHMCS DNS uses **Cardo DNS 1.1+** for provider integration. See the authoritative [Cardo DNS supported providers table](https://github.com/getnamingo/cardo-dns#supported-providers) for provider availability, credentials, requirements, and DNSSEC support.

The WHMCS addon currently exposes DigitalOcean, Gandi LiveDNS, Scaleway, and all previously available Cardo DNS providers in its provider selector.

## WHMCS Module Installation instructions

### 1. Upload the Module

1. Download the latest release archive of the module.
2. Extract the archive on your local machine.
3. Upload the `whmcs_dns` directory to your WHMCS installation so the final structure is: `/modules/addons/whmcs_dns/`
4. Verify that the module files are readable by the web server user.

### 2. Activate the Addon in WHMCS

1. Log in to the **WHMCS Admin Area**.
2. Navigate to **System Settings → Addons**.
3. Locate **DNS Hosting** in the list.
4. Click **Activate**.

### (BIND9 Module only) 3. Installation of BIND9 API:

To use the BIND9 module, you must install the [bind9-api](https://github.com/getnamingo/bind9-api) on your master BIND server. This API allows for seamless integration and management of your DNS zones via API.

Make sure to configure the API according to your BIND installation parameters to ensure proper synchronization of your DNS zones.

### 4. Configure the Addon

After activating the addon, configure the module settings in **WHMCS → System Settings → Addons**:

- **DNS Provider**  
  Identifier of the Cardo DNS-supported provider  
  *(e.g. `Desec`, `PowerDNS`, `Cloudflare`, etc.)*

- **API Key**  
  API key for the selected DNS provider.

- **SOA Email**  
  Email address used in the SOA record (where applicable).

- **Nameservers (NS1–NS5)**  
  Nameservers that clients should point their domains to when using this DNS service.

- **Maximum Zones Per Client**: Set a positive number to cap manually enabled zones per client; `0` allows unlimited zones. Existing zones continue to work if the limit is later reduced.

Provider-specific settings:
- **AnycastDNS:** **AnycastDNS Server ID** is optional and defaults to `0`.
- **ClouDNS:** set **ClouDNS Auth ID** and **ClouDNS Auth Password**.
- **Scaleway:** set **Scaleway Project ID**. **Scaleway Parent Domain** is optional and normally left empty for root-zone hosting.
- **Gandi LiveDNS:** the regular **API Key** field is the token. **Gandi Sharing ID** is optional; **Bearer** authentication is recommended, with legacy **Apikey** available when needed.
- **DigitalOcean:** only the regular **API Key** field is required.

Click **Save Changes** to apply the configuration.

The Composer package name remains `namingo/plexdns` for backward compatibility, while the library namespace and project name are Cardo DNS. This module requires Cardo DNS `^1.1.0`.

### 5. Usage (Client Area)

- Clients access DNS management from their **Domain Details** page.
- A **“DNS Manager”** link appears in the domain sidebar.
- DNS zones are **not created automatically**.
- Clients must explicitly click **“Enable DNS”** to create a DNS zone.
- Once enabled, DNS records can be **added, edited, or deleted**.
- The DNS Manager domain menu shows domains from the client's WHMCS account. The zone settings cards show nameservers and, where supported, DNSSEC status and DS records. Cloudflare's nameservers are fetched for each zone.
- Remove DS records at the registrar before disabling DNSSEC, or the domain may stop resolving.
- Clicking **“Disable DNS”** removes the DNS zone from providers that expose zone deletion. Gandi LiveDNS zones cannot be removed through its Cardo DNS provider API. Scaleway managed root zones also cannot be deleted independently, but Scaleway child zones are deletable when **Scaleway Parent Domain** is configured. WHMCS only offers **Disable DNS** when the selected zone is safely deletable.

## WHMCS Module Update instructions

To update the DNS hosting module to the latest version, download the newest release and replace the existing module files.

### Manual update

1. Download the **latest release** archive from the repository.
2. Extract the archive to a temporary directory.
3. Locate the `whmcs_dns` directory inside the extracted release.
4. Copy the `whmcs_dns` directory into `/modules/addons`, **overwriting** the existing `whmcs_dns` directory.

### Update via console

From your server:

```bash
cd /tmp
wget https://github.com/getnamingo/whmcs-dns/releases/download/v1.0.2/whmcs-dns-v1.0.2.tar.gz
tar xzf whmcs-dns-v1.0.2.tar.gz
cd whmcs-dns-v1.0.2
mv whmcs_dns /path/to/whmcs/modules/addons/whmcs_dns
```

## Support

Your feedback and inquiries are invaluable to Namingo's evolutionary journey. If you need support, have questions, or want to contribute your thoughts:

- **Email**: Feel free to reach out directly at [help@namingo.org](mailto:help@namingo.org).

- **Discord**: Or chat with us on our [Discord](https://discord.gg/97R9VCrWgc) channel.
  
- **GitHub Issues**: For bug reports or feature requests, please use the [Issues](https://github.com/getnamingo/whmcs-dns/issues) section of our GitHub repository.

We appreciate your involvement and patience as Namingo continues to grow and adapt.

## Support This Project

If you find DNS hosting module for WHMCS useful, consider donating:

- [Donate via Stripe](https://donate.stripe.com/7sI2aI4jV3Offn28ww)
- BTC: `bc1q9jhxjlnzv0x4wzxfp8xzc6w289ewggtds54uqa`
- ETH: `0x330c1b148368EE4B8756B176f1766d52132f0Ea8`

## Licensing

DNS hosting module for WHMCS is licensed under the MIT License.
