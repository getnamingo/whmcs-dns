<?php
/**
 * WHMCS-DNS module
 *
 * Written in 2025 by Taras Kondratyuk (https://namingo.org)
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

add_hook('ClientAreaSecondarySidebar', 1, function ($sidebar) {
    static $injected = false;
    if ($injected) {
        return;
    }
    $injected = true;

    if (empty($_SESSION['uid'])) {
        return;
    }

    $action = (string)($_GET['action'] ?? '');

    // Domain-related pages where domainid/id is present
    $allowedActions = [
        'domaindetails',
        'domaincontacts',
        'domainregisterns',
        'domaingetepp',
        'domains', // optional
    ];

    if (!in_array($action, $allowedActions, true)) {
        return;
    }

    $domainId = 0;
    if (!empty($_GET['domainid'])) {
        $domainId = (int) $_GET['domainid'];
    } elseif (!empty($_GET['id'])) {
        $domainId = (int) $_GET['id'];
    }

    if ($domainId <= 0) {
        return;
    }

    $clientId = (int) $_SESSION['uid'];

    $domain = Capsule::table('tbldomains')
        ->select('domain')
        ->where('id', $domainId)
        ->where('userid', $clientId)
        ->first();

    if (!$domain || empty($domain->domain)) {
        return;
    }

    $domainName = (string) $domain->domain;
    $url = 'index.php?m=whmcs_dns&domain=' . urlencode($domainName);

    try {
        // Prefer to attach into existing Domains panel if present
        $panel = $sidebar->getChild('Domains')
            ?: $sidebar->getChild('Domain Details')
            ?: $sidebar->getChild('DomainDetailsActions')
            ?: null;

        // Create panel if needed
        if (!$panel) {
            $panel = $sidebar->addChild('Domains', [
                'label' => 'Domains',
                'icon'  => 'fas fa-globe',
                'order' => 10,
            ]);
        }

        // De-dupe (by child name)
        if ($panel->getChild('DNSManagerLink')) {
            return;
        }

        $panel->addChild('DNSManagerLink', [
            'label' => 'DNS Manager',
            'uri'   => $url,
            'order' => 55,
        ]);
    } catch (\Throwable $e) {
        // no-op
    }
});