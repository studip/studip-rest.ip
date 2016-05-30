<?php
namespace RestIP;

use \APIPlugin, \DBManager, \StudipPDO, \PDO, \Request;

class ForumRoute implements APIPlugin
{
    public function describeRoutes()
    {
        return array(

        );
    }

    public static function before()
    {
        require_once 'public/plugins_packages/core/Forum/models/ForumCat.php';
        require_once 'public/plugins_packages/core/Forum/models/ForumEntry.php';
        require_once 'public/plugins_packages/core/Forum/models/ForumPerm.php';
        require_once 'public/plugins_packages/core/Forum/models/ForumHelpers.php';
        require_once 'public/plugins_packages/core/Forum/models/ForumVisit.php';
    }

    public function routes(&$router)
    {
        // Forums / Categories
        $router->get('/courses/:course_id/forum_categories', function ($course_id) use ($router) {
            if (!\ForumPerm::has('search', $course_id)) {
                $router->halt(401);
            }

            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;

            \ForumEntry::checkRootEntry($course_id);

            $categories = Forum::getCatList($course_id);
            $total      = sizeof($categories);
            $categories = array_splice($categories, $offset, $limit);

            $result = array(
                'forums'     => $categories,
                'pagination' => $router->paginate($total, $offset, $limit, '/courses', $course_id, 'forum_categories'),
            );

            $router->render($result);
        });


        $router->post('/courses/:course_id/forum_categories', function ($course_id) use ($router) {
            if (!\ForumPerm::has("add_category", $course_id)) {
                 $router->halt(403);
            }

            $name  = Helper::Sanitize(Request::get('name'));

            if (!strlen($name = trim($name))) {
                $router->halt(400, 'Category name required.');
            }

             $category_id = \ForumCat::add($course_id, $name);
             if (!$category_id) {
                 $router->halt(500, 'Error creating the forum category.');
             }

            $category = Forum::findCategory($category_id, $router);
            $router->render(compact('category'), 201);
        });


        $router->put('/courses/:course_id/set_forum_read', function ($course_id) use ($router) {
            if (!\ForumPerm::has('search', $course_id)) {
                $router->halt(401);
            }

            // do this twice, to set them REALLY as visited, without storing the previous visitdate
            \ForumVisit::setVisit($course_id);
            \ForumVisit::setVisit($course_id);
            $router->halt(204);
        });

        // get 5 most recent forum-entries for passed seminar
        $router->get('/courses/:course_id/forum_newest', function ($course_id) use ($router) {
            if (!\ForumPerm::has('search', $course_id)) {
                $router->halt(401);
            }

            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;

            \ForumEntry::checkRootEntry($course_id);

            $entries = array();

            foreach (Forum::getLatestEntries($course_id, $limit) as $entry) {
                $entries[] = Forum::convertEntry($entry);
            }

            $result = array(
                'entries' => $entries,
            );

            $router->render($result);
        });

        $router->get('/forum_category/:category_id', function ($category_id) use ($router) {
            $category = Forum::findCategory($category_id, $router);
            $cid = $category['seminar_id'];

            if (!\ForumPerm::has('search', $cid)) {
                $router->halt(401);
            }

            $router->render(compact('category'));
        });


        $router->put('/forum_category/:category_id', function ($category_id) use ($router) {
            $category = Forum::findCategory($category_id, $router);

            if (!\ForumPerm::has("edit_category", $category['seminar_id'])) {
                $router->halt(401);
            }

            $name = Helper::Sanitize(Request::get('name'));

            if (!strlen($name = trim($name))) {
                $router->halt(400, 'Category name required.');
            }

            \ForumCat::setName($category_id, $name);

            $category = Forum::findCategory($category_id, $router);
            $router->render(compact('category'), 205);
        });


        $router->delete('/forum_category/:category_id', function ($category_id) use ($router) {
            $category = Forum::findCategory($category_id, $router);
            $cid = $category['seminar_id'];

            if (!\ForumPerm::has("remove_category", $cid)) {
                $router->halt(401);
            }

            \ForumCat::remove($category_id, $cid);

            $router->halt(204);
        });


        // Areas (= Bereiche)
        $router->get('/forum_category/:category_id/areas', function ($category_id) use ($router) {
            $category = Forum::findCategory($category_id, $router);

            if (!\ForumPerm::has('search', $category['seminar_id'])) {
                $router->halt(401);
            }

            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $total  = Forum::countAreas($category_id);

            $result = array(
                'areas'     => Forum::getAreas($category_id, $offset, $limit),
                'pagination' => $router->paginate($total, $offset, $limit, '/forum_category', $category_id, 'areas'),
            );

            $router->render($result, 200);
        });

        $router->post('/forum_category/:category_id/areas', function ($category_id) use ($router) {
            $category = Forum::findCategory($category_id, $router);
            $cid = $category['seminar_id'];

            if (!\ForumPerm::has('add_area', $cid)) {
                $router->halt(401);
            }

            $subject = Helper::Sanitize(Request::get('subject'));

            if (!strlen($subject = trim($subject))) {
                $router->halt(400, 'Subject required.');
            }

            $content = Helper::Sanitize(Request::get('content'));

            if (!strlen($content)) {
                $router->halt(400, 'Content required.');
            }
            $content = trim($content);

            $anonymous = Request::int('anonymous', 0);

            $entry_id = Forum::createEntry($cid, $cid, $subject, $content, $anonymous);

            \ForumCat::addArea($category_id, $entry_id);

            $entry = Forum::findEntry($entry_id, $router);
            $router->render(compact('entry'), 201);
        });


        // Postings (= Themen und Postings)
        $router->get('/forum_entry/:entry_id', function($entry_id) use ($router) {
            $entry = Forum::findEntry($entry_id, $router);
            $cid   = $entry['seminar_id'];

            if (!\ForumPerm::has('search', $cid)) {
                $router->halt(401);
            }

            $router->render(compact('entry'), 200);
        });

        $router->post('/forum_entry/:entry_id', function($parent_id) use ($router) {
            $entry = Forum::findEntry($parent_id, $router);
            $cid = $entry['seminar_id'];

            $perm = Forum::isArea($entry) ? 'add_area' : 'add_entry';

            if (!\ForumPerm::has($perm, $cid)) {
                $router->halt(401);
            }

            $subject = (string)trim(Helper::Sanitize(Request::get('subject')));
            $content = (string)trim(Helper::Sanitize(Request::get('content')));

            // areas and threads need a subject, postings do not
            if ($entry['depth'] < 2 && !$subject) {
                $router->halt(400, 'Subject required.');
            }

            // all entries besides the area need content
            if ($entry['depth'] > 1 && !$content) {
                $router->halt(400, 'Content required.');
            }

            if ($entry['depth'] >= 2 && $subject) {
                $router->halt(400, 'Must not have subject here.');
            }

            $anonymous = Request::int('anonymous', 0);

            $entry_id = Forum::createEntry($parent_id, $cid, $subject, $content, $anonymous);

            $entry = Forum::findEntry($entry_id, $router);
            $router->render(compact('entry'), 201);
        });

        $router->put('/forum_entry/:entry_id', function($entry_id) use ($router) {
            $entry = Forum::findEntry($entry_id, $router);
            $cid = $entry['seminar_id'];

            $perm = Forum::isArea($entry) ? 'edit_area' : 'edit_entry';

            if (!\ForumPerm::hasEditPerms($entry_id) || !\ForumPerm::has($perm, $cid)) {
                $router->halt(401);
            }

            $subject = (string)trim(Request::get('subject'));
            $content = (string)trim(Request::get('content'));

            // areas and threads need a subject, postings do not
            if ($entry['depth'] < 3 && !$subject) {
                $router->halt(400, 'Subject required.');
            }

            // all entries besides the area need content
            if ($entry['depth'] > 1 && !$content) {
                $router->halt(400, 'Content required.');
            }

            if ($entry['depth'] >= 3 && $subject) {
                $router->halt(400, 'Must not have subject here.');
            }

            \ForumEntry::update($entry_id, $subject, $content);

            $entry = Forum::findEntry($entry_id, $router);
            $router->render(compact('entry'), 205);
        });

        $router->delete('/forum_entry/:entry_id', function($entry_id) use ($router) {

            $entry = Forum::findEntry($entry_id, $router);
            $cid = $entry['seminar_id'];

            if (!\ForumPerm::hasEditPerms($entry_id) || !\ForumPerm::has('remove_entry', $cid)) {
                Forum::error(401);
            }

            \ForumEntry::delete($entry_id);
            $router->halt(204);
        });

        $router->get('/forum_entry/:entry_id/children', function($entry_id) use ($router) {
            $entry = Forum::findEntry($entry_id, $router);
            $cid   = $entry['seminar_id'];

            if (!\ForumPerm::has('search', $cid)) {
                $router->halt(401);
            }

            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $sort_ascending = Request::get('sort_ascending') ? true : false;

            $entries = Forum::getChildren($entry_id, $offset, $limit, $cid, $sort_ascending);
            $total   = sizeof($entries);

            $result = array(
                'entries'    => $entries,
                'pagination' => $router->paginate($total, $offset, $limit, '/forum_entry', $cid, 'children'),
            );

            $router->render($result);
        });
    }
}


