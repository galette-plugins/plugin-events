<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Filters;

use Analog\Analog;
use Galette\Core\Pagination;
use Galette\Enums\SQLOrder;
use GaletteEvents\Repository\Bookings;

/**
 * Bookings lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property string          $query
 * @property string|int|null $event_filter
 * @property int|string      $paid_filter
 * @property int             $payment_type_filter
 * @property array<string>   $selected
 * @property string|int|null $group_filter
 */

class BookingsList extends Pagination
{
    //filters
    private string|int|null $event_filter;
    private int|string $paid_filter;
    private int $payment_type_filter;
    private string|int|null $group_filter;

    /** @var array<int> */
    private array $selected;
    private string $query;

    /** @var array<string> */
    protected array $list_fields = [
        'event_filter',
        'paid_filter',
        'payment_type_filter',
        'selected',
        'group_filter'
    ];

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
        $this->event_filter = 'all';
        $this->paid_filter = Bookings::FILTER_DC_PAID;
        $this->payment_type_filter = -1;
        $this->selected = [];
        $this->group_filter = null;
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->list_fields)) {
                return $this->$name;
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                __CLASS__,
                $name
            )
        );
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     */
    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, $this->pagination_fields)) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[BookingsList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'selected':
                    if (is_array($value)) {
                        $this->$name = $value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[BookingsList] Value for property `' . $name
                            . '` should be an array (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'payment_type_filter':
                    $this->$name = (int)$value;
                    break;
                default:
                    $this->$name = $value;
                    break;
            }
        }
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
