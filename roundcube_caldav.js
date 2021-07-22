function accept(response) {
    // $('#disp_invitation').html(response.message);
    console.log('exception: ' + response.request);
    console.log('response: ' + response.response);
}


rcmail.addEventListener('init', function (evt) {
    rcmail.addEventListener('plugin.accept', accept)


    document.getElementById('confirm_button').addEventListener('click', function evt() {
        rcmail.http_post('plugin.accept_action', {_uid: rcmail.env.uid, _mbox: rcmail.env.mailbox, _type:'CONFIRMED'});
    });
    document.getElementById('tentative_button').addEventListener('click', function evt() {
        rcmail.http_post('plugin.accept_action', {_uid: rcmail.env.uid, _mbox: rcmail.env.mailbox, _type:'TENTATIVE'});
    });
    document.getElementById('decline_button').addEventListener('click', function evt() {
        rcmail.http_post('plugin.accept_action', {_uid: rcmail.env.uid, _mbox: rcmail.env.mailbox, _type:'DECLINE'});
    });
});