class Forum {
    static function findEntry($entry_id, $router)
    {
        $raw = \ForumEntry::getConstraints($entry_id);
        if ($raw === false) {
            $router->halt(404);
        }

        return Forum::convertEntry($raw);
    }

    static function getChildren($entry_id, $start, $limit, $seminar_id, $sort_ascending = false) {
        $children = \ForumEntry::getEntries($entry_id, \ForumEntry::WITHOUT_CHILDS, '', $sort_ascending ? 'ASC' : 'DESC', $start ?: 0, $limit);

        if (isset($children['list'][$entry_id])) {
            unset($children['list'][$entry_id]);
        }

        $return_children = array();
        foreach (array_values($children['list']) as $childentry) {
            $childentry['seminar_id'] = $seminar_id;
            $return_children[] = Forum::convertEntry($childentry);
        }

        return $return_children;
    }

    public static function convertEntry($raw)
    {
        $entry = array();
        foreach(words("topic_id seminar_id mkdate chdate anonymous depth") as $key) {
            $entry[$key] = $raw[$key];
        }

        $last_visit = \ForumVisit::getLastVisit($raw['seminar_id']);

        $entry['subject']      = $raw['name'];
        $entry['subject_raw']      = $raw['name_raw'];
        $entry['content_html'] = formatReady(\ForumEntry::parseEdit($raw['content']));
        $entry['content']      = \ForumEntry::killEdit($raw['content']);
        $entry['user_id']      = $raw['user_id'] ?: $raw['owner_id'];
        $entry['new']          = ($raw['chdate'] >= $last_visit && $entry['user_id'] != $GLOBALS['user']->id) ? true : false;
        $entry['new_children'] = (int)\ForumVisit::getCount($raw['topic_id'], $last_visit);
        $entry['mkdate_iso']   = date('Y-m-d\TH:i:s', $entry['mkdate']);
        $entry['chdate_iso']   = date('Y-m-d\TH:i:s', $entry['chdate']);

        return $entry;
    }


