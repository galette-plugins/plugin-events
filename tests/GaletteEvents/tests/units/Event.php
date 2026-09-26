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

    /**
     * Activities linked to an event are added, changed and removed
     */
    public function testActivitiesSync(): void
    {
        $this->logSuperAdmin();
        $dinner = $this->insertActivity('Dinner');
        $lodging = $this->insertActivity('Lodging');
        $visit = $this->insertActivity('Visit');
        $ids = array_map('strval', [$dinner, $lodging, $visit]);

        $event = new \GaletteEvents\Event($this->zdb, $this->login);
        $this->assertTrue($event->check($this->getFormValues([
            'activities_ids'    => $ids,
            'activities_status' => ['1', '1', '2'],
        ])));
        $this->assertTrue($event->store());
        $id = (int)$event->getId();
        $this->assertSame([$dinner => 1, $lodging => 1, $visit => 2], $this->getEventActivities($id));

        //change status of activities that are not the last one
        $event = new \GaletteEvents\Event($this->zdb, $this->login, $id);
        $this->assertTrue($event->check($this->getFormValues([
            'activities_ids'    => $ids,
            'activities_status' => ['2', '0', '2'],
        ])));
        $this->assertTrue($event->store());
        $this->assertSame([$dinner => 2, $lodging => 0, $visit => 2], $this->getEventActivities($id));

        //remove two activities before storing
        $event = new \GaletteEvents\Event($this->zdb, $this->login, $id);
        $this->assertTrue($event->check($this->getFormValues([
            'remove_activity'   => '1',
            'detach_activity'   => (string)$dinner,
            'activities_ids'    => $ids,
            'activities_status' => ['2', '0', '2'],
        ])));
        $this->assertTrue($event->check($this->getFormValues([
            'remove_activity'   => '1',
            'detach_activity'   => (string)$lodging,
            'activities_ids'    => [(string)$lodging, (string)$visit],
            'activities_status' => ['0', '2'],
        ])));
        $this->assertTrue($event->store());
        $this->assertSame([$visit => 2], $this->getEventActivities($id));

        //reloading does not keep activities of the previous event
        $other = (int)$this->insertEvent('Other event');
        $this->assertTrue($event->load($other));
        $this->assertSame([], $event->getActivities());
    }

    /**
     * Only active activities can be attached to events
     */
    public function testInactiveActivities(): void
    {
        $this->logSuperAdmin();
        $dinner = $this->insertActivity('Dinner');
        $lodging = $this->insertActivity('Lodging');
        $update = $this->zdb->update(EVENTS_PREFIX . \GaletteEvents\Activity::TABLE);
        $update->set(['is_active' => $this->zdb->isPostgres() ? 'false' : 0])
            ->where([\GaletteEvents\Activity::PK => $lodging]);
        $this->zdb->execute($update);

        $event = new \GaletteEvents\Event($this->zdb, $this->login);
        $this->assertSame(
            [$dinner],
            array_map(fn($row): int => (int)$row[\GaletteEvents\Activity::PK], $event->availableActivities())
        );

        $this->assertTrue($event->check($this->getFormValues([
            'add_activity'      => '1',
            'attach_activity'   => (string)$lodging,
        ])));
        $this->assertSame([], $event->getActivities());
    }

    /**
     * Edition rights are checked for given login, not for the one the event has been loaded with
     */
    public function testCanEditChecksGivenLogin(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $managed = $this->createGroup('Managed group', [$member_two]);
        $other = $this->createGroup('Other group', [$member_one]);
        $managed_event = $this->insertEvent('Managed event', ['id_group' => $managed->getId()]);
        $other_event = $this->insertEvent('Other event', ['id_group' => $other->getId()]);

        //events are loaded with member one logged in
        $this->logMember($this->dataAdherentOne());
        $manager = new \Galette\Core\Login($this->zdb, $this->i18n);
        $this->assertTrue($manager->login($this->dataAdherentTwo()['login_adh'], $this->dataAdherentTwo()['mdp_adh']));

        $this->assertTrue((new \GaletteEvents\Event($this->zdb, $this->login, $managed_event))->canEdit($manager));
        $this->assertFalse((new \GaletteEvents\Event($this->zdb, $this->login, $other_event))->canEdit($manager));
        $this->assertFalse((new \GaletteEvents\Event($this->zdb, $this->login, $this->insertEvent('Public event')))->canEdit($manager));
    }

    /**
     * Posted values are checked
     */
    public function testCheck(): void
    {
        $this->logSuperAdmin();
        $event = new \GaletteEvents\Event($this->zdb, $this->login);

        $this->assertSame(
            ['Begin date is mandatory', 'Name is mandatory', 'Town is mandatory'],
            $event->check(['begin_date' => ''])
        );
        $this->assertSame(
            ['- Wrong date format (Y-m-d) for Begin date!'],
            $event->check($this->getFormValues(['begin_date' => 'tomorrow']))
        );
        $this->assertSame(
            ['End date must be later or equal to begin date'],
            $event->check($this->getFormValues(['begin_date' => '2026-10-10', 'end_date' => '2026-10-09']))
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been threw attempting to edit/store an event');

        //end date defaults to begin date
        $values = $this->getFormValues(['begin_date' => '2026-10-10']);
        unset($values['end_date']);
        $this->assertTrue($event->check($values));
        $this->assertSame('2026-10-10', $event->getEndDate(false));
        $this->assertTrue($event->isOpenFlag());

        $this->assertTrue($event->check($this->getFormValues(['open' => null])));
        $values = $this->getFormValues();
        unset($values['open']);
        $this->assertTrue($event->check($values));
        $this->assertFalse($event->isOpenFlag());
        $this->assertFalse($event->isOpen());
    }

    /**
     * Events are removed with their bookings and activities links
     */
    public function testRemove(): void
    {
        $member_one = $this->getMemberOne();
        $id = $this->insertEvent('Event');
        $this->linkActivity($id, $this->insertActivity('Dinner'));
        $this->insertBooking($id, $member_one->id, ['number_people' => 3, 'is_paid' => $this->zdb->isPostgres() ? 'true' : 1]);

        $event = new \GaletteEvents\Event($this->zdb, $this->login, $id);
        $attendees = [];
        foreach ($event->countAttendees() as $row) {
            $attendees[(int)(bool)$row['is_paid']] = (int)$row['count'];
        }
        $this->assertSame([1 => 3], $attendees);

        $this->assertTrue($event->remove());
        $this->assertSame(0, $this->countBookings($id));
        $this->assertSame([], $this->getEventActivities($id));
        $this->assertFalse((new \GaletteEvents\Event($this->zdb, $this->login))->load($id));
    }
}
