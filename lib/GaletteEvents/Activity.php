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
use Analog\Analog;
use Laminas\Db\Sql\Expression;

/**
 * Activity entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activity
{
    use EntityTrait;

    public const string TABLE = 'activities';
    public const string PK = 'id_activity';

    public const int NO = 0;
    public const int YES = 1;
    public const int REQUIRED = 2;

    private Db $zdb;
    private History $history;
    /** @var array<string> */
    private array $errors = [];

    private ?int $id = null;
    private string $name = '';
    private bool $active = false;
    private ?string $creation_date = null;
    private string $comment = '';

    /**
     * Default constructor
     *
     * @param Db                                  $zdb     Database instance
     * @param History                             $history History instance
     * @param null|int|ArrayObject<string, mixed> $args    Either a ResultSet row or its id for to load
     *                                                     a specific activity, or null to just
     *                                                     instanciate object
     */
    public function __construct(Db $zdb, History $history, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;
        $this->history = $history;

        if (is_int($args)) {
            $this->load($args);
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
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
    public function store(): void
    {
        $this->transactional(function (): void {
            $values = [
                'name'                  => $this->name,
                'is_active'             => ($this->active ? $this->active
                                                : ($this->zdb->isPostgres() ? 'false' : 0)),
                'comment'               => $this->comment
            ];

            if ($this->id === null) {
                //we're inserting a new activity
                $this->creation_date = date("Y-m-d");
                $values['creation_date'] = $this->creation_date;

                $insert = $this->zdb->insert($this->getTableName());
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() === 0) {
                    $this->history->add(_T("Fail to add new activity.", "events"));
                    throw new \RuntimeException(
                        'An error occurred inserting new activity!'
                    );
                }
                $this->id = $this->getLastInsertId();

                // logging
                $this->history->add(
                    _T("Activity added", "events"),
                    $this->name
                );
            } else {
                //we're editing an existing activity
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
            }
        });
    }

    /**
     * Get activity id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get activity name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get creation date, as Y-m-d
     */
    public function getCreationDate(): string
    {
        return $this->creation_date ?? '';
    }

    /**
     * Is actvity active?
     */
    public function isActive(): bool
    {
        return $this->active;
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
        if ($this->id === null) {
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
