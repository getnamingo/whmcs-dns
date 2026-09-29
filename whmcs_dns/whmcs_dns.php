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
use Namingo\Cardo\DNS\Service as CardoService;

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
        'description' => 'DNS management addon enabling zone and record control via external providers',
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
                    'DigitalOcean' => 'DigitalOcean',
                    'GandiLiveDNS' => 'Gandi LiveDNS',
                    'Hetzner'    => 'Hetzner',
                    'PowerDNS'     => 'PowerDNS',
                    'Scaleway' => 'Scaleway',
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
                'Description' => 'Required only for ClouDNS.',
            ],
            'cloudns_auth_password' => [
                'FriendlyName' => 'ClouDNS Auth Password', 'Type' => 'password', 'Size' => '30',
                'Description' => 'Required only for ClouDNS.',
            ],
            'scaleway_project_id' => [
                'FriendlyName' => 'Scaleway Project ID', 'Type' => 'text', 'Size' => '50',
                'Description' => 'Required only for Scaleway.',
            ],
            'scaleway_parent_domain' => [
                'FriendlyName' => 'Scaleway Parent Domain', 'Type' => 'text', 'Size' => '50',
                'Description' => 'Optional. Usually leave empty for normal root-zone hosting.',
            ],
            'gandi_sharing_id' => [
                'FriendlyName' => 'Gandi Sharing ID', 'Type' => 'text', 'Size' => '50',
                'Description' => 'Optional Gandi organization sharing context.',
            ],
            'gandi_auth_scheme' => [
                'FriendlyName' => 'Gandi Auth Scheme',
                'Type' => 'dropdown',
                'Options' => ['Bearer' => 'Bearer (recommended)', 'Apikey' => 'Apikey (legacy)'],
                'Default' => 'Bearer',
                'Description' => 'Bearer is recommended for current Gandi personal access tokens.',
            ],
            'max_zones_per_client' => [
                'FriendlyName' => 'Maximum Zones Per Client', 'Type' => 'text', 'Size' => '8',
                'Default' => '0', 'Description' => '0 means unlimited. Applies when a client enables DNS.',
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
                $table->string('domain_name', 75)->nullable()->unique();
                $table->string('provider_id', 11)->nullable();
                $table->string('zoneId', 100)->nullable();
                $table->text('config');
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
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
 * Drop tables
 */
function whmcs_dns_deactivate()
{
    try {
        if (Capsule::schema()->hasTable(WHMCSDNS_TABLE_RECORDS)) {
            Capsule::schema()->drop(WHMCSDNS_TABLE_RECORDS);
        }
        if (Capsule::schema()->hasTable(WHMCSDNS_TABLE_ZONES)) {
            Capsule::schema()->drop(WHMCSDNS_TABLE_ZONES);
        }

        return ['status' => 'success', 'description' => 'WHMCS-DNS addon deactivated.'];
    } catch (Throwable $e) {
        return ['status' => 'error', 'description' => 'Deactivation failed: ' . $e->getMessage()];
    }
}

function whmcs_dns_upgrade($vars)
{
    // Keep for future migrations.
}

function whmcs_dns_output($vars)
{
    echo '<div class="alert alert-info">
        Namingo DNS is configured from 
        <strong>Configuration → System Settings → Addon Modules</strong>.
        No additional administration is required here.
    </div>';
}

function whmcs_dns_provider_config(array $vars, string $domainName): array
{
    $config = [
        'domain_name' => $domainName,
        'provider' => $vars['provider'] ?? '',
        'apikey' => $vars['apikey'] ?? '',
        'cloudns_auth_id' => $vars['cloudns_auth_id'] ?? '',
        'cloudns_auth_password' => $vars['cloudns_auth_password'] ?? '',
        'project_id' => $vars['scaleway_project_id'] ?? '',
        'parent_domain' => $vars['scaleway_parent_domain'] ?? '',
        'sharing_id' => $vars['gandi_sharing_id'] ?? '',
        'auth_scheme' => $vars['gandi_auth_scheme'] ?? 'Bearer',
        'soa_email' => $vars['soa_email'] ?? '',
    ];
    if ($config['provider'] === 'PowerDNS') $config['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? '';
    if ($config['provider'] === 'Bind') $config['bindip'] = $vars['bind_powerdns_api_ip'] ?? '';
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($vars['ns' . $i])) $config['ns' . $i] = $vars['ns' . $i];
    }
    return $config;
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
    $zoneLimit = max(0, (int)($vars['max_zones_per_client'] ?? 0));
    $zoneCount = Capsule::table(WHMCSDNS_TABLE_ZONES)->where('client_id', $clientId)->count();

    // List user WHMCS domains
    $clientDomains = Capsule::table('tbldomains')
        ->select('id', 'domain', 'status')
        ->where('userid', $clientId)
        ->orderBy('domain', 'asc')
        ->get()
        ->map(function ($d) {
            return [
                'id'     => (int)$d->id,
                'domain' => (string)$d->domain,
                'status' => (string)$d->status,
            ];
        })
        ->toArray();

    $selectedDomain = trim((string)($_REQUEST['domain'] ?? ''));
    $message = null;

    $pdo = Capsule::connection()->getPdo();
    $cardo = new CardoService($pdo);

    // Helper: fetch zone
    $getZone = function (string $domainName) use ($clientId) {
        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
            ->where('domain_name', $domainName)
            ->where('client_id', $clientId)
            ->first();
        return $zone ?: null;
    };

    // Handle actions (add / update / delete)
    if (!empty($_POST['action'])) {
        check_token(); // WHMCS client token

        $action = (string)$_POST['action'];
        $domainName = trim((string)($_POST['domain_name'] ?? ''));
        if ($domainName === '') {
            $message = ['type' => 'error', 'text' => 'Domain is required.'];
        } else {
            // Ownership check: must be in tbldomains for this user
            $owns = Capsule::table('tbldomains')
                ->where('userid', $clientId)
                ->where('domain', $domainName)
                ->exists();
            if (!$owns) {
                $message = ['type' => 'error', 'text' => 'Domain does not exist.'];
            } elseif ($provider === '') {
                $message = ['type' => 'error', 'text' => 'DNS provider is not configured.'];
            } else {
                try {
                    if ($action === 'enable_dns') {
                        // Create zone explicitly (no silent auto-create)
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();

                        if ($zone) {
                            $message = ['type' => 'success', 'text' => 'DNS is already enabled for this domain.'];
                        } else {
                            if (Capsule::table(WHMCSDNS_TABLE_ZONES)->where('domain_name', $domainName)->exists()) {
                                throw new RuntimeException('This DNS zone is already managed by another client.');
                            }
                            if ($zoneLimit > 0 && $zoneCount >= $zoneLimit) {
                                throw new RuntimeException('DNS zone limit reached. Disable a zone or contact support.');
                            }
                            $cfg = whmcs_dns_provider_config($vars, $domainName);

                            $domainOrder = [
                                'client_id' => $clientId,
                                'config'    => json_encode($cfg, JSON_UNESCAPED_SLASHES),
                            ];

                            $cardo->createDomain($domainOrder);

                            // Ensure local row exists if Cardo DNS didn't insert it itself
                            $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)->where('domain_name', $domainName)->first();
                            if (!$zone) {
                                Capsule::table(WHMCSDNS_TABLE_ZONES)->insert([
                                    'client_id'   => $clientId,
                                    'domain_name' => $domainName,
                                    'config'      => $domainOrder['config'],
                                    'created_at'  => date('Y-m-d H:i:s'),
                                    'updated_at'  => date('Y-m-d H:i:s'),
                                ]);
                            }

                            $message = ['type' => 'success', 'text' => 'DNS enabled. Zone created.'];
                            $zoneCount++;
                        }
                    }

                    if ($action === 'disable_dns') {
                        // Delete zone explicitly
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();

                        if (!$zone) {
                            $message = ['type' => 'success', 'text' => 'DNS is already disabled (zone not found).'];
                        } else {
                            $cfg = whmcs_dns_provider_config($vars, $domainName);

                            $cardo->deleteDomain([
                                'config' => json_encode($cfg, JSON_UNESCAPED_SLASHES),
                            ]);

                            Capsule::table(WHMCSDNS_TABLE_RECORDS)->where('domain_id', $zone->id)->delete();
                            Capsule::table(WHMCSDNS_TABLE_ZONES)->where('id', $zone->id)->delete();

                            $message = ['type' => 'success', 'text' => 'DNS disabled. Zone deleted.'];
                            $zoneCount--;
                        }
                    }

                    if ($action === 'add_record') {
                        $recordName = strtolower(trim((string)($_POST['record_name'] ?? '')));
                        if ($recordName === '@' || rtrim($recordName, '.') === $domainName) {
                            $recordName = '';
                        }
                        if (str_ends_with($recordName, '.' . $domainName . '.')) {
                            $recordName = substr($recordName, 0, -strlen($domainName) - 2);
                        }
                        if (str_ends_with($recordName, '.' . $domainName)) {
                            $recordName = substr($recordName, 0, -strlen($domainName) - 1);
                        }
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
                            ->first();
                        if (!$zone) {
                            throw new Exception('DNS is not enabled for this domain. Click "Enable DNS" first.');
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

                        $cardo->addRecord(array_merge(whmcs_dns_provider_config($vars, $domainName), $req));

                        $message = ['type' => 'success', 'text' => 'Record added.'];
                    }

                    if ($action === 'update_record') {
                        $rowId      = (int)($_POST['row_id'] ?? 0);
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

                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();
                        if (!$zone) {
                            throw new Exception('DNS is not enabled for this domain. Click "Enable DNS" first.');
                        }

                        $recordName = strtolower(trim((string)$rec->host));
                        if ($recordName === '@' || rtrim($recordName, '.') === $domainName) {
                            $recordName = '';
                        }
                        if (str_ends_with($recordName, '.' . $domainName . '.')) {
                            $recordName = substr($recordName, 0, -strlen($domainName) - 2);
                        }
                        if (str_ends_with($recordName, '.' . $domainName)) {
                            $recordName = substr($recordName, 0, -strlen($domainName) - 1);
                        }

                        $req = [
                            'domain_name'      => $domainName,
                            'record_id'        => $rowId,
                            'record_name'      => $recordName,
                            'record_type'      => strtoupper((string)$rec->type),
                            'record_value'     => $recordValue,
                            'old_value'        => (string)$rec->value,
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

                        $cardo->updateRecord(array_merge(whmcs_dns_provider_config($vars, $domainName), $req));

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

                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();
                        if (!$zone) {
                            throw new Exception('DNS is not enabled for this domain. Click "Enable DNS" first.');
                        }

                        $req = [
                            'domain_name'      => $domainName,
                            'record_id'        => $rowId,
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

                        $cardo->delRecord(array_merge(whmcs_dns_provider_config($vars, $domainName), $req));

                        $message = ['type' => 'success', 'text' => 'Record deleted.'];
                    }
                    if ($action === 'enable_dnssec' || $action === 'disable_dnssec') {
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)->where('client_id', $clientId)->first();
                        if (!$zone) throw new RuntimeException('Enable DNS for this domain first.');
                        $config = whmcs_dns_provider_config($vars, $domainName);
                        if ((json_decode((string)$zone->config, true)['provider'] ?? '') !== $config['provider']) {
                            throw new RuntimeException('DNS provider settings have changed. Contact support.');
                        }
                        $capabilities = $cardo->getDNSSECCapabilities($config);
                        $operation = $action === 'enable_dnssec' ? 'can_enable' : 'can_disable';
                        if (!$capabilities['supported'] || !$capabilities[$operation]) {
                            throw new RuntimeException('This provider does not support that DNSSEC action.');
                        }
                        if ($action === 'enable_dnssec') $cardo->enableDNSSEC($config);
                        else $cardo->disableDNSSEC($config);
                        $message = ['type' => 'success', 'text' => 'DNSSEC updated.'];
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
    $nameservers = [];
    $dnssec = null;

    if ($selectedDomain !== '') {
        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
            ->where('domain_name', $selectedDomain)
            ->where('client_id', $clientId)
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

            $config = whmcs_dns_provider_config($vars, $selectedDomain);
            for ($i = 1; $i <= 5; $i++) {
                if (!empty($config['ns' . $i])) $nameservers[] = trim((string)$config['ns' . $i]);
            }
            if ($provider === 'Cloudflare') {
                // Cloudflare assigns nameservers per zone, unlike the global NS settings.
                $nameservers = [];
                try {
                    $details = (new \Namingo\Cardo\DNS\Providers\Cloudflare($config))->getDomain($selectedDomain);
                    $nameservers = array_values(array_filter($details['name_servers'] ?? [], 'is_string'));
                } catch (Throwable $e) {
                    $message = ['type' => 'error', 'text' => 'Could not load assigned nameservers: ' . $e->getMessage()];
                }
            }
            try {
                if ((json_decode((string)$zone->config, true)['provider'] ?? '') !== $provider) {
                    throw new RuntimeException('DNS provider settings have changed. Contact support.');
                }
                $capabilities = $cardo->getDNSSECCapabilities($config);
                if ($capabilities['supported']) {
                    $status = $cardo->getDNSSECStatus($config);
                    $ds = $status['ds'] ?? null;
                    if (($status['enabled'] ?? $capabilities['enforced']) && ($ds === null || $ds === [])) {
                        $ds = $cardo->getDSRecords($config);
                    }
                    if (!is_array($ds)) $ds = $ds ? [$ds] : [];
                    elseif ($ds && !array_is_list($ds)) $ds = [$ds];
                    $dnssec = array_merge($capabilities, [
                        'enabled' => (bool)($status['enabled'] ?? $capabilities['enforced']),
                        'ds' => array_map(static function ($record) {
                            if (is_string($record)) return $record;
                            if (!is_array($record)) return '';
                            $parts = [
                                $record['key_tag'] ?? $record['keytag'] ?? $record['keyTag'] ?? '',
                                $record['algorithm'] ?? '',
                                $record['digest_type'] ?? $record['digestType'] ?? '',
                                $record['digest'] ?? '',
                            ];
                            return in_array('', $parts, true) ? json_encode($record, JSON_UNESCAPED_SLASHES) : implode(' ', $parts);
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
            'zoneLimit'      => $zoneLimit,
            'zoneCount'      => $zoneCount,
        ],
    ];
}
