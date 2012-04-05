<?php
namespace RestIP;
use \DBManager, \PDO, \APIPlugin;

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
            '/messages/:box'                     => _('Nachrichten: Posteingang und -ausgang'),
            '/messages/:box/:folder'             => _('Nachrichten: Ordner'),
            '/messages/:box/:folder/:message_id' => _('Nachrichten: Ordner - Nachrichtverwaltung'),
            '/messages/:message_id'              => _('Nachrichten'),
        );
    }

    /**
     *
     **/
    public function routes(&$router)
    {
    // Inbox and outbox
        // List folders
        $router->get('/messages/:box', function ($box) use (&$router) {
            $val = Helper::getUserData();
            $settings = $val['my_messaging_settings'] ?: array();
            $folders = $settings['folder'];

            $folders['in'][0]  = _('Posteingang');
            $folders['out'][0] = _('Postausgang');

            $folders = $folders[$box];

            $router->value(compact('folders'));
        });

    // Folders
        // List messages
        $router->get('/messages/:box/:folder', function ($box, $folder) use (&$router) {
            $val = Helper::getUserData();
            $settings = $val['my_messaging_settings'] ?: array();

            if ($folder != 0 && !isset($settings['folder'][$box][$folder])) {
                $router->halt(404, sprintf('Folder %s-%s not found', $box, $folder));
            }

            $ids      = Message::folder($box == 'in' ? 'rec' : 'snd', $folder);
            $messages = Message::load($ids);
            
            $users    = array();
            array_walk($messages, function (&$message) use (&$router, &$users) {
                if (!isset($users[$message['autor_id']])) {
                    $users[$message['autor_id']] = $router->dispatch('get', '/user(/:user_id)', $message['autor_id']);
                }
            });

            $router->value(compact('messages', 'users'));
        })->conditions((array('box' => '(in|out)')));

    // Direct access to messages
        // Load a message
        $router->get('/messages/:message_id', function ($message_id) use (&$router) {
            $message = Message::load($message_id);
            if (!$message) {
                $router->halt(404, sprintf('Message %s not found', $message_id));
            }
            $router->value($message);
        });

        // Destroy a message
        $router->delete('/messages/:message_id', function ($message_id) use (&$router) {
            $message = Message::load($message_id);
            if (!$message) {
                $router->halt(404, sprintf('Message %s not found', $message_id));
            }
            Message::delete($message_id);
        });
    }
}

class Message
{
    static function load($ids, $additional_fields = array()) {
        if (empty($ids)) {
            return array();
        }

        $add = implode(',', $additional_fields);

        $query = "SELECT m.message_id, autor_id, subject, message, m.mkdate, priority, 1 - mu.readed AS unread
                 {$add}
                  FROM message AS m
                  LEFT JOIN message_user AS mu USING(message_id)
                  WHERE user_id = ? AND message_id IN (?) AND deleted = 0
                  ORDER BY m.mkdate DESC";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($GLOBALS['user']->id, $ids, $trash));
        $messages = $statement->fetchAll(PDO::FETCH_ASSOC);

        array_walk($messages, function (&$message) {
            $message['message'] = formatReady($message['message']);
        });

        return is_array($ids) ? $messages : reset($messages);
    }

    static function delete($ids) {
        if (empty($ids)) {
            return array();
        }

        $query = "UPDATE message_user
                  SET deleted = 1
                  WHERE message_id IN (?) AND user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($ids));

        if ($statement->rowCount()) {
            $query = "SELECT message_id
                      FROM message_user
                      WHERE message_id IN (?)
                      GROUP BY message_id
                      HAVING SUM(deleted) = 2;";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array($ids));
            $ids = $statement->fetchAll(PDO::FETCH_COLUMN);

            // Prune messages that both sides have deleted
            // TODO: Transfer this into a cronjob
            $query = "DELETE m, mu
                      FROM message AS m
                      INNER JOIN message_user AS mu
                      WHERE m.message_id = mu.message_id AND m.message_id IN (?)";
            DBManager::get()
                ->prepare($query)
                ->execute(array($ids));
        }
    }
    
    static function folder($sndrec, $folder) {
        $query = "SELECT message_id
                  FROM message_user
                  WHERE snd_rec = ? AND folder = ? AND user_id = ? AND deleted = 0";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array(
            $sndrec,
            $folder,
            $GLOBALS['user']->id,
        ));
        return $statement->fetchAll(PDO::FETCH_COLUMN);        
    }
}