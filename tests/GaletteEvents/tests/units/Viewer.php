<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\tests\units;

use Galette\Tests\GaletteTestCase;
use GaletteEvents\tests\EventsFixtures;

/**
 * Viewer tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Viewer extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20261027081512;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        $this->cleanEvents();
        parent::tearDown();
    }

    /**
     * A member without session is seen as when logged in
     */
    public function testFromMemberAsLogin(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $managed = $this->createGroup('Managed group', [$member_two], [$member_one]);
        $this->createGroup('Other group', [], [$member_two]);

        $this->logMember($this->dataAdherentTwo());
        $viewer = \GaletteEvents\Viewer::fromLogin($this->login);
        $this->assertEquals($viewer, \GaletteEvents\Viewer::fromMember(new \Galette\Entity\Adherent($this->zdb, (int)$member_two->id)));
        $this->assertSame((int)$member_two->id, $viewer->id);
        $this->assertSame([(int)$managed->getId()], $viewer->managed);
        $this->assertFalse($viewer->seesAll());
        $this->login->logout();

        //staff members manage every group de facto, none is listed
        $staff = $this->getStaffMember($member_one);
        $this->login->logout();
        $this->logMember($this->dataAdherentOne());
        $viewer = \GaletteEvents\Viewer::fromLogin($this->login);
        $this->assertEquals($viewer, \GaletteEvents\Viewer::fromMember(new \Galette\Entity\Adherent($this->zdb, (int)$member_one->id)));
        $this->assertTrue($viewer->seesAll());
        $this->assertSame([], $viewer->managed);
        $this->login->logout();
        $this->resetStaffStatus($staff, $member_two);
    }

    /**
     * Superadmin is not a member
     */
    public function testSuperAdmin(): void
    {
        $this->logSuperAdmin();
        $viewer = \GaletteEvents\Viewer::fromLogin($this->login);
        $this->assertNull($viewer->id);
        $this->assertTrue($viewer->seesAll());
    }
}
