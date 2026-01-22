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
        'description' => 'DNS management addon enabling zone and record control via external providers',
        'author'      => 'Namingo',
        'language'    => 'english',
        'version'     => '1.0.0',
        'fields'      => [
            'provider' => [
                'FriendlyName' => 'Provider',
                'Type'         => 'dropdown',
                'Options'      => [
                    'AnycastDNS'    => 'AnycastDNS',
                    'Bind'     => 'Bind',
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
    $plex = new PlexService($pdo);

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
                            $cfg = [
                                'domain_name' => $domainName,
                                'provider'    => $provider,
                                'apikey'      => $apikey,
                            ];

                            if ($provider === 'PowerDNS') {
                                $cfg['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? null;
                                for ($i = 1; $i <= 5; $i++) {
                                    $k = 'ns' . $i;
                                    if (!empty($vars[$k])) $cfg[$k] = $vars[$k];
                                }
                            } elseif ($provider === 'Bind') {
                                $cfg['bindip'] = $vars['bind_powerdns_api_ip'] ?? null;
                                for ($i = 1; $i <= 5; $i++) {
                                    $k = 'ns' . $i;
                                    if (!empty($vars[$k])) $cfg[$k] = $vars[$k];
                                }
                            }

                            $domainOrder = [
                                'client_id' => $clientId,
                                'config'    => json_encode($cfg, JSON_UNESCAPED_SLASHES),
                            ];

                            $plex->createDomain($domainOrder);

                            // Ensure local row exists if PlexDNS didn't insert it itself
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
                            $cfg = [
                                'domain_name' => $domainName,
                                'provider'    => $provider,
                                'apikey'      => $apikey,
                            ];

                            if ($provider === 'PowerDNS') {
                                $cfg['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? null;
                                for ($i = 1; $i <= 5; $i++) {
                                    $k = 'ns' . $i;
                                    if (!empty($vars[$k])) $cfg[$k] = $vars[$k];
                                }
                            } elseif ($provider === 'Bind') {
                                $cfg['bindip'] = $vars['bind_powerdns_api_ip'] ?? null;
                                for ($i = 1; $i <= 5; $i++) {
                                    $k = 'ns' . $i;
                                    if (!empty($vars[$k])) $cfg[$k] = $vars[$k];
                                }
                            }

                            $plex->deleteDomain([
                                'config' => json_encode($cfg, JSON_UNESCAPED_SLASHES),
                            ]);

                            Capsule::table(WHMCSDNS_TABLE_RECORDS)->where('domain_id', $zone->id)->delete();
                            Capsule::table(WHMCSDNS_TABLE_ZONES)->where('id', $zone->id)->delete();

                            $message = ['type' => 'success', 'text' => 'DNS disabled. Zone deleted.'];
                        }
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

                        $plex->addRecord($req);

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

                        $recordId = $rec->recordId ?? null;
                        if (empty($recordId)) {
                            throw new Exception('This record is missing provider recordId. Please delete and re-create it.');
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
                            'record_id'        => $recordId,
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

                        $plex->updateRecord($req);

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

                        $recordId = $rec->recordId ?? null;
                        if (empty($recordId)) {
                            throw new Exception('This record is missing provider recordId. Please delete and re-create it.');
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

                        $plex->delRecord($req);

                        $message = ['type' => 'success', 'text' => 'Record deleted.'];
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
        ],
    ];
}