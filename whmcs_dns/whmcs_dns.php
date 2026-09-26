<?php
/**
 * WHMCS-DNS module
 *
 * Written in 2025-2026 by Taras Kondratyuk (https://namingo.org)
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Setting;
use PlexDNS\Service as PlexService;

define('WHMCSDNS_TABLE_ZONES', 'zones');
define('WHMCSDNS_TABLE_RECORDS', 'records');

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Addon module config
 */
function whmcs_dns_config()
{
    return [
        'name'        => 'DNS Hosting',
        'description' => 'DNS product provisioning and client zone management via PlexDNS',
        'author'      => 'Namingo',
        'language'    => 'english',
        'version'     => '1.1.0',
        'fields'      => [
            'provider' => [
                'FriendlyName' => 'Provider',
                'Type'         => 'dropdown',
                'Options'      => [
                    'AnycastDNS'    => 'AnycastDNS',
                    'Bind'     => 'Bind',
                    'Bunny'     => 'Bunny',
                    'Cloudflare' => 'Cloudflare',
                    'ClouDNS'    => 'ClouDNS',
                    'Desec'     => 'Desec',
                    'DNSimple' => 'DNSimple',
                    'Hetzner'    => 'Hetzner',
                    'PowerDNS'     => 'PowerDNS',
                    'Vultr' => 'Vultr',
                ],
                'Default'      => 'Vultr',
                'Description'  => 'Select your DNS provider from the list. Ensure you have an account with the chosen service.',
            ],
            'apikey' => [
                'FriendlyName' => 'API Key',
                'Type'         => 'password',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => "Enter your DNS provider's API key. Keep it confidential and ensure it's valid for requests.",
            ],
            'cloudns_auth_id' => [
                'FriendlyName' => 'ClouDNS Auth ID', 'Type' => 'text', 'Size' => '30',
                'Description' => 'Required when using ClouDNS.',
            ],
            'cloudns_auth_password' => [
                'FriendlyName' => 'ClouDNS Auth Password', 'Type' => 'password', 'Size' => '30',
                'Description' => 'Required when using ClouDNS.',
            ],

            'soa_email' => [
                'FriendlyName' => 'SOA Email',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Email address for the responsible person of this DNS zone (used in SOA).',
            ],

            'bind_powerdns_api_ip' => [
                'FriendlyName' => 'BIND/PowerDNS API IP',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '127.0.0.1',
                'Description'  => 'IP address of your BIND/PowerDNS server where the API is accessible.',
            ],

            'ns1' => [
                'FriendlyName' => 'NS1',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 1 for your DNS zone.',
            ],
            'ns2' => [
                'FriendlyName' => 'NS2',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 2 for your DNS zone.',
            ],
            'ns3' => [
                'FriendlyName' => 'NS3',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 3 for your DNS zone (optional).',
            ],
            'ns4' => [
                'FriendlyName' => 'NS4',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 4 for your DNS zone (optional).',
            ],
            'ns5' => [
                'FriendlyName' => 'NS5',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 5 for your DNS zone (optional).',
            ],
        ],
    ];
}

/** Read settings through WHMCS's model so password fields are decrypted correctly. */
function whmcs_dns_settings(): array
{
    $settings = [];
    foreach (array_merge(['provider', 'apikey', 'cloudns_auth_id', 'cloudns_auth_password',
        'soa_email', 'bind_powerdns_api_ip'], array_map(static fn($i) => 'ns' . $i, range(1, 5))) as $key) {
        $settings[$key] = Setting::getSettingValueForModule('whmcs_dns', $key) ?? '';
    }
    return $settings;
}

function whmcs_dns_provider_config(array $settings, string $domain): array
{
    if (empty($settings['provider'])) {
        throw new RuntimeException('Configure the DNS provider in the WHMCS addon settings first.');
    }
    $config = [
        'domain_name' => $domain,
        'provider' => (string)$settings['provider'],
        'apikey' => (string)($settings['apikey'] ?? ''),
        'cloudns_auth_id' => (string)($settings['cloudns_auth_id'] ?? ''),
        'cloudns_auth_password' => (string)($settings['cloudns_auth_password'] ?? ''),
        'soa_email' => (string)($settings['soa_email'] ?? ''),
    ];
    if ($config['provider'] === 'Bind') {
        $config['bindip'] = $settings['bind_powerdns_api_ip'] ?? '';
    } elseif ($config['provider'] === 'PowerDNS') {
        $config['powerdnsip'] = $settings['bind_powerdns_api_ip'] ?? '';
    }
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($settings['ns' . $i])) {
            $config['ns' . $i] = trim((string)$settings['ns' . $i]);
        }
    }
    return $config;
}

