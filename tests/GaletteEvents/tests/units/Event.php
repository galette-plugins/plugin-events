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
 * Event entity tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Event extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20260926151512;

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
     * Get values posted from event form
     *
     * @param array<string,mixed> $values Values to override
     *
     * @return array<string,mixed>
     */
    private function getFormValues(array $values = []): array
    {
        return $values + [
            'name'          => 'Event',
            'address'       => '',
            'zip'           => '',
            'town'          => 'Lille',
            'country'       => '',
            'comment'       => '',
            'color'         => '',
            'begin_date'    => date('Y-m-d', strtotime('+10 days')),
            'end_date'      => date('Y-m-d', strtotime('+11 days')),
            'open'          => '1',
            'save'          => '1',
        ];
    }

    /**
     * Group managers create events of the groups they manage
     */
    public function testManagerCreatesEvent(): void
    {
        $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $managed = $this->createGroup('Managed group', [$member_two]);
        $other = $this->createGroup('Other group', [], [$member_two]);
        $this->logMember($this->dataAdherentTwo());

        $event = new \GaletteEvents\Event($this->zdb, $this->login);
        $this->assertSame(
            [_T('Please select a group you own!', 'events')],
            $event->check($this->getFormValues(['group' => (string)$other->getId()]))
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been threw attempting to edit/store an event');

        $event = new \GaletteEvents\Event($this->zdb, $this->login);
        $this->assertTrue($event->check($this->getFormValues(['group' => (string)$managed->getId()])));
        $this->assertTrue($event->store());

        $event = new \GaletteEvents\Event($this->zdb, $this->login, (int)$event->getId());
        $this->assertSame($managed->getId(), $event->getGroup());
    }

    /**
     * Events are stored with mandatory values only
     */
    public function testStoreMandatoryValuesOnly(): void
    {
        $this->logSuperAdmin();
        $event = new \GaletteEvents\Event($this->zdb, $this->login);
        $this->assertTrue($event->check([
            'name'          => 'Event',
            'town'          => 'Lille',
            'begin_date'    => date('Y-m-d', strtotime('+10 days')),
        ]));
        $this->assertTrue($event->store());

        $event = new \GaletteEvents\Event($this->zdb, $this->login, (int)$event->getId());
        $this->assertSame('Event', $event->getName());
        $this->assertSame('', $event->getAddress());
        $this->assertNull($event->getGroup());
    }

    /**
     * Optional values may be NULL in database
     */
    public function testLoadNullValues(): void
    {
        $id = $this->insertEvent('Event', ['comment' => null, 'country' => null]);

        $event = new \GaletteEvents\Event($this->zdb, $this->login, $id);
        $this->assertSame('', $event->getComment());
        $this->assertNull($event->getGroup());
        $this->assertSame('', $event->getColor());
    }
}
