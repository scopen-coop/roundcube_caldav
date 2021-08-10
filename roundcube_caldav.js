var $event;

function affichage(response) {
    console.log(response.request)
}

/**
 * teste légalité de deux string
 * @param str1
 * @param str2
 * @returns {boolean}
 */
function isEqual(str1, str2) {
    return str1.toUpperCase() === str2.toUpperCase()
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
        if (isEqual(date[0]['day'], date[1]['day']) && isEqual(date[0]['month'], date[1]['month']) && isEqual(date[0]['year'], date[1]['year'])) {
            display_date = date[0]['day'] + '/' + date[0]['month'] + '/' + date[0]['year'];
        } else {
            display_date = date[0]['day'] + '/' + date[0]['month'] + '/' + date[0]['year'] +
                ' - ' + date[1]['day'] + '/' + date[1]['month'] + '/' + date[1]['year'];
        }
    } else {
        if (isEqual(date[0]['day'], date[1]['day']) && isEqual(date[0]['month'], date[1]['month']) && isEqual(date[0]['year'], date[1]['year'])) {
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
function change_status(status) {
    if (isEqual(status, 'CONFIRMED')) {
        let confirm = $event.find($('.confirm_button'));
        if (confirm) {
            confirm.attr('disabled', true);
            confirm.html(rcmail.gettext('confirmed', 'roundcube_caldav'));
        }
    } else if (isEqual(status, 'TENTATIVE')) {
        let tentative = $event.find($('.tentative_button'));
        if (tentative) {
            tentative.attr('disabled', true);
            tentative.html(rcmail.gettext('tentatived', 'roundcube_caldav'));
        }
    } else if (isEqual(status, 'CANCELLED')) {
        let decline = $event.find($('.decline_button'));
        if (decline) {
            decline.attr('disabled', true);
            decline.html(rcmail.gettext('declined', 'roundcube_caldav'));

        }
    }
}

function undirect_rendering(response) {

    // On recupère la réponse du serveur
    let array_response = response.request;

    // On copie le template html et l'on cache la partie chargement...
    let template = $event.find($('.display')).html()
    $event.html(template);
    $("#loading").hide();

    // On récupère l'evt
    let used_event = array_response['used_event'];

    // On affiche le titre
    $event.find($('.invitation').children('.summary')).html(used_event['summary']);

    // On regarde si le serveur est en avance
    if (!array_response['found_advance']) {
        $event.find($('.found_advance')).hide();
    } else {
        $event.find($('.found_advance')).html(rcmail.gettext('modified_event', 'roundcube_caldav') + array_response['found_on_calendar']);
        change_status(array_response['found_advance'][1].status);
    }



    // On affiche la date
    if (array_response['same_date']) {
        $event.find($('.different_date')).hide();
        let $same_date = $event.find($('.same_date'));
        $same_date.children('.d').html(array_response['date_day_start']);
        $same_date.children('.m').html(array_response['date_month_start']);
        $same_date.children('.h').html(array_response['date_hours_start'] + ' : ' + array_response['date_hours_end']);
    } else {
        $event.find($('.same_date')).hide();
        let $dif_date_start = $event.find($('.different_dat,.start'));
        $dif_date_start.children('.d').html(array_response['date_day_start']);
        $dif_date_start.children('.m').html(array_response['date_month_start']);
        $dif_date_start.children('.h').html(array_response['date_hours_start']);

        let $dif_date_end = $event.find($('.different_date,.end'));
        $dif_date_end.children('.d').html(array_response['date_day_end']);
        $dif_date_end.children('.m').html(array_response['date_month_end']);
        $dif_date_end.children('.h').html(array_response['date_hours_end']);
    }

    // On affiche la description et le lieu de l'evt
    let $location = $event.find($('.location'));
    if (!used_event['description'] && !used_event['location']) {
        $event.find($('.location_description')).hide();
    } else {
        if (!used_event['description']) {
            $event.find($('.description')).hide();
            $location.append(used_event['location']);
        } else {
            $location.hide();
            $event.find($('.description')).append(used_event['description']);
        }
    }

    // On regarde s'il s'agit d'un evt reccurent
    let reccurent_event = array_response['recurrent_events'][used_event['uid']];
    if (reccurent_event.length > 1) {
        for (let pretty_date_evt of reccurent_event) {
            $event.find($('.repeated')).append(pretty_date_evt + '<br>');
        }
    } else {
        $event.find($('.repeated')).hide();
    }

    // On affiche les boutons de réponse aux autres participants..
    if (array_response['attendee'] !== undefined) {
        for (let attendee of array_response['attendee']) {
            let link = `<a href='mailto` + attendee["email"] + `' aria-haspopup="false" onClick="` + attendee["onclick"]
                + `">` + attendee['name'] + `</a><br>`;
            $event.find($('.attendee_link')).append(link);

        }

        let attribut = array_response['attr_reply_all'];
        let link = `<a href='` + attribut["href"] + `' aria-haspopup="false" onClick="` + attribut["onclick"] + `">`
            + rcmail.gettext('reply_all', 'roundcube_caldav') + `</a><br>`;
        $event.find($('.reply_all')).append(link);
    } else {
        $event.find($('.attendee')).hide();
    }

    // On regarde les evt en colision / avant / apres
    let prev = array_response['display_caldav_info']['close_meeting']['previous'];
    let next = array_response['display_caldav_info']['close_meeting']['next'];
    let collisions = array_response['display_caldav_info']['collision'];
    if (prev || next || collisions) {
        if (prev) {
            $event.find($('.previous')).append(prev['summary'] + ': ' + '<i>' + '('
                + prev['calendar'] + ')' + '</i><br>' + prev['pretty_date']);
        } else {
            $event.find($('.previous')).hide();
        }

        if (next) {
            $event.find($('.next')).append(next['summary'] + ': ' + '<i>' + '('
                + next['calendar'] + ')' + '</i><br>' + next['pretty_date']);
        } else {
            $event.find($('.next')).hide();
        }

        if (collisions !== undefined) {
            let bool = false;
            for (let calendar_name in collisions) {
                let collided_events = collisions[calendar_name];

                for (let collided in collided_events) {
                    let collided_event = collided_events[collided];
                    let display_date = pretty_date(parse_date(collided_event['dtstart'], collided_event['dtend']));
                    bool = true;
                    $event.find($('.meeting_collision')).append(collided_event['summary'] + ':  ' + display_date + ' <i>' + '('
                        + calendar_name + ')' + '</i><br>');
                }

            }
            if (!bool) {
                $event.find($('.meeting_collision')).hide();
            }
        } else {
            $event.find($('.meeting_collision')).hide();
        }
    } else {
        $event.find($('.info_caldav_server')).hide();
    }

    // On récupère la valeur du champs select ou l'on selectionne les calendriers
    let select = $event.find($('.choose_calendar_to_add_event'));
    for (let calendar in array_response['used_calendar']) {
        let selected = isEqual(calendar, array_response['main_calendar']) ? 'selected' : '';
        select.append(
            `<option value="` + calendar + `"` + selected + '>' + calendar + '</option>'
        );
    }

    let $date_start = $event.find($('.date_start')),
        $time_start = $event.find($('.time_start')),
        $date_end = $event.find($('.date_end')),
        $time_end = $event.find($('.time_end'));

    // On rajoute les dates des anciens evt comme valeur par défaut dans les inputs
    let date = parse_date(used_event['dtstart_array'][1], used_event['dtend_array'][1])
    $date_start.attr("value", date[0]['year'] + '-' + date[0]['month'] + '-' + date[0]['day']);
    $date_end.attr("value", date[1]['year'] + '-' + date[1]['month'] + '-' + date[1]['day']);

    //
    let $div_to_add = $event.find($('.if_rescheduled'));
    let $location_input = $event.find($('.location_input'));




    // Spécification des propriétés de la popup de dialogue
    let dialog = $event.find($(".dialog-form")).dialog({
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

    $event.find($(".open_dialog")).button().on("click", function () {
        $event.find($(".form_reschedule")).show();
        dialog.dialog("open");
    });

    // On récupère les boutons
    let confirm = $event.find($('.confirm_button'));
    let tentative = $event.find($('.tentative_button'));
    let decline = $event.find($('.decline_button'));
    if (confirm) {
        // Lors d'un clic sur le bouton 'confirm' on envoie au serveur les informations nécessaires pour l'ajout au calendrier
        // Et on modifie le texte dans les boutons
        confirm.bind('click', function evt() {

            rcmail.http_post('plugin.import_action', {
                _mail_uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _calendar: select.val(),
                _event_uid: used_event['uid'],
                _type: 'CONFIRMED',
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
            rcmail.http_post('plugin.import_action', {
                _mail_uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _calendar: select.val(),
                _type: 'TENTATIVE',
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

            rcmail.http_post('plugin.import_action', {
                _mail_uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _calendar: select.val(),
                _type: 'CANCELLED',
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

                if (isEqual(chosenDateStart, chosenDateEnd)) {
                    $event.find($(".msg_date")).remove();
                    $div_to_add.append('<p class="msg_date" >' + chosenDateStart + ' ' + chosenTimeStart + ' - '
                        + chosenTimeEnd + '</p>');
                } else {
                    $event.find($(".msg_date")).remove();
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
            $event.find($(".msg_location")).remove();
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
            rcmail.http_post('plugin.import_action', {
                _mail_uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _calendar: select.val(),
                _type: 'TENTATIVE',
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
}


rcmail.addEventListener('init', function (evt) {


    $(".conteneur").each(function (index) {
        // On initialise la variable $event qui représente la balise mère de toute le template html que l'on va utiliser
        // Dans la cas ou il y a plusieurs événements on aura donc plusieurs affichages différents.
        $event = $(this);

        // On demande les informations au serveur concernant ce mail
        rcmail.http_post('plugin.get_info_server', {
            _uid: rcmail.env.uid,
            _mbox: rcmail.env.mailbox,
        });

        // On récupère toutes ces information et on affiche la bannière
        rcmail.addEventListener('plugin.undirect_rendering_js', undirect_rendering);

        // Fonction de debug A SUPPRIMER
        rcmail.addEventListener('plugin.affichage', affichage);

    })
});


