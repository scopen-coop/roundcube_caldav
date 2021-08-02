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


rcmail.addEventListener('init', function (evt) {
    $(".conteneur").each(function (index) {
        const $event = $(this);
        let dialog, form,
            chosenDateStart = null,
            chosenDateEnd = null,
            chosenTimeStart = null,
            chosenTimeEnd = null,
            chosenLocation = null,
            $current_uid = $event.find($('.uid')).html();

        // On récupère le calendrier choisi dans le menu select
        let calendar = $('#choose_calendar_to_add_event').val();


        rcmail.addEventListener('plugin.affichage', affichage);
        // On demande au serveur le status de l'event si on en a un semblable sur notre serveur
        rcmail.http_post('plugin.get_status', {
            _uid: rcmail.env.uid,
            _mbox: rcmail.env.mailbox,
            _calendar: calendar,
        });

        // On récupère la réponse au post çi dessus
        rcmail.addEventListener('plugin.change_status', change_status)


        let confirm = $event.find($('.confirm_button'));
        let tentative = $event.find($('.tentative_button'));
        let decline = $event.find($('.decline_button'));

        if (confirm) {
            // Lors d'un clic sur le bouton 'confirm' on envoie au serveur les information necessaire pour l'ajout au calendrier
            // Et on modifie le texte dans les boutons
            confirm.bind('click', function evt() {
                rcmail.http_post('plugin.import_action', {
                    _mail_uid: rcmail.env.uid,
                    _mbox: rcmail.env.mailbox,
                    _calendar: calendar,
                    _event_uid: $current_uid,
                    _type: 'CONFIRMED',
                    _chosenDateStart: chosenDateStart,
                    _chosenDateEnd: chosenDateEnd,
                    _chosenTimeStart: chosenTimeStart,
                    _chosenTimeEnd: chosenTimeEnd,
                    _chosenLocation: chosenLocation,
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
                    _calendar: calendar,
                    _type: 'TENTATIVE',
                    _event_uid: $current_uid,
                    _chosenDateStart: chosenDateStart,
                    _chosenDateEnd: chosenDateEnd,
                    _chosenTimeStart: chosenTimeStart,
                    _chosenTimeEnd: chosenTimeEnd,
                    _chosenLocation: chosenLocation,
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
                    _calendar: calendar,
                    _type: 'CANCELLED',
                    _event_uid: $current_uid,
                    _chosenDateStart: chosenDateStart,
                    _chosenDateEnd: chosenDateEnd,
                    _chosenTimeStart: chosenTimeStart,
                    _chosenTimeEnd: chosenTimeEnd,
                    _chosenLocation: chosenLocation,

                });
                decline.attr('disabled', 'true');
                decline.html(rcmail.gettext('declined', 'roundcube_caldav'));
                confirm.removeAttr('disabled');
                confirm.html(rcmail.gettext('confirm', 'roundcube_caldav'));
                tentative.removeAttr('disabled');
                tentative.html(rcmail.gettext('tentative', 'roundcube_caldav'));
            });
        }


        // A modifier pas propre
        let $location = $event.find($('.location')),
            $date_start = $event.find($('.date_start')),
            $time_start = $event.find($('.time_start')),
            $date_end = $event.find($('.date_end')),
            $time_end = $event.find($('.time_end')),
            $divtoadd = $event.find($('.if_rescheduled'));

        /**
         * Lorsque l'utilisateur décide de reprogrammer l'évenement,
         * on verifie que les date sont valide et toutes remplies et on affiche un balise html pour indiquer à l'utlisateur
         * les informations modifiées avant l'ajout dans son calendrier
         * @returns {number}
         */
        function changeDateAndLocation() {
            let areFieldsFilled = false;


            if ($date_start.val() && $date_end.val() && $time_start.val() && $time_end.val()) {

                chosenDateStart = $date_start.val();
                chosenDateEnd = $date_end.val();
                chosenTimeStart = $time_start.val();
                chosenTimeEnd = $time_end.val();

                let datestr = new Date(chosenDateStart + ' ' + chosenTimeStart).getTime();
                let dateend = new Date(chosenDateEnd + ' ' + chosenTimeEnd).getTime();

                if (dateend > datestr) {
                    areFieldsFilled = true;
                    if (isEqual(chosenDateStart, chosenDateEnd)) {
                        $event.find($(".msg_date")).remove();
                        $divtoadd.append('<p class="msg_date" >' + chosenDateStart + ' ' + chosenTimeStart + ' - ' + chosenTimeEnd + '</p>');
                    } else {
                        $event.find($(".msg_date")).remove();
                        $divtoadd.append('<p class="msg_date">' + chosenDateStart + ' ' + chosenTimeStart + ' / ' + chosenDateEnd + ' ' + chosenTimeEnd + '</p>');
                    }
                } else {
                    window.alert(rcmail.gettext('error_date_inf', 'roundcube_caldav'));
                    return;
                }
            }
            if ($location.val()) {
                areFieldsFilled = true;
                chosenLocation = $location.val();
                $event.find($(".msg_location")).remove();
                $divtoadd.append('<p class="msg_location">' + rcmail.gettext('location', 'roundcube_caldav') + $location.val() + '</p>');
            }
            if (areFieldsFilled) {
                $divtoadd.show();
                // Si les informations ont correctement été remplies on peut fermer la boite de dialogue
                dialog.dialog("close");

            } else {
                window.alert(rcmail.gettext('error_incomplete_field', 'roundcube_caldav'));
                return 0;
            }
            return 0;
        }


        // Spécification des propriétés de la popup de dialogue
        dialog = $(this).find($(".dialog-form")).dialog({
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

        form = dialog.find("form").on("submit", function (event) {
            event.preventDefault();
            changeDateAndLocation();
        });

        $(this).find($(".open_dialog")).button().on("click", function () {
            dialog.dialog("open");
        });
    })


    /**
     * Récupère le status de l'événement si l'utilisateur le possede déja dans son calendrier
     * @param response
     */
    function change_status(response) {
        let status = response.request;
        if (isEqual(status, 'CONFIRMED')) {
            let confirm = $(this).find($('.confirm_button'));
            if (confirm) {
                confirm.attr('disabled', true);
                confirm.html(rcmail.gettext('confirmed', 'roundcube_caldav'));
            }
        } else if (isEqual(status, 'TENTATIVE')) {
            let tentative = $(this).find($('.tentative_button'));
            if (tentative) {
                tentative.attr('disabled', true);
                tentative.html(rcmail.gettext('tentatived', 'roundcube_caldav'));
            }
        } else if (isEqual(status, 'CANCELLED')) {
            let decline = $(this).find($('.decline_button'));
            if (decline) {
                decline.attr('disabled', true);
                decline.html(rcmail.gettext('declined', 'roundcube_caldav'));

            }
        }
    }

});