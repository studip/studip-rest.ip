jQuery(function ($) {
    $.litelighter.styles.mystyle = {
        code: 'background-color:#141414;color:#ffffff;',
        comment: 'color:#999;',
        string: 'color:#8F9657;',
        number: 'color:#CF6745;',
        keyword: 'color:#6F87A8;'
    };

    $('pre[type="application/json"]').litelighter({
        clone: false,
        style: 'mystyle',
        language: 'js'
    });
});