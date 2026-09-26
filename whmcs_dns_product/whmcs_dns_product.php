<?php
/** WHMCS provisioning module for paid or free DNS hosting products. */
if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once dirname(__DIR__, 2) . '/addons/whmcs_dns/whmcs_dns.php';

use WHMCS\Database\Capsule;
use PlexDNS\Service as PlexService;

function whmcs_dns_product_MetaData(): array
{
    return ['DisplayName' => 'Namingo DNS Hosting', 'APIVersion' => '1.1', 'RequiresServer' => false];
}

function whmcs_dns_product_CreateAccount(array $params): string
{
    try {
        $serviceId = (int)($params['serviceid'] ?? 0);
        $clientId = (int)($params['userid'] ?? 0);
        $domain = strtolower(rtrim(trim((string)($params['domain'] ?? '')), '.'));
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) $domain = strtolower($ascii);
        }
        if (!$serviceId || !$clientId || strlen($domain) > 75
            || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || strpos($domain, '.') === false) {
            throw new RuntimeException('Enter a valid domain on the DNS product before provisioning.');
        }
        if (!Capsule::schema()->hasTable(WHMCSDNS_TABLE_ZONES)
            || !Capsule::schema()->hasColumn(WHMCSDNS_TABLE_ZONES, 'service_id')) {
            throw new RuntimeException('Activate or upgrade the DNS Hosting addon first.');
        }
        $existing = Capsule::table(WHMCSDNS_TABLE_ZONES)->where('domain_name', $domain)->first();
        if ($existing) {
            if ((int)$existing->client_id !== $clientId
                || ($existing->service_id && (int)$existing->service_id !== $serviceId)) {
                throw new RuntimeException('This DNS zone is already assigned to another service.');
            }
            $config = whmcs_dns_provider_config(whmcs_dns_settings(), $domain);
            if ((json_decode((string)$existing->config, true)['provider'] ?? '') !== $config['provider']) {
                throw new RuntimeException('This zone uses a different DNS provider.');
            }
            // Adopt an existing client zone without trying to recreate it at the provider.
            Capsule::table(WHMCSDNS_TABLE_ZONES)->where('id', $existing->id)
                ->update(['service_id' => $serviceId]);
            return 'success';
        }
        if (Capsule::table(WHMCSDNS_TABLE_ZONES)->where('service_id', $serviceId)->exists()) {
            throw new RuntimeException('This service already has a DNS zone; remove it before changing domains.');
        }
        $config = whmcs_dns_provider_config(whmcs_dns_settings(), $domain);
        (new PlexService(Capsule::connection()->getPdo()))->createDomain([
            'client_id' => $clientId,
            'config' => json_encode($config, JSON_THROW_ON_ERROR),
        ]);
        Capsule::table(WHMCSDNS_TABLE_ZONES)->where('domain_name', $domain)
            ->where('client_id', $clientId)->update(['service_id' => $serviceId]);
        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function whmcs_dns_product_SuspendAccount(array $params): string
{
    // Leave DNS resolving; client editing requires the service to be Active.
    return 'success';
}

function whmcs_dns_product_UnsuspendAccount(array $params): string
{
    return 'success';
}

function whmcs_dns_product_TerminateAccount(array $params): string
{
    try {
        whmcs_dns_delete_service((int)$params['serviceid'], (int)$params['userid']);
        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function whmcs_dns_product_ClientArea(array $params): string
{
    $domain = (string)($params['domain'] ?? '');
    return '<p><a class="btn btn-primary" href="index.php?m=whmcs_dns&amp;domain='
        . rawurlencode($domain) . '">Manage DNS for ' . htmlspecialchars($domain, ENT_QUOTES, 'UTF-8')
        . '</a></p>';
}
