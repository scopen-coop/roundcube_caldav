rcmail.addEventListener('init', function(evt) {
    // create custom button
    var button = get
    button.bind('click', function(e){ return rcmail.command('reply', this); });

    // add and register
    rcmail.add_element(button, 'toolbar');
    rcmail.register_button('plugin.samplecmd', 'rcmSampleButton', 'link');
    rcmail.register_command('plugin.samplecmd', sample_handler, true);
});