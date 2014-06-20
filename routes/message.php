<?php
namespace RestIP;

use \APIPlugin, \DBManager, \PDO, \messaging, \Request, \User, \UserConfig;

/**
 * Message route for Rest.IP
 *
 * @author     Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @version    1.0
 * @package    Stud.IP
 * @subpackage Rest.IP
 * @license    GPL
 **/
class MessageRoute implements APIPlugin
{
    static $settings;

    /**
     * Return human readable descriptions of all routes
     **/
    public function describeRoutes()
    {
        return array(
            '/messages'                          => _('Nachrichten schreiben'),
            '/messages/:box'                     => _('Nachrichten: Posteingang und -ausgang'),
            '/messages/:box/:folder'             => _('Nachrichten: Ordner'),
            '/messages/:message_id'              => _('Nachrichten'),
            '/messages/:message_id/read'         => _('Nachricht als gelesen markieren'),
            '/messages/:message_id/move/:folder' => _('Nachrichten verschieben'),
        );
    }

    public static function before()
    {
        require_once 'lib/messaging.inc.php';
        require_once 'lib/sms_functions.inc.php';
        if (function_exists('check_messaging_default')) { // < Stud.IP 2.4
            check_messaging_default();
            self::$settings = $GLOBALS['my_messaging_settings'] ?: array();
        } else {
            self::$settings = UserConfig::get($GLOBALS['user']->id)->MESSAGING_SETTINGS ?: array();
        }
    }

    /**
     * Define routes on router
     *
     * @param Slim Slim instance as router
     **/
    public function routes(&$router)
    {
    // Inbox and outbox
        // List folders
        $router->get('/messages/:box', function ($box) use ($router) {
            $settings = MessageRoute::$settings;
            $folders = $settings['folder'];

            $folders['in'][0]  = _('Posteingang');
            $folders['out'][0] = _('Postausgang');

            $folders = $folders[$box];

            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $total  = count($folders);

            $result = array(
                'folders'    => array_slice($folders, $offset, $limit),
                'pagination' => $router->paginate($total, $offset, $limit, '/messages', $box),
            );

            header('Cache-Control: private');
            $router->expires('+1 day');
            $router->render($result);
        })->conditions(array('box' => '(in|out)'));

        // Create new folder
        $router->post('/messages/:box', function ($box) use ($router) {
            $folder = trim(Request::get('folder', ''));
            $settings = MessageRoute::$settings;

            if (empty($folder)) {
                $router->halt(406, 'No folder name provided');
            }
            if (false and preg_match('/[^a-z0-9]/', $folder)) {
                $router->halt(406, 'Invalid folder name provided');
            }
            if (in_array($folder, $settings['folder'][$box])
              || ($box === 'in' and $folder === _('Posteingang'))
              || ($box === 'out' and $folder === _('Postausgang')))
            {
                $router->halt(409, 'Duplicate');
            }

            $settings['folder'][$box][] = $folder;
            if (function_exists('check_messaging_default')) { // < Stud.IP 2.4
                $GLOBALS['my_messaging_settings'] = $settings;
            } else {
                UserConfig::get($GLOBALS['user']->id)->store('MESSAGING_SETTINGS', $settings);
            }

            $router->halt(201);
        })->conditions(array('box' => '(in|out)'));

    // Folders
        // List messages
        $router->get('/messages/:box/:folder', function ($box, $folder) use ($router) {
            $settings = MessageRoute::$settings;
            if ($folder != 0 && !isset($settings['folder'][$box][$folder])) {
                $router->halt(404, sprintf('Folder %s-%s not found', $box, $folder));
            }

            $ids = Message::folder($box == 'in' ? 'rec' : 'snd', $folder);

            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $total  = count($ids);

            $ids    = array_slice($ids, $offset, $limit);
            $messages = Message::load($ids);

            $result = array(
                'messages'   => $messages,
                'pagination' => $router->paginate($total, $offset, $limit, '/messages', $box, $folder),
            );

            header('Cache-Control: private');
            $router->expires('+10 minutes');
            $router->render($result);
        })->conditions((array('box' => '(in|out)', array('folder' => '\d+'))));

    // Direct access to messages
        // Get count of message
        $router->get('/messages', function () use ($router) {
            $count = array(
                'read'   => 0 + count_messages_from_user('in', ' AND message_user.readed = 1 '),
                'unread' => 0 + count_messages_from_user('in', ' AND message_user.readed = 0 '),
            );
            $router->render($count);
        });

        // Create a message
        $router->post('/messages', function () use ($router) {
            $subject = trim($_POST['subject'] ?: '');
            if (empty($subject)) {
                $router->halt(406, 'No subject provided');
            }

            $message = trim($_POST['message'] ?: '');
            // if (empty($message)) {
            //     $router->halt(406, 'No message provided');
            // }

            // Try to detect and convert utf-8 to windows-1252
            $subject = Helper::Sanitize($subject);
            $message = Helper::Sanitize($message);

            $usernames = array_map(function ($id) use ($router) {
                $user = User::find($id);
                if (!$user) {
                    $router->halt(404, sprintf('Receiver user id %s not found', $id));
                }
                return $user['username'];
            }, (array)($_POST['user_id'] ?: null));

            $message_id = md5(uniqid('message', true));

            $messaging = new messaging;
            $result = $messaging->insert_message($message, $usernames,
                                                 $GLOBALS['user']->id, time(),
                                                 $message_id, false, Request::get('signature'),
                                                 $subject, Request::int('email', 0));

            if (Request::int('reading_confirmation')) {
                $query = "UPDATE message SET reading_confirmation = 1 WHERE message_id = ?";
                $statement = DBManager::get()->prepare($query);
                $statement->execute(array($message_id));
            }

            if (!$result) {
                $this->halt(500, 'Could not create message');
            }

            $message = Message::load($message_id);
            unset($message['deleted2']);

            $router->render(compact('message'), 201);
        });

        // Load a message
        $router->get('/messages/:message_id', function ($message_id) use ($router) {
            $message = Message::load($message_id);
            if (!$message
               || ($message['sender_id'] === $GLOBALS['user']->id && $message['deleted'])
               || ($messages['receiver_id'] === $GLOBALS['user']->id && $message['deleted2']))
            {
                $router->halt(404, sprintf('Message %s not found', $message_id));
                return;
            }
            unset($message['deleted2']);

            $router->render(compact('message'));
        });

        // Destroy a message
        $router->delete('/messages/:message_id', function ($message_id) use ($router) {
            $message = Message::load($message_id, array('mu.dont_delete'));
            if (!$message) {
                $router->halt(404, sprintf('Message %s not found', $message_id));
            }
            if ($message['dont_delete']) {
                $router->halt(403, 'Message shall not be deleted');
            }

            $messaging = new messaging;
            $messaging->delete_message($message_id, $GLOBALS['user']->id, true);

            $router->halt(204);
        });

        // Read (load and update read flag) a message
        $router->put('/messages/:message_id/read', function ($message_id) use ($router) {
            $message = Message::load($message_id);
            if (!$message) {
                $router->halt(404, sprintf('Message %s not found', $message_id));
            }

            $messaging = new messaging;
            $messaging->set_read_message($message_id);

            $router->halt(204);
        });

        $router->put('/messages/read', function () use ($router) {
            Message::readAll($GLOBALS['user']->id);
            $router->halt(200);
        });

        // Move message
        $router->put('/messages/:message_id/move/:folder', function ($folder, $message_id) use ($router) {
            $settings = MessageRoute::$settings;

            if ($folder != 0 && !isset($settings['folder'][$box][$folder])) {
                $router->halt(404, sprintf('Folder %s-%s not found', $box, $folder));
            }

            $message = Message::load($message_id);
            if (!$message) {
                $router->halt(404, sprintf('Message %s not found', $message_id));
            }

            Message::move($message_id, $folder);

            $router->halt(204);
        })->conditions(array('folder' => '\d+'));
    }
}

