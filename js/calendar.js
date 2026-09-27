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

  var calendar = new Calendar(calendarEl, {
    plugins: [ themePlugin, interactionPlugin, dayGridPlugin, listPlugin ],
    buttons: options.buttons,
    headerToolbar: {
      left: 'title',
      right: 'dayGridMonth,listDay,listWeek,listMonth prevYear,prev,today,next,nextYear'
    },
    height: 'auto',
    locales: allLocales,
    locale: options.locale,
    weekNumbers: true,
    events: options.dataurl,
    selectable: true,
    eventClick: function(info) {
      var infos = info.event.extendedProps;
      //description is built and escaped server side, other values must be displayed as text
      $modal.find('.header').text(infos.name + ' (' + infos.begin_date_fmt + ' - ' + infos.end_date_fmt + ')');
      $modal.find('.content').html(infos.description);
      //links are given with the event, edit one only when current user can edit it
      $edit
        .attr('href', infos.edit_url || '#')
        .toggleClass('displaynone', !infos.edit_url);
      $booking.attr('href', infos.booking_url);
      $modal.modal('show');
    },
    eventMouseEnter: function(info) {
      $(info.el).popup({
        exclusive: true,
        hoverable: true,
        variation: 'basic',
        html: info.event.extendedProps.description
      }).popup('show');
    }
  });

  calendar.render();
});
