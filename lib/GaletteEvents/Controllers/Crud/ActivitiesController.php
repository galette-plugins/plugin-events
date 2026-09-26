<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\Crud;

use Galette\Controllers\Crud\AbstractPluginController;
use GaletteEvents\Filters\ActivitiesList;
use GaletteEvents\Activity;
use GaletteEvents\Repository\Activities;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use DI\Attribute\Inject;

/**
 * Activities controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ActivitiesController extends AbstractPluginController
{
    /**
     * @var array<string,mixed>
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
        if (isset($this->session->{$this->getFilterName('activities')})) {
            $filters = $this->session->{$this->getFilterName('activities')};
        } else {
            $filters = new ActivitiesList();
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

        $activities = new Activities($this->zdb, $this->login, $this->preferences, $filters);
        $list = $activities->getList();

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $this->session->{$this->getFilterName('activities')} = $filters;

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('activities'),
            [
                'page_title'            => _T("Activities management", "events"),
                'require_dialog'        => true,
                'activities'            => $list,
                'nb_activities'         => $activities->getCount(),
                'filters'               => $filters
            ]
        );
        return $response;
    }

    /**
     * Filtering
     */
    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        if (isset($this->session->{$this->getFilterName('activities')})) {
            $filters = $this->session->{$this->getFilterName('activities')};
        } else {
            $filters = new ActivitiesList();
        }

        //reinitialize filters
        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }
        }

        $this->session->{$this->getFilterName('activities')} = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('events_activities'));
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
        if ($this->session->plugin_events_activity !== null) {
            $activity = $this->session->plugin_events_activity;
            $this->session->plugin_events_activity = null;
        } else {
            $activity = new Activity($this->zdb, $this->login);
        }

        if ($id !== null && $activity->getId() != $id) {
            $activity->load($id);
        }

        // template variable declaration
        $title = _T("Activity", "events");
        if ($activity->getId() != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('activity'),
            [
                'autocomplete'  => true,
                'page_title'    => $title,
                'activity'      => $activity,
                // pseudo random int
                'time'          => time()
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
        $activity = new Activity($this->zdb, $this->login);
        if (isset($post['id']) && !empty($post['id'])) {
            $activity->load((int)$post['id']);
        }

        $success_detected = [];
        $error_detected = [];

        // Validation
        $valid = $activity->check($post);
        if ($valid !== true) {
            $error_detected = array_merge($error_detected, $activity->getErrors());
        }

        if (count($error_detected) == 0) {
            //all goes well, we can proceed

            $new = false;
            if ($activity->getId() == '') {
                $new = true;
            }
            $store = $activity->store();
            if ($store === true) {
                //member has been stored :)
                if ($new) {
                    $success_detected[] = _T("New activity has been successfully added.", "events");
                } else {
                    $success_detected[] = _T("Activity has been modified.", "events");
                }
            } else {
                //something went wrong :'(
                $error_detected[] = _T("An error occurred while storing the activity.", "events");
            }
        }

        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
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

        if (count($error_detected) == 0) {
            $redirect_url = $this->routeparser->urlFor('events_activities');
        } else {
            //store entity in session
            $this->session->plugin_events_activity = $activity;

            if ($activity->getId()) {
                $redirect_url = $this->routeparser->urlFor(
                    'events_activity_edit',
                    ['id' => (string)$activity->getId()]
                );
            } else {
                $redirect_url = $this->routeparser->urlFor('events_activity_add');
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
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
        return $this->routeparser->urlFor('events_activities');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'events_do_remove_activity',
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
        $activity = new Activity($this->zdb, $this->login, (int)$args['id']);
        return sprintf(
            //TRANS %1$s is activity name
            _T('Remove activity %1$s', 'events'),
            $activity->getName()
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
        $activity = new Activity($this->zdb, $this->login, (int)$args['id']);
        return $activity->remove();
    }

    // /CRUD - Delete
    // /CRUD
}
