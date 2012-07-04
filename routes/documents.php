<?php
namespace RestIP;

class DocumentsRoute implements \APIPlugin
{
    function describeRoutes()
    {
        return array(
            '/documents/:range_id/folder(/:folder_id)' => _('Dateiordner'),
            '/documents/:document_id'                  => _('Dateien'),
            '/documents/:document_id/download'         => _('Dateidownloads'),
        );
    }

    /**
     *
     */
    public static function before()
    {
        require_once 'lib/datei.inc.php';
        require_once 'lib/classes/StudipDocument.class.php';
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

            $folders   = Document::loadFolders($folder_id);
            $documents = Document::loadFiles($folder_id, 'folder');

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

            $router->render(compact('folders', 'documents', 'users'));
        });

        $router->get('/documents/:document_id', function($document_id) use ($router) {
            $document = new \StudipDocument($document_id);
            if (!$document->checkAccess($GLOBALS['user']->id)) {
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
            $document = new \StudipDocument($document_id);
            if (!$document->checkAccess($GLOBALS['user']->id)) {
                $router->halt(403, sprintf('User may not access file %s', $document_id));
            }

            $file = $path_file = get_upload_file_path($document_id);
            if (!file_exists($file)) {
                $router->halt(404, sprintf('File contents for file %s not found', $document_id));
            }

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
            header(sprintf('Content-Type: %s; name="%s"',
                           get_mime_type($document->getValue('filename')),
                           $document->getValue('filename')));
            header('Content-Description: File Transfer');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . filesize($file));
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
        $statement = \DBManager::get()->prepare($query);
        $statement->execute(array($range_id));
        return $statement->fetchColumn();
    }
    
    static function folderBelongsToRange($range_id, $folder_id)
    {
        $query = "SELECT range_id FROM folder WHERE folder_id = ?";
        $statement = \DBManager::get()->prepare($query);

        $top_folder = md5($range_id . 'top_folder');
        while ($folder_id
            && $folder_id != $range_id
            && $folder_id != $top_folder)
        {
            $statement->execute(array($folder_id));
            $folder_id = $statement->fetchColumn();
            $statement->closeCursor();
        }

        return $folder_id !== false;
    }

    static function loadFolders($folder_id)
    {
        $query = "SELECT folder_id, user_id, name, description, mkdate, chdate, permission
                  FROM folder
                  WHERE range_id IN (:folder_id, MD5(CONCAT(:folder_id, 'top_folder'))) AND permission > 0
                  
                  UNION
                  
                  SELECT DISTINCT folder_id, folder.user_id, folder.name, folder.description,
                                  folder.mkdate, folder.chdate, folder.permission
                  FROM themen AS th
                  LEFT JOIN themen_termine AS tt USING (issue_id) 
                  LEFT JOIN termine AS t USING (termin_id)
                  INNER JOIN folder ON (th.issue_id = folder.range_id)
                  WHERE th.seminar_id = :folder_id AND folder.permission > 0
                  
                  UNION
                  
                  SELECT folder_id, folder.user_id, folder.name, folder.description, folder.mkdate,
                         folder.chdate, folder.permission
                  FROM statusgruppen sg
                  INNER JOIN folder ON sg.statusgruppe_id = folder.range_id
                  WHERE sg.range_id = :folder_id AND folder.permission > 0";
        $statement = \DBManager::get()->prepare($query);
        $statement->bindParam(':folder_id', $folder_id);
        $statement->execute();
        $folders =  $statement->fetchAll(\PDO::FETCH_ASSOC);

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

    static function loadFiles($id, $type = 'file')
    {
        if ($type === 'folder') {
            $query = "SELECT dokument_id AS document_id, user_id, name, description, mkdate, chdate,
                             filename, filesize, downloads
                      FROM dokumente
                      WHERE range_id = ?";
        } else {
            $query = "SELECT dokument_id AS document_id, user_id, name, description, mkdate, chdate,
                             filename, filesize, downloads
                      FROM dokumente
                      WHERE dokument_id IN (?)";
        }
        $statement = \DBManager::get()->prepare($query);
        $statement->execute(array($id));
        $files =  $statement->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($files as &$file) {
            $file['mime_type'] = get_mime_type($file['filename']);
            $file['icon'] = \Assets::image_path(GetFileIcon(getFileExtension($file['filename'])));
        }

        return ($type !== 'folder' && !is_array($id)) ? reset($files) : $files;
    }
}