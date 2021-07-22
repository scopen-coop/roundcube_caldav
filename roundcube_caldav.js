function accept(response) {
    // $('#disp_invitation').html(response.message);
    console.log('exception: ' + response.request);
}


rcmail.addEventListener('init', function (evt) {
    rcmail.addEventListener('plugin.accept', accept)


    var confirm = document.getElementById('confirm_button');
    if (confirm) {
        confirm.addEventListener('click', function evt() {
            rcmail.http_post('plugin.accept_action', {
                _uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _type: 'CONFIRMED'
            });
        });
    }

    var tentative = document.getElementById('tentative_button');
    if (tentative) {
        tentative.addEventListener('click', function evt() {
            rcmail.http_post('plugin.accept_action', {
                _uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _type: 'TENTATIVE'
            });
        });
    }

    var decline = document.getElementById('decline_button')
    if (decline) {
        decline.addEventListener('click', function evt() {
            rcmail.http_post('plugin.accept_action', {
                _uid: rcmail.env.uid,
                _mbox: rcmail.env.mailbox,
                _type: 'DECLINE'
            });
        });
    }

});