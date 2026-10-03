<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

/**
 * Requested event, booking or activity does not exist
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class NotFoundException extends \RuntimeException
{
}
