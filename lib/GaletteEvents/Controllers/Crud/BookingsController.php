<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\Crud;

use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Galette\Core\Pagination;
use Galette\Filters\MembersList;
use GaletteEvents\Filters\BookingsList;
use GaletteEvents\Booking;
use GaletteEvents\Event;
use GaletteEvents\NotFoundException;
use GaletteEvents\Repository\Bookings;
use GaletteEvents\Repository\Events;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Bookings controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @extends AbstractController<BookingsList>
 */

class BookingsController extends AbstractController
{
    /**
     * Entity name, for session keys and logs
     */
    protected function getEntityName(): string
    {
        return 'booking';
    }

    /**
     * List name, for filters session key
     */
    protected function getListName(): string
    {
        return 'bookings';
    }

    /**
     * Create empty list filters
     */
    protected function createFilters(): Pagination
    {
        return new BookingsList();
    }

    /**
     * Get the message for a booking that does not exist
     *
     * @param int $id Requested booking identifier
     */
    protected function getNotFoundMessage(int $id): string
    {
        return sprintf(
            //TRANS: %1$s is the booking identifier
            _T('No booking #%1$s.', 'events'),
            $id
        );
    }

    // CRUD - Create

    /**
     * Add page
     *
     * @param int|null $id_adh Booking id
     */
    public function add(Request $request, Response $response, ?int $id_adh = null): Response
    {
        return $this->edit($request, $response, null, 'add', $id_adh);
    }

    /**
     * Add action
     */
    public function doAdd(Request $request, Response $response): Response
    {
        return $this->doEdit($request, $response, null, 'add');
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param string|null     $option One of 'page', 'order' or 'clear_filter'
     * @param string|int|null $value  Value of the option
     * @param string|int      $event  Linked event. May be an event ID, 'all' or 'guess'.
     */
    public function list(
        Request $request,
        Response $response,
        ?string $option = null,
        string|int|null $value = null,
        string|int $event = 'all'
    ): Response {
        $filters = $this->getFilters($option, $value);
        $linked_event = $event == 'guess' ? ($filters->event_filter ?? 'all') : $event;

        $event = null;
        if ($linked_event !== 'all') {
            try {
                $event = new Event($this->zdb, $this->login, $this->history, (int)$linked_event);
            } catch (NotFoundException) {
                //event may have been removed since it has been filtered
                $filters->event_filter = null;
                $this->storeFilters($filters);
                return $this->redirectWithErrors(
                    response: $response,
                    errors: [sprintf(
                        //TRANS: %1$s is the event identifier
                        _T('No event #%1$s.', 'events'),
                        (int)$linked_event
                    )],
                    redirect_url: $this->routeparser->urlFor('events_bookings', ['event' => 'all'])
                );
            }
            $filters->event_filter = (int)$linked_event;
        } else {
            $filters->event_filter = null;
        }

        //Groups
        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getList();

        $bookings = new Bookings($this->zdb, $this->login, $this->history, $this->preferences, $filters);
        $list = $bookings->getList();
        $events = new Events($this->zdb, $this->login, $this->history, $this->preferences);

        return $this->renderList(
            $response,
            'bookings',
            $filters,
            [
                'page_title'        => _T("Bookings management", "events"),
                'bookings'          => $bookings,
                'bookings_list'     => $list,
                'nb_bookings'       => $bookings->getCount(),
                'event'             => $event,
                'eventid'           => $linked_event,
                'events'            => $events->getList(full: true),
                'groups'            => $groups_list
            ]
        );
    }

    /**
     * Filtering; list shows the filtered event
     */
    public function filter(Request $request, Response $response): Response
    {
        $filters = $this->updateFilters($request);
        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor('events_bookings', ['event' => (string)($filters->event_filter ?? 'all')])
            );
    }

    /**
     * Apply posted bookings filters
     *
     * @param BookingsList        $filters Filters
     * @param array<string,mixed> $post    Posted values
     */
    protected function applyPostedFilters(Pagination $filters, array $post): void
    {
        foreach (['paid_filter', 'payment_type_filter', 'event_filter', 'group_filter'] as $name) {
            if (isset($post[$name])) {
                $filters->$name = $post[$name];
            }
        }
    }

