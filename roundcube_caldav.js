rcmail.addEventListener('init', function (evt) {


    // Si la page indique qu'elle est en train de charger on fait un appel au serveur pour récupérer les donnée
    if ($('#loading').length > 0) {
        rcmail.http_post('plugin.roundcube_caldav_get_info_server', {
            _uid: rcmail.env.uid,
            _mbox: rcmail.env.mailbox,
        });
    }
    // On récupère toutes ces information et on affiche la bannière
    rcmail.addEventListener('plugin.undirect_rendering_js', undirect_rendering);

    // On récupère toutes ces information et on affiche la bannière
    rcmail.addEventListener('plugin.display_after_response', display_after_response);

});


/**
 * Display of event banner
 * @param response
 */
function undirect_rendering(response) {

    // On copie le template html et l'on cache la partie chargement...
    let $event_template_html;
    $event_template_html = $($('template#display').html());
    $("#loading").hide();

    // On recupère la réponse du serveur
    let array_response = response.request;

    // On dispose l'uid de l'event pour pouvoir le retrouver ensuite lors de l'appel à display_after_response
    $event_template_html.attr("id", array_response['uid']);


    const displayInfo = display_informations($event_template_html, array_response);
    let select = displayInfo.select;
    $event_template_html = displayInfo.$event_template_html;
    array_response = displayInfo.array_response;


    /**
     * Lorsque l'utilisateur décide de reprogrammer l'évenement,
     * on verifie que les date sont valide et toutes remplies et on affiche un balise html pour indiquer à l'utilisateur
     * les informations modifiées avant l'ajout dans son calendrier, On a besoin d'initialiser les variables car la fonction
     * ne peut pas prendre de paramètres, et est appelée lors du clic sur un bouton à l'intérieur de la popup
     * @returns {number}
     */
    var {
        $date_start,
        $date_end,
        $div_to_add,
        $location_input,
        $time_start,
        $time_end
    } = initialized_var_for_reschedule_popup($event_template_html, array_response);

    function changeDateAndLocation() {
        return change_date_location_out(rescheduledPopup, $event_template_html, $div_to_add, $date_start, $date_end, $time_start,
            $time_end, $location_input, array_response, select);
    }


    // affichage de la popup de dialog en cas de clic sur le bouton reschedule
    let rescheduledPopup = display_rescheduled_popup($event_template_html, changeDateAndLocation);


    // Affichage des boutons et envoi de la requete lors d'un clic
    display_button_and_send_request_on_clic(select, array_response, $event_template_html);

    // Enfin on ajoute le contenu que l'on a modifié au corps du message
    $("#message-objects").append($event_template_html);
}

/**
 * Re-display the hidden banner (when sending a message or adding it to the server), after the sever responses
 * @param response
 */
function display_after_response(response) {
    $('#' + response.uid).show();
    $('#saving_and_sending').hide()
}

/**
 * Displays the information contained in array response
 * @param $event_template_html
 * @param array_response
 * @returns {{select, $event_template_html, array_response}}
 */
function display_informations($event_template_html, array_response) {
    let modification = new Display($event_template_html, array_response);

    // On récupère le role du participant et la methode du fichier reçu
    let {isOrganizer, isACounter} = modification.init_method_and_status();


    // On affiche le titre
    modification.display_title();

    // On affiche la date
    modification.display_date();

    // On affiche la description et le lieu de l'evt
    modification.display_location_and_description();

    // On affiche les boutons de réponse aux autres participants
    modification.display_attendee();

    // Si le mail est une proposition de modifications envoyée par un participant
    // ou une modification envoyée par l'employeur par rapport à l'événement correspondant stocké sur notre calendrier
    if (isACounter || (!isOrganizer && array_response['found_older_event_on_calendar'])) {
        // Si il y a des modifications on affiche un message
        modification.display_modification_message();
        // Puis on affiche les champs modifiés
        modification.display_modified_date();
        modification.display_modified_location_and_description();

    }


    // On affiche s'il s'agit d'un evt reccurent
    modification.display_reccurent_events();

    // On affiche les evt en colision / avant / apres
    modification.display_close_events();

    // On regarde si le serveur est possede uniquement si l'utilisateur n'est pas l'organisateur
    if (!isOrganizer) {
        modification.display_message_if_event_is_already_on_server();
    }

    // Affichage du commentaire de l'expediteur
    modification.display_comment();

    // On récupère la valeur du champs select ou l'on selectionne les calendriers
    let select = modification.display_select_calendars();

    $event_template_html = modification.event_template_html;
    array_response = modification.array_response;



    return {select, $event_template_html, array_response};
}

