function change_status(response) {
    let status = response.request;
    console.log(response.request);
    if(isEqual(status,'CONFIRMED')){
        let confirm = document.getElementById('confirm_button');
        if (confirm) {
            confirm.setAttribute('disabled', true);
            confirm.innerHTML = rcmail.gettext('confirmed', 'roundcube_caldav');
        }
    }else if(isEqual(status,'TENTATIVE')){
        let tentative = document.getElementById('tentative_button');
        if (tentative) {
            tentative.setAttribute('disabled', true);
            tentative.innerHTML = rcmail.gettext('tentatived', 'roundcube_caldav');
        }
    }else if(isEqual(status,'CANCELLED')){
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
                _type: 'CONFIRMED'
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
                _type: 'CANCELLED'
            });
            decline.setAttribute('disabled', 'true');
            decline.innerHTML = rcmail.gettext('declined', 'roundcube_caldav');
            confirm.removeAttribute('disabled');
            confirm.innerHTML = rcmail.gettext('confirm', 'roundcube_caldav');
            tentative.removeAttribute('disabled');
            tentative.innerHTML = rcmail.gettext('tentative', 'roundcube_caldav');
        });
    }
});
