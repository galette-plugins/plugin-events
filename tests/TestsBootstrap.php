<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Bootstrap tests file for Galette Events plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

define('GALETTE_PLUGINS_PATH', __DIR__ . '/../../');
$basepath = __DIR__ . '/../../../'; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used from Core testBootstrap

include_once __DIR__ . '/../../../../tests/TestsBootstrap.php';
require_once __DIR__ . '/../_config.inc.php';
require_once __DIR__ . '/EventsFixtures.php';