/**
 *  Display of the popup to reschedule the event when clicking on the "reschedule" button
 * @param $event_template_html
 * @param changeDateAndLocation
 * @returns {*}
 */
function display_rescheduled_popup($event_template_html, changeDateAndLocation) {
    // Spécification des propriétés de la popup de dialogue
    let dialog = $event_template_html.find(".dialog-form")
    let form = dialog.find("form");

    dialog.dialog({
        autoOpen: false,
        height: 'auto',
        width: 600,
        modal: true,
        resizable: false,

        buttons: [
            {
                text: rcmail.gettext('cancel', 'roundcube_caldav'),
                click: function () {
                    dialog.dialog("close");
                }
            },
            {
                text: rcmail.gettext('reschedule_meeting', 'roundcube_caldav'),
                click: changeDateAndLocation
            }

        ],
    });
    form.on("submit", function (event) {
        event.preventDefault();
        changeDateAndLocation();
    });


    $event_template_html.find(".open_dialog").button().on("click", function () {
        dialog.dialog("open");
    });
    return dialog;
}


/**
 * Initialize var for the reschedule popup
 * @param $event_template_html
 * @param array_response
 * @returns {{$date_end, $location_input, $date_start, $time_start, $div_to_add, $time_end}}
 */
function initialized_var_for_reschedule_popup($event_template_html, array_response) {
    var $date_start = $event_template_html.find('.date_start'),
        $date_end = $event_template_html.find('.date_end'),
        $div_to_add = $event_template_html.find('.if_rescheduled'),
        $location_input = $event_template_html.find('.location_input'),
        $time_start = $event_template_html.find('.time_start'),
        $time_end = $event_template_html.find('.time_end');

    // On rajoute les dates des anciens evt comme valeur par défaut dans les inputs

    $date_start.val(array_response['date_start']);
    $date_end.val(array_response['date_end']);
    $time_start.val(array_response['date_hours_start']);
    $time_end.val(array_response['date_hours_end']);


    return {$date_start, $date_end, $div_to_add, $location_input, $time_start, $time_end};
}

/**
 * Date and location change extracted from the undirect_rendering function
 *  (je n'ai pas réussi à la sortir completement car elle est appelée par une autre fonction)
 * @param rescheduledPopup
 * @param $event_template_html
 * @param $div_to_add
 * @param $date_start
 * @param $date_end
 * @param $time_start
 * @param $time_end
 * @param $location_input
 * @param array_response
 * @param select
 * @returns {number}
 */
