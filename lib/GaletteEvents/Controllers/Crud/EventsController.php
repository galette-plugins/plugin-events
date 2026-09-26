<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\Crud;

use Analog\Analog;
use Galette\Repository\Groups;
use Galette\Controllers\Crud\AbstractPluginController;
use GaletteEvents\Filters\EventsList;
use GaletteEvents\Event;
use GaletteEvents\Repository\Events;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use DI\Attribute\Inject;

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
 */

class EventsController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Events")]
    protected array $module_info;

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
        if (isset($this->session->filter_events)) {
            $filters = $this->session->filter_events;
        } else {
            $filters = new EventsList();
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
            }
        }

        $events = new Events($this->zdb, $this->login, $filters);
        $events_list = $events->getList();

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $this->session->filter_events = $filters;

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('events'),
            [
                'page_title'            => _T("Events management", "events"),
                'require_dialog'        => true,
                'events'                => $events_list,
                'nb_events'             => $events->getCount(),
                'filters'               => $filters
            ]
        );
        return $response;
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

        $events = new Events($this->zdb, $this->login, $filters);

        return $this->withJson($response, $events->getList(false, true));
    }

    /**
     * Filtering
     */
    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        if (isset($this->session->filter_events)) {
            $filters = $this->session->filter_events;
        } else {
            $filters = new EventsList();
        }

        //reintialize filters
        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }
        }

        $this->session->filter_events = $filters;

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
     */
    public function edit(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        if ($this->session->event !== null) {
            $event = $this->session->event;
            $this->session->event = null;
        } else {
            $event = new Event($this->zdb, $this->login);
        }
        $can = $event->canCreate($this->login);

        if ($id !== null && $event->getId() != $id) {
            $event->load($id);
            $can = $event->canEdit($this->login);
        }

        //check if logged-in user can edit event
        if (!$can) {
            return $this->redirectForbidden($response, $event);
        }

        // template variable declaration
        $title = _T("Event", "events");
        if ($event->getId() != '') {
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
                'autocomplete'      => true,
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
        $event = new Event($this->zdb, $this->login);
        $can = $event->canCreate($this->login);
        if (isset($post['id']) && !empty($post['id'])) {
            $event->load((int)$post['id']);
            $can = $event->canEdit($this->login);
        }

        //check if logged-in user can edit event
        if (!$can) {
            return $this->redirectForbidden($response, $event);
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
        } elseif ($valid !== true) {
            $error_detected = array_merge($error_detected, $valid);
        } elseif (isset($post['save'])) {
            $new = $event->getId() === null;
            if ($event->store() === true) {
                if ($new) {
                    $success_detected[] = _T("New event has been successfully added.", "events");
                } else {
                    $success_detected[] = _T("Event has been modified.", "events");
                }
            } else {
                $error_detected[] = _T("An error occurred while storing the event.", "events");
            }
        } else {
            $goto_list = false;
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
            $redirect_url = $this->routeparser->urlFor('events_events');
        } else {
            //store entity in session
            $this->session->event = $event;

            if ($event->getId()) {
                $redirect_url = $this->routeparser->urlFor(
                    'events_event_edit',
                    ['id' => (string)$event->getId()]
                );
            } else {
                $redirect_url = $this->routeparser->urlFor('events_event_add');
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
    }

    /**
     * Redirect when current logged-in user cannot edit an event
     *
     * @param Event $event Event
     */
    private function redirectForbidden(Response $response, Event $event): Response
    {
        Analog::log(
            'Logged in member ' . $this->login->login
            . ' has tried to edit event #' . $event->getId()
            . ' without the right to do so.',
            Analog::WARNING
        );
        return $this->redirectWithErrors(
            response: $response,
            errors: [_T("You do not have permission for requested URL.")],
            redirect_url: $this->routeparser->urlFor('events_events')
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
        $event = new Event($this->zdb, $this->login, (int)$args['id']);
        return sprintf(
            //TRANS: %1$s is the event name
            _T('Remove event \'%1$s\'"', 'events'),
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
        $event = new Event($this->zdb, $this->login, (int)$post['id']);
        return $event->remove();
    }

    // /CRUD - Delete
    // /CRUD
}
