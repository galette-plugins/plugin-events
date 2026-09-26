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

  var calendar = new Calendar(calendarEl, {
    plugins: [ themePlugin, interactionPlugin, dayGridPlugin, listPlugin ],
    views: _fullcalendar_views,
    headerToolbar: {
      left: 'title',
      right: 'dayGridMonth,listDay,listWeek,listMonth prevYear,prev,today,next,nextYear'
    },
    height: 'auto',
    locales: allLocales,
    locale: _fullcalendar_locale,
    weekNumbers: true,
    events: _calendar_dataurl,
    selectable: true,
    eventClick: function(info) {
      var _infos = JSON.parse(JSON.stringify(info.event.extendedProps));
      _infos.url = _calendar_event_url.replace('PLACEBO', _infos.id_event);
      _infos.booking = _calendar_booking_url.replace('PLACEBO', _infos.id_event);
      _booking_action = function() {
          window.location.href = _infos.booking;
      };
      if (_modal_actions.length == 2) {
        _modal_actions[0].click = _booking_action;
      } else {
        _modal_actions[1].click = _booking_action;
      }
      //description is built and escaped server side, other values must be displayed as text
      var _elt = $('<div class="ui tiny modal"><div class="header"></div><div class="content"></div></div>');
      _elt.find('.header').text(_infos.name + ' (' + _infos.begin_date_fmt + ' - ' + _infos.end_date_fmt + ')');
      _elt.find('.content').html(_infos.description);
      _elt.appendTo('body');
      _elt.modal({
        onApprove: function() {
          window.location.href = _infos.url;
        },
        actions: _modal_actions
      }).modal('show');
    },
    eventMouseEnter: function(info) {
      var _el = $(info.el);
      _el.popup({
        exclusive: true,
        hoverable: true,
        variation: 'basic',
        html: info.event.extendedProps.description
      }).popup('show');
    }
  });

  calendar.render();
});