    static function isArea($entry)
    {
        return 1 === $entry['depth'];
    }

    static function createEntry($parent_id, $course_id, $subject, $content, $anonymous)
    {
        $topic_id  = Forum::generateID();

        $data = array(
            'topic_id'    => $topic_id,
            'seminar_id'  => $course_id,
            'user_id'     => $GLOBALS['user']->id,
            'name'        => $subject,
            'content'     => $content,
            'author'      => $GLOBALS['user']->getFullName(),
            'author_host' => $_SERVER['REMOTE_ADDR'],
            'anonymous'   => (int) $anonymous
        );
        \ForumEntry::insert($data, $parent_id);

        return $topic_id;
    }

    static function findCategory($category_id, $router)
    {
        $result = array();

        if ($cat = \ForumCat::get($category_id)) {
            $result = $cat;
        } else {
            $router->halt(404);
        }

        return $result;
    }

    static function countAreas($category_id)
    {
        return sizeof(self::getCatAreas($category_id));
    }

    static function getAreas($category_id, $offset = 0, $limit = 10)
    {
        $offset = (int) $offset;
        $limit  = (int) $limit;

        $areas = array();

        foreach (self::getCatAreas($category_id, $offset, $limit) as $area) {
            $areas[] = Forum::convertEntry($area);
        }

        return $areas;
    }

    static function generateID()
    {
        return md5(uniqid(rand()));
    }


    static function getCatList($seminar_id)
    {
        $stmt = DBManager::get()->prepare("SELECT * FROM forum_categories
            WHERE seminar_id = ? ORDER BY pos ASC");
        $stmt->execute(array($seminar_id));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return the areas for the passed category_id
     *
     * @param type $category_id
     * @param type $start  limit start (optional)
     * @param type $limit  number of entries to fetch (optional, default is 20)
     *
     * @return array the data for the passed category_id
     */
    static function getCatAreas($category_id, $start = null, $num = 20)
    {
        $category = \ForumCat::get($category_id);

        $limit = '';
        if ($start !== null && $num) {
            $limit = " LIMIT $start, $num";
        }

        if ($category_id == $category['seminar_id']) {
            $stmt = DBManager::get()->prepare("SELECT fe.* FROM forum_entries AS fe
                LEFT JOIN forum_categories_entries AS fce USING (topic_id)
                WHERE seminar_id = ? AND depth = 1 AND (
                    fce.category_id = ? OR fce.category_id IS NULL
                ) ORDER BY category_id DESC, pos ASC" . $limit);
            $stmt->execute(array($category_id, $category_id));
        } else {
            $stmt = DBManager::get()->prepare("SELECT forum_entries.* FROM forum_categories_entries
                LEFT JOIN forum_entries USING(topic_id)
                WHERE category_id = ?
                ORDER BY pos ASC" . $limit);

            $stmt->execute(array($category_id));
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    static function getLatestEntries($course_id, $limit)
    {
        $stmt = DBManager::get()->prepare("SELECT * FROM forum_entries
            WHERE seminar_id = ?
            ORDER BY chdate DESC
            LIMIT 0, ?");

        $stmt->execute(array($course_id, $limit));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
