<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

use Galette\Core\Db;
use Galette\Entity\Adherent;

/**
 * Personal calendar subscriptions
 *
 * Each member may get a secret token, that gives read access to the events
 * they can see, without session. Tokens are stored as is, so that members
 * can display their subscription URL again; they are only created on demand.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Feeds
{
    public const string TABLE = 'feeds';
    public const string TOKEN_PATTERN = '[0-9a-f]{64}';

    /**
     * Constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Get token of a member, if any
     *
     * @param int $member Member identifier
     */
    public function getToken(int $member): ?string
    {
        $select = $this->zdb->select(EVENTS_PREFIX . self::TABLE);
        $select->where([Adherent::PK => $member]);
        $row = $this->zdb->execute($select)->current();
        return $row ? (string)$row['token'] : null;
    }

    /**
     * Get token of a member, create it if missing
     *
     * @param int $member Member identifier
     */
    public function getOrCreateToken(int $member): string
    {
        return $this->getToken($member) ?? $this->regenerate($member);
    }

    /**
     * Give a member a new token; the previous one, if any, no longer works
     *
     * @param int $member Member identifier
     */
    public function regenerate(int $member): string
    {
        $token = bin2hex(random_bytes(32));
        $values = [
            'token'         => $token,
            'creation_date' => date('Y-m-d'),
        ];

        if ($this->getToken($member) === null) {
            $insert = $this->zdb->insert(EVENTS_PREFIX . self::TABLE);
            $insert->values($values + [Adherent::PK => $member]);
            $this->zdb->execute($insert);
        } else {
            $update = $this->zdb->update(EVENTS_PREFIX . self::TABLE);
            $update->set($values)->where([Adherent::PK => $member]);
            $this->zdb->execute($update);
        }

        return $token;
    }

    /**
     * Get member a token belongs to
     *
     * @param string $token Token
     */
    public function getMember(string $token): ?int
    {
        if (!preg_match('/^' . self::TOKEN_PATTERN . '$/', $token)) {
            return null;
        }

        $select = $this->zdb->select(EVENTS_PREFIX . self::TABLE);
        $select->where(['token' => $token]);
        $row = $this->zdb->execute($select)->current();
        return $row ? (int)$row[Adherent::PK] : null;
    }
}