function change_date_location_out(rescheduledPopup, $event_template_html, $div_to_add, $date_start, $date_end,
                                  $time_start, $time_end, $location_input, array_response, select) {


    let isOrganizer = false;
    if (array_response['identity']) {
        isOrganizer = array_response['identity']['role'] === 'ORGANIZER';
    }

    let used_event = array_response['used_event'];

    let areFieldsFilled = false;
    let $comment = rescheduledPopup.find("textarea");

    let confirm = $event_template_html.find('.confirm_button');
    let tentative = $event_template_html.find('.tentative_button');
    let decline = $event_template_html.find('.decline_button');



    if ($div_to_add.find(".if_rescheduled_msg").length === 0) {
        if (isOrganizer) {
            $div_to_add.prepend('<p class="if_rescheduled_msg"><b>' + rcmail.gettext("if_rescheduled_msg", 'roundcube_caldav') + '</b></p>');
        } else {
            $div_to_add.prepend('<p class="if_rescheduled_msg"><b>' + rcmail.gettext("ask_rescheduled_msg", 'roundcube_caldav') + '</b></p>');
        }
    }


    if ($date_start.val() !== array_response['date_start'] || $date_end.val() !== array_response['date_end']
        || $time_start.val() !== array_response['date_hours_start'] || $time_end.val() !== array_response['date_hours_end']) {


        var chosenDateStart = $date_start.val();
        var chosenDateEnd = $date_end.val();
        var chosenTimeStart = $time_start.val();
        var chosenTimeEnd = $time_end.val();

        let datestr = new Date(chosenDateStart + ' ' + chosenTimeStart).getTime();
        let dateend = new Date(chosenDateEnd + ' ' + chosenTimeEnd).getTime();

        // On vérifie que la date est valide
        if (dateend >= datestr) {
            areFieldsFilled = true;
            let $message_date = $div_to_add.find(".msg_date");

            if (chosenDateStart === chosenDateEnd) {
                $message_date.show();
                $message_date.html(chosenDateStart + ' ' + chosenTimeStart + ' - ' + chosenTimeEnd + '    ');
            } else {
                $message_date.show();
                $message_date.html(chosenDateStart + ' ' + chosenTimeStart + ' / ' + chosenDateEnd + ' ' + chosenTimeEnd + '    ');
            }
        } else {
            window.alert(rcmail.gettext('error_date_inf', 'roundcube_caldav'));
            return 0;
        }

        if (!$location_input.val()) {
            $div_to_add.find(".msg_location").hide();
        }
    }

    // Si le champs location est rempli
    if ($location_input.val()) {
        areFieldsFilled = true;
        var chosenLocation = $location_input.val();
        let $message_location = $div_to_add.find(".msg_location");
        $message_location.show();
        $message_location.html(rcmail.gettext('location', 'roundcube_caldav') + $location_input.val());

        if (!($date_start.val() && $date_end.val() && $time_start.val() && $time_end.val())) {
            $div_to_add.find(".msg_date").hide();
        }
    }

    // Si au moins une des deux conditions est remplie
    if (areFieldsFilled) {
        let comment = $comment.val();
        $div_to_add.show();
        rescheduledPopup.dialog("close");
        // Si les informations ont correctement été remplies on peut fermer la boite de dialogue
        tentative.attr('disabled', 'true');
        tentative.html(rcmail.gettext('tentatived', 'roundcube_caldav'));
        confirm.removeAttr('disabled');
        confirm.html(rcmail.gettext('confirm', 'roundcube_caldav'));
        decline.removeAttr('disabled');
        decline.html(rcmail.gettext('decline', 'roundcube_caldav'));

        let calendar = array_response['found_older_event_on_calendar'] ? array_response['found_older_event_on_calendar'] : null;
        if ((!calendar && select.val()) || !isOrganizer) {
            calendar = select.val()
        }

        let modification = {
            _chosenDateStart: chosenDateStart,
            _chosenDateEnd: chosenDateEnd,
            _chosenTimeStart: chosenTimeStart,
            _chosenTimeEnd: chosenTimeEnd,
            _chosenLocation: chosenLocation,
        }

        post_import_event_server(
            $event_template_html,
            calendar,
            array_response,
            used_event,
            isOrganizer ? 'CONFIRMED' : 'TENTATIVE',
            isOrganizer ? 'REQUEST' : 'COUNTER',
            comment,
            modification
        );

    } else {
        window.alert(rcmail.gettext('error_incomplete_field', 'roundcube_caldav'));
    }
}

/**
 * Display of the popup dialog allowing to leave a comment for the recipient
 * @param $event_template_html
 * @param calendar
 * @param array_response
 * @param used_event
 * @param current_button
 */
