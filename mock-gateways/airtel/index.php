<?php
/**
 * Simulateur airtel_money -- routeur du serveur integre de PHP.
 * Demarrage : scripts\mock-gateways.bat (ou voir mock-gateways/README.md).
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/amorce.php';

Simulateur::servir('airtel_money');