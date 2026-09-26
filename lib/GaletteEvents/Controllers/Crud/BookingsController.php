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
use Galette\Controllers\Crud\AbstractPluginController;
use Galette\Filters\MembersList;
use GaletteEvents\Filters\BookingsList;
use GaletteEvents\Booking;
use GaletteEvents\Event;
use GaletteEvents\Repository\Bookings;
use GaletteEvents\Repository\Events;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use DI\Attribute\Inject;

/**
 * Bookings controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class BookingsController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Events")]
    protected array $module_info;

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
     * @param string|null     $option One of 'page' or 'order'
     * @param string|int|null $value  Value of the option
     */
    public function list(Request $request, Response $response, ?string $option = null, string|int|null $value = null): Response
    {
        //just for inheritance. see listBookings which signature changes.
        return $response;
    }

    /**
     * List page
     *
     * @param string|int      $event  Linked event. May be an event ID, 'all' or 'guess'.
     * @param string|null     $option One of 'page' or 'order'
     * @param string|int|null $value  Value of the option
     */
    public function listBookings(Response $response, string|int $event, ?string $option = null, string|int|null $value = null): Response
    {
        $filters = $this->session->filter_bookings ?? new BookingsList();

        if ($event == 'guess') {
            $linked_event = $filters->event_filter;
        } else {
            $linked_event = $event;
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
                case 'clear_filter':
                    $filters->reinit();
                    break;
            }
        }

        $event = null;
        if ($linked_event !== 'all') {
            $filters->event_filter = (int)$linked_event;
            $event = new Event($this->zdb, $this->login, (int)$linked_event);
        }

        //Groups
        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getList();

        $bookings = new Bookings($this->zdb, $this->login, $filters);

        $events = new Events($this->zdb, $this->login);
        $list = $bookings->getList();
        $count = $bookings->getCount();

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $this->session->filter_bookings = $filters;

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('bookings'),
            [
                'page_title'        => _T("Bookings management", "events"),
                'bookings'          => $bookings,
                'bookings_list'     => $list,
                'nb_bookings'       => $count,
                'event'             => $event,
                'eventid'           => $linked_event,
                'require_dialog'    => true,
                'filters'           => $filters,
                'events'            => $events->getList(),
                'groups'            => $groups_list
            ]
        );
        return $response;
    }

    /**
     * Filtering
     */
    public function filter(Request $request, Response $response): Response
    {
        //just for inheritance. see filterBookings which signature changes.
        return $response;
    }

    /**
     * Filtering
     *
     * @param string|int $event Linked event. May be an event ID, 'all' or 'guess'.
     */
    public function filterBookings(Request $request, Response $response, string|int $event): Response
    {
        $post = $request->getParsedBody();
        if (isset($this->session->filter_bookings)) {
            $filters = $this->session->filter_bookings;
        } else {
            $filters = new BookingsList();
        }

        //reintialize filters
        if (isset($post['clear_filter'])) {
            $filters->reinit();
            $event = 'all';
        } else {
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }

            if (isset($post['paid_filter'])) {
                if (is_numeric($post['paid_filter'])) {
                    $filters->paid_filter = $post['paid_filter'];
                }
            }

            if (isset($post['payment_type_filter'])) {
                if (is_numeric($post['payment_type_filter'])) {
                    $filters->payment_type_filter = $post['payment_type_filter'];
                }
            }

            if (isset($post['event_filter'])) {
                if (is_numeric($post['event_filter'])) {
                    $filters->event_filter = $post['event_filter'];
                }
            }

            if (isset($post['group_filter'])) {
                if (is_numeric($post['group_filter'])) {
                    $filters->group_filter = $post['group_filter'];
                }
            }
        }

        $this->session->filter_bookings = $filters;

        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor('events_bookings', ['event' => $event])
            );
    }

    /**
     * Batch actions handler
     */
    public function handleBatch(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        if (isset($post['entries_sel'])) {
            if (isset($this->session->filter_bookings)) {
                $filters = clone $this->session->filter_bookings;
            } else {
                $filters = new BookingsList();
            }

            //$this->session->filter_bookings = $filters;
            $filters->selected = $post['entries_sel'];

            $bookings = new Bookings($this->zdb, $this->login, $filters);
            $members = [];
            foreach ($bookings->getList() as $booking) {
                $members[] = $booking->getMemberId();
            }
            $mfilter = new MembersList();
            $mfilter->selected = $members;

            if (isset($post['mailing'])) {
                $this->session->members_sendmail_filter = $mfilter;
                $this->session->redirect_mailing = $this->routeparser->urlFor(
                    'events_bookings',
                    [
                        'event' => $filters->event_filter == null
                            ? 'all'
                            : $filters->event_filter
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

            $this->flash->addMessage(
                'error_detected',
                _T("No action was matching.", "events")
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("No booking was selected, please check at least one.", "events")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('events_events'));
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

        if ($this->session->booking !== null) {
            $booking = $this->session->booking;
            $this->session->booking = null;
        } else {
            $booking = new Booking($this->zdb, $this->login);
        }

        if ($id !== null && $booking->getId() != $id) {
            $booking->load($id);
        }

        if ($booking->getId() !== null && !$booking->canEdit($this->login)) {
            return $this->redirectForbidden($response, $booking);
        }

        // template variable declaration
        $title = _T("Booking", "events");
        if ($booking->getId() != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        //Events
        $events = new Events($this->zdb, $this->login);
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
            $route_params['autocomplete'] = true;

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
                    'autocomplete'      => true,
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
        $booking = new Booking($this->zdb, $this->login);
        if (isset($post['id']) && !empty($post['id'])) {
            $booking->load((int)$post['id']);
        }

        if ($booking->getId() !== null && !$booking->canEdit($this->login)) {
            return $this->redirectForbidden($response, $booking);
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
        $valid = $booking->check($post);
        if ($valid !== true) {
            $error_detected = array_merge($error_detected, $valid);
        }

        if (count($error_detected) == 0 && isset($post['save'])) {
            //all goes well, we can proceed

            $new = false;
            if ($booking->getId() == '') {
                $new = true;
            }
            $store = $booking->store();
            if ($store === true) {
                //member has been stored :)
                if ($new) {
                    $success_detected[] = _T("New booking has been successfully added.", "events");
                } else {
                    $success_detected[] = _T("Booking has been modified.", "events");
                }
            } else {
                //something went wrong :'(
                $error_detected[] = _T("An error occurred while storing the booking.", "events");
            }
        }

        if (!isset($post['save'])) {
            $this->session->booking = $booking;
            $error_detected = [];
            $goto_list = false;
            $warning_detected[] = _T('Do not forget to store the booking', 'events');
        }

        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        if (count($warning_detected) > 0) {
            foreach ($warning_detected as $warning) {
                $this->flash->addMessage(
                    'warning_detected',
                    $warning
                );
            }
        }
        if (count($success_detected) > 0) {
            foreach ($success_detected as $success) {
                $this->flash->addMessage(
                    'success_detected',
                    $success
                );
            }
        }

        if (count($error_detected) == 0 && $goto_list) {
            $redirect_url = $this->routeparser->urlFor(
                'events_bookings',
                ['event' => (string)$booking->getEventId()]
            );
        } else {
            //store entity in session
            $this->session->booking = $booking;

            if ($booking->getId()) {
                $route = 'events_booking_edit';
                $rparams = [
                    'id'        => $booking->getId(),
                    'action'    => 'edit'
                ];
            } else {
                $route = 'events_booking_add';
                $rparams = ['action' => 'add'];
            }
            $redirect_url = $this->routeparser->urlFor(
                $route,
                $rparams
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
    }

    /**
     * Redirect when current logged-in user cannot edit a booking
     *
     * @param Booking $booking Booking
     */
    private function redirectForbidden(Response $response, Booking $booking): Response
    {
        Analog::log(
            'Logged in member ' . $this->login->login
            . ' has tried to edit booking #' . $booking->getId()
            . ' without the right to do so.',
            Analog::WARNING
        );
        return $this->redirectWithErrors(
            response: $response,
            errors: [_T("You do not have permission for requested URL.")],
            redirect_url: $this->routeparser->urlFor('events_bookings', ['event' => 'all'])
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
        $booking = new Booking($this->zdb, $this->login, (int)$args['id']);
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
        $booking = new Booking($this->zdb, $this->login, (int)$post['id']);
        return $booking->remove();
    }

    // /CRUD - Delete
    // /CRUD
}
