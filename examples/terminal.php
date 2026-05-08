<?php
/**
 * Example: open a terminal session against a QEMU VM.
 *
 * Run with:
 *
 *   composer require textalk/websocket
 *
 *   PVE_HOST=https://pve.example.com:8006 \
 *   PVE_TOKEN='PVEAPIToken=root@pam!auto=...' \
 *   PVE_NODE=orca PVE_VMID=100 \
 *   php examples/terminal.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClientApi\PveClient\Configuration;
use ClientApi\PveClient\Pve;
use ClientApi\PveClient\TerminalTarget;

$host = getenv('PVE_HOST') ?: 'https://localhost:8006';
$config = (new Configuration())
    ->setHost($host . '/api2/json')
    // PVE wants `Authorization: PVEAPIToken=<id>=<secret>` with NO
    // space; the openapi-generator's prefix-join would inject one,
    // so put the full prefixed string in the api_key value.
    ->setApiKey('Authorization', getenv('PVE_TOKEN') ?: '');

$node = getenv('PVE_NODE') ?: 'pve1';
$vmid = (int) (getenv('PVE_VMID') ?: '100');

printf("Opening terminal on %s:qemu/%d...\n", $node, $vmid);

$target = new TerminalTarget(
    kind: TerminalTarget::KIND_QEMU,
    node: $node,
    vmid: $vmid,
);

$session = (new Pve($config))->connectTerminal($target);
$session->resize(120, 32);
$session->send("uname -a\n");

$deadline = microtime(true) + 5.0;
while (microtime(true) < $deadline) {
    $msg = $session->recv();
    if ($msg === null) break;
    echo $msg;
}
$session->close();
