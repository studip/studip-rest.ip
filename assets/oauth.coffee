jQuery ($) ->
    $(document).on 'click', '[data-behaviour~=modal]', (event) ->
        href  = $(@).attr 'href'
        title = $(@).attr 'title'
        $('<div/>').load href, ->
            $(@).dialog
                modal: true
                title: title ? false
                width: 500
                buttons:
                    'Schliessen': ->
                        $(@).dialog('close')
        event.preventDefault()

    $(document).on 'click', '[data-behaviour~=confirm]', (event) ->
        title = $(@).attr('title') || $(@).val() || $(@).text()
        message = 'Wollen Sie die folgende Aktion wirklich ausführen?'.toLocaleString()
        message += "\n\n\"" + title + "\""
        event.preventDefault() unless confirm(message)