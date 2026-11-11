/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/* Event form: activities are attached or detached once one has been chosen */
var _eventsActivities = function() {
    $('#add_activity, #remove_activity').on('click', function(e) {
        var $button = $(this);
        if ($('#' + $button.attr('aria-controls')).val() !== '') {
            return;
        }
        e.preventDefault();
        $.modal({
            content: $button.data('message'),
            class: 'tiny',
            actions: [{
                text: $button.data('close'),
                icon: 'times',
                class: 'icon labeled cancel'
            }]
        }).modal('show');
    });
};

/* Event form: times are hidden for all day events, period is shown as it will be displayed */
var _eventsWhen = function() {
    var $when = $('#event_when');
    if (!$when.length) {
        return;
    }
    var $allDay = $('#all_day');
    var value = function(id) {
        return $.trim($('#' + id).val());
    };
    //same rules as Event::getPeriod(); dates are already in the displayed format
    var update = function() {
        var allDay = $allDay.is(':checked');
        $when.find('.event-time').toggleClass('displaynone', allDay);
        var beginDate = value('begin_date');
        var endDate = value('end_date') || beginDate;
        var beginTime = allDay ? '' : value('begin_time');
        var endTime = allDay ? '' : value('end_time');
        var begin = beginDate + (beginTime ? ' ' + beginTime : '');
        var period;
        if (beginDate === endDate) {
            period = (!endTime || endTime === beginTime) ? begin : begin + ' – ' + endTime;
        } else {
            period = begin + ' – ' + endDate + (endTime ? ' ' + endTime : '');
        }
        $('#event_period').text(period);
    };
    $allDay.on('change', update);
    $when.on('input change', 'input', update);
    //dates picked in the calendar popup do not trigger any event on their field
    $(document).on('click keyup', '.ui.popup', function() {
        setTimeout(update);
    });
    update();
};

/* Event form: group is chosen only for events restricted to a group */
var _eventsVisibility = function() {
    var $choices = $('#event_visibility input[name="visibility"]');
    $choices.on('change', function() {
        var $checked = $choices.filter(':checked');
        $('#group_field').toggleClass('displaynone', $checked.val() !== 'group');
        $('#visibility_help')
            .text($checked.data('help'))
            .toggleClass('warning', $checked.val() === 'public')
            .toggleClass('info', $checked.val() !== 'public');
    });
};

/* Calendar: first subscription opens the calendar application at once, address changes are confirmed */
var _eventsSubscription = function() {
    $('#feed_regenerate').on('submit', function(event) {
        var form = this;
        var $form = $(form);
        event.preventDefault();
        $.modal({
            title: $form.data('title'),
            content: $form.data('confirm'),
            class: 'tiny',
            actions: [{
                text: $form.data('cancel'),
                class: 'cancel'
            }, {
                text: $form.data('ok'),
                class: 'primary',
                click: function() {
                    //native submit, without this handler
                    form.submit();
                }
            }]
        }).modal('show');
    });

    //how to subscribe, from each calendar application
    var $help = $('#subscription_help');
    $help.find('.tabular.menu .item').tab({context: $help});
    $('[data-subscription-help]').on('click', function() {
        $help.modal('show');
    });
    $('#feed_subscribe').on('submit', function(event) {
        var $form = $(this);
        event.preventDefault();
        $.post($form.attr('action'))
            .done(function(urls) {
                window.location.href = urls.webcal;
                //back on the calendar, with the menu of the new subscription
                setTimeout(function() {
                    window.location.href = $form.data('back');
                }, 1500);
            })
            .fail(function() {
                $form.off('submit')[0].submit();
            });
    });
};

/* Booking form: choosing another event reloads the form with its activities */
var _eventsBookingEvent = function() {
    $('#modifform #event').on('change', function() {
        $(this).closest('form').trigger('submit');
    });
};

/* Bookings list: mailing to the members of selected bookings */
var _sendmail = function() {
    $('#listform')
        .append('<input type="hidden" name="sendmail" value="true"/>')
        .append('<input type="hidden" name="mailing_new" value="true"/>')
        .append('<input type="hidden" name="mailing" value="true"/>')
        .trigger('submit');
};

$(function() {
    _eventsActivities();
    _eventsBookingEvent();
    _eventsWhen();
    _eventsVisibility();
    _eventsSubscription();
});