    /**
     * Batch actions handler
     */
    public function handleBatch(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        foreach (['mailing', 'csv', 'csvbooking', 'labels'] as $action) {
            if (isset($post[$action]) && !$this->canBatch($action)) {
                Analog::log(
                    'Logged in member ' . $this->login->login
                    . ' has tried to run "' . $action . '" batch action on bookings'
                    . ' without the right to do so.',
                    Analog::WARNING
                );
                return $this->redirectWithErrors(
                    response: $response,
                    errors: [_T("You do not have permission for requested URL.")],
                    redirect_url: $this->routeparser->urlFor('events_bookings', ['event' => 'all'])
                );
            }
        }

        if (isset($post['entries_sel'])) {
            $filters = clone $this->getFilters();

            $filters->selected = $post['entries_sel'];

            //selection is restricted to bookings current logged-in user can list
            $bookings = new Bookings($this->zdb, $this->login, $this->history, $this->preferences, $filters);
            $members = [];
            foreach ($bookings->getList() as $booking) {
                $members[] = $booking->getMemberId();
            }
            if (count($members) === 0) {
                return $this->redirectWithErrors(
                    response: $response,
                    errors: [_T("No booking was selected, please check at least one.", "events")],
                    redirect_url: $this->routeparser->urlFor('events_events')
                );
            }
            $mfilter = new MembersList();
            $mfilter->selected = $members;

            if (isset($post['mailing'])) {
                $this->session->members_sendmail_filter = $mfilter;
                $this->session->redirect_mailing = $this->routeparser->urlFor(
                    'events_bookings',
                    [
                        'event' => $filters->event_filter ?? 'all'
                    ]
                );
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('mailing') . '?mailing_new=true');
            }

            if (isset($post['csv'])) {
                $session_var = 'plugin-events-members';
                $this->session->$session_var = $mfilter;
                return $response
                    ->withStatus(307)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor('csv-memberslist') . '?session_var=' . $session_var
                    );
            }

