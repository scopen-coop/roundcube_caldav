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
 * Affichage de la banière de l'événement
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


    const displayInfo = display_informations( $event_template_html, array_response);
    let select = displayInfo.select;
    $event_template_html = displayInfo.$event_template_html;
    array_response = displayInfo.array_response;

    // affichage de la popup de dialog en cas de clic sur le bouton reschedule
    let rescheduledPopup = display_rescheduled_popup($event_template_html, changeDateAndLocation);

    /**
     * Lorsque l'utilisateur décide de reprogrammer l'évenement,
     * on verifie que les date sont valide et toutes remplies et on affiche un balise html pour indiquer à l'utilisateur
     * les informations modifiées avant l'ajout dans son calendrier, On a besoin d'initialiser les variables car la fonction
     * ne peut pas prendre de paramètres.
     * @returns {number}
     */
    var $div_to_add = $event_template_html.find('.if_rescheduled'),
        $location_input = $event_template_html.find('.location_input'),
        $date_start = $event_template_html.find('.date_start'),
        $time_start = $event_template_html.find('.time_start'),
        $date_end = $event_template_html.find('.date_end'),
        $time_end = $event_template_html.find('.time_end');
    function changeDateAndLocation() {
        return change_date_location_out(rescheduledPopup, $event_template_html, $div_to_add, $date_start, $date_end, $time_start,
            $time_end, $location_input, array_response, select);
    }

    // Affichage des boutons et envoi de la requete lors d'un clic
    display_button_and_send_request_on_clic(select, array_response, $event_template_html);

    // Enfin on ajoute le contenu que l'on a modifié au corps du message
    $("#message-objects").append($event_template_html);
}

/**
 * On réaffiche la bannière cachée lors de l'envoi d'un message ou l'ajout sur le serveur
 * @param response
 */
function display_after_response(response) {
    $('#' + response.uid).show();
    $('#saving_and_sending').hide()
}

/**
 * Affiche les informations contenue dans array response
 * @param $event_template_html
 * @param array_response
 * @returns {{select, $event_template_html, array_response}}
 */
function display_informations( $event_template_html, array_response) {
    let modification = new Display($event_template_html,array_response);

    // On récupère le role du participant et la methode du fichier reçu
    let {isOrganizer, isACounter} = modification.init_method_and_status();


    // On affiche le titre
    modification.display_title();

    // Si il y a des modifications on affiche un message
    modification.display_modification_message();

    // On affiche la date
    modification.display_date();
    // Si le mail est une proposition de modifications et possède des champs date modifiés
    if (isACounter) {
        modification.display_modified_date();
    }

    // On affiche la description et le lieu de l'evt
    modification.display_location_and_description();
    if (isACounter) {
        modification.display_modified_location_and_description();
    }


    // On affiche s'il s'agit d'un evt reccurent
    modification.display_reccurent_events();

    // On affiche les boutons de réponse aux autres participants
    modification.display_attendee();
    if (isACounter) {
        // On affiche les boutons de réponse aux nouveaux participants si modifications
        modification.display_new_attendee();
    }

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


    $event_template_html = modification.get_event_template();
    array_response = modification.get_array_response();
    return {select, $event_template_html, array_response};
}

/**
 * Affichage de la popup pour reprogrammer l'événement en cas de clic sur le bouton "reprogrammer"
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
                text: rcmail.gettext('reschedule_meeting', 'roundcube_caldav'),
                click: changeDateAndLocation
            },
            {
                text: rcmail.gettext('cancel', 'roundcube_caldav'),
                click: function () {
                    dialog.dialog("close");
                }
            }
        ],
        open: function () {
        },
        close: function () {
            form[0].reset();
        }
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
 * Changement de date et d'emplacement extrait de la fonction undirect_rendering (je n'ai pas réussi à la sortir
 * completement car elle est appelée par une autre fonction
 */