function display_message_popup($event_template_html, calendar, array_response, used_event, current_button) {

    // Spécification des propriétés de la popup de dialogue
    let $comment = $event_template_html.find(".message-dialog > textarea");
    let dialog = $event_template_html.find(".message-dialog").dialog({
        autoOpen: false,
        height: 'auto',
        width: 600,
        modal: true,
        resizable: false,

        buttons: [

            {
                text: rcmail.gettext('send_without_comment', 'roundcube_caldav'),
                click: function () {
                    post_import_event_server($event_template_html, calendar, array_response, used_event, current_button.attr("status"), current_button.attr("method"), '');
                    $(this).dialog("destroy");
                }
            },
            {
                text: rcmail.gettext('confirm', 'roundcube_caldav'),
                click: function () {
                    post_import_event_server($event_template_html, calendar, array_response, used_event, current_button.attr("status"), current_button.attr("method"), $comment.val());
                    $(this).dialog("destroy");
                }
            }
        ],
        open: function () {
        },
        close: function () {
            $(this).dialog("destroy");
        }
    });

    dialog.dialog("open");

}


/**
 * Send the post request to the server to perform the "import_event_on_server" action
 * @param $event_template_html
 * @param calendar
 * @param array_response
 * @param used_event
 * @param status
 * @param method
 * @param comment
 * @param modification
 */
function post_import_event_server($event_template_html, calendar, array_response, used_event, status, method, comment, modification = null) {

    rcmail.http_post('plugin.roundcube_caldav_import_event_on_server', {
        _mail_uid: rcmail.env.uid,
        _mbox: rcmail.env.mailbox,
        _calendar: calendar,
        _status: status,
        _method: method,
        _comment: comment,
        _identity: array_response['identity'] ? array_response['identity'] : 'NO_PARTICIPANTS',
        _event_uid: used_event['uid'],
        _modification: modification,
    });

    // On cache la banière le temps de l'execution des requetes par le serveur Roundcube
    // et on affiche un message de chargement
    $event_template_html.hide();
    $('#saving_and_sending').show();

}

/**
 * Display of the different buttons and programming of the different behaviors when a button is clicked
 * @param select_calendars
 * @param array_response
 * @param $event_template_html
 */
function display_button_and_send_request_on_clic(select_calendars, array_response, $event_template_html) {

    let buttons_array = {
        'reschedule': 'reschedule',
        'confirm_button': $event_template_html.find('.confirm_button'),
        'tentative_button': $event_template_html.find('.tentative_button'),
        'decline_button': $event_template_html.find('.decline_button'),
        'update_button': $event_template_html.find('.update_button'),
        'cancel_button': $event_template_html.find('.cancel_button'),
        'cancel_button_organizer': $event_template_html.find('.cancel_button_organizer'),
        'cancel_recurrent_button_organizer': $event_template_html.find('.cancel_recurrent_button_organizer'),
        'update_button_organizer': $event_template_html.find('.update_button_organizer'),
        'confirm_button_organizer': $event_template_html.find('.confirm_button_organizer'),
        'decline_button_organizer': $event_template_html.find('.decline_button_organizer')
    }
    // On assigne a quel calendrier ajouter l'événement en effet le choix du calendrier n'est pas tout le temps proposé
    // par exemple dans le cas ou on est l'organisateur de l'événement
    let calendar;
    if (select_calendars.val()) {
        calendar = select_calendars.val()
    } else {
        calendar = array_response['found_older_event_on_calendar'];
    }


    let isOrganizer = false;
    if (array_response['identity']) {
        isOrganizer = array_response['identity']['role'] === 'ORGANIZER';
    }

    // On récupère l'evt
    let used_event = array_response['used_event'];

    for (let button of array_response['buttons_to_display']) {
        if (buttons_array[button]) {
            if (typeof buttons_array[button] === "string") {
                let reschedule_dialog = $event_template_html.find('.open_dialog')
                // On change les attributs du bouton reschedule si c'est un organisateur
                if (isOrganizer) {
                    reschedule_dialog.attr('method', 'REQUEST');
                    reschedule_dialog.attr('status', 'CONFIRMED');
                    reschedule_dialog.html(reschedule_dialog.attr("data-label-organizer"));
                }
                reschedule_dialog.show();
            } else {
                buttons_array[button].show();
                if (!buttons_array[button].attr('disabled')) {
                    buttons_array[button].html(buttons_array[button].attr("data-label-enabled"));
                }
                buttons_array[button].bind('click', function evt() {
                    // On affiche pas la popup de message si il s'agit uniquement d'importer l'événement sur notre serveur
                    if (button !== 'update_button' && button !== 'update_button_organizer') {
                        display_message_popup($event_template_html, calendar, array_response, used_event, buttons_array[button]);
                    } else {
                        post_import_event_server($event_template_html, calendar, array_response, used_event, array_response['sender_partstat'], buttons_array[button].attr("method"), '');
                    }

                    buttons_array[button].attr('disabled', 'true');
                    buttons_array[button].html(buttons_array[button].attr("data-label-disabled"));
                    // On remet les autres bouton en clickable si il ne le sont plus
                    for (let other_button of array_response['buttons_to_display']) {
                        if (typeof buttons_array[other_button] !== 'string' && other_button !== button) {
                            buttons_array[other_button].removeAttr('disabled');
                            buttons_array[other_button].html(buttons_array[other_button].attr("data-label-enabled"));
                        }
                    }
                })
            }
        }
    }
}


