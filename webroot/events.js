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
    _eventsSubscription();
});
