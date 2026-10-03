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
use GaletteEvents\Repository\Bookings;

/**
 * Bookings lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property-read  ?int       $event_filter
 * @property-read  int        $paid_filter
 * @property-read  int        $payment_type_filter
 * @property-read  array<int> $selected
 * @property-read  ?int       $group_filter
 * @property-write mixed      $event_filter
 * @property-write mixed      $paid_filter
 * @property-write mixed      $payment_type_filter
 * @property-write mixed      $selected
 * @property-write mixed      $group_filter
 */
class BookingsList extends Pagination
{
    use FiltersTrait;

    //filters
    private ?int $event_filter = null;
    private int $paid_filter = Bookings::FILTER_DC_PAID;
    private int $payment_type_filter = -1;
    private ?int $group_filter = null;
    /** @var array<int> */
    private array $selected = [];

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
        return Bookings::ORDERBY_BOOKDATE;
    }

    /**
     * Return the default direction for ordering
     */
    protected function getDefaultDirection(): SQLOrder
    {
        return SQLOrder::DESC;
    }

    /**
     * Reinit default parameters
     */
    public function reinit(): void
    {
        parent::reinit();
        $this->event_filter = null;
        $this->paid_filter = Bookings::FILTER_DC_PAID;
        $this->payment_type_filter = -1;
        $this->selected = [];
        $this->group_filter = null;
    }

    /**
     * Names of the filtering properties
     *
     * @return array<string>
     */
    protected function getFilterNames(): array
    {
        return ['event_filter', 'paid_filter', 'payment_type_filter', 'selected', 'group_filter'];
    }

    /**
     * Set a filtering property
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     */
    protected function setFilter(string $name, mixed $value): bool
    {
        switch ($name) {
            case 'event_filter':
            case 'group_filter':
                $id = $this->toId($name, $value);
                if ($id !== false) {
                    $this->$name = $id;
                }
                return true;
            case 'paid_filter':
                $this->paid_filter = $this->toChoice(
                    $name,
                    $value,
                    [Bookings::FILTER_DC_PAID, Bookings::FILTER_PAID, Bookings::FILTER_NOT_PAID]
                ) ?? $this->paid_filter;
                return true;
            case 'payment_type_filter':
                if (is_numeric($value)) {
                    $this->payment_type_filter = (int)$value;
                } else {
                    $this->logInvalid($name, $value);
                }
                return true;
            case 'selected':
                if (is_array($value)) {
                    $this->selected = array_values(array_map('intval', $value));
                } else {
                    $this->logInvalid($name, $value);
                }
                return true;
        }
        return false;
    }

    /**
     * Build href
     * Override to keep "event" parameter
     *
     * @param int $page Page
     */
    protected function getHref(int $page): string
    {
        $args = [
            'option'    => 'page',
            'value'     => (string)$page,
            'event'     => $this->event_filter === null ? 'all' : (string)$this->event_filter
        ];
        $href = $this->routeparser->urlFor(
            $this->view->getEnvironment()->getGlobals()['cur_route'],
            $args
        );
        return $href;
    }
}