            if (isset($post['csvbooking'])) {
                $session_var = 'plugin-events-bookings';
                $this->session->$session_var = $filters;
                return $response
                    ->withStatus(307)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor('events_bookings_export') . '?session_var=' . $session_var
                    );
            }

            if (isset($post['labels'])) {
                $session_var = 'plugin-events-labels';
                $this->session->$session_var = $mfilter;
                return $response
                    ->withStatus(307)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor('pdf-members-labels') . '?session_var=' . $session_var
                    );
            }

            $error = _T("No action was matching.", "events");
        } else {
            $error = _T("No booking was selected, please check at least one.", "events");
        }

        return $this->redirectWithErrors(
            response: $response,
            errors: [$error],
            redirect_url: $this->routeparser->urlFor('events_events')
        );
    }

    /**
     * Can current logged-in user run a batch action on bookings
     *
     * Group managers run exports and mailings as core preferences allow them to.
     *
     * @param string $action Batch action
     */
    private function canBatch(string $action): bool
    {
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            return true;
        }

        if ($action === 'mailing') {
            return (bool)$this->preferences->pref_bool_groupsmanagers_mailings;
        }
        return (bool)$this->preferences->pref_bool_groupsmanagers_exports;
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param int|null $id     Model id
     * @param string   $action Action
     * @param int|null $id_adh Member ID (for add)
     */
    public function edit(Request $request, Response $response, ?int $id = null, string $action = 'edit', ?int $id_adh = null): Response
    {
        $get = $request->getQueryParams();
        $route_params = [];

        $booking = new Booking($this->zdb, $this->login, $this->history);

        if ($id !== null) {
            try {
                $booking->load($id);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, $id);
            }
        }

        if ($booking->getId() !== null && !$booking->canEdit($this->login)) {
            return $this->redirectForbidden($response, $booking->getId());
        }

        //values posted before an error, or before the event has been changed
        $values = $this->getPostedValues($booking->getId());
        if ($values !== null) {
            $booking->check($values);
        }

        // template variable declaration
        $title = _T("Booking", "events");
        if ($booking->getId() !== null) {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        //Events
        $events = new Events($this->zdb, $this->login, $this->history, $this->preferences);
        if ($action === 'add') {
            if (isset($get['event'])) {
                $booking->setEvent((int)$get['event']);
            }
            if (
                $id_adh !== null
                && ($this->login->isAdmin() || $this->login->isStaff() || $this->login->isGroupManager())
            ) {
                $booking->setMember($id_adh);
            } elseif (
                !$this->login->isSuperAdmin()
                && !$this->login->isAdmin()
                && !$this->login->isStaff()
                && !$this->login->isGroupManager()
            ) {
                $booking->setMember($this->login->id);
            }
        }

        if (
            $this->login->isAdmin()
            || $this->login->isStaff()
            || $this->login->isGroupManager()
        ) {
            // members
            $m = new Members();
            $members = $m->getDropdownMembers($this->zdb, $this->login);

            $route_params['members'] = [
                'filters'   => $m->getFilters(),
                'count'     => $m->getCount()
            ];

            //check if current attached member is part of the list
            if (
                $booking->getMemberId() > 0
                && !isset($members[$booking->getMemberId()])
            ) {
                $members[$booking->getMemberId()] = Adherent::getSName($this->zdb, $booking->getMemberId(), true);
            }

            if (count($members)) {
                $route_params['members']['list'] = $members;
            }
        } else {
            $booking->setMember($this->login->id);
        }

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('booking'),
            array_merge(
                $route_params,
                [
                    'page_title'        => $title,
                    'booking'           => $booking,
                    'events'            => $events->getList(true),
                    'require_dialog'    => true,
                    'require_calendar'  => true,
                    // pseudo random int
                    'time'              => time()
                ]
            )
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param null|int $id     Model id for edit
     * @param string   $action Either add or edit
     */
    public function doEdit(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        $post = $request->getParsedBody();
        $booking = new Booking($this->zdb, $this->login, $this->history);
        if (!empty($post['id'])) {
            try {
                $booking->load((int)$post['id']);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, (int)$post['id']);
            }
        }

        if ($booking->getId() !== null && !$booking->canEdit($this->login)) {
            return $this->redirectForbidden($response, $booking->getId());
        }

        if (isset($post['cancel'])) {
            $redirect_url = $this->routeparser->urlFor(
                'events_bookings',
                ['event' => 'guess']
            );
            return $response
                ->withStatus(301)
                ->withHeader('Location', $redirect_url);
        }

        $success_detected = [];
        $warning_detected = [];
        $error_detected = [];
        $goto_list = true;

        // Validation
        if (!$booking->check($post)) {
            $error_detected = array_merge($error_detected, $booking->getErrors());
        }

        if (count($error_detected) == 0 && isset($post['save'])) {
            $this->storeEntity(
                $booking,
                _T("New booking has been successfully added.", "events"),
                _T("Booking has been modified.", "events"),
                _T("An error occurred while storing the booking.", "events"),
                $success_detected,
                $error_detected
            );
        }

        if (!isset($post['save'])) {
            $error_detected = [];
            $goto_list = false;
            $warning_detected[] = _T('Do not forget to store the booking', 'events');
        }

        if (count($error_detected) == 0 && $goto_list) {
            $redirect_url = $this->routeparser->urlFor(
                'events_bookings',
                ['event' => (string)$booking->getEventId()]
            );
        } else {
            $this->keepPostedValues($booking->getId(), $post);
            $redirect_url = $booking->getId() !== null
                ? $this->routeparser->urlFor('events_booking_edit', ['id' => (string)$booking->getId(), 'action' => 'edit'])
                : $this->routeparser->urlFor('events_booking_add', ['action' => 'add']);
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $success_detected,
            warnings: $warning_detected,
            errors: $error_detected
        );
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('events_bookings', ['event' => 'all'] + $args);
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'events_do_remove_booking',
            $args
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        try {
            $booking = new Booking($this->zdb, $this->login, $this->history, (int)$args['id']);
        } catch (NotFoundException) {
            return $this->getNotFoundMessage((int)$args['id']);
        }
        $member = $booking->getMember();
        $event = $booking->getEvent();
        return sprintf(
            //TRANS: %1$s is the member name, %2$s the event name.
            _T('Remove booking for %1$s on %2$s', 'events'),
            $member->sname,
            $event->getName()
        );
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        $booking = new Booking($this->zdb, $this->login, $this->history, (int)$post['id']);
        $booking->remove();
        return true;
    }

    // /CRUD - Delete
    // /CRUD
}
