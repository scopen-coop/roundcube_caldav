class Display {
    constructor($event_template_html, array_response) {
        this.event_template_html = $event_template_html;
        this.array_response = array_response;
    }


    /**
     * Display a message to notify the user of changes
     */
    display_modification_message() {
        let isACounter = this.array_response['METHOD'] === 'COUNTER';
        let $modifications = this.event_template_html.find('.if_modification');
        if (this.array_response['new_description'] || this.array_response['new_location'] || this.array_response['new_date']) {
            $modifications.html(rcmail.gettext('if_modification', 'roundcube_caldav'));
            $modifications.show();
        } // Si la methode est un counter mais que l'on a deja effectué les changements,
        else if (isACounter && this.array_response['found_older_event_on_calendar']) {
            $modifications.html(rcmail.gettext('if_no_modification', 'roundcube_caldav'));
            $modifications.show();
        }
    }

    /**
     * Display indicating to the user if the event he is looking at, is the one he received or a more recent one
     */
    display_message_if_event_is_already_on_server() {
        let $event_found_on_server = this.event_template_html.find('.is_on_server');

        if (this.array_response['found_advance']) {
            if (!this.array_response['is_sequences_equal']) {
                $event_found_on_server.html(rcmail.gettext('modified_event', 'roundcube_caldav') + this.array_response['found_on_calendar']['display_name']);
            }
            this.change_status(this.array_response['used_event'].status);
        } else if (this.array_response['found_older_event_on_calendar']) {
            $event_found_on_server.html(rcmail.gettext('present_but_older', 'roundcube_caldav'));
        } else {
            $event_found_on_server.hide();
        }
    }

    /**
     * Display date
     */
    display_date() {
        if (this.array_response['same_date']) {
            this.event_template_html.find('.different_date').hide();
            let $same_date = this.event_template_html.find('.same_date');
            $same_date.children('.d').html(this.array_response['date_day_start']);
            $same_date.children('.m').html(this.array_response['date_month_start']);
            if (this.array_response['date_hours_start'] !== '0:00' || this.array_response['date_hours_end'] != '0:00') {
                $same_date.children('.h').html(this.array_response['date_hours_start'] + ' : ' + this.array_response['date_hours_end']);
            } else {
                $same_date.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
            }
        } else {
            this.event_template_html.find('.same_date').hide();
            let $dif_date_start = this.event_template_html.find('.different_date.start');
            $dif_date_start.children('.d').html(this.array_response['date_day_start']);
            $dif_date_start.children('.m').html(this.array_response['date_month_start']);
            if (this.array_response['date_hours_start'] !== '0:00') {
                $dif_date_start.children('.h').html(this.array_response['date_hours_start']);
            } else {
                $dif_date_start.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
            }


            let $dif_date_end = this.event_template_html.find('.different_date.end');
            $dif_date_end.children('.d').html(this.array_response['date_day_end']);
            $dif_date_end.children('.m').html(this.array_response['date_month_end']);
            if (this.array_response['date_hours_end'] !== '0:00') {
                $dif_date_end.children('.h').html(this.array_response['date_hours_end']);
            } else {
                $dif_date_end.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
            }

        }
    }

    /**
     * Display modified date
     */
    display_modified_date() {
        if (this.array_response['new_date']) {
            // On affiche la nouvelle  date
            let $new_date = this.event_template_html.find('.new_date_event');
            $new_date.show();
            if (this.array_response['new_date']['same_date']) {
                this.event_template_html.find('.arrow-right.new').show();
                $new_date.children('.different_date').hide();
                let $same_date = $new_date.children('.same_date');
                $same_date.show()
                $same_date.children('.d').html(this.array_response['new_date']['date_day_start']);
                $same_date.children('.m').html(this.array_response['new_date']['date_month_start']);
                if (this.array_response['new_date']['date_hours_start'] !== '0:00' || this.array_response['new_date']['date_hours_end'] !== '0:00') {
                    $same_date.children('.h').html(this.array_response['new_date']['date_hours_start'] + ' : ' + this.array_response['new_date']['date_hours_end']);
                } else {
                    $same_date.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
                }
            } else {
                this.event_template_html.find('.date_container').css("flex-direction", "column")
                this.event_template_html.find('.arrow-down.new').show();
                $new_date.css("display", "flex");
                $new_date.css("flex-direction", "raw");
                $new_date.children('.same_date').hide();
                let $dif_date_start = $new_date.children('.different_date.start');
                $dif_date_start.show();
                $dif_date_start.children('.d').html(this.array_response['new_date']['date_day_start']);
                $dif_date_start.children('.m').html(this.array_response['new_date']['date_month_start']);
                if (this.array_response['new_date']['date_hours_start'] !== '0:00') {
                    $dif_date_start.children('.h').html(this.array_response['new_date']['date_hours_start']);
                } else {
                    $dif_date_start.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
                }


                $new_date.children('.different_date.arrow-right').show();
                let $dif_date_end = $new_date.children('.different_date.end');
                $dif_date_end.show();
                $dif_date_end.children('.d').html(this.array_response['new_date']['date_day_end']);
                $dif_date_end.children('.m').html(this.array_response['new_date']['date_month_end']);

                if (this.array_response['new_date']['date_hours_end'] !== '0:00') {
                    $dif_date_end.children('.h').html(this.array_response['new_date']['date_hours_end']);
                } else {
                    $dif_date_end.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
                }
            }
        }
    }

    /**
     * Display Location and description of the event
     */
    display_location_and_description() {
        let display_location = true,
            display_description = true;
        if (this.array_response['display_modification_made_by_organizer']) {
            if (this.array_response['new_location']) {
                display_location = false;
            }
            if (this.array_response['new_description']) {
                display_description = false;
            }
        }
        let $location = this.event_template_html.find('.location');
        let $description = this.event_template_html.find('.description');
        let $div_location_description = this.event_template_html.find('.location_description');
        if (this.array_response['description'] && display_description) {
            $div_location_description.show();
            $description.show();
            $description.append(this.array_response['description']);
        }
        if (this.array_response['location'] && display_location) {
            $div_location_description.show();
            $location.show();
            $location.append(this.array_response['location']);
        }
    }

    /**
     * Display modified Location and Description of the event
     */
    display_modified_location_and_description() {
        let $div_location_description = this.event_template_html.find('.location_description');
        let $new_location = this.event_template_html.find('.if_new_location');
        let $new_description = this.event_template_html.find('.if_new_description');

        if (this.array_response.identity && this.array_response['identity']['role'] === 'ORGANIZER') {
            $new_location.html($new_location.attr('isOrganizer'));
            $new_description.html($new_description.attr('isOrganizer'));
        }

        if (this.array_response['new_location']) {
            $div_location_description.show();
            $new_location.show();
            $new_location.append(this.array_response['new_location'])
        }
        if (this.array_response['new_description']) {
            $div_location_description.show();
            $new_description.show();
            $new_description.append(this.array_response['new_description'])
        }
    }

    /**
     * Display sender's comment
     */
    display_comment() {

        if (this.array_response['comment']) {
            let $comment = this.event_template_html.find('.comment');
            $comment.show();

            $comment.append('<div><b>' + rcmail.gettext("comment", "roundcube_caldav") + '</b></div>');
            $comment.append('<div>' + this.array_response['comment'] + '</div>');
        }
    }


    /**
     * Display link to reply to participants, and a link to reply to everyone
     */
    display_attendee() {
        FontAwesome.dom.watch({
            autoReplaceSvgRoot: document,
            observeMutationsRoot: document.body
        })
        if (this.array_response['attendees'] !== undefined) {
            for (let attendee of this.array_response['attendees']) {
                let display = attendee['name'] ? attendee['name'] : attendee["email"];
                let status;

                switch (attendee['partstat']) {
                    case 'ACCEPTED':
                        // status = '<i className="fas fa-check-circle"></i>';
                        status = '  [OK]';
                        break;
                    case 'TENTATIVE':
                        // status = '<i className="fas fa-question-circle"></i>';
                        status = '  [TC]';
                        break;
                    case 'DECLINED':
                        // status = '<i className="fas fa-times-circle"></i>';
                        status = '  [KO]';
                        break;
                    case 'NEEDS_ACTION':
                    case 'NEEDS-ACTION':
                        // status = '<i className="fas fa-times-circle"></i>';
                        status = '  [?]';
                        break;
                    case 'ORGANIZER':
                        // status = '<i className="fas fa-times-circle"></i>';
                        status = '  [ORG]';
                        break;
                    default:
                            status = '';
                }

                let link = `<a href='mailto:` + attendee["email"] + `' aria-haspopup="false" onClick="` + attendee["onclick"]
                    + `">` + display + `</a>` + status + `<br>`;
                this.event_template_html.find('.attendee_link').append(link);
            }

            let attribut = this.array_response['attr_reply_all'];
            let link = `<a href='reply_all' aria-haspopup="false" onClick="` + attribut["onclick"] + `">`
                + rcmail.gettext('reply_all', 'roundcube_caldav') + `</a><br>`;
            this.event_template_html.find('.reply_all').append(link);
        } else {
            this.event_template_html.find('.attendee').hide();
        }
    }


    /**
     * Display of nearby events (before, during, after)
     */
    display_close_events() {
        let prev = this.array_response['display_caldav_info']['close_meeting']['previous'];
        let next = this.array_response['display_caldav_info']['close_meeting']['next'];
        let collisions = this.array_response['display_caldav_info']['collision'];
        if (prev || next || collisions.length > 0) {
            if (prev) {
                let summary = prev['summary'];
                if (!prev['summary']) {
                    summary = rcmail.gettext('missing_summary', 'roundcube_caldav');
                }
                this.event_template_html.find('.previous').append(summary + ': ' + '<i>' + '('
                    + prev['calendar'] + ')' + '</i><br>' + prev['pretty_date']);
            } else {
                this.event_template_html.find('.previous').hide();
            }

            if (next) {
                let summary = next['summary'];
                if (!next['summary']) {
                    summary = rcmail.gettext('missing_summary', 'roundcube_caldav');
                }
                this.event_template_html.find('.next').append(summary + ': ' + '<i>' + '('
                    + next['calendar'] + ')' + '</i><br>' + next['pretty_date']);
            } else {
                this.event_template_html.find('.next').hide();
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
                        this.event_template_html.find('.meeting_collision').append(summary + ':  ' + display_date + ' <i>' + '('
                            + calendar_name + ')' + '</i><br>');
                    }

                }
                if (!bool) {
                    this.event_template_html.find('.meeting_collision').hide();
                }
            } else {
                this.event_template_html.find('.meeting_collision').hide();
            }
        } else {
            this.event_template_html.find('.info_caldav_server').hide();
        }
    }

    /**
     * Display the select calendar input
     */
    display_select_calendars() {
        let {
            isOrganizer,
            isACounter,
        } = this.init_method_and_status(this.array_response);
        let $calendar_choice = this.event_template_html.find('.calendar_choice');
        let select = this.event_template_html.find('.choose_calendar_to_add_event');

        if (!isOrganizer || this.array_response['display_select']) {
            if (!isOrganizer) {
                $calendar_choice.prepend(rcmail.gettext('choose_calendar_to_add', 'roundcube_caldav'));
            } else if (isACounter && !this.array_response['found_advance']) {
                $calendar_choice.prepend(rcmail.gettext('event_not_found_which_calendar_to_add', 'roundcube_caldav'));
            }
            for (let [calendar_id, calendar_name] of Object.entries(this.array_response['used_calendar'])) {
                let selected = calendar_name === this.array_response['main_calendar_name'] ? 'selected' : '';
                select.append(
                    `<option value="` + calendar_id + `"` + selected + '>' + calendar_name + '</option>'
                );
            }
        } else {
            $calendar_choice.hide();
        }
        return select;
    }


    /**
     * Display header and title of invitation
     */
    display_title() {
        let used_event = this.array_response['used_event'];

        // On récupère les participants qui sont respectivement l'expediteur et le destinataire de l'email
        let sender;
        let receiver;
        if (this.array_response['attendees']) {
            sender = find_among_attendee(this.array_response['sender_email'], this.array_response['attendees']);
            receiver = find_among_attendee(this.array_response['receiver_email'], this.array_response['attendees']);
        }

        // On récupère le role du participant et la methode du fichier reçu
        let {
            isOrganizer,
            isACounter,
            isAReply,
            isARequest,
            isADeclineCounter,
            isACancel
        } = this.init_method_and_status(this.array_response);

        let modif = this.array_response['new_description'] || this.array_response['new_location']
            || this.array_response['new_date'] || this.array_response['new_attendees']
        let $invitation = this.event_template_html.find('.invitation')
        let sender_name = '';
        if (sender) {
            sender_name = sender['name'] ? sender['name'] : sender['email'];
        }

        let receiver_name = '';
        if (receiver) {
            receiver_name = receiver['name'] ? receiver['name'] : receiver['email'];
        }

        // On affiche des titres différents selon le role ou la methode
        if (isOrganizer && isACounter) {
            if (modif || !this.array_response['found_older_event_on_calendar']) {
                $invitation.append('<h3>' + rcmail.gettext('invitation_modification', 'roundcube_caldav') + '</h3>')
            } else {
                $invitation.append('<h3>' + rcmail.gettext('invitation_modification_already_studied', 'roundcube_caldav') + '</h3>')
            }
        } else if (isOrganizer && isAReply) {
            if (sender) {
                if (this.array_response['sender_partstat'] === 'ACCEPTED') {
                    $invitation.append('<h3>' + sender_name + rcmail.gettext('invitation_accepted', 'roundcube_caldav') + '</h3>')
                } else if (this.array_response['sender_partstat'] === 'TENTATIVE') {
                    $invitation.append('<h3>' + sender_name + rcmail.gettext('invitation_tentative', 'roundcube_caldav') + '</h3>')
                } else {
                    $invitation.append('<h3>' + sender_name + rcmail.gettext('invitation_declined', 'roundcube_caldav') + '</h3>')
                }
            } else {
                $invitation.append('<h3>' + this.array_response['sender_email'] + rcmail.gettext('unknown_reply', 'roundcube_caldav') + '</h3>')
            }

        } else if (isOrganizer && isARequest) {
            $invitation.append('<h3>' + rcmail.gettext('invitation_send', 'roundcube_caldav') + '</h3>')
        } else if (isOrganizer && isADeclineCounter) {
            $invitation.append('<h3>' + rcmail.gettext('invitation_decline_modifications1', 'roundcube_caldav') + receiver_name
                + rcmail.gettext('invitation_decline_modifications2', 'roundcube_caldav') + '</h3>')
        } else if (!isOrganizer && isACancel) {
            $invitation.append('<h3>' + rcmail.gettext('invitation_cancel', 'roundcube_caldav') + '</h3>')
        } else if (!isOrganizer && isADeclineCounter) {
            $invitation.append('<h3>' + rcmail.gettext('invitation_declined_by_organizer', 'roundcube_caldav') + '</h3>')
        } else {
            $invitation.append('<h3>' + rcmail.gettext('invitation', 'roundcube_caldav') + '</h3>')
        }
        if (used_event['summary']) {
            $invitation.append('<h4>' + used_event['summary'] + '</h4>');
        }
    }


    /**
     * Display occurrence date when the event is a recurrent one
     */
    display_reccurent_events() {
        let used_event = this.array_response['used_event'];
        let recurrent_event = this.array_response['recurrent_events'][used_event['uid']];
        if (recurrent_event.length > 1) {
            // on affiche seulement les dix premier evt si il yen a plus
            for (let i = 0; i < 10; i++) {
                if (recurrent_event[i]) {
                    this.event_template_html.find('.repeated').append(recurrent_event[i] + '<br>');
                } else {
                    break;
                }

                if (i === 9) {
                    this.event_template_html.find('.repeated').append('...');
                }
            }
        } else {
            this.event_template_html.find('.repeated').hide();
        }
    }


    /**
     * Initialization of constants indicating methods and status
     */
    init_method_and_status() {
        let isOrganizer = false;
        if (this.array_response['identity']) {
            isOrganizer = this.array_response['identity']['role'] === 'ORGANIZER';
        }
        let isACancel = this.array_response['METHOD'] === 'CANCEL';
        let isACounter = this.array_response['METHOD'] === 'COUNTER';
        let isAReply = this.array_response['METHOD'] === 'REPLY';
        let isARequest = this.array_response['METHOD'] === 'REQUEST';
        let isADeclineCounter = this.array_response['METHOD'] === 'DECLINECOUNTER';
        return {isOrganizer, isACounter, isAReply, isARequest, isADeclineCounter, isACancel};
    }


    /**
     * Changes the display of the buttons according to the status of the event
     * @param status
     */
    change_status(status) {
        if (status === 'CONFIRMED') {
            let confirm = this.event_template_html.find('.confirm_button');
            if (confirm) {
                confirm.html(confirm.attr('data-label-disabled'));
                confirm.attr('disabled', true);
            }
        } else if (status === 'TENTATIVE') {
            let tentative = this.event_template_html.find('.tentative_button');
            if (tentative) {
                tentative.attr('disabled', true);
                tentative.html(tentative.attr('data-label-disabled'));
            }
        } else if (status === 'CANCELLED') {
            let decline = this.event_template_html.find('.decline_button');
            if (decline) {
                decline.attr('disabled', true);
                decline.html(decline.attr('data-label-disabled'));
            }
        }
    }

}

