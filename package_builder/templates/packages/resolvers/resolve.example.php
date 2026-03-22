<?php
/**
 * @var xPDO\Transport\xPDOTransport $transport
 * @var xPDO\xPDO|MODX\Revolution\modX $object
 * @var array $options
 */

$modx = &$transport->xpdo;

if (!($transport && $object && $options)) {
    return true;
}

$action = $options[xPDOTransport::PACKAGE_ACTION] ?? '';

switch ($action) {
    case xPDOTransport::ACTION_INSTALL:
        $modx->log(xPDO::LOG_LEVEL_INFO, '[{{package_name}}] Resolver: install');
        break;

    case xPDOTransport::ACTION_UPGRADE:
        $modx->log(xPDO::LOG_LEVEL_INFO, '[{{package_name}}] Resolver: upgrade');
        break;

    case xPDOTransport::ACTION_UNINSTALL:
        $modx->log(xPDO::LOG_LEVEL_INFO, '[{{package_name}}] Resolver: uninstall');
        break;
}

return true;
