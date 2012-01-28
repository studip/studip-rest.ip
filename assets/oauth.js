jQuery(function($) {
  $('[data-behaviour~=modal]').live('click', function(event) {
    var href, title;
    href = $(this).attr('href');
    title = $(this).attr('title');
    $('<div/>').load(href, function() {
      return $(this).dialog({
        modal: true,
        title: title != null ? title : false,
        width: 500,
        buttons: {
          'Schliessen': function() {
            return $(this).dialog('close');
          }
        }
      });
    });
    return event.preventDefault();
  });
  return $('[data-behaviour~=confirm]').live('click', function(event) {
    var message, title;
    title = $(this).attr('title') || $(this).val() || $(this).text();
    message = 'Wollen Sie die folgende Aktion wirklich ausführen?'.toLocaleString();
    message += "\n\n\"" + title + "\"";
    if (!confirm(message)) return event.preventDefault();
  });
});