<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\Crud;

use Analog\Analog;
use DI\Attribute\Inject;
use Galette\Controllers\Crud\AbstractPluginController;
use Galette\Core\Pagination;
use GaletteEvents\Activity;
use GaletteEvents\Booking;
use GaletteEvents\Event;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Common code for events, bookings and activities: lists, filters, forms and storage
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @template TFilters of Pagination
 */
abstract class AbstractController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Events")]
    protected array $module_info;

    /**
     * Entity name, for session keys and logs: event, booking or activity
     */
    abstract protected function getEntityName(): string;

    /**
     * List name, for filters session key: events, bookings or activities
     */
    abstract protected function getListName(): string;

    /**
     * Create empty list filters
     *
     * @return TFilters
     */
    abstract protected function createFilters(): Pagination;

    /**
     * Get the message for an entity that does not exist
     *
     * @param int $id Requested identifier
     */
    abstract protected function getNotFoundMessage(int $id): string;

    /**
     * Apply posted filters specific to the list
     *
     * @param TFilters            $filters Filters
     * @param array<string,mixed> $post    Posted values
     */
    protected function applyPostedFilters(Pagination $filters, array $post): void
    {
    }

    /**
     * Get list filters from session, with page or order of the list route
     *
     * @param string|null     $option One of 'page', 'order' or 'clear_filter'
     * @param string|int|null $value  Value of the option
     *
     * @return TFilters
     */
    protected function getFilters(?string $option = null, string|int|null $value = null): Pagination
    {
        $filters = $this->session->{$this->getFilterName($this->getListName())} ?? $this->createFilters();

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

        return $filters;
    }

    /**
     * Store list filters in session
     *
     * @param Pagination $filters Filters
     */
    protected function storeFilters(Pagination $filters): void
    {
        $this->session->{$this->getFilterName($this->getListName())} = $filters;
    }

    /**
     * Render a list page, with its pagination
     *
     * @param string              $template Template name
     * @param Pagination          $filters  Filters, once the list has been counted
     * @param array<string,mixed> $params   Template parameters
     */
    protected function renderList(Response $response, string $template, Pagination $filters, array $params): Response
    {
        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);
        $this->storeFilters($filters);

        $this->view->render(
            $response,
            $this->getTemplate($template),
            $params + [
                'require_dialog'    => true,
                'filters'           => $filters
            ]
        );
        return $response;
    }

    /**
     * Filtering
     */
    public function filter(Request $request, Response $response): Response
    {
        $this->updateFilters($request);
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->redirectUri([]));
    }

    /**
     * Update list filters from posted values
     *
     * @return TFilters
     */
    protected function updateFilters(Request $request): Pagination
    {
        $post = $request->getParsedBody();
        $filters = $this->getFilters();

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }
            $this->applyPostedFilters($filters, $post);
        }

        $this->storeFilters($filters);
        return $filters;
    }

    /**
     * Keep posted values, to fill the form again after a redirection
     *
     * @param ?int                $id   Entity identifier, null for a new one
     * @param array<string,mixed> $post Posted values
     */
    protected function keepPostedValues(?int $id, array $post): void
    {
        $this->session->{$this->getPostedValuesKey()} = [
            'id'        => $id,
            'values'    => $post
        ];
    }

    /**
     * Get values posted on the form of an entity, once
     *
     * @param ?int $id Entity identifier, null for a new one
     *
     * @return ?array<string,mixed>
     */
    protected function getPostedValues(?int $id): ?array
    {
        $key = $this->getPostedValuesKey();
        $data = $this->session->$key ?? null;
        unset($this->session->$key);
        return is_array($data) && $data['id'] === $id ? $data['values'] : null;
    }

    /**
     * Session key of posted values
     */
    private function getPostedValuesKey(): string
    {
        return 'plugin_events_' . $this->getEntityName() . '_data';
    }

    /**
     * Store an entity, and report how it went
     *
     * @param Event|Booking|Activity $entity    Entity
     * @param string                 $added     Message for a new entity
     * @param string                 $modified  Message for an existing entity
     * @param string                 $failed    Message when storage failed
     * @param array<string>          $successes Success messages
     * @param array<string>          $errors    Error messages
     */
    protected function storeEntity(
        Event|Booking|Activity $entity,
        string $added,
        string $modified,
        string $failed,
        array &$successes,
        array &$errors
    ): void {
        $new = $entity->getId() === null;
        try {
            $entity->store();
            $successes[] = $new ? $added : $modified;
        } catch (\Throwable $e) {
            Analog::log(
                sprintf(
                    'Unable to store %1$s #%2$s | %3$s',
                    $this->getEntityName(),
                    $entity->getId() ?? 'new',
                    $e->getMessage()
                ),
                Analog::ERROR
            );
            $errors[] = $failed;
        }
    }

    /**
     * Redirect when requested entity does not exist
     *
     * @param int $id Requested identifier
     */
    protected function redirectNotFound(Response $response, int $id): Response
    {
        return $this->redirectWithErrors(
            response: $response,
            errors: [$this->getNotFoundMessage($id)],
            redirect_url: $this->redirectUri([])
        );
    }

    /**
     * Redirect when current logged-in user cannot edit an entity
     *
     * @param ?int $id Entity identifier
     */
    protected function redirectForbidden(Response $response, ?int $id): Response
    {
        Analog::log(
            'Logged in member ' . $this->login->login
            . ' has tried to edit ' . $this->getEntityName() . ' #' . $id
            . ' without the right to do so.',
            Analog::WARNING
        );
        return $this->redirectWithErrors(
            response: $response,
            errors: [_T("You do not have permission for requested URL.")],
            redirect_url: $this->redirectUri([])
        );
    }
}
