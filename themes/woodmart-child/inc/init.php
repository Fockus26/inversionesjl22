<?php
/**
 * Archivo central de carga para el plugin/tema
 * Incluye helpers, filtros, acciones AJAX, estados, etc.
 */

// Cargar helpers primero, para que estén disponibles en todo
require_once __DIR__ . '/helpers.php';

// Cargar configuración global de roles y permisos
require_once __DIR__ . '/roles-permissions/config.php';

// Cargar filtros relacionados a roles y permisos
require_once __DIR__ . '/roles-permissions/filters.php';

// Cargar acciones AJAX de roles y permisos
require_once __DIR__ . '/roles-permissions/ajax-actions.php';

// Cargar estados personalizados y transiciones
require_once __DIR__ . '/order-status/custom-status.php';
require_once __DIR__ . '/order-status/transitions.php';
require_once __DIR__ . '/order-status/filters.php';

// Cargar integraciones, por ejemplo YITH
require_once __DIR__ . '/integrations/yith.php';

// Cargar reportes PDF
require_once __DIR__ . '/pdf-reports/shipping-report.php';
require_once __DIR__ . '/pdf-reports/pdf-generator.php';
require_once __DIR__ . '/pdf-reports/ajax.php';

// Cargar otros archivos sueltos, como SKU generator, enqueue, etc.
require_once __DIR__ . '/sku-generator.php';
require_once __DIR__ . '/enqueue.php';
