<?php
/**
 * Example: resilient terminal session with auto-reconnect.
 *
 * Run with:
 *
 *   composer require textalk/websocket
 *
 *   PVE_HOST=https://pve.example.com:8006 \
 *   PVE_TOKEN='PVEAPIToken=root@pam!auto=...' \
 *   PVE_NODE=orca PVE_VMID=100 \
 *   php examples/resilient_terminal.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClientApi\PveClient\Configuration;
use ClientApi\PveClient\ResilientTerminalSession;
use ClientApi\PveClient\RetryOptions;
use ClientApi\PveClient\TerminalTarget;

$host = getenv('PVE_HOST') ?: 'https://localhost:8006';
$config = (new Configuration())
    ->setHost($host . '/api2/json')
    ->setApiKey('Authorization', getenv('PVE_TOKEN') ?: '');

$node = getenv('PVE_NODE') ?: 'pve1';
$vmid = (int) (getenv('PVE_VMID') ?: '100');

$target = new TerminalTarget(
    kind: TerminalTarget::KIND_QEMU,
    node: $node,
    vmid: $vmid,
);

$session = new ResilientTerminalSession(
    config: $config,
    target: $target,
    retry: new RetryOptions(maxRetries: 20, initialDelaySeconds: 0.25),
);

$session->send("date\n");

$deadline = microtime(true) + 5 * 60;
$nextCmd = microtime(true) + 30;
while (microtime(true) < $deadline) {
    $msg = $session->recv();
    if ($msg === null) break;
    echo $msg;
    if (microtime(true) >= $nextCmd) {
        $session->send("date\n");
        $nextCmd = microtime(true) + 30;
    }
}
$session->close();
