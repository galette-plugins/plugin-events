<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\Crud;

use ArrayObject;
use Galette\Repository\Groups;
use Galette\Core\Pagination;
use GaletteEvents\Filters\EventsList;
use GaletteEvents\Event;
use GaletteEvents\NotFoundException;
use GaletteEvents\Repository\Events;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Events controller
 *
 * @category  Controllers
 * @name      EventsController
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2025 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2021-05-09
 *
 * @extends AbstractController<EventsList>
 */

class EventsController extends AbstractController
{
    /**
     * Entity name, for session keys and logs
     */
    protected function getEntityName(): string
    {
        return 'event';
    }

    /**
     * List name, for filters session key
     */
    protected function getListName(): string
    {
        return 'events';
    }

    /**
     * Create empty list filters
     */
    protected function createFilters(): Pagination
    {
        return new EventsList();
    }

    /**
     * Get the message for an event that does not exist
     *
     * @param int $id Requested event identifier
     */
    protected function getNotFoundMessage(int $id): string
    {
        return sprintf(
            //TRANS: %1$s is the event identifier
            _T('No event #%1$s.', 'events'),
            $id
        );
    }

    // CRUD - Create

    /**
     * Add page
     */
    public function add(Request $request, Response $response): Response
    {
        return $this->edit($request, $response, null, 'add');
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
        $filters = $this->getFilters($option, $value);
        $events = new Events($this->zdb, $this->login, $this->history, $this->preferences, $filters);

        return $this->renderList(
            $response,
            'events',
            $filters,
            [
                'page_title'            => _T("Events management", "events"),
                'events'                => $events->getList(),
                'nb_events'             => $events->getCount(),
            ]
        );
    }

    /**
     * Calendar view
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param string|int|null $value  Value of the option
     */
    public function calendar(
        Request $request,
        Response $response,
        ?string $option = null,
        string|int|null $value = null
    ): Response {
        //check if JS has been generated
        if (!file_exists(__DIR__ . '/../../../../webroot/js/calendar.bundle.js')) {
            $this->flash->addMessageNow(
                'error_detected',
                _T('Javascript libraries has not been built!', 'events')
            );
        }

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('calendar'),
            [
                'page_title'            => _T("Events calendar", "events"),
                'require_dialog'        => true,
                'module_id'             => $this->getModuleId()
            ]
        );
        return $response;
    }

    /**
     * Calendar view
     */
    public function ajaxCalendar(Request $request, Response $response): Response
    {
        $get = $request->getQueryParams();
        $start = strtotime((string)($get['start'] ?? ''));
        $end = strtotime((string)($get['end'] ?? ''));
        if ($start === false || $end === false) {
            return $this->withJson($response, [], 400);
        }

        $filters = new EventsList();
        $filters->calendar_filter = true;
        $filters->start_date_filter = date(__("Y-m-d"), $start);
        $filters->end_date_filter = date(__("Y-m-d"), $end);

        $events = new Events($this->zdb, $this->login, $this->history, $this->preferences, $filters);
        $list = $events->getList(false, true);

        //links of the event modal
        foreach ($list as $row) {
            if (!$row instanceof ArrayObject) {
                continue;
            }
            $id = (string)$row[Event::PK];
            if ($row['can_edit']) {
                $row['edit_url'] = $this->routeparser->urlFor('events_event_edit', ['id' => $id]);
            }
            $row['booking_url'] = $this->routeparser->urlFor('events_booking_add') . '?event=' . $id;
        }

        return $this->withJson($response, $list);
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param int|null $id     Model id
     * @param string   $action Action
     */
    public function edit(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        $event = new Event($this->zdb, $this->login, $this->history);
        $can = $event->canCreate($this->login);

        if ($id !== null) {
            try {
                $event->load($id);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, $id);
            }
            $can = $event->canEdit($this->login);
        }

        //check if logged-in user can edit event
        if (!$can) {
            return $this->redirectForbidden($response, $event->getId());
        }

        //values posted before an error, or before activities have been changed
        $values = $this->getPostedValues($event->getId());
        if ($values !== null) {
            $event->check($values);
        }

        // template variable declaration
        $title = _T("Event", "events");
        if ($event->getId() !== null) {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        //Groups
        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getList();

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('event'),
            [
                'page_title'        => $title,
                'event'             => $event,
                'require_calendar'  => true,
                // pseudo random int
                'time'              => time(),
                'groups'            => $groups_list,
            ]
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
        $event = new Event($this->zdb, $this->login, $this->history);
        $can = $event->canCreate($this->login);
        if (!empty($post['id'])) {
            try {
                $event->load((int)$post['id']);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, (int)$post['id']);
            }
            $can = $event->canEdit($this->login);
        }

        //check if logged-in user can edit event
        if (!$can) {
            return $this->redirectForbidden($response, $event->getId());
        }

        $success_detected = [];
        $warning_detected = [];
        $error_detected = [];
        $goto_list = true;

        // Validation
        $valid = $event->check($post);

        if (isset($post['add_activity']) || isset($post['remove_activity'])) {
            //activities are changed on a form that may not be complete yet, event is stored later
            $goto_list = false;
            if (isset($post['add_activity'])) {
                if (isset($event->getActivities()[(int)($post['attach_activity'] ?? 0)])) {
                    $success_detected[] = _T("Activity has been attached to event.", "events");
                    $warning_detected[] = _T('Do not forget to store the event', 'events');
                } else {
                    $error_detected[] = _T("Please choose an activity to add", "events");
                }
            } else {
                $success_detected[] = _T("Activity has been detached from event.", "events");
                $warning_detected[] = _T('Do not forget to store the event', 'events');
            }
        } elseif (!$valid) {
            $error_detected = array_merge($error_detected, $event->getErrors());
        } elseif (isset($post['save'])) {
            $this->storeEntity(
                $event,
                _T("New event has been successfully added.", "events"),
                _T("Event has been modified.", "events"),
                _T("An error occurred while storing the event.", "events"),
                $success_detected,
                $error_detected
            );
        } else {
            $goto_list = false;
        }

        if (count($error_detected) == 0 && $goto_list) {
            $redirect_url = $this->routeparser->urlFor('events_events');
        } else {
            $this->keepPostedValues($event->getId(), $post);
            $redirect_url = $event->getId() !== null
                ? $this->routeparser->urlFor('events_event_edit', ['id' => (string)$event->getId()])
                : $this->routeparser->urlFor('events_event_add');
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
        return $this->routeparser->urlFor('events_events');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'events_do_remove_event',
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
            $event = new Event($this->zdb, $this->login, $this->history, (int)$args['id']);
        } catch (NotFoundException) {
            return $this->getNotFoundMessage((int)$args['id']);
        }
        return sprintf(
            //TRANS: %1$s is the event name
            _T('Remove event \'%1$s\'', 'events'),
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
        $event = new Event($this->zdb, $this->login, $this->history, (int)$post['id']);
        $event->remove();
        return true;
    }

    // /CRUD - Delete
    // /CRUD
}