/** Used by TerminateAccount and by ServiceDelete when a service is removed directly. */
function whmcs_dns_delete_service(int $serviceId, int $clientId = 0): void
{
    $query = Capsule::table(WHMCSDNS_TABLE_ZONES)->where('service_id', $serviceId);
    if ($clientId) $query->where('client_id', $clientId);
    $zone = $query->first();
    if (!$zone) return;

    $config = whmcs_dns_provider_config(whmcs_dns_settings(), (string)$zone->domain_name);
    $stored = json_decode((string)$zone->config, true);
    if (($stored['provider'] ?? '') !== $config['provider']) {
        throw new RuntimeException('The DNS provider changed; restore the original provider settings before deleting this zone.');
    }
    (new PlexService(Capsule::connection()->getPdo()))->deleteDomain([
        'config' => json_encode($config, JSON_THROW_ON_ERROR),
    ]);
}

/**
 * Create DB tables
 */
function whmcs_dns_activate()
{
    try {
        if (!Capsule::schema()->hasTable(WHMCSDNS_TABLE_ZONES)) {
            Capsule::schema()->create(WHMCSDNS_TABLE_ZONES, function ($table) {
                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->bigIncrements('id');
                $table->bigInteger('client_id')->unsigned()->index();
                $table->bigInteger('service_id')->unsigned()->nullable()->unique();
                $table->string('domain_name', 75)->nullable()->unique();
                $table->string('provider_id', 11)->nullable();
                $table->string('zoneId', 100)->nullable();
                $table->text('config');
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (!Capsule::schema()->hasColumn(WHMCSDNS_TABLE_ZONES, 'service_id')) {
            Capsule::schema()->table(WHMCSDNS_TABLE_ZONES, function ($table) {
                $table->bigInteger('service_id')->unsigned()->nullable()->unique();
            });
        }

        if (!Capsule::schema()->hasTable(WHMCSDNS_TABLE_RECORDS)) {
            Capsule::schema()->create(WHMCSDNS_TABLE_RECORDS, function ($table) {
                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->bigIncrements('id');
                $table->bigInteger('domain_id')->unsigned()->index();
                $table->string('recordId', 100)->nullable();
                $table->string('type', 10);
                $table->string('host', 255);
                $table->text('value');
                $table->integer('ttl')->nullable();
                $table->integer('priority')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

                // Foreign keys are optional in many WHMCS installs; enable only if you know your DB config allows it.
                // $table->foreign('domain_id')->references('id')->on(WHMCSDNS_TABLE_ZONES)->onDelete('cascade');
            });
        }

        return ['status' => 'success', 'description' => 'WHMCS-DNS addon activated.'];
    } catch (Throwable $e) {
        return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
    }
}

/**
 * Preserve live DNS data on addon deactivation.
 */
function whmcs_dns_deactivate()
{
    return ['status' => 'success', 'description' => 'Addon deactivated; DNS zones and records were preserved.'];
}

function whmcs_dns_upgrade($vars)
{
    if (Capsule::schema()->hasTable(WHMCSDNS_TABLE_ZONES)
        && !Capsule::schema()->hasColumn(WHMCSDNS_TABLE_ZONES, 'service_id')) {
        Capsule::schema()->table(WHMCSDNS_TABLE_ZONES, function ($table) {
            $table->bigInteger('service_id')->unsigned()->nullable()->unique();
        });
    }
}

function whmcs_dns_output($vars)
{
    echo '<div class="alert alert-info">
        Namingo DNS is configured from 
        <strong>Configuration → System Settings → Addon Modules</strong>.
        No additional administration is required here.
    </div>';
}

/**
 * Client area page
 */
function whmcs_dns_clientarea($vars)
{
    // Ensure client is logged in
    if (empty($_SESSION['uid'])) {
        return [
            'pagetitle'    => 'DNS Manager',
            'breadcrumb'   => ['index.php?m=whmcs_dns' => 'DNS Manager'],
            'templatefile' => 'clientarea',
            'requirelogin' => true,
            'vars'         => ['error' => 'Please login first.'],
        ];
    }

    $clientId = (int) $_SESSION['uid'];

    $provider = $vars['provider'] ?? '';
    $apikey   = $vars['apikey'] ?? '';

    // Only provisioned, active DNS products can manage a zone.
    $services = Capsule::table('tblhosting')->where('userid', $clientId)
        ->where('domainstatus', 'Active')->pluck('id')->toArray();
    $activeServices = $services ?: [-1];
    $clientDomains = Capsule::table(WHMCSDNS_TABLE_ZONES)
        ->where('client_id', $clientId)->whereIn('service_id', $activeServices)
        ->orderBy('domain_name')->get()
        ->map(static fn($z) => ['domain' => (string)$z->domain_name])->toArray();

    $selectedDomain = trim((string)($_REQUEST['domain'] ?? ''));
    $message = null;

    $pdo = Capsule::connection()->getPdo();
    $plex = new PlexService($pdo);

    // Handle actions (add / update / delete)
    if (!empty($_POST['action'])) {
        check_token(); // WHMCS client token

        $action = (string)$_POST['action'];
        $domainName = trim((string)($_POST['domain_name'] ?? ''));
        if ($domainName === '') {
            $message = ['type' => 'error', 'text' => 'Domain is required.'];
        } else {
            $managedZone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                ->where('client_id', $clientId)->whereIn('service_id', $activeServices)
                ->where('domain_name', $domainName)->first();
            if (!$managedZone) {
                $message = ['type' => 'error', 'text' => 'No active DNS hosting service for this domain.'];
            } elseif ($provider === '') {
                $message = ['type' => 'error', 'text' => 'DNS provider is not configured.'];
            } else {
                try {
                    $config = whmcs_dns_provider_config($vars, $domainName);
                    if ((json_decode((string)$managedZone->config, true)['provider'] ?? '') !== $config['provider']) {
                        throw new RuntimeException('DNS provider settings have changed. Contact support.');
                    }
                    if ($action === 'add_record') {
                        $recordName  = (string)($_POST['record_name'] ?? '');
                        $recordType  = strtoupper((string)($_POST['record_type'] ?? ''));
                        $recordValue = (string)($_POST['record_value'] ?? '');
                        $ttl         = isset($_POST['record_ttl']) ? (int)$_POST['record_ttl'] : 3600;
                        $priority    = (isset($_POST['record_priority']) && $_POST['record_priority'] !== '')
                            ? (int)$_POST['record_priority'] : null;

                        if ($recordType === '' || $recordValue === '') {
                            throw new Exception('Record type and value are required.');
                        }

                        if ($recordType === 'MX' && $priority === null) {
                            $priority = 0;
                        }

                        if ($recordType === 'TXT') {
                            $v = trim($recordValue);
                            if ($v === '' || $v[0] !== '"' || substr($v, -1) !== '"') {
                                $recordValue = '"' . str_replace('"', '\"', $v) . '"';
                            }
                        }

                        if (in_array($provider, ['PowerDNS'], true) && $recordType === 'CNAME') {
                            $recordValue = rtrim(trim($recordValue), '.') . '.';
                        }

                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->whereIn('service_id', $activeServices)
                            ->first();
                        if (!$zone) {
                            throw new Exception('DNS is not enabled for this domain. Contact support.');
                        }

                        $req = [
                            'domain_name'      => $domainName,
                            'record_name'      => $recordName,
                            'record_type'      => $recordType,
                            'record_value'     => $recordValue,
                            'record_ttl'       => $ttl,
                            'record_priority'  => $priority,
                            'provider'         => $provider,
                            'apikey'           => $apikey,
                        ];

                        if ($provider === 'PowerDNS') {
                            $req['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        } elseif ($provider === 'Bind') {
                            $req['bindip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        }

                        $plex->addRecord(array_merge($config, $req));

                        $message = ['type' => 'success', 'text' => 'Record added.'];
                    }

                    if ($action === 'update_record') {
                        $rowId      = (int)($_POST['row_id'] ?? 0);
                        $recordName  = (string)($_POST['record_name'] ?? '');
                        $recordType  = strtoupper((string)($_POST['record_type'] ?? ''));
                        $recordValue = (string)($_POST['record_value'] ?? '');
                        $ttl         = isset($_POST['record_ttl']) ? (int)$_POST['record_ttl'] : 3600;
                        $priority    = (isset($_POST['record_priority']) && $_POST['record_priority'] !== '')
                            ? (int)$_POST['record_priority'] : null;

                        if ($rowId <= 0) {
                            throw new Exception('Invalid record row id.');
                        }
                        
                        if ($recordType === 'MX' && $priority === null) {
                            $priority = 0;
                        }

                        if ($recordType === 'TXT') {
                            $v = trim($recordValue);
                            if ($v === '' || $v[0] !== '"' || substr($v, -1) !== '"') {
                                $recordValue = '"' . str_replace('"', '\"', $v) . '"';
                            }
                        }

                        if (in_array($provider, ['PowerDNS'], true) && $recordType === 'CNAME') {
                            $recordValue = rtrim(trim($recordValue), '.') . '.';
                        }

                        // Resolve zone + row ownership
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->whereIn('service_id', $activeServices)
                            ->first();
                        if (!$zone) {
                            throw new Exception('Zone not found. Refresh and try again.');
                        }

                        $rec = Capsule::table(WHMCSDNS_TABLE_RECORDS)
                            ->where('id', $rowId)
                            ->where('domain_id', $zone->id)
                            ->first();
                        if (!$rec) {
                            throw new Exception('Record not found. Please refresh and try again.');
                        }

                        $oldValue = (string)($_POST['old_value'] ?? '');
                        if ($oldValue !== '' && (string)$rec->value !== $oldValue) {
                            throw new Exception('Record changed since page load. Please refresh and try again.');
                        }

                        $recordId = $rec->recordId ?? null;
                        if (empty($recordId)) {
                            throw new Exception('This record is missing provider recordId. Please delete and re-create it.');
                        }
                        
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->whereIn('service_id', $activeServices)
                            ->first();
                        if (!$zone) {
                            throw new Exception('DNS is not enabled for this domain. Contact support.');
                        }

                        $req = [
                            'domain_name'      => $domainName,
                            'record_id'        => $recordId,
                            'record_name'      => $recordName,
                            'record_type'      => $recordType,
                            'record_value'     => $recordValue,
                            'old_value'        => $oldValue,
                            'record_ttl'       => $ttl,
                            'record_priority'  => $priority,
                            'provider'         => $provider,
                            'apikey'           => $apikey,
                        ];

                        if ($provider === 'PowerDNS') {
                            $req['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        } elseif ($provider === 'Bind') {
                            $req['bindip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        }

                        $plex->updateRecord(array_merge($config, $req));

                        $message = ['type' => 'success', 'text' => 'Record updated.'];
                    }

                    if ($action === 'delete_record') {
                        $rowId = (int)($_POST['row_id'] ?? 0);
                        if ($rowId <= 0) {
                            throw new Exception('Invalid record row id.');
                        }

                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->whereIn('service_id', $activeServices)
                            ->first();
                        if (!$zone) {
                            throw new Exception('Zone not found. Refresh and try again.');
                        }

                        $rec = Capsule::table(WHMCSDNS_TABLE_RECORDS)
                            ->where('id', $rowId)
                            ->where('domain_id', $zone->id)
                            ->first();
                        if (!$rec) {
                            throw new Exception('Record not found. Please refresh and try again.');
                        }

                        $recordId = $rec->recordId ?? null;
                        if (empty($recordId)) {
                            throw new Exception('This record is missing provider recordId. Please delete and re-create it.');
                        }
                        
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->whereIn('service_id', $activeServices)
                            ->first();
                        if (!$zone) {
                            throw new Exception('DNS is not enabled for this domain. Contact support.');
                        }

                        $req = [
                            'domain_name'      => $domainName,
                            'record_id'        => $recordId,
                            'record_name'      => (string)$rec->host,
                            'record_type'      => strtoupper((string)$rec->type),
                            'record_value'     => (string)$rec->value,
                            'provider'         => $provider,
                            'apikey'           => $apikey,
                        ];

                        if ($provider === 'PowerDNS') {
                            $req['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        } elseif ($provider === 'Bind') {
                            $req['bindip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        }

                        $plex->delRecord(array_merge($config, $req));

                        $message = ['type' => 'success', 'text' => 'Record deleted.'];
                    }
                    if ($action === 'enable_dnssec' || $action === 'disable_dnssec') {
                        $capabilities = $plex->getDNSSECCapabilities($config);
                        $allowed = $action === 'enable_dnssec' ? 'can_enable' : 'can_disable';
                        if (!$capabilities['supported'] || !$capabilities[$allowed]) {
                            throw new RuntimeException('This provider does not support that DNSSEC action.');
                        }
                        if ($action === 'enable_dnssec') {
                            $plex->enableDNSSEC($config);
                        } else {
                            $plex->disableDNSSEC($config);
                        }
                        $message = ['type' => 'success', 'text' => 'DNSSEC settings updated.'];
                    }
                } catch (Throwable $e) {
                    $message = ['type' => 'error', 'text' => $e->getMessage()];
                }
            }
        }

        // keep domain selected after POST
        $selectedDomain = $selectedDomain ?: $domainName;
    }

    // Fetch zone + records for selected domain
    $zoneData = null;
    $records = [];
    $dnssec = null;
    $nameservers = [];

    if ($selectedDomain !== '') {
        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
            ->where('domain_name', $selectedDomain)
            ->where('client_id', $clientId)
            ->whereIn('service_id', $activeServices)
            ->first();

        if ($zone) {
            $zoneData = [
                'id'          => (int)$zone->id,
                'domain_name' => (string)$zone->domain_name,
                'created_at'  => (string)$zone->created_at,
                'updated_at'  => (string)$zone->updated_at,
                'config'      => json_decode((string)$zone->config, true),
            ];

            $records = Capsule::table(WHMCSDNS_TABLE_RECORDS)
                ->select('id', 'type', 'host', 'value', 'ttl', 'priority', 'recordId')
                ->where('domain_id', $zone->id)
                ->orderBy('type', 'asc')
                ->orderBy('host', 'asc')
                ->get()
                ->map(function ($r) {
                    return [
                        'id'       => (int)$r->id,
                        'type'     => (string)$r->type,
                        'host'     => (string)$r->host,
                        'value'    => (string)$r->value,
                        'ttl'      => $r->ttl !== null ? (int)$r->ttl : null,
                        'priority' => $r->priority !== null ? (int)$r->priority : null,
                        'recordId' => (string)($r->recordId ?? ''),
                    ];
                })
                ->toArray();

            try {
                $config = whmcs_dns_provider_config($vars, $selectedDomain);
                for ($i = 1; $i <= 5; $i++) {
                    if (!empty($config['ns' . $i])) $nameservers[] = $config['ns' . $i];
                }
                if ((json_decode((string)$zone->config, true)['provider'] ?? '') !== $config['provider']) {
                    throw new RuntimeException('The DNS provider settings have changed.');
                }
                if ($config['provider'] === 'Cloudflare') {
                    // Cloudflare assigns nameservers per zone; global NS settings may be wrong.
                    $nameservers = [];
                    $details = (new \PlexDNS\Providers\Cloudflare($config))->getDomain($selectedDomain);
                    $nameservers = array_values(array_filter($details['name_servers'] ?? [], 'is_string'));
                }
                $capabilities = $plex->getDNSSECCapabilities($config);
                if ($capabilities['supported']) {
                    $status = $plex->getDNSSECStatus($config);
                    $ds = $status['ds'] ?? $plex->getDSRecords($config);
                    if (!is_array($ds)) $ds = $ds ? [$ds] : [];
                    $dnssec = array_merge($capabilities, [
                        'enabled' => (bool)($status['enabled'] ?? $capabilities['enforced']),
                        'ds' => array_map(static function ($record) {
                            if (is_string($record)) return $record;
                            if (!is_array($record)) return '';
                            $parts = [$record['key_tag'] ?? $record['keytag'] ?? $record['keyTag'] ?? '',
                                $record['algorithm'] ?? '', $record['digest_type'] ?? $record['digestType'] ?? '',
                                $record['digest'] ?? ''];
                            return implode(' ', $parts);
                        }, $ds),
                    ]);
                }
            } catch (Throwable $e) {
                $message = ['type' => 'error', 'text' => 'DNSSEC status unavailable: ' . $e->getMessage()];
            }
        }
    }

    $domainCrumbs = [
        'index.php?m=whmcs_dns&domain=' . urlencode($selectedDomain) => 'DNS Manager',
    ];

    if ($selectedDomain !== '') {
        $domainId = (int) Capsule::table('tbldomains')
            ->where('userid', $clientId)
            ->where('domain', $selectedDomain)
            ->value('id');

        if ($domainId > 0) {
            $domainCrumbs = [
                'clientarea.php?action=domains' => 'My Domains',
                'clientarea.php?action=domaindetails&id=' . $domainId => $selectedDomain,
                'index.php?m=whmcs_dns&domain=' . urlencode($selectedDomain) => 'DNS Manager',
            ];
        }
    }

    return [
        'pagetitle'    => 'DNS Manager',
        'breadcrumb' => $domainCrumbs,
        'templatefile' => 'clientarea',
        'requirelogin' => true,
        'vars'         => [
            'message'        => $message,
            'clientDomains'  => $clientDomains,
            'selectedDomain' => $selectedDomain,
            'zone'           => $zoneData,
            'records'        => $records,
            'nameservers'    => $nameservers,
            'dnssec'         => $dnssec,
        ],
    ];
}
