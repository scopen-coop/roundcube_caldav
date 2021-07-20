function accept(response){
    // $('#disp_invitation').html(response.message);
    console.log(response.message);
}


rcmail.addEventListener('init', function(evt) {
    rcmail.addEventListener('plugin.accept',accept)

    document.getElementById('accept_button').addEventListener('click',function evt(){
        rcmail.http_post('plugin.accept_action');
    })

});