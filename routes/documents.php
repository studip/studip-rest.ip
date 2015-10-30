<?php
namespace RestIP;
use \APIPlugin, \Assets, \DBManager, \StudipDocument, \PDO;

class DocumentsRoute implements APIPlugin
{
    function describeRoutes()
    {
        return array(
            '/documents/:range_id/folder(/:folder_id)' => _('Dateiordner'),
            '/documents/:document_id'                  => _('Dateien'),
            '/documents/:course_id/new(/:timestamp)'   => _('Neue Dateien'),
            '/documents/:document_id/download'         => _('Dateidownloads'),
        );
    }

    /**
     *
     */
    public static function before()
    {
        require_once 'lib/datei.inc.php';
    }

    function routes(&$router)
    {
        $router->get('/documents/:range_id/folder(/:folder_id)', function($range_id, $folder_id = null) use ($router) {
            $folder_id = $folder_id ?: $range_id;

            if (!Document::isActivated($range_id)) {
                $router->halt(400, sprintf('Range %s has no documents', $range_id));
            }
            if (!Helper::UserHasAccessToRange($range_id)) {
                $router->halt(403, sprintf('User may not access range %s', $range_id));
            }

            if (!Document::folderBelongsToRange($range_id, $folder_id)) {
                $router->halt(404, sprintf('No folder %s for range %s', $folder_id, $range_id));
            }

            $last_visit = object_get_visit($range_id, "documents");
            $folders   = Document::loadFolders($folder_id);
            $documents = Document::loadFiles($folder_id, 'folder', $last_visit);

            if ($router->compact()) {
                $router->render(compact('folders', 'documents'));
                return;
            }

            $users = array();
            foreach ($folders as &$folder) {
                if (!isset($users[$folder['user_id']])) {
                    $users[$folder['user_id']] = reset($router->dispatch('get', '/user(/:user_id)', $folder['user_id']));
                }
            }

            foreach ($documents as &$document) {
                if (!isset($users[$document['user_id']])) {
                    $users[$document['user_id']] = reset($router->dispatch('get', '/user(/:user_id)', $document['user_id']));
                }
            }

            header('Cache-Control: private');
            $router->expires('+10 minutes');
            $router->render(compact('folders', 'documents', 'users'));
        });

        $router->get('/documents/:course_id/new(/:timestamp)', function($course_id, $timestamp = 0) use ($router) {
            if (!Document::isActivated($course_id)) {
                $router->halt(400, sprintf('Course %s has no documents', $course_id));
            }
            if (!Helper::UserHasAccessToRange($course_id)) {
                $router->halt(403, sprintf('User may not access course %s', $course_id));
            }

            $documents = Document::loadNewFiles($course_id, $timestamp);

            header('Cache-Control: private');
            $router->expires('+10 minutes');
            $router->render(compact('documents'));
        });

        $router->get('/documents/:document_id', function($document_id) use ($router) {
            $document = new StudipDocument($document_id);
            if (!$document->checkAccess($GLOBALS['user']->id) && $document->seminar_id != $GLOBALS['user']->id) {
                $router->halt(403, sprintf('User may not access file %s', $document_id));
            }

            $document = Document::loadFiles($document_id);

            if ($router->compact()) {
                $router->render(compact('document'));
                return;
            }

            $user[$document['user_id']] = reset($router->dispatch('get', '/user(/:user_id)', $document['user_id']));

            $router->render(compact('document', 'user'));
        });

        // See public/sendfile.php
        $router->get('/documents/:document_id/download', function($document_id) use ($router) {
            $document = new StudipDocument($document_id);
            if (!$document->checkAccess($GLOBALS['user']->id) && $document->seminar_id != $GLOBALS['user']->id) {
                $router->halt(403, sprintf('User may not access file %s', $document_id));
            }

            // check if linked file is obtainable
            if ($document->url) {
                $path_file = $document->url;
                $link_data = parse_link($path_file);
                if ($link_data['response_code'] != 200) {
                    $router->halt(404, sprintf('File contents for file %s not found', $document_id));
                }
                $filesize = $link_data['Content-Length'];
            } else {
                $path_file = get_upload_file_path($document_id);
                $filesize = @filesize($path_file);

                if (!file_exists($path_file)) {
                    $router->halt(404, sprintf('File contents for file %s not found', $document_id));
                }
            }

            $filename = $document->getValue('filename');
            header('Expires: Mon, 12 Dec 2001 08:00:00 GMT');
            header('Last-Modified: ' . gmdate ('D, d M Y H:i:s') . ' GMT');
            if ($_SERVER['HTTPS'] == 'on'){
                header('Pragma: public');
                header('Cache-Control: private');
            } else {
                header('Pragma: no-cache');
                header('Cache-Control: no-store, no-cache, must-revalidate');   // HTTP/1.1
            }
            header('Cache-Control: post-check=0, pre-check=0', false);
            // header('Content-Type: ' . get_mime_type($filename) . '; name="' . $filename . '"');
            header('Content-Type: ' . get_mime_type($filename));
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');
            if (is_int($filesize)) {
                header('Content-Length: ' . $filesize);
            }
            header('ETag: "' . $document_id . '"');
            @readfile_chunked($path_file);
            TrackAccess($document_id, 'dokument');
            die;
        });
    }
}

