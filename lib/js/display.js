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
        let my_identity = null;
        if (this.array_response.identity) {
            my_identity = find_among_attendee(this.array_response.identity.email, this.array_response['attendees']);
        }
        if (this.array_response['found_advance']) {
            if (!this.array_response['is_sequences_equal']) {
                $event_found_on_server.html(rcmail.gettext('modified_event', 'roundcube_caldav') + this.array_response['found_on_calendar']['display_name']);
            } else {
                $event_found_on_server.hide();
            }
            if (my_identity) {
                this.change_status(my_identity.partstat);
            }
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
            $same_date.children('.D').html(this.array_response['date_weekday_start']);
            if (this.array_response['date_hours_start'] !== '0:00' || this.array_response['date_hours_end'] !== '0:00') {
                $same_date.children('.h').html(this.array_response['date_hours_start'] + ' : ' + this.array_response['date_hours_end']);
            } else {
                $same_date.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
            }
        } else {
            this.event_template_html.find('.same_date').hide();
            let $dif_date_start = this.event_template_html.find('.different_date.start');
            $dif_date_start.children('.d').html(this.array_response['date_day_start']);
            $dif_date_start.children('.m').html(this.array_response['date_month_start']);
            $dif_date_start.children('.D').html(this.array_response['date_weekday_start']);

            if (this.array_response['date_hours_start'] !== '0:00') {
                $dif_date_start.children('.h').html(this.array_response['date_hours_start']);
            } else {
                $dif_date_start.children('.h').html(rcmail.gettext('all_day', 'roundcube_caldav'));
            }


            let $dif_date_end = this.event_template_html.find('.different_date.end');
            $dif_date_end.children('.d').html(this.array_response['date_day_end']);
            $dif_date_end.children('.m').html(this.array_response['date_month_end']);
            $dif_date_end.children('.D').html(this.array_response['date_weekday_end']);

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
        if (this.array_response['attendees'] !== undefined) {
            for (let attendee of this.array_response['attendees']) {

                let link = '';
                let display = attendee['name'] ? attendee['name'] : attendee["email"];
                let status;
                // let tentative_logo = '<svg aria-hidden="true" focusable="false" style="height: 1.5em; width: 1.5em"  data-prefix="fas" data-icon="ban" class="logo svg-inline--fa fa-ban" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M256 0C114.6 0 0 114.6 0 256s114.6 256 256 256s256-114.6 256-256S397.4 0 256 0zM64 256c0-41.4 13.3-79.68 35.68-111.1l267.4 267.4C335.7 434.7 297.4 448 256 448C150.1 448 64 361.9 64 256zM412.3 367.1L144.9 99.68C176.3 77.3 214.6 64 256 64c105.9 0 192 86.13 192 192C448 297.4 434.7 335.7 412.3 367.1z"></path></svg>'
                // let needs_action_logo = '<svg aria-hidden="true" focusable="false" style="height: 1.5em; width: 1.5em" data-prefix="fas" data-icon="logo circle-exclamation" class="svg-inline--fa fa-circle-exclamation" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M256 0C114.6 0 0 114.6 0 256s114.6 256 256 256s256-114.6 256-256S397.4 0 256 0zM232 152C232 138.8 242.8 128 256 128s24 10.75 24 24v128c0 13.25-10.75 24-24 24S232 293.3 232 280V152zM256 400c-17.36 0-31.44-14.08-31.44-31.44c0-17.36 14.07-31.44 31.44-31.44s31.44 14.08 31.44 31.44C287.4 385.9 273.4 400 256 400z"></path></svg>'
                // let declined_logo = '<svg aria-hidden="true" focusable="false" style="height: 1.5em; width: 1.5em" data-prefix="fas" data-icon="ban" class="logo svg-inline--fa fa-ban" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M256 0C114.6 0 0 114.6 0 256s114.6 256 256 256s256-114.6 256-256S397.4 0 256 0zM64 256c0-41.4 13.3-79.68 35.68-111.1l267.4 267.4C335.7 434.7 297.4 448 256 448C150.1 448 64 361.9 64 256zM412.3 367.1L144.9 99.68C176.3 77.3 214.6 64 256 64c105.9 0 192 86.13 192 192C448 297.4 434.7 335.7 412.3 367.1z"></path></svg>';
                // let organizer_logo = '<svg aria-hidden="true" focusable="false" style="height: 1.5em; width: 1.5em" data-prefix="far" data-icon="circle-user" class="logo svg-inline--fa fa-circle-user" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M256 112c-48.6 0-88 39.4-88 88C168 248.6 207.4 288 256 288s88-39.4 88-88C344 151.4 304.6 112 256 112zM256 240c-22.06 0-40-17.95-40-40C216 177.9 233.9 160 256 160s40 17.94 40 40C296 222.1 278.1 240 256 240zM256 0C114.6 0 0 114.6 0 256s114.6 256 256 256s256-114.6 256-256S397.4 0 256 0zM256 464c-46.73 0-89.76-15.68-124.5-41.79C148.8 389 182.4 368 220.2 368h71.69c37.75 0 71.31 21.01 88.68 54.21C345.8 448.3 302.7 464 256 464zM416.2 388.5C389.2 346.3 343.2 320 291.8 320H220.2c-51.36 0-97.35 26.25-124.4 68.48C65.96 352.5 48 306.3 48 256c0-114.7 93.31-208 208-208s208 93.31 208 208C464 306.3 446 352.5 416.2 388.5z"></path></svg>';
                // let accepted_logo = '<svg aria-hidden="true" focusable="false" style="height: 1.5em; width: 1.5em" data-prefix="far" data-icon="circle-check" class="logo svg-inline--fa fa-circle-check" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M335 175L224 286.1L176.1 239c-9.375-9.375-24.56-9.375-33.94 0s-9.375 24.56 0 33.94l64 64C211.7 341.7 217.8 344 224 344s12.28-2.344 16.97-7.031l128-128c9.375-9.375 9.375-24.56 0-33.94S344.4 165.7 335 175zM256 0C114.6 0 0 114.6 0 256s114.6 256 256 256s256-114.6 256-256S397.4 0 256 0zM256 464c-114.7 0-208-93.31-208-208S141.3 48 256 48s208 93.31 208 208S370.7 464 256 464z"></path></svg>';

                switch (attendee['partstat']) {

                    case 'ACCEPTED':
                        // status = accepted_logo;
                        status = '[✓]'; //U+2713
                        break;
                    case 'TENTATIVE':
                        // status = tentative_logo;
                        status = '[~]';
                        break;
                    case 'DECLINED':
                        // status = declined_logo;
                        status = '[✗]';
                        break;
                    case 'NEEDS_ACTION':
                    case 'NEEDS-ACTION':
                        // status = needs_action_logo;
                        status = '[?]';
                        break;
                    case 'ORGANIZER':
                        // status = organizer_logo;
                        status = '[O]';
                        break;
                    default:
                        status = '';
                }

                link = status + `<a class="attendee_link" href='mailto:` + attendee["email"] + `' aria-haspopup="false" onClick="` + attendee["onclick"]
                    + `">` + display + `</a><br>`;

                if (attendee['partstat'] === 'ORGANIZER') {
                    this.event_template_html.find('.attendee').prepend(link);
                } else {
                    this.event_template_html.find('.attendee').append(link);
                }

            }

            let attribut = this.array_response['attr_reply_all'];
            let link = `<a href='reply_all' aria-haspopup="false" onClick="` + attribut["onclick"] + `">`
                + rcmail.gettext('reply_all', 'roundcube_caldav') + `</a><br>`;
            this.event_template_html.find('.reply_all').append(link);
        } else {
            this.event_template_html.find('.attendees').hide();
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
            $invitation.append('<h3>' + rcmail.gettext('invitation_send', 'roundcube_caldav') + '</h3>');
        } else if (isOrganizer && isADeclineCounter) {
            $invitation.append('<h3>' + rcmail.gettext('invitation_decline_modifications1', 'roundcube_caldav') + receiver_name
                + rcmail.gettext('invitation_decline_modifications2', 'roundcube_caldav') + '</h3>');
        } else if (isOrganizer && isACancel) {
            $invitation.append('<h3>' + rcmail.gettext('<h3>' + rcmail.gettext('invitation_cancel_for_organizer', 'roundcube_caldav') + '</h3>'));
        } else if (!isOrganizer && isACancel) {
            $invitation.append('<h3>' + rcmail.gettext('invitation_cancel', 'roundcube_caldav') + '</h3>');
        } else if (!isOrganizer && isADeclineCounter) {
            $invitation.append('<h3>' + rcmail.gettext('invitation_declined_by_organizer', 'roundcube_caldav') + '</h3>');
        } else {
            $invitation.append('<h3>' + rcmail.gettext('invitation', 'roundcube_caldav') + '</h3>')
        }
        if (used_event['summary']) {
            $invitation.append('<h4>' + used_event['summary'] + '</h4>');
        } else {
            $invitation.append('<h4>' + rcmail.gettext('no_title', 'roundcube_caldav') + '</h4>');
        }
    }


    /**
     * Display occurrence date when the event is a recurrent one
     */
    display_reccurent_events() {
        let used_event = this.array_response['used_event'];
        let recurrent_event = this.array_response['recurrent_events'][used_event['uid']];
        if (recurrent_event.length > 1) {
            if (this.array_response['rrule_to_text']) {
                this.event_template_html.find('.repeated').append(this.array_response['rrule_to_text'] + '<br>-<br>');
            }
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
        if (status === 'ACCEPTED') {
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
        } else if (status === 'DECLINED') {
            let decline = this.event_template_html.find('.decline_button');
            if (decline) {
                decline.attr('disabled', true);
                decline.html(decline.attr('data-label-disabled'));
            }
        }
    }

    hide_is_already_on_server() {
        this.event_template_html.find('.is_on_server').hide();
    }
}