function change_date_location_out(rescheduledPopup, $event_template_html, $div_to_add, $date_start, $date_end,
                                  $time_start, $time_end, $location_input, array_response, select) {

    let isOrganizer = false;
    if (this.array_response['identity']) {
        isOrganizer = array_response['identity']['role'] === 'ORGANIZER';
    }

    let used_event = array_response['used_event'];

    let areFieldsFilled = false;
    let $comment = rescheduledPopup.find("textarea");

    let confirm = $event_template_html.find('.confirm_button');
    let tentative = $event_template_html.find('.tentative_button');
    let decline = $event_template_html.find('.decline_button');

    // On rajoute les dates des anciens evt comme valeur par défaut dans les inputs
    let date = parse_date(used_event['dtstart_array'][1], used_event['dtend_array'][1])
    $date_start.attr("value", date[0]['year'] + '-' + date[0]['month'] + '-' + date[0]['day']);
    $date_end.attr("value", date[1]['year'] + '-' + date[1]['month'] + '-' + date[1]['day']);




    // Si tous les champs dates sont remplis
    if ($div_to_add.find(".if_rescheduled_msg").length === 0) {
        if (isOrganizer) {
            $div_to_add.append('<p class="if_rescheduled_msg"><b>' + rcmail.gettext("if_rescheduled_msg", 'roundcube_caldav') + '</b></p>');
        } else {
            $div_to_add.append('<p class="if_rescheduled_msg"><b>' + rcmail.gettext("ask_rescheduled_msg", 'roundcube_caldav') + '</b></p>');
        }
    }
    if ($date_start.val() && $date_end.val() && $time_start.val() && $time_end.val()) {

        var chosenDateStart = $date_start.val();
        var chosenDateEnd = $date_end.val();
        var chosenTimeStart = $time_start.val();
        var chosenTimeEnd = $time_end.val();

        let datestr = new Date(chosenDateStart + ' ' + chosenTimeStart).getTime();
        let dateend = new Date(chosenDateEnd + ' ' + chosenTimeEnd).getTime();

        // On vérifie que la date est valide
        if (dateend > datestr) {
            areFieldsFilled = true;

            if (chosenDateStart === chosenDateEnd) {
                $event_template_html.find(".msg_date").remove();
                $div_to_add.append('<span class="msg_date" >' + chosenDateStart + ' ' + chosenTimeStart + ' - '
                    + chosenTimeEnd + '<br></span>');
            } else {
                $event_template_html.find(".msg_date").remove();
                $div_to_add.append('<span class="msg_date">' + chosenDateStart + ' ' + chosenTimeStart + ' / '
                    + chosenDateEnd + ' ' + chosenTimeEnd + '<br></span>');
            }
        } else {
            window.alert(rcmail.gettext('error_date_inf', 'roundcube_caldav'));
            return 0;
        }
    }

    // Si le champs location est rempli
    if ($location_input.val()) {
        areFieldsFilled = true;
        var chosenLocation = $location_input.val();
        $event_template_html.find(".msg_location").remove();
        $div_to_add.append('<span class="msg_location">' + rcmail.gettext('location', 'roundcube_caldav')
            + $location_input.val() + '<br></span>');
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
 * Affichage de la popup dialog permettant de laisser un commentaire pour le destinataire
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
                text: rcmail.gettext('confirm', 'roundcube_caldav'),
                click: function () {
                    post_import_event_server($event_template_html, calendar, array_response, used_event, current_button.attr("status"), current_button.attr("method"), $comment.val());
                    $(this).dialog("destroy");
                }
            },
            {
                text: rcmail.gettext('send_without_comment', 'roundcube_caldav'),
                click: function () {
                    post_import_event_server($event_template_html, calendar, array_response, used_event, current_button.attr("status"), current_button.attr("method"), '');
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
 * Envoi de la requete post au serveur pour qu'il effectue l'action "import_event_on_server"
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
 * Affichage des différents boutons et programmation des différents comportement en cas de clic sur un bouton
 */
function display_button_and_send_request_on_clic(select, array_response, $event_template_html) {

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
    if (select.val()) {
        calendar = select.val()
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
                buttons_array[button].html(buttons_array[button].attr("data-label-enabled"));
                buttons_array[button].bind('click', function evt() {
                    // On affiche pas la popup de message si il s'agit uniquement d'importer l'événement sur notre serveur
                    if (button !== 'update_button' && button !== 'update_button_organizer') {
                        display_message_popup($event_template_html, calendar, array_response, used_event, buttons_array[button]);
                    } else {
                        post_import_event_server($event_template_html, calendar, array_response, used_event, buttons_array[button].attr("status"), buttons_array[button].attr("method"), '');
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
 * On retrouve l'utilisateur parmis la liste des participants
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
 * Parse la date recus sous le format yyyymmdd\Thhii(Z)
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
 * Retourne une chaine lisible pour la date
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
