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
 * Calendar subscriptions tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Feeds extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20261027091512;

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
     * Tokens are created on demand, kept, and changed on request
     */
    public function testTokens(): void
    {
        $member_one = (int)$this->getMemberOne()->id;
        $member_two = (int)$this->getMemberTwo()->id;
        $feeds = new \GaletteEvents\Feeds($this->zdb);

        $this->assertNull($feeds->getToken($member_one));
        $token = $feeds->getOrCreateToken($member_one);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, $feeds->getOrCreateToken($member_one));
        $this->assertSame($token, $feeds->getToken($member_one));
        $this->assertSame($member_one, $feeds->getMember($token));

        $other = $feeds->getOrCreateToken($member_two);
        $this->assertNotSame($token, $other);
        $this->assertSame($member_two, $feeds->getMember($other));

        //previous token no longer works
        $new = $feeds->regenerate($member_one);
        $this->assertNotSame($token, $new);
        $this->assertNull($feeds->getMember($token));
        $this->assertSame($member_one, $feeds->getMember($new));
        $this->assertSame($other, $feeds->getToken($member_two));

        $this->assertNull($feeds->getMember(str_repeat('0', 64)));
        $this->assertNull($feeds->getMember(strtoupper($new)));
        $this->assertNull($feeds->getMember($new . "' OR '1'='1"));
    }

    /**
     * Subscription gets the events of the calendar, ended for less than given date
     */
    public function testFeedEvents(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $own = $this->createGroup('Own group', [], [$member_one]);
        $other = $this->createGroup('Other group', [], [$member_two]);
        $managed = $this->createGroup('Managed group', [$member_one]);
        $past = [
            'begin_date'    => date('Y-m-d', strtotime('-3 months')),
            'end_date'      => date('Y-m-d', strtotime('-3 months')),
        ];
        $old = [
            'begin_date'    => date('Y-m-d', strtotime('-14 months')),
            'end_date'      => date('Y-m-d', strtotime('-13 months')),
        ];

        $this->insertEvent('public');
        $this->insertEvent('public closed past', ['is_open' => false] + $past);
        $this->insertEvent('public too old', $old);
        $this->insertEvent('own group', ['id_group' => $own->getId()]);
        $this->insertEvent('managed group', ['id_group' => $managed->getId()]);
        $this->insertEvent('other group', ['id_group' => $other->getId()]);
        $this->insertBooking($this->insertEvent('other group booked', ['id_group' => $other->getId()]), $member_one->id);

        $since = date('Y-m-d', strtotime('-12 months'));
        $events = new \GaletteEvents\Repository\Events($this->zdb, $this->login, $this->history, $this->preferences);
        $names = function (\GaletteEvents\Viewer $viewer) use ($events, $since): array {
            $names = array_map(fn(\GaletteEvents\Event $event): string => $event->getName(), $events->getFeed($viewer, $since));
            sort($names);
            return $names;
        };

        //nobody is logged in
        $this->assertSame(
            ['managed group', 'other group booked', 'own group', 'public', 'public closed past'],
            $names(\GaletteEvents\Viewer::fromMember($member_one))
        );
        $this->assertSame(
            ['other group', 'other group booked', 'public', 'public closed past'],
            $names(\GaletteEvents\Viewer::fromMember($member_two))
        );
        $this->assertSame(
            ['managed group', 'other group', 'other group booked', 'own group', 'public', 'public closed past'],
            $names(new \GaletteEvents\Viewer((int)$member_two->id, false, true, []))
        );
    }
}
