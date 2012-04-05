<?php

/**
 *
 **/
class MessageRoute implements APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/messages/folders'        => _('Nachrichtenordner des Nutzers'),
            '/messages/:box(/:folder)' => _('Nachrichten des Nutzers'),
            '/message/:message_id'     => _('Nachrichten'),
        );
    }

    /**
     *
     **/
    public function routes(&$router)
    {
        $router->get('/messages/folders', function () use (&$router) {
            $val = RestIP\Helper::getUserData();
            $settings = $val['my_messaging_settings'] ?: array();
            $folders = $settings['folder'];

            $folders['in'][0] = _('Posteingang');
            $folders['out'][0] = _('Postausgang');

            $router->value(compact('folders'));
        });

        $router->post('/messages/folders', function () use (&$router) {
            $val = RestIP\Helper::getUserData();
            $settings = $val['my_messaging_settings'] ?: array();
            $folders = $settings['folder'];

            $folders['in'][0] = _('Posteingang');
            $folders['out'][0] = _('Postausgang');

            $router->value(compact('folders'));
        });

        $router->get('/messages/:box(/:folder)', function ($box, $folder = 0) use (&$router) {
            $val = RestIP\Helper::getUserData();
            $settings = $val['my_messaging_settings'] ?: array();

            if (!in_array($box, array('in', 'out'))) {
                throw new Exception('Invalid box parameter, try "in" or "out"');
            }

            if ($folder != 0 && !isset($settings['folder'][$box][$folder])) {
                throw new Exception('Invalid folder parameter');
            }

            $query = "SELECT m.message_id, autor_id, subject, message, m.mkdate, mu.readed, priority
                      FROM message AS m
                      LEFT JOIN message_user AS mu USING (message_id)
                      WHERE snd_rec = ? AND user_id = ? AND folder = ? AND deleted = 0
                      ORDER BY m.mkdate DESC";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array(
                $box === 'in' ? 'rec' : 'snd',
                $GLOBALS['user']->id,
                $folder,
            ));
            $messages = $statement->fetchAll(PDO::FETCH_ASSOC);

            array_walk($messages, function (&$message) {
                $message['message'] = formatReady($message['message']);
            });

            $router->value(compact('messages'));
        });

        $router->get('/message/:message_id', function ($message_id) use (&$router) {
            $router->value(array('implement' => __METHOD__ . ':' . __FILE__ . '#L' . __LINE__));
        });
    }
}
