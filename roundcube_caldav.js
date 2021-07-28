function change_status(response) {
    let status = response.request;
    if (isEqual(status, 'CONFIRMED')) {
        let confirm = document.getElementById('confirm_button');
        if (confirm) {
            confirm.setAttribute('disabled', true);
            confirm.innerHTML = rcmail.gettext('confirmed', 'roundcube_caldav');
        }
    } else if (isEqual(status, 'TENTATIVE')) {
        let tentative = document.getElementById('tentative_button');
        if (tentative) {
            tentative.setAttribute('disabled', true);
            tentative.innerHTML = rcmail.gettext('tentatived', 'roundcube_caldav');
        }
    } else if (isEqual(status, 'CANCELLED')) {
        let decline = document.getElementById('decline_button');
        if (decline) {
            decline.setAttribute('disabled', true);
            decline.innerHTML = rcmail.gettext('declined', 'roundcube_caldav');

        }
    }
}


function isEqual(str1, str2) {
    return str1.toUpperCase() === str2.toUpperCase()
}


rcmail.addEventListener('init', function (evt) {
    rcmail.addEventListener('plugin.change_status', change_status)
    let calendar = $('#choose_calendar_to_add_event').val();

    rcmail.http_post('plugin.get_status', {
        _uid: rcmail.env.uid,
        _mbox: rcmail.env.mailbox,
        _calendar: calendar,
    });

    let confirm = document.getElementById('confirm_button');
    let tentative = document.getElementById('tentative_button');
    let decline = document.getElementById('decline_button')

    if (confirm) {
        confirm.addEventListener('click', function evt() {
            rcmail.http_post('plugin.accept_action', {
                _uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _calendar: calendar,
                _type: 'CONFIRMED',
                _chosenDateStart: chosenDateStart,
                _chosenDateEnd: chosenDateEnd,
                _chosenTimeStart: chosenTimeStart,
                _chosenTimeEnd: chosenTimeEnd,
                _chosenLocation: chosenLocation,
            });
            confirm.setAttribute('disabled', 'true');
            confirm.innerHTML = rcmail.gettext('confirmed', 'roundcube_caldav');
            tentative.removeAttribute('disabled');
            tentative.innerHTML = rcmail.gettext('tentative', 'roundcube_caldav');
            decline.removeAttribute('disabled');
            decline.innerHTML = rcmail.gettext('decline', 'roundcube_caldav');
        });
    }
    if (tentative) {
        tentative.addEventListener('click', function evt() {
            rcmail.http_post('plugin.accept_action', {
                _uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _calendar: calendar,
                _type: 'TENTATIVE'
            });
            tentative.setAttribute('disabled', 'true');
            tentative.innerHTML = rcmail.gettext('tentatived', 'roundcube_caldav');
            confirm.removeAttribute('disabled');
            confirm.innerHTML = rcmail.gettext('confirm', 'roundcube_caldav');
            decline.removeAttribute('disabled');
            decline.innerHTML = rcmail.gettext('decline', 'roundcube_caldav');
        });
    }
    if (decline) {
        decline.addEventListener('click', function evt() {

            rcmail.http_post('plugin.accept_action', {
                _uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _calendar: calendar,
                _type: 'CANCELLED',

            });
            decline.setAttribute('disabled', 'true');
            decline.innerHTML = rcmail.gettext('declined', 'roundcube_caldav');
            confirm.removeAttribute('disabled');
            confirm.innerHTML = rcmail.gettext('confirm', 'roundcube_caldav');
            tentative.removeAttribute('disabled');
            tentative.innerHTML = rcmail.gettext('tentative', 'roundcube_caldav');
        });
    }


    var dialog, form,
        chosenDateStart = null,
        chosenDateEnd = null,
        chosenTimeStart = null,
        chosenTimeEnd = null,
        chosenLocation = null,
        $location = $('#location'),
        $date_start = $('#date_start'),
        $time_start = $('#time_start'),
        $date_end = $('#date_end'),
        $time_end = $('#time_end');

    function changeDate() {
        let $divtoadd = $('#if_rescheduled');
        let areFieldsFilled = false;

        if ($date_start.val() && $date_end.val() && $time_start.val() && $time_end.val()) {
            areFieldsFilled = true;


            let datestr = new Date(chosenDateStart + ' ' + chosenTimeStart).getTime();
            let dateend = new Date(chosenDateEnd + ' ' + chosenTimeEnd).getTime();

            if (dateend > datestr) {

                chosenDateStart = $date_start.val();
                chosenDateEnd = $date_end.val();
                chosenTimeStart = $time_start.val();
                chosenTimeEnd = $time_end.val();

                if (isEqual(chosenDateStart, chosenDateEnd)) {
                    $(".msg_date").remove();
                    $divtoadd.append('<p class="msg_date" >' + chosenDateStart + ' ' + chosenTimeStart + ' - ' + chosenTimeEnd + '</p>');
                } else {
                    $(".msg_date").remove();
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
            $(".msg_location").remove();
            $divtoadd.append('<p class="msg">' + rcmail.gettext('location', 'roundcube_caldav') + $location.val() + '</p>');
        }
        if (areFieldsFilled) {
            dialog.dialog("close");
            $divtoadd.show();
        } else {
            window.alert(rcmail.gettext('error_incomplete_field', 'roundcube_caldav'));
            return;
        }
        return 0;
    }

    dialog = $("#dialog-form").dialog({
        autoOpen: false,
        height: 'auto',
        width: 350,
        modal: true,

        buttons: {
            "Reschedule": changeDate,
            Cancel: function () {
                dialog.dialog("close");
            }
        },
        open: function () {
        },
        close: function () {
            form[0].reset();
        }
    });

    form = dialog.find("form").on("submit", function (event) {
        event.preventDefault();
        changeDate();
    });

    $("#open_dialog").button().on("click", function () {
        dialog.dialog("open");
    });
});