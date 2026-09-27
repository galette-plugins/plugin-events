<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

use ArrayObject;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Analog\Analog;
use Laminas\Db\Sql\Expression;

/**
 * Activity entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activity
{
    public const string TABLE = 'activities';
    public const string PK = 'id_activity';

    public const int NO = 0;
    public const int YES = 1;
    public const int REQUIRED = 2;

    private Db $zdb;
    private Login $login;
    private History $history;
    /** @var array<string> */
    private array $errors = [];

    private int $id;
    private string $name;
    private bool $active = false;
    private string $creation_date;
    private string $comment = '';

    /**
     * Default constructor
     *
     * @param Db                                  $zdb     Database instance
     * @param Login                               $login   Login instance
     * @param History                             $history History instance
     * @param null|int|ArrayObject<string, mixed> $args    Either a ResultSet row or its id for to load
     *                                                     a specific activity, or null to just
     *                                                     instanciate object
     */
    public function __construct(Db $zdb, Login $login, History $history, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;
        $this->history = $history;

        if (is_int($args) && $args > 0) {
            $this->load($args);
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Loads an activity from its id
     *
     * @param int $id the identifiant for the activity to load
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load(int $id): bool
    {
        try {
            $select = $this->zdb->select($this->getTableName());
            $select->where([self::PK => $id]);
            $results = $this->zdb->execute($select);

            if ($results->count() > 0) {
                $this->loadFromRS($results->current());
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load activity #`' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, mixed> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r['id_activity'];
        $this->name = $r['name'];
        $this->active = (bool)$r['is_active'];
        $this->creation_date = $r['creation_date'];
        $this->comment = $r['comment'] ?? '';
    }

    /**
     * Remove specified event
     */
    public function remove(): bool
    {
        $transaction = false;

        try {
            if (!$this->zdb->connection->inTransaction()) {
                $this->zdb->connection->beginTransaction();
                $transaction = true;
            }

            $delete = $this->zdb->delete($this->getTableName());
            $delete->where([self::PK => $this->id]);
            $this->zdb->execute($delete);

            //commit all changes
            if ($transaction) {
                $this->zdb->connection->commit();
            }

            return true;
        } catch (\Exception $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'Unable to delete activity ' . $this->name
                . ' (' . $this->id . ') |' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Check posted values validity
     *
     * @param array<string, mixed> $values All values to check, basically the $_POST array
     *                                     after sending the form
     */
    public function check(array $values): bool
    {
        $this->errors = [];

        if (empty($values['name'])) {
            $this->errors[] = _T('Name is mandatory', 'events');
        } else {
            $this->name = $values['name'];
        }

        if (isset($values['active'])) {
            $this->active = true;
        } else {
            $this->active = false;
        }

        if (isset($values['comment'])) {
            $this->comment = $values['comment'];
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been thrown attempting to edit/store an activity' . "\n"
                . print_r($this->errors, true),
                Analog::ERROR
            );
            return false;
        } else {
            Analog::log(
                'Activity checked successfully.',
                Analog::DEBUG
            );
            return true;
        }
    }

    /**
     * Store the activity
     */
    public function store(): bool
    {
        try {
            $values = [
                'name'                  => $this->name,
                'is_active'             => ($this->active ? $this->active
                                                : ($this->zdb->isPostgres() ? 'false' : 0)),
                'comment'               => $this->comment
            ];

            if (empty($this->id)) {
                //we're inserting a new event
                $this->creation_date = date("Y-m-d");
                $values['creation_date'] = $this->creation_date;

                $insert = $this->zdb->insert($this->getTableName());
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    if ($this->zdb->isPostgres()) {
                        /** @phpstan-ignore-next-line */
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue(
                            PREFIX_DB . $this->getTableName() . '_id_seq'
                        );
                    } else {
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue();
                    }

                    // logging
                    $this->history->add(
                        _T("Activity added", "events"),
                        $this->name
                    );
                    return true;
                } else {
                    $this->history->add(_T("Fail to add new activity.", "events"));
                    throw new \Exception(
                        'An error occurred inserting new activity!'
                    );
                }
            } else {
                //we're editing an existing event
                $values[self::PK] = $this->id;
                $update = $this->zdb->update($this->getTableName());
                $update
                    ->set($values)
                    ->where([self::PK => $this->id]);

                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $this->history->add(
                        _T("Activity updated", "events"),
                        $this->name
                    );
                }
                return true;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n"
                . $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get event id
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get event name
     */
    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * Get date
     *
     * @param string $prop      Property to use
     * @param bool   $formatted Return date formatted, raw if false
     */
    private function getDate(string $prop, bool $formatted = true): string
    {
        if (!isset($this->$prop)) {
            return '';
        }

        if ($formatted === true) {
            $date = new \DateTime($this->$prop);
            return $date->format(__("Y-m-d"));
        } else {
            return $this->$prop;
        }
    }

    /**
     * Get creation date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getCreationDate(bool $formatted = true): string
    {
        return $this->getDate('creation_date', $formatted);
    }

    /**
     * Is actvity active?
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Get table's name
     */
    protected function getTableName(): string
    {
        return EVENTS_PREFIX . self::TABLE;
    }

    /**
     * Get comment
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Count number of events using this Activity
     */
    public function countEvents(): int
    {
        if (empty($this->id)) {
            return 0;
        }

        $select = $this->zdb->select(EVENTS_PREFIX . 'activitiesevents');

        $select->columns(
            [
                'counter' => new Expression('COUNT(' . Event::PK . ')')
            ]
        )->where([self::PK => $this->id]);
        $results = $this->zdb->execute($select);
        $result = $results->current();
        $count = $result->counter;
        return (int)$count;
    }

    /**
     * Get errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
