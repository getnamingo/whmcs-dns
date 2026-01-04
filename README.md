# DNS hosting module for WHMCS

[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

[![SWUbanner](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner2-direct.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

DNS hosting module for WHMCS

## Supported Providers

Most DNS providers **require an API key**, while some may need **additional settings** such as authentication credentials or specific server configurations. All required values must be set in the `.env` file.

| Provider    | Credentials in .env | Requirements  | Status | DNSSEC |
|------------|---------------------|------------|---------------------|---------------------|
| **AnycastDNS** | `API_KEY` | | ✅ | ❌ |
| **Bind9** | `API_KEY:BIND_IP` | [bind9-api-server](https://github.com/getnamingo/bind9-api-server)/[bind9-api-server-sqlite](https://github.com/getnamingo/bind9-api-server-sqlite) | ✅ | 🚧 |
| **Cloudflare** | `EMAIL:API_KEY` or `API_TOKEN` | | ✅ | ❌ |
| **ClouDNS** | `AUTH_ID:AUTH_PASSWORD` | | ✅ | ✅ |
| **Desec** | `API_KEY` | | ✅ | ✅ |
| **DNSimple** | `API_KEY` | | ✅ | ❌ |
| **Hetzner** | `API_KEY` | | 🚧 | ❌ |
| **PowerDNS** | `API_KEY:POWERDNS_IP` | gmysql-dnssec=yes in pdns.conf | ✅ | ✅ |
| **Vultr** | `API_KEY` | | ✅ | ❌ |

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

### (BIND9 Module only) 3. Installation of BIND9 API Server:

To use the BIND9 module, you must install the [bind9-api-server](https://github.com/getnamingo/bind9-api-server) on your master BIND server. This API server allows for seamless integration and management of your DNS zones via API.

Make sure to configure the API server according to your BIND installation parameters to ensure proper synchronization of your DNS zones.

### 4. Configure the Addon

After activating the addon, configure the module settings in **WHMCS → System Settings → Addons**:

- **DNS Provider**  
  Identifier of the PlexDNS-supported provider  
  *(e.g. `Desec`, `PowerDNS`, `Cloudflare`, etc.)*

- **API Key**  
  API key for the selected DNS provider.

- **SOA Email**  
  Email address used in the SOA record (where applicable).

- **Nameservers (NS1–NS5)**  
  Nameservers that clients should point their domains to when using this DNS service.

Click **Save Changes** to apply the configuration.

### 5. Usage (Client Area)

- Clients access DNS management from their **Domain Details** page.
- A **“DNS Manager”** link appears in the domain sidebar.
- DNS zones are **not created automatically**.
- Clients must explicitly click **“Enable DNS”** to create a DNS zone.
- Once enabled, DNS records can be **added, edited, or deleted**.
- Clicking **“Disable DNS”** removes (deletes) the DNS zone from the provider.

## Support

Your feedback and inquiries are invaluable to Namingo's evolutionary journey. If you need support, have questions, or want to contribute your thoughts:

- **Email**: Feel free to reach out directly at [help@namingo.org](mailto:help@namingo.org).

- **Discord**: Or chat with us on our [Discord](https://discord.gg/97R9VCrWgc) channel.
  
- **GitHub Issues**: For bug reports or feature requests, please use the [Issues](https://github.com/getnamingo/whmcs-dns/issues) section of our GitHub repository.

We appreciate your involvement and patience as Namingo continues to grow and adapt.

## 💖 Support This Project

If you find DNS hosting module for WHMCS useful, consider donating:

- [Donate via Stripe](https://donate.stripe.com/7sI2aI4jV3Offn28ww)
- BTC: `bc1q9jhxjlnzv0x4wzxfp8xzc6w289ewggtds54uqa`
- ETH: `0x330c1b148368EE4B8756B176f1766d52132f0Ea8`

## Licensing

DNS hosting module for WHMCS is licensed under the MIT License.