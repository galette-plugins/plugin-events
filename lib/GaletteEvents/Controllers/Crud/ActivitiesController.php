<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\Crud;

use Galette\Core\Pagination;
use GaletteEvents\Filters\ActivitiesList;
use GaletteEvents\Activity;
use GaletteEvents\NotFoundException;
use GaletteEvents\Repository\Activities;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Activities controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @extends AbstractController<ActivitiesList>
 */

class ActivitiesController extends AbstractController
{
    /**
     * Entity name, for session keys and logs
     */
    protected function getEntityName(): string
    {
        return 'activity';
    }

    /**
     * List name, for filters session key
     */
    protected function getListName(): string
    {
        return 'activities';
    }

    /**
     * Create empty list filters
     */
    protected function createFilters(): Pagination
    {
        return new ActivitiesList();
    }

    /**
     * Get the message for an activity that does not exist
     *
     * @param int $id Requested activity identifier
     */
    protected function getNotFoundMessage(int $id): string
    {
        return sprintf(
            //TRANS: %1$s is the activity identifier
            _T('No activity #%1$s.', 'events'),
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
        $activities = new Activities($this->zdb, $this->login, $this->history, $this->preferences, $filters);

        return $this->renderList(
            $response,
            'activities',
            $filters,
            [
                'page_title'            => _T("Activities management", "events"),
                'activities'            => $activities->getList(),
                'nb_activities'         => $activities->getCount(),
            ]
        );
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
        $activity = new Activity($this->zdb, $this->history);

        if ($id !== null) {
            try {
                $activity->load($id);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, $id);
            }
        }

        //values posted before an error
        $values = $this->getPostedValues($activity->getId());
        if ($values !== null) {
            $activity->check($values);
        }

        // template variable declaration
        $title = _T("Activity", "events");
        if ($activity->getId() !== null) {
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
        $activity = new Activity($this->zdb, $this->history);
        if (!empty($post['id'])) {
            try {
                $activity->load((int)$post['id']);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, (int)$post['id']);
            }
        }

        $successes = [];
        $errors = [];
        if ($activity->check($post)) {
            $this->storeEntity(
                $activity,
                _T("New activity has been successfully added.", "events"),
                _T("Activity has been modified.", "events"),
                _T("An error occurred while storing the activity.", "events"),
                $successes,
                $errors
            );
        } else {
            $errors = $activity->getErrors();
        }

        if (count($errors) === 0) {
            $redirect_url = $this->routeparser->urlFor('events_activities');
        } else {
            $this->keepPostedValues($activity->getId(), $post);
            $redirect_url = $activity->getId() !== null
                ? $this->routeparser->urlFor('events_activity_edit', ['id' => (string)$activity->getId()])
                : $this->routeparser->urlFor('events_activity_add');
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $successes,
            errors: $errors
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
        try {
            $activity = new Activity($this->zdb, $this->history, (int)$args['id']);
        } catch (NotFoundException) {
            return $this->getNotFoundMessage((int)$args['id']);
        }
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
        $activity = new Activity($this->zdb, $this->history, (int)$args['id']);
        $activity->remove();
        return true;
    }

    // /CRUD - Delete
    // /CRUD
}
