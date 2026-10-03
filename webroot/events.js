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
});
