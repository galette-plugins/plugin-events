<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Repository\Groups;

/**
 * Who events are listed for
 *
 * Either the logged-in member, or a member without session, as calendar
 * applications fetching a subscription are.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class Viewer
{
    /**
     * Constructor
     *
     * @param ?int       $id      Member identifier, none for superadmin
     * @param bool       $admin   Is an administrator
     * @param bool       $staff   Is a staff member
     * @param array<int> $managed Managed groups identifiers
     */
    public function __construct(
        public ?int $id,
        public bool $admin,
        public bool $staff,
        public array $managed
    ) {
    }

    /**
     * Logged-in member
     *
     * @param Login $login Login instance
     */
    public static function fromLogin(Login $login): self
    {
        //superadmin is not a member, its identifier is 0
        $id = (int)$login->id;
        //admins and staff members manage every group de facto
        $privileged = $login->isAdmin() || $login->isStaff();
        return new self(
            $id > 0 ? $id : null,
            $login->isAdmin(),
            $login->isStaff(),
            $privileged ? [] : array_values(array_map('intval', $login->managed_groups))
        );
    }

    /**
     * Any member, as if they were logged in
     *
     * @param Adherent $member Member
     */
    public static function fromMember(Adherent $member): self
    {
        //admins and staff members manage every group de facto
        $privileged = $member->isAdmin() || $member->isStaff();
        return new self(
            (int)$member->id,
            $member->isAdmin(),
            $member->isStaff(),
            $privileged ? [] : array_values(array_map('intval', Groups::loadManagedGroups((int)$member->id, as_group: false)))
        );
    }

    /**
     * Does viewer see every event?
     */
    public function seesAll(): bool
    {
        return $this->admin || $this->staff;
    }
}
