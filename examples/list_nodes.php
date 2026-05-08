<?php
/**
 * Example: list cluster nodes.
 *
 * Run with:
 *
 *   PVE_HOST=https://pve.example.com:8006 \
 *   PVE_TOKEN='PVEAPIToken=root@pam!auto=...' \
 *   php examples/list_nodes.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClientApi\PveClient\Configuration;
use ClientApi\PveClient\Pve;

$host = getenv('PVE_HOST') ?: 'https://localhost:8006';
$config = (new Configuration())
    ->setHost($host . '/api2/json')
    ->setApiKey('Authorization', getenv('PVE_TOKEN') ?: '');

$pve = new Pve($config);
$response = $pve->nodes()->nodesGetNodes();
$nodes = $response->getData() ?? [];

printf("Found %d node(s):\n", count($nodes));
foreach ($nodes as $node) {
    printf(
        "  - %s (status=%s, cpu=%s, mem=%s/%s)\n",
        $node->getNode() ?? '?',
        $node->getStatus() ?? '?',
        $node->getCpu() ?? '?',
        $node->getMem() ?? '?',
        $node->getMaxmem() ?? '?',
    );
}
