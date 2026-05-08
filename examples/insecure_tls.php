<?php
/**
 * Example: connect to a Proxmox host with a self-signed certificate.
 *
 * The PVE web UI ships with a self-signed cert by default. Production
 * setups should use a real CA-signed cert (Let's Encrypt via the
 * Proxmox UI), but home-lab and dev setups commonly need to opt out
 * of cert verification.
 *
 * **Security note:** disabling verification is vulnerable to MITM.
 * Use only on trusted networks.
 *
 * Run with:
 *
 *   composer require textalk/websocket
 *
 *   PVE_HOST=https://pve.example.com:8006 \
 *   PVE_TOKEN='PVEAPIToken=root@pam!auto=...' \
 *   PVE_NODE=orca PVE_VMID=100 \
 *   php examples/insecure_tls.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClientApi\PveClient\Configuration;
use ClientApi\PveClient\Pve;
use ClientApi\PveClient\TerminalTarget;
use ClientApi\PveClient\TextalkTransport;
use GuzzleHttp\Client as GuzzleClient;

$host = getenv('PVE_HOST') ?: 'https://localhost:8006';
$token = getenv('PVE_TOKEN') ?: '';

$config = (new Configuration())
    ->setHost($host . '/api2/json')
    // Full `PVEAPIToken=…` string goes in here; PHP's prefix-join
    // adds a space between the prefix and the value, but PVE rejects
    // `PVEAPIToken= <value>` (with space). Set the full string and
    // leave the prefix unset.
    ->setApiKey('Authorization', $token);

// ── 1. REST: a Guzzle client with `verify => false` for self-signed PVE.
// ── 2. WebSocket: TextalkTransport::insecure() builds a stream_context
//      with verify_peer=false / allow_self_signed=true.
//
// The `Pve` facade carries both; per-tag accessors and connectTerminal
// honor the one you pass in.
$pve = new Pve(
    config: $config,
    http: new GuzzleClient(['verify' => false, 'timeout' => 30]),
    wsTransport: TextalkTransport::insecure(),
);

$response = $pve->nodes()->nodesGetNodes();
$nodes = $response->getData() ?? [];
printf("Connected (insecure TLS): %d node(s)\n", count($nodes));
foreach ($nodes as $n) {
    $arr = is_object($n) && method_exists($n, 'jsonSerialize') ? (array) $n->jsonSerialize() : (array) $n;
    printf("  - %s (status=%s)\n", $arr['node'] ?? '?', $arr['status'] ?? '?');
}

if (!getenv('PVE_NODE') || !getenv('PVE_VMID')) {
    echo "(skip terminal: set PVE_NODE and PVE_VMID to test the WebSocket leg)\n";
    exit(0);
}

$target = new TerminalTarget(
    kind: TerminalTarget::KIND_QEMU,
    node: getenv('PVE_NODE'),
    vmid: (int) getenv('PVE_VMID'),
);

$session = $pve->connectTerminal($target);
$session->send("uname -a\n");

$deadline = microtime(true) + 3.0;
while (microtime(true) < $deadline) {
    $msg = $session->recv();
    if ($msg === null) break;
    echo $msg;
}
$session->close();
