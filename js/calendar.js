/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import $ from 'jquery';
import { Calendar } from 'fullcalendar';
import dayGridPlugin from 'fullcalendar/daygrid';
import interactionPlugin from 'fullcalendar/interaction';
import listPlugin from 'fullcalendar/list';
import themePlugin from 'fullcalendar/themes/classic';
import allLocales from 'fullcalendar/locales-all';
import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/classic/theme.css';
import 'fullcalendar/themes/classic/palette.css';

$(function() {
  var calendarEl = document.getElementById('calendar');
  var options = JSON.parse(document.getElementById('calendar_options').textContent);
  //modal is written in the page, only its content changes
  var $modal = $('#calendar_event');
  var $edit = $modal.find('[data-action="edit"]');
  var $booking = $modal.find('[data-action="booking"]');
  var $ics = $modal.find('[data-action="ics"]');

  var calendar = new Calendar(calendarEl, {
    plugins: [ themePlugin, interactionPlugin, dayGridPlugin, listPlugin ],
    buttons: options.buttons,
    headerToolbar: {
      left: 'title',
      right: options.public
        ? 'dayGridMonth,listMonth prev,today,next'
        : 'dayGridMonth,listDay,listWeek,listMonth prevYear,prev,today,next,nextYear'
    },
    height: 'auto',
    locales: allLocales,
    locale: options.locale,
    weekNumbers: !options.public,
    //public events are given with the page, member ones are fetched
    events: options.public ? options.events : options.dataurl,
    selectable: !options.public,
    eventClick: function(info) {
      if (options.public) {
        //public events window is the one of their list
        $(document).trigger('galette-events:show', [info.event.id]);
        return;
      }
      var infos = info.event.extendedProps;
      //description is built and escaped server side, other values must be displayed as text
      $modal.find('.header').text(infos.name + ' (' + infos.period + ')');
      $modal.find('.content').html(infos.description);
      //links are given with the event, edit one only when current user can edit it
      $edit
        .attr('href', infos.edit_url || '#')
        .toggleClass('displaynone', !infos.edit_url);
      $booking.attr('href', infos.booking_url);
      $ics.attr('href', infos.ics_url);
      $modal.modal('show');
    },
    eventMouseEnter: function(info) {
      if (!info.event.extendedProps.description) {
        return;
      }
      $(info.el).popup({
        exclusive: true,
        hoverable: true,
        variation: 'basic',
        html: info.event.extendedProps.description
      }).popup('show');
    }
  });

  var $views = $('#events_views');
  if (!$views.length) {
    calendar.render();
    return;
  }

  //public page: list or calendar, as last chosen
  var rendered = false;
  var showView = function(view) {
    $views.find('[data-view]').each(function() {
      var active = $(this).data('view') === view;
      $(this).toggleClass('active', active).attr('aria-pressed', active ? 'true' : 'false');
    });
    $('#events_list').toggleClass('displaynone', view !== 'list');
    $('#events_calendar').toggleClass('displaynone', view !== 'calendar');
    //calendar cannot be sized while hidden, it follows its container size once rendered
    if (view === 'calendar' && !rendered) {
      calendar.render();
      rendered = true;
    }
    try {
      window.localStorage.setItem('galette-events-view', view);
    } catch (e) {
      //view is just not remembered
    }
  };
  $views.on('click', '[data-view]', function() {
    showView($(this).data('view'));
  });
  var saved = null;
  try {
    saved = window.localStorage.getItem('galette-events-view');
  } catch (e) {
    //no remembered view
  }
  showView(saved === 'calendar' ? 'calendar' : 'list');
});
