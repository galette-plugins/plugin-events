<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers;

use Analog\Analog;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\IO\Csv;
use Galette\IO\CsvOut;
use GaletteEvents\Event;
use GaletteEvents\Filters\BookingsList;
use GaletteEvents\Repository\Bookings;

/**
 * CSV controller for events plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CsvController extends \Galette\Controllers\CsvController
{
    /**
     * Bookings CSV exports
     *
     * @param ?int $id Event ID, if any
     */
    public function bookingsExport(Request $request, Response $response, ?int $id = null): Response
    {
        if (
            !$this->login->isAdmin()
            && !$this->login->isStaff()
            && !$this->preferences->pref_bool_groupsmanagers_exports
        ) {
            Analog::log(
                'Logged in member ' . $this->login->login
                . ' has tried to export bookings without the right to do so.',
                Analog::WARNING
            );
            return $this->redirectWithErrors(
                response: $response,
                errors: [_T("You do not have permission for requested URL.")],
                redirect_url: $this->routeparser->urlFor('events_bookings', ['event' => 'all'])
            );
        }

        $post = $request->getParsedBody();
        $get = $request->getQueryParams();
        $csv = new CsvOut();

        $session_var = $post['session_var'] ?? $get['session_var'] ?? 'filter_bookings';
        if (isset($this->session->$session_var) && $id === null) {
            $filters = $this->session->$session_var;
        } else {
            $filters = new BookingsList();
        }

        if ($id !== null) {
            $filters->event_filter = $id;
        }

        $bookings = new Bookings($this->zdb, $this->login, $filters);
        $bookings_list = $bookings->getList(true);

        $labels = [
            _T('Event', 'events'),
            _T('Name'),
            _T('First name'),
            _T('Address'),
            _T('Zip code', 'events'),
            _T('City'),
            _T('Phone'),
            _T('GSM'),
            _T('Email'),
            _T('Group'),
            _T('Number of persons', 'events'),
        ];

        //activities are onl:y available for one event
        if ($filters->event_filter > 0) {
            $event = new Event($this->zdb, $this->login, (int)$filters->event_filter);
            $activities = $event->getActivities();
            foreach ($activities as $activity) {
                $labels[] = $activity['activity']->getName();
            }
        }

        $labels = array_merge(
            $labels,
            [
                _T('Amount', 'events'),
                _T('Payment type'),
                _T('Bank name', 'events'),
                //TRANS: Bank check number
                _T('Check number', 'events'),
            ]
        );

        //prepare labels to work with external soft: requires no accent and MAJ
        foreach ($labels as &$label) {
            $string = htmlentities($label, ENT_NOQUOTES, 'utf-8');
            $string = preg_replace(
                '#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#',
                '\1',
                $string
            );
            $string = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $string);
            $string = preg_replace('#&[^;]+;#', '', $string);
            $label = strtoupper($string);
        }

        $list = [];
        foreach ($bookings_list as $booking) {
            $member = $booking->getMember();
            $entry = [
                $booking->getEvent()->getName(),
                $member->name,
                $member->surname,
                $member->address,
                $member->zipcode,
                $member->town,
                $member->phone,
                $member->gsm,
                $member->email,
                $booking->getEvent()->getGroupName(),
                $booking->getNumberPeople()
            ];

            if ($filters->event_filter > 0) {
                $bactivities = $booking->getActivities();
                foreach (array_keys($activities) as $aid) {
                    $entry[] = isset($bactivities[$aid]) && $bactivities[$aid]['checked'] ? _T('Yes') : _T('No');
                }
            }

            $entry = array_merge(
                $entry,
                [
                    $booking->getAmount(),
                    $booking->getPaymentMethodName(),
                    $booking->getBankName(),
                    $booking->getCheckNumber()
                ]
            );

            $list[] = $entry;
        }

        //TRANS: this is a filename: all lowercase, no special character, no space.
        $filename = _T('bookingslist', 'events') . '.csv';
        $filepath = CsvOut::DEFAULT_DIRECTORY . $filename;
        $fp = fopen($filepath, 'w');
        if ($fp) {
            $csv->export(
                $list,
                Csv::DEFAULT_SEPARATOR,
                Csv::DEFAULT_QUOTE,
                $labels,
                $fp
            );
            fclose($fp);
            $written[] = [
                'name' => $filename,
                'file' => $filepath
            ];
        }

        return $this->sendResponse(
            request: $request,
            response: $response,
            filepath: $filepath,
            filename: $filename
        );
    }
}
