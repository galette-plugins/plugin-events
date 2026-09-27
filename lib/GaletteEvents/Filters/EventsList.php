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
use GaletteEvents\Repository\Events;

/**
 * Events lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property-read  bool    $calendar_filter
 * @property-read  ?string $start_date_filter
 * @property-read  ?string $raw_start_date_filter
 * @property-read  ?string $end_date_filter
 * @property-read  ?string $raw_end_date_filter
 * @property-write mixed   $calendar_filter
 * @property-write mixed   $start_date_filter
 * @property-write mixed   $end_date_filter
 */

class EventsList extends Pagination
{
    use FiltersTrait;

    //filters
    private ?string $start_date_filter = null;
    private ?string $end_date_filter = null;
    private bool $calendar_filter = false;

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
        return Events::ORDERBY_DATE;
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
        $this->start_date_filter = null;
        $this->end_date_filter = null;
        $this->calendar_filter = false;
    }

    /**
     * Names of the filtering properties; raw dates are read only
     *
     * @return array<string>
     */
    protected function getFilterNames(): array
    {
        return [
            'start_date_filter',
            'raw_start_date_filter',
            'end_date_filter',
            'raw_end_date_filter',
            'calendar_filter'
        ];
    }

    /**
     * Get a filtering property; dates are localized, raw ones are not
     *
     * @param string $name Property name
     */
    protected function getFilter(string $name): mixed
    {
        switch ($name) {
            case 'raw_start_date_filter':
                return $this->start_date_filter;
            case 'raw_end_date_filter':
                return $this->end_date_filter;
            case 'start_date_filter':
            case 'end_date_filter':
                if ($this->$name === null) {
                    return null;
                }
                return (new \DateTime($this->$name))->format(__("Y-m-d"));
            default:
                return $this->$name;
        }
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
            case 'start_date_filter':
            case 'end_date_filter':
                $this->$name = $value === '' || $value === null
                    ? null
                    : $this->parseDate($name, (string)$value);
                return true;
            case 'calendar_filter':
                $this->calendar_filter = (bool)$value;
                return true;
        }
        return false;
    }

    /**
     * Parse a date filter typed as a year, a month or a day
     *
     * A year or a month starts on its first day, or ends on its last one for the end date.
     *
     * @param string $name  Property name
     * @param string $value Typed value
     *
     * @return string Date, as Y-m-d
     */
    private function parseDate(string $name, string $value): string
    {
        $end = $name === 'end_date_filter';

        $date = \DateTime::createFromFormat('!' . __("Y"), $value);
        if ($date !== false) {
            return ($end ? $date->setDate((int)$date->format('Y'), 12, 31) : $date)->format('Y-m-d');
        }

        $date = \DateTime::createFromFormat('!' . __("Y-m"), $value);
        if ($date !== false) {
            return ($end ? $date->modify('last day of this month') : $date)->format('Y-m-d');
        }

        $date = \DateTime::createFromFormat(__("Y-m-d"), $value);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }

        Analog::log(
            'Wrong date format. field: ' . $name . ', value: ' . $value . ', expected fmt: ' . __("Y-m-d"),
            Analog::INFO
        );
        throw new \RuntimeException(
            sprintf(
                //TRANS: %1$s is field label, %2$s is list of known date formats
                _T('Unknown date format for %1$s.<br/>Know formats are: %2$s'),
                $end ? _T("end date filter") : _T("start date filter"),
                implode(', ', [__("Y"), __("Y-m"), __("Y-m-d")])
            )
        );
    }
}
