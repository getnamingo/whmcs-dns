# DNS hosting as a paid or free WHMCS product, backed by PlexDNS 1.0.15 or later in the 1.x series. Zones are provisioned when a product is activated and removed on termination or direct service deletion. A domain registered elsewhere works too.

## Install and configure

1. Copy `whmcs_dns/` to `modules/addons/whmcs_dns/` and `whmcs_dns_product/` to `modules/servers/whmcs_dns_product/`.
2. In `modules/addons/whmcs_dns/`, run `composer install --no-dev` with PHP 8.3+; this installs PlexDNS and its provider libraries. Activate **DNS Hosting** in WHMCS System Settings → Addon Modules. An update from 1.0.x adds a nullable `service_id` to the existing `zones` table without deleting old zones.
3. Configure the provider, credentials, optional SOA email, API endpoint for BIND/PowerDNS, and **NS1–NS5** in the addon settings. ClouDNS uses its separate Auth ID and Auth Password. For Cloudflare, use an API token or `email:global_api_key` in API Key; the client area fetches its per-zone assigned nameservers. Configure nameservers explicitly for other providers so they can be shown to customers.
4. Create a WHMCS product of type **Other**, set **Require Domain**, select **Namingo DNS Hosting** as the module, and set its price. A zero price makes it free. Offer the DNS product alongside a registration or let customers use a domain they already own. Configure automatic module activation after the desired payment or acceptance stage, and enable termination automation as desired.
5. Customers manage records and DNSSEC via their product or DNS Manager. DNSSEC displays the provider's enabled status and DS records to publish with the registrar. Remove parent DS records before disabling DNSSEC to avoid a resolution failure.

Suspension blocks editing but keeps existing DNS resolving. Termination deletes the provider zone and local records. Deleting the WHMCS service directly triggers the same cleanup; a provider failure is written to the WHMCS activity log for staff to investigate and clean up manually. Deactivating the addon preserves zones and records.

Existing manually created zones are adopted when a matching client's DNS product is provisioned, if the configured provider is unchanged. They are not shown for DNS management until attached to an active product. One DNS product manages one zone, and a domain can have only one DNS service. To switch providers, migrate the zone before changing the addon provider settings.

## Supported providers

PlexDNS supports AnycastDNS, BIND, Bunny, Cloudflare, ClouDNS, deSEC, DNSimple, Hetzner, PowerDNS, and Vultr. DNSSEC controls are shown only when PlexDNS reports support; provider capabilities determine whether enable and disable buttons appear. BIND and Hetzner do not currently expose DNSSEC through PlexDNS. Nameservers come from the addon settings except for Cloudflare, whose assigned nameservers are fetched per zone.

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
