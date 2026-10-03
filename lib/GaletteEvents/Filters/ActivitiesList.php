<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Filters;

use Galette\Core\Pagination;
use Galette\Enums\SQLOrder;
use GaletteEvents\Repository\Activities;

/**
 * Activities lists paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ActivitiesList extends Pagination
{
    use FiltersTrait;

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->reinit();
    }

    /**
     * Returns the field we want to default set order to
     *
     * @return int|string field name
     */
    protected function getDefaultOrder(): int|string
    {
        return Activities::ORDERBY_DATE;
    }

    /**
     * Return the default direction for ordering
     */
    protected function getDefaultDirection(): SQLOrder
    {
        return SQLOrder::DESC;
    }

    /**
     * Activities lists have no filter, only pagination
     *
     * @return array<string>
     */
    protected function getFilterNames(): array
    {
        return [];
    }

    /**
     * Activities lists have no filter, only pagination
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     */
    protected function setFilter(string $name, mixed $value): bool
    {
        return false;
    }
}
