function affichage(response) {
    console.log(response.request)
}

/**
 * teste l'égalité de deux string
 * @param str1
 * @param str2
 * @returns {boolean}
 */
function isStringEquals(str1, str2) {
    if (str1 && str2) {
        return str1.toUpperCase() === str2.toUpperCase();
    }
    return false;
}

function find_among_attendee(email_to_find, attendees) {

    for (let attendee of attendees) {
        if (isStringEquals(attendee['email'], email_to_find)) {
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
        if (isStringEquals(date[0]['day'], date[1]['day']) && isStringEquals(date[0]['month'], date[1]['month']) && isStringEquals(date[0]['year'], date[1]['year'])) {
            display_date = date[0]['day'] + '/' + date[0]['month'] + '/' + date[0]['year'];
        } else {
            display_date = date[0]['day'] + '/' + date[0]['month'] + '/' + date[0]['year'] +
                ' - ' + date[1]['day'] + '/' + date[1]['month'] + '/' + date[1]['year'];
        }
    } else {
        if (isStringEquals(date[0]['day'], date[1]['day']) && isStringEquals(date[0]['month'], date[1]['month']) && isStringEquals(date[0]['year'], date[1]['year'])) {
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


/**
 * Récupère le status de l'événement plus récent importé dans le calendrier
 * @param status
 */
function change_status(status, $event) {
    if (isStringEquals(status, 'CONFIRMED')) {
        let confirm = $event.find('.confirm_button');
        if (confirm) {
            confirm.attr('disabled', true);
            confirm.html(rcmail.gettext('confirmed', 'roundcube_caldav'));
        }
    } else if (isStringEquals(status, 'TENTATIVE')) {
        let tentative = $event.find('.tentative_button');
        if (tentative) {
            tentative.attr('disabled', true);
            tentative.html(rcmail.gettext('tentatived', 'roundcube_caldav'));
        }
    } else if (isStringEquals(status, 'CANCELLED')) {
        let decline = $event.find('.decline_button');
        if (decline) {
            decline.attr('disabled', true);
            decline.html(rcmail.gettext('declined', 'roundcube_caldav'));

        }
    }
}


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

    // On récupère le role du participant et la methode du fichier reçu
    let isOrganizer = isStringEquals(array_response['identity']['role'], 'ORGANIZER');
    let isACounter = isStringEquals(array_response['METHOD'], 'COUNTER');
    let isAReply = isStringEquals(array_response['METHOD'], 'REPLY');
    let isARequest = isStringEquals(array_response['METHOD'], 'REQUEST');
    let isADeclineCounter = isStringEquals(array_response['METHOD'], 'DECLINECOUNTER');


    // On récupère les participants qui sont respectivement l'expediteur et le destinataire de l'email
    if (array_response['attendees'].length > 0) {
        var sender = find_among_attendee(array_response['sender_email'], array_response['attendees']);
        var receiver = find_among_attendee(array_response['receiver_email'], array_response['attendees']);
    }

    // On récupère l'evt
    let used_event = array_response['used_event'];


    // On affiche le titre
    let $invitation = $event_template_html.find('.invitation')
    let sender_name ='';
    if(sender){
        sender_name = sender['name'] ? sender['name'] : sender['email'];
    }

    let receiver_name = '';
    if(receiver){
        receiver_name = receiver['name'] ? receiver['name'] : receiver['email'];
    }

    // On affiche des titres différents selon le role ou la methode
    if (isOrganizer && isACounter) {
        $invitation.append('<h3>' + rcmail.gettext('invitation_modification', 'roundcube_caldav') + '</h3>')
    } else if (isOrganizer && isAReply) {
        if (isStringEquals(sender['partstat'], 'ACCEPTED')) {
            $invitation.append('<h3>' + sender_name + rcmail.gettext('invitation_accepted', 'roundcube_caldav') + '</h3>')
        } else {
            $invitation.append('<h3>' + sender_name + rcmail.gettext('invitation_declined', 'roundcube_caldav') + '</h3>')
        }
    } else if (isOrganizer && isARequest) {
        $invitation.append('<h3>' + rcmail.gettext('invitation_send', 'roundcube_caldav') + '</h3>')
    } else if (isOrganizer && isADeclineCounter) {
        $invitation.append('<h3>' + rcmail.gettext('invitation_decline_modifications1', 'roundcube_caldav') + receiver_name
            + rcmail.gettext('invitation_decline_modifications2', 'roundcube_caldav') + '</h3>')
    } else {
        $invitation.append('<h3>' + rcmail.gettext('invitation', 'roundcube_caldav') + '</h3>')
    }
    $invitation.append('<h4>' + used_event['summary'] + '</h4>');

    // Si il y a des modifications on affiche un message
    let $modifications = $event_template_html.find('.if_modification');
    if (isACounter && (array_response['new_description'] || array_response['new_location'] || array_response['new_date'])) {
        $modifications.html(rcmail.gettext('if_modification','roundcube_caldav'));
        $modifications.show();
    }else if (isACounter){
        $modifications.html(rcmail.gettext('if_no_modification','roundcube_caldav'));
        $modifications.show();
    }

    // On regarde si le serveur est en avance uniquement si l'utilisateur n'est pas l'organisateur
    if (!isOrganizer) {

        if (!array_response['found_advance']) {
            $event_template_html.find('.found_advance').hide();
        } else {
            if (!array_response['is_sequences_equal']) {
                $event_template_html.find('.found_advance').html(rcmail.gettext('modified_event', 'roundcube_caldav') + array_response['found_on_calendar']['display_name']);
            }
            change_status(array_response['found_advance'][1].status, $event_template_html);
        }
    }


    // On affiche la date
    if (array_response['same_date']) {
        $event_template_html.find('.different_date').hide();
        let $same_date = $event_template_html.find('.same_date');
        $same_date.children('.d').html(array_response['date_day_start']);
        $same_date.children('.m').html(array_response['date_month_start']);
        if (!isStringEquals(array_response['date_hours_start'], '0:00') || !isStringEquals(array_response['date_hours_end'], '0:00')) {
            $same_date.children('.h').html(array_response['date_hours_start'] + ' : ' + array_response['date_hours_end']);
        } else {
            $same_date.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
        }
    } else {
        $event_template_html.find('.same_date').hide();
        let $dif_date_start = $event_template_html.find('.different_date.start');
        $dif_date_start.children('.d').html(array_response['date_day_start']);
        $dif_date_start.children('.m').html(array_response['date_month_start']);
        if (!isStringEquals(array_response['date_hours_start'], '0:00')) {
            $dif_date_start.children('.h').html(array_response['date_hours_start']);
        } else {
            $dif_date_start.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
        }


        let $dif_date_end = $event_template_html.find('.different_date.end');
        $dif_date_end.children('.d').html(array_response['date_day_end']);
        $dif_date_end.children('.m').html(array_response['date_month_end']);
        if (!isStringEquals(array_response['date_hours_end'], '0:00')) {
            $dif_date_end.children('.h').html(array_response['date_hours_end']);
        } else {
            $dif_date_end.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
        }

    }
    // Si le mail est une proposition de modifications et possède des champs date modifiés
    if (isACounter) {
        if (array_response['new_date']) {
            // On affiche la nouvelle  date
            let $new_date = $event_template_html.find('.new_date_event');
            $new_date.show();
            if (array_response['new_date']['same_date']) {
                $event_template_html.find('.arrow-right.new').show();
                $new_date.children('.different_date').hide();
                let $same_date = $new_date.children('.same_date');
                $same_date.show()
                $same_date.children('.d').html(array_response['new_date']['date_day_start']);
                $same_date.children('.m').html(array_response['new_date']['date_month_start']);
                if (!isStringEquals(array_response['new_date']['date_hours_start'], '0:00') || !isStringEquals(array_response['new_date']['date_hours_end'], '0:00')) {
                    $same_date.children('.h').html(array_response['new_date']['date_hours_start'] + ' : ' + array_response['new_date']['date_hours_end']);
                } else {
                    $same_date.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
                }
            } else {
                $event_template_html.find('.date_container').css("flex-direction", "column")
                $event_template_html.find('.arrow-down.new').show();
                $new_date.css("display", "flex");
                $new_date.css("flex-direction", "raw");
                $new_date.children('.same_date').hide();
                let $dif_date_start = $new_date.children('.different_date.start');
                $dif_date_start.show();
                $dif_date_start.children('.d').html(array_response['new_date']['date_day_start']);
                $dif_date_start.children('.m').html(array_response['new_date']['date_month_start']);
                if (!isStringEquals(array_response['new_date']['date_hours_start'], '0:00')) {
                    $dif_date_start.children('.h').html(array_response['new_date']['date_hours_start']);
                } else {
                    $dif_date_start.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
                }


                $new_date.children('.different_date.arrow-right').show();
                let $dif_date_end = $new_date.children('.different_date.end');
                $dif_date_end.show();
                $dif_date_end.children('.d').html(array_response['new_date']['date_day_end']);
                $dif_date_end.children('.m').html(array_response['new_date']['date_month_end']);

                if (!isStringEquals(array_response['new_date']['date_hours_end'], '0:00')) {
                    $dif_date_end.children('.h').html(array_response['new_date']['date_hours_end']);
                } else {
                    $dif_date_end.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
                }

            }
        }
    }


    // On affiche la description et le lieu de l'evt
    let $location = $event_template_html.find('.location');
    let $description = $event_template_html.find('.description');
    let $div_location_description = $event_template_html.find('.location_description');
    if (array_response['description']) {
        $div_location_description.show();
        $description.show();
        $description.append(array_response['description']);
    }
    if (array_response['location']) {
        $div_location_description.show();
        $location.show();
        $location.append(array_response['location']);
    }

    if (isACounter) {
        let $new_location = $event_template_html.find('.if_new_location');
        let $new_description = $event_template_html.find('.if_new_description');
        if (array_response['new_location']) {
            $div_location_description.show();
            $new_location.show();
            $new_location.append(array_response['new_location'])
        }
        if (array_response['new_description']) {
            $div_location_description.show();
            $new_description.show();
            $new_description.append(array_response['new_description'])
        }
    }


    // On regarde s'il s'agit d'un evt reccurent
    let recurrent_event = array_response['recurrent_events'][used_event['uid']];
    if (recurrent_event.length > 1) {
        // on affiche seulement les dix premier evt si il yen a plus
        for (let i = 0; i < 10; i++) {
            if (recurrent_event[i]) {
                $event_template_html.find('.repeated').append(recurrent_event[i] + '<br>');
            } else {
                break;
            }

            if (i == 9) {
                $event_template_html.find('.repeated').append('...');
            }
        }
    } else {
        $event_template_html.find('.repeated').hide();
    }

    // On affiche les boutons de réponse aux autres participants..
    if (array_response['attendees'] !== undefined) {
        for (let attendee of array_response['attendees']) {
            let display = attendee['name'] ? attendee['name'] : attendee["email"];
            let link = `<a href='mailto` + attendee["email"] + `' aria-haspopup="false" onClick="` + attendee["onclick"]
                + `">` + display + `</a><br>`;
            $event_template_html.find('.attendee_link').append(link);
        }

        let attribut = array_response['attr_reply_all'];
        let link = `<a href='` + attribut["href"] + `' aria-haspopup="false" onClick="` + attribut["onclick"] + `">`
            + rcmail.gettext('reply_all', 'roundcube_caldav') + `</a><br>`;
        $event_template_html.find('.reply_all').append(link);
    } else {
        $event_template_html.find('.attendee').hide();
    }


    // On regarde les evt en colision / avant / apres
    let prev = array_response['display_caldav_info']['close_meeting']['previous'];
    let next = array_response['display_caldav_info']['close_meeting']['next'];
    let collisions = array_response['display_caldav_info']['collision'];
    if (prev || next || collisions.length > 0) {
        if (prev) {
            let summary = prev['summary'];
            if (!prev['summary']) {
                summary = rcmail.gettext('missing_summary', 'roundcube_caldav');
            }
            $event_template_html.find('.previous').append(summary + ': ' + '<i>' + '('
                + prev['calendar'] + ')' + '</i><br>' + prev['pretty_date']);
        } else {
            $event_template_html.find('.previous').hide();
        }

        if (next) {
            let summary = next['summary'];
            if (!next['summary']) {
                summary = rcmail.gettext('missing_summary', 'roundcube_caldav');
            }
            $event_template_html.find('.next').append(summary + ': ' + '<i>' + '('
                + next['calendar'] + ')' + '</i><br>' + next['pretty_date']);
        } else {
            $event_template_html.find('.next').hide();
        }

        if (collisions !== undefined) {
            let bool = false;
            for (let calendar_name in collisions) {
                let collided_events = collisions[calendar_name];

                for (let collided in collided_events) {
                    let collided_event = collided_events[collided];
                    let display_date = pretty_date(parse_date(collided_event['dtstart'], collided_event['dtend']));
                    bool = true;
                    let summary = collided_event['summary'];
                    if (!collided_event['summary']) {
                        summary = rcmail.gettext('missing_summary', 'roundcube_caldav');
                    }
                    $event_template_html.find('.meeting_collision').append(summary + ':  ' + display_date + ' <i>' + '('
                        + calendar_name + ')' + '</i><br>');
                }

            }
            if (!bool) {
                $event_template_html.find('.meeting_collision').hide();
            }
        } else {
            $event_template_html.find('.meeting_collision').hide();
        }
    } else {
        $event_template_html.find('.info_caldav_server').hide();
    }


    // On récupère la valeur du champs select ou l'on selectionne les calendriers
    let $calendar_choice = $event_template_html.find('.calendar_choice');
    let select = $event_template_html.find('.choose_calendar_to_add_event');
    if (!isOrganizer || (isACounter && !array_response['found_advance'])) {
        if (!isOrganizer) {
            $calendar_choice.prepend(rcmail.gettext('choose_calendar_to_add', 'roundcube_caldav'));
        } else if (isACounter && !array_response['found_advance']) {
            $calendar_choice.prepend(rcmail.gettext('event_not_found_which_calendar_to_add', 'roundcube_caldav'));
        }
        for (let [calendar_id, calendar_name] of Object.entries(array_response['used_calendar'])) {
            let selected = isStringEquals(calendar_name, array_response['main_calendar_name']) ? 'selected' : '';
            select.append(
                `<option value="` + calendar_id + `"` + selected + '>' + calendar_name + '</option>'
            );
        }
    } else {
        $calendar_choice.hide();
    }


    // On rajoute les dates des anciens evt comme valeur par défaut dans les inputs
    let date = parse_date(used_event['dtstart_array'][1], used_event['dtend_array'][1])
    $event_template_html.find('.date_start').attr("value", date[0]['year'] + '-' + date[0]['month'] + '-' + date[0]['day']);
    $event_template_html.find('.date_end').attr("value", date[1]['year'] + '-' + date[1]['month'] + '-' + date[1]['day']);

    var $div_to_add = $event_template_html.find('.if_rescheduled'),
        $location_input = $event_template_html.find('.location_input'),
        $date_start = $event_template_html.find('.date_start'),
        $time_start = $event_template_html.find('.time_start'),
        $date_end = $event_template_html.find('.date_end'),
        $time_end = $event_template_html.find('.time_end');

    /**
     * Lorsque l'utilisateur décide de reprogrammer l'évenement,
     * on verifie que les date sont valide et toutes remplies et on affiche un balise html pour indiquer à l'utilisateur
     * les informations modifiées avant l'ajout dans son calendrier
     * @returns {number}
     */
    function changeDateAndLocation() {

        let areFieldsFilled = false;


        // Si tous les champs dates sont remplis
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

                if (isStringEquals(chosenDateStart, chosenDateEnd)) {
                    $event_template_html.find(".msg_date").remove();
                    $div_to_add.append('<p class="msg_date" >' + chosenDateStart + ' ' + chosenTimeStart + ' - '
                        + chosenTimeEnd + '</p>');
                } else {
                    $event_template_html.find(".msg_date").remove();
                    $div_to_add.append('<p class="msg_date">' + chosenDateStart + ' ' + chosenTimeStart + ' / '
                        + chosenDateEnd + ' ' + chosenTimeEnd + '</p>');
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
            $div_to_add.append('<p class="msg_location">' + rcmail.gettext('location', 'roundcube_caldav') + $location_input.val() + '</p>');
        }
        // Si au moins un des deux est rempli
        if (areFieldsFilled) {
            $div_to_add.show();
            dialog.dialog("close");
            // Si les informations ont correctement été remplies on peut fermer la boite de dialogue
            tentative.attr('disabled', 'true');
            tentative.html(rcmail.gettext('tentatived', 'roundcube_caldav'));
            confirm.removeAttr('disabled');
            confirm.html(rcmail.gettext('confirm', 'roundcube_caldav'));
            decline.removeAttr('disabled');
            decline.html(rcmail.gettext('decline', 'roundcube_caldav'));


            // On demande au serveur d'enregistrer notre changement sur le serveur avec le status provisoire
            rcmail.http_post('plugin.roundcube_caldav_import_event_on_server', {
                _mail_uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _calendar: isOrganizer ? array_response['found_on_calendar']['calendar_id'] : select.val(),
                _status: isOrganizer ? 'CONFIRMED' : 'TENTATIVE',
                _role: array_response['identity']['role'],
                _event_uid: used_event['uid'],
                _chosenDateStart: chosenDateStart,
                _chosenDateEnd: chosenDateEnd,
                _chosenTimeStart: chosenTimeStart,
                _chosenTimeEnd: chosenTimeEnd,
                _chosenLocation: chosenLocation,
            });
        } else {
            window.alert(rcmail.gettext('error_incomplete_field', 'roundcube_caldav'));
        }

    }

    // Spécification des propriétés de la popup de dialogue
    let dialog = $event_template_html.find(".dialog-form").dialog({
        autoOpen: false,
        height: 'auto',
        width: 350,
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
    let form = dialog.find("form").on("submit", function (event) {
        event.preventDefault();
        changeDateAndLocation();
    });


    $event_template_html.find(".open_dialog").button().on("click", function () {
        $event_template_html.find(".form_reschedule").show();
        dialog.dialog("open");
    });

    let confirm = $event_template_html.find('.confirm_button');
    let tentative = $event_template_html.find('.tentative_button');
    let decline = $event_template_html.find('.decline_button');
    let confirm_organizer = $event_template_html.find('.confirm_button_organizer');
    let decline_organizer = $event_template_html.find('.decline_button_organizer');

    if (!isOrganizer) {
        // On cache les boutons destinés à l'organisateur
        confirm_organizer.hide();
        decline_organizer.hide();
        // On récupère les boutons

        if (confirm) {
            // Lors d'un clic sur le bouton 'confirm' on envoie au serveur les informations nécessaires pour l'ajout au calendrier
            // Et on modifie le texte dans les boutons
            confirm.bind('click', function evt() {

                rcmail.http_post('plugin.roundcube_caldav_import_event_on_server', {
                    _mail_uid: rcmail.env.uid,
                    _mbox: rcmail.env.mailbox,
                    _calendar: select.val(),
                    _event_uid: used_event['uid'],
                    _status: 'CONFIRMED',
                    _role: array_response['identity']['role'],
                });
                confirm.attr('disabled', 'true');
                confirm.html(rcmail.gettext('confirmed', 'roundcube_caldav'));
                tentative.removeAttr('disabled');
                tentative.html(rcmail.gettext('tentative', 'roundcube_caldav'));
                decline.removeAttr('disabled');
                decline.html(rcmail.gettext('decline', 'roundcube_caldav'));
            });
        }
        if (tentative) {
            tentative.bind('click', function evt() {
                rcmail.http_post('plugin.roundcube_caldav_import_event_on_server', {
                    _mail_uid: rcmail.env.uid,
                    _mbox: rcmail.env.mailbox,
                    _calendar: select.val(),
                    _status: 'TENTATIVE',
                    _role: array_response['identity']['role'],
                    _event_uid: used_event['uid'],
                });
                tentative.attr('disabled', 'true');
                tentative.html(rcmail.gettext('tentatived', 'roundcube_caldav'));
                confirm.removeAttr('disabled');
                confirm.html(rcmail.gettext('confirm', 'roundcube_caldav'));
                decline.removeAttr('disabled');
                decline.html(rcmail.gettext('decline', 'roundcube_caldav'));
            });
        }
        if (decline) {
            decline.bind('click', function evt() {

                rcmail.http_post('plugin.roundcube_caldav_import_event_on_server', {
                    _mail_uid: rcmail.env.uid,
                    _mbox: rcmail.env.mailbox,
                    _calendar: select.val(),
                    _status: 'CANCELLED',
                    _role: array_response['identity']['role'],
                    _event_uid: used_event['uid'],
                });
                decline.attr('disabled', 'true');
                decline.html(rcmail.gettext('declined', 'roundcube_caldav'));
                confirm.removeAttr('disabled');
                confirm.html(rcmail.gettext('confirm', 'roundcube_caldav'));
                tentative.removeAttr('disabled');
                tentative.html(rcmail.gettext('tentative', 'roundcube_caldav'));
            });
        }

    } else if (isACounter) {
        // On cache les boutons destinés aux participants normaux
        confirm.hide();
        tentative.hide();
        decline.hide();
        // On récupère les boutons

        if (confirm_organizer) {
            // Lors d'un clic sur le bouton 'confirm' on envoie au serveur les informations nécessaires pour l'ajout au calendrier
            // Et on modifie le texte dans les boutons
            confirm_organizer.bind('click', function evt() {
                rcmail.http_post('plugin.roundcube_caldav_import_event_on_server', {
                    _mail_uid: rcmail.env.uid,
                    _mbox: rcmail.env.mailbox,
                    _calendar: array_response['found_on_calendar']['calendar_id'],
                    _event_uid: used_event['uid'],
                    _status: 'CONFIRMED',
                    _role: array_response['identity']['role'],
                });
                confirm_organizer.attr('disabled', 'true');
                confirm_organizer.html(rcmail.gettext('confirmed_organizer', 'roundcube_caldav'));
                decline_organizer.removeAttr('disabled');
                decline_organizer.html(rcmail.gettext('decline_modification', 'roundcube_caldav'));
            });
        }
        if (decline_organizer) {
            decline_organizer.bind('click', function evt() {
                rcmail.http_post('plugin.roundcube_caldav_decline_counter', {
                    _mail_uid: rcmail.env.uid,
                    _mbox: rcmail.env.mailbox,
                    _event_uid: used_event['uid'],
                });
                decline_organizer.attr('disabled', 'true');
                decline_organizer.html(rcmail.gettext('declined_organizer', 'roundcube_caldav'));
                confirm_organizer.removeAttr('disabled');
                confirm_organizer.html(rcmail.gettext('confirm_modification', 'roundcube_caldav'));
            });
        }
    } else if (isAReply || isARequest || isADeclineCounter) {
        confirm.hide();
        tentative.hide();
        decline.hide();
        confirm_organizer.hide();
        decline_organizer.hide();
    }


    $("#message-objects").append($event_template_html);
}


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


    // Fonction de debug A SUPPRIMER
    rcmail.addEventListener('plugin.affichage', affichage);
});


