jQuery(function ($) {
    $.litelighter.styles.mystyle = {
        code: 'background-color:#141414;color:#ffffff;',
        comment: 'color:#999;',
        string: 'color:#8F9657;',
        number: 'color:#CF6745;',
        keyword: 'color:#6F87A8;'
    };

    $('pre[type="application/json"]').each(function () {
        var content = $(this).text(), match;
        if (match = $.trim(content).match(/^\{% include (.*?) %\}$/)) {
            $(this).load('_includes/' + match[1], function () {
                $(this).litelighter({
                    clone: false,
                    style: 'mystyle',
                    language: 'js'
                });
            });
        } else {
            $(this).litelighter({
                clone: false,
                style: 'mystyle',
                language: 'js'
            });
        }
    });
});