class Document
{
    static function isActivated($range_id)
    {
        // Documents is 2nd bit (0-based indexed!) in modules flag
        $query = "SELECT modules & (1 << 1) != 0 FROM seminare WHERE Seminar_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($range_id));
        return $statement->fetchColumn();
    }

    static function folderBelongsToRange($range_id, $folder_id)
    {
        $top_folders = array(
            $range_id,
            md5($range_id . 'top_folder'),
        );

        $query = "SELECT issue_id FROM themen WHERE seminar_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($range_id));
        $top_folders = array_merge($top_folders, $statement->fetchAll(PDO::FETCH_COLUMN));

        $query = "SELECT statusgruppe_id FROM statusgruppen WHERE range_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($range_id));
        $top_folders = array_merge($top_folders, $statement->fetchAll(PDO::FETCH_COLUMN));

        $query = "SELECT range_id FROM folder WHERE folder_id = ?";
        $statement = DBManager::get()->prepare($query);

        while ($folder_id && !in_array($folder_id, $top_folders))
        {
            $statement->execute(array($folder_id));
            $folder_id = $statement->fetchColumn();
            $statement->closeCursor();
        }

        return $folder_id !== false;
    }

    static function loadFolders($folder_id)
    {
        $query = "SELECT * FROM (
                    SELECT folder_id, user_id, name, mkdate, chdate, permission,
                         IFNULL(description, '') AS description
                    FROM folder
                    WHERE range_id IN (:folder_id, MD5(CONCAT(:folder_id, 'top_folder')))
                      AND permission > 0

                    UNION

                    SELECT DISTINCT folder_id, folder.user_id, folder.name,
                                    folder.mkdate, folder.chdate, folder.permission,
                                    IFNULL(folder.description, '') AS description
                    FROM themen AS th
                    INNER JOIN folder ON (th.issue_id = folder.range_id)
                    WHERE th.seminar_id = :folder_id AND folder.permission > 0

                    UNION

                    SELECT folder_id, folder.user_id, folder.name,
                           folder.mkdate, folder.chdate, folder.permission,
                           IFNULL(folder.description, '') AS description
                    FROM statusgruppen sg
                    LEFT JOIN seminar_user AS su
                        ON (su.user_id = :user_id AND su.seminar_id = :folder_id)
                    INNER JOIN statusgruppe_user AS sgu
                       ON (sg.statusgruppe_id = sgu.statusgruppe_id
                               AND (sgu.user_id = :user_id OR su.status NOT IN ('autor', 'user')))
                    INNER JOIN folder ON (sgu.statusgruppe_id = folder.range_id)
                    WHERE sg.range_id = :folder_id AND folder.permission > 0
                  ) AS folders ORDER BY name ASC";
        $statement = DBManager::get()->prepare($query);
        $statement->bindParam(':folder_id', $folder_id);
        $statement->bindParam(':user_id', $GLOBALS['user']->id);
        $statement->execute();
        $folders =  $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($folders as &$folder) {
            $folder['permissions'] = array(
                'visible'    => (bool)($folder['permission'] & 1),
                'writable'   => (bool)($folder['permission'] & 2),
                'readable'   => (bool)($folder['permission'] & 4),
                'extendable' => (bool)($folder['permission'] & 8),
            );
            unset($folder['permission']);
        }

        return $folders;
    }

    static function loadFiles($id, $type = 'file', $last_visit = 7776000)
    {
        if ($type === 'folder') {
            $query = "SELECT dokument_id AS document_id, user_id, name,
                             IFNULL(description, '') AS description,
                             mkdate, chdate, filename, filesize, downloads,
                             protected
                      FROM dokumente
                      WHERE range_id = ?
                      ORDER BY name ASC";
        } else {
            $query = "SELECT dokument_id AS document_id, user_id, name,
                             IFNULL(description, '') AS description,
                             mkdate, chdate, filename, filesize, downloads,
                             protected
                      FROM dokumente
                      WHERE dokument_id IN (?)
                      ORDER BY name ASC";
        }
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($id));
        $files = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($files as &$file) {
            $file['protected'] = !empty($file['protected']);
            $file['mime_type'] = get_mime_type($file['filename']);
            $file['icon']      = Assets::image_path(GetFileIcon(getFileExtension($file['filename'])));
            $file['new']       = $file['chdate'] >= $last_visit;
        }

        return ($type !== 'folder' && !is_array($id)) ? reset($files) : $files;
    }

    static function loadNewFiles($course_id, $timestamp)
    {
        $query = "SELECT dokument_id AS document_id, range_id AS folder_id, user_id, name,
                         IFNULL(description, '') AS description,
                         mkdate, chdate, filename, filesize, downloads,
                         protected
                  FROM dokumente
                  WHERE seminar_id = ? AND chdate >= ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($course_id, $timestamp));
        $files = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($files as &$file) {
            $file['protected'] = !empty($file['protected']);
            $file['mime_type'] = get_mime_type($file['filename']);
            $file['icon']      = Assets::image_path(GetFileIcon(getFileExtension($file['filename'])));
        }

        return $files;
    }
}