/**
 * Find the user among the attendee list
 * @param email_to_find
 * @param attendees
 * @returns {*}
 */
function find_among_attendee(email_to_find, attendees) {
    for (let attendee of attendees) {
        if (attendee['email'] === email_to_find) {
            return attendee;
        }
    }
}

/**
 * Parse the received date in the format yyyymmdd\Thhii(Z)
 * @param str_date_start
 * @param str_date_end
 * @returns {{month: string, year: string, day: string}[]}
 */
function parse_date(str_date_start, str_date_end) {
    let year_s = str_date_start.substring(0, 4);
    let month_s = str_date_start.substring(4, 6);
    let day_s = str_date_start.substring(6, 8);

    let year_e = str_date_end.substring(0, 4);
    let month_e = str_date_end.substring(4, 6);
    let day_e = str_date_end.substring(6, 8);

    let date_s = {'year': year_s, 'month': month_s, 'day': day_s}
    let date_e = {'year': year_e, 'month': month_e, 'day': day_e}


    if (str_date_start.length > 8) {
        var hour_s = str_date_start.substring(9, 11);
        var minutes_s = str_date_start.substring(11, 13);

        var hour_e = str_date_end.substring(9, 11);
        var minutes_e = str_date_end.substring(11, 13);

        date_s = Object.assign({'hour': hour_s, 'minutes': minutes_s}, date_s);
        date_e = Object.assign({'hour': hour_e, 'minutes': minutes_e}, date_e);
    }

    return [date_s, date_e];
}

/**
 * Returns a readable string for the date
 * @param date
 * @returns {string}
 */
function pretty_date(date) {
    var display_date;
    if (date[0]['hour'] === undefined || date[1]['hour'] === undefined) {
        if (date[0]['day'] === date[1]['day'] && date[0]['month'] === date[1]['month'] && date[0]['year'] === date[1]['year']) {
            display_date = date[0]['day'] + '/' + date[0]['month'] + '/' + date[0]['year'];
        } else {
            display_date = date[0]['day'] + '/' + date[0]['month'] + '/' + date[0]['year'] +
                ' - ' + date[1]['day'] + '/' + date[1]['month'] + '/' + date[1]['year'];
        }
    } else {
        if (date[0]['day'] === date[1]['day'] && date[0]['month'] === date[1]['month'] && date[0]['year'] === date[1]['year']) {
            display_date = date[0]['day'] + '/' + date[0]['month'] + '/' + date[0]['year'] + ' ' + date[0]['hour'] + ':'
                + date[0]['minutes'] + '-' + date[1]['hour'] + ':' + date[1]['minutes'];
        } else {
            display_date = date[0]['day'] + '/' + date[0]['month'] + '/' + date[0]['year'] + ' ' + date[0]['hour'] + ':'
                + date[0]['minutes'] + '-' + date[1]['day'] + '/' + date[1]['month'] + '/' + date[1]['year'] + ' '
                + date[1]['hour'] + ':' + date[1]['minutes'];
        }
    }
    return display_date;
}
