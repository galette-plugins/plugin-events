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
 * @property string  $query
 * @property bool    $calendar_filter
 * @property ?string $start_date_filter
 * @property ?string $raw_start_date_filter
 * @property ?string $end_date_filter
 * @property ?string $raw_end_date_filter
 */

class EventsList extends Pagination
{
    //filters
    private ?string $start_date_filter = null;
    private ?string $end_date_filter = null;
    private bool $calendar_filter = false;
    private string $query;

    /** @var array<string> */
    protected array $list_fields = [
        'start_date_filter',
        'raw_start_date_filter',
        'end_date_filter',
        'raw_end_date_filter',
        'calendar_filter'
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
                switch ($name) {
                    case 'raw_start_date_filter':
                        return $this->start_date_filter;
                    case 'raw_end_date_filter':
                        return $this->end_date_filter;
                    case 'start_date_filter':
                    case 'end_date_filter':
                        try {
                            if ($this->$name !== null) {
                                $d = new \DateTime($this->$name);
                                return $d->format(__("Y-m-d"));
                            }
                        } catch (\Exception $e) {
                            //oops, we've got a bad date :/
                            Analog::log(
                                'Bad date (' . $this->$name . ') | '
                                . $e->getMessage(),
                                Analog::INFO
                            );
                            return $this->$name;
                        }
                        break;
                    default:
                        return $this->$name;
                }
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
                '[EventsList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'start_date_filter':
                case 'end_date_filter':
                    try {
                        if ($value !== '') {
                            $y = \DateTime::createFromFormat(__("Y"), $value);
                            if ($y !== false) {
                                $month = 1;
                                $day = 1;
                                if ($name === 'end_date_filter') {
                                    $month = 12;
                                    $day = 31;
                                }
                                $y->setDate(
                                    (int)$y->format('Y'),
                                    $month,
                                    $day
                                );
                                $this->$name = $y->format('Y-m-d');
                            }

                            $ym = \DateTime::createFromFormat(__("Y-m"), $value);
                            if ($y === false && $ym  !== false) {
                                $day = 1;
                                if ($name === 'end_date_filter') {
                                    $day = (int)$ym->format('t');
                                }
                                $ym->setDate(
                                    (int)$ym->format('Y'),
                                    (int)$ym->format('m'),
                                    $day
                                );
                                $this->$name = $ym->format('Y-m-d');
                            }

                            $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                            if ($y === false && $ym  === false && $d !== false) {
                                $this->$name = $d->format('Y-m-d');
                            }

                            if ($y === false && $ym === false && $d === false) {
                                $formats = [
                                    __("Y"),
                                    __("Y-m"),
                                    __("Y-m-d"),
                                ];

                                $field = null;
                                if ($name === 'start_date_filter') {
                                    $field = _T("start date filter");
                                }
                                if ($name === 'end_date_filter') {
                                    $field = _T("end date filter");
                                }

                                throw new \Exception(
                                    sprintf(
                                        //TRANS: %1$s is field label, %2$s is list of known date formats
                                        _T('Unknown date format for %1$s.<br/>Know formats are: %2$s'),
                                        $field,
                                        implode(', ', $formats)
                                    )
                                );
                            }
                        } else {
                            $this->$name = null;
                        }
                    } catch (\Exception $e) {
                        Analog::log(
                            'Wrong date format. field: ' . $name
                            . ', value: ' . $value . ', expected fmt: '
                            . __("Y-m-d") . ' | ' . $e->getMessage(),
                            Analog::INFO
                        );
                        throw $e;
                    }
                    break;
                default:
                    $this->$name = $value;
                    break;
            }
        }
    }
}