class Message
{
    static function load($ids, $additional_fields = array()) {
        if (empty($ids)) {
            return array();
        }

        $additional_fields = empty($additional_fields)
                           ? ''
                           : ',' . implode(',', $additional_fields);

        $query = "SELECT DISTINCT m.message_id, mu.user_id AS sender_id, mu2.user_id AS receiver_id, subject,
                         message, m.mkdate, priority, 1 - mu2.readed AS unread, mu.deleted, mu2.deleted AS deleted2
                         {$additional_fields}
                  FROM message AS m
                  INNER JOIN message_user AS mu ON (m.message_id = mu.message_id AND mu.snd_rec = 'snd')
                  INNER JOIN message_user AS mu2 ON (mu.message_id = mu2.message_id AND mu2.snd_rec = 'rec')
                  WHERE m.message_id IN (:ids) AND :user_id IN (mu.user_id, mu2.user_id)";
        if (is_array($ids) and count($ids) > 1) {
            $query .= " ORDER BY m.mkdate DESC";
        }

        $statement = DBManager::get()->prepare($query);
        $statement->execute(array(
            ':ids'     => $ids,
            ':user_id' => $GLOBALS['user']->id
        ));
        $messages = $statement->fetchAll(PDO::FETCH_ASSOC);

        array_walk($messages, function (&$message) {
            $message['message_original'] = $message['message'] ?: '';
            $message['message']          = formatReady($message['message']) ?: '';
            $message['attachments']      = Message::loadAttachments($message['message_id']);
        });

        return is_array($ids) ? $messages : reset($messages);
    }

    static function loadAttachments($id)
    {
        $query = "SELECT dokument_id, name, filesize
                  FROM dokumente
                  WHERE range_id = :msg_id AND user_id = seminar_id
                  ORDER BY filename ASC";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':msg_id', $id);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    static function folder($sndrec, $folder)
    {
        $query = "SELECT message_id
                  FROM message_user
                  WHERE snd_rec = ? AND folder = ? AND user_id = ? AND deleted = 0
                  ORDER BY mkdate DESC";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array(
            $sndrec,
            $folder,
            $GLOBALS['user']->id,
        ));
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    static function move($message_id, $folder)
    {
        $query = "UPDATE message_user SET folder = ? WHERE message_id = ? AND user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($folder, $message_id, $GLOBALS['user']->id));
        return $statement->rowCount() > 0;
    }

    static function readAll($user_id)
    {
        $query = "UPDATE message_user SET readed = 1 WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        return $statement->execute(array($user_id));
    }

}
