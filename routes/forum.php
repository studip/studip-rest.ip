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
    }

    public function routes(&$router)
    {
        // Forums / Categories
        $router->get('/courses/:course_id/forum_categories', function ($course_id) use ($router) {
            if (!\ForumPerm::has('view', $course_id)) {
                $this->error(401);
            }

            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;

            \ForumEntry::checkRootEntry($course_id);

            $categories = \ForumCat::getList($course_id, false);
            $total      = sizeof($categories);
            $categories = array_splice($categories, $offset, $limit ?: 10);

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

            $category = $this->findCategory($category_id, $router);
            $router->render(compact('category'), 201);
        });


        $router->get('/forum_category/:category_id', function ($category_id) use ($router) {
            $category = $this->findCategory($category_id, $router);
            $cid = $category['seminar_id'];

            if (!\ForumPerm::has('view', $cid)) {
                $router->halt(401);
            }

            $router->render(compact('category'));
        });


        $router->put('/forum_category/:category_id', function ($category_id) use ($router) {
            $category = $this->findCategory($category_id, $router);

            if (!\ForumPerm::has("edit_category", $category['seminar_id'])) {
                $router->halt(401);
            }

            $name = Helper::Sanitize(Request::get('name'));

            if (!strlen($name = trim($name))) {
                $router->halt(400, 'Category name required.');
            }

            \ForumCat::setName($category_id, $name);

            $category = $this->findCategory($category_id, $router);
            $router->render(compact('category'), 205);
        });


        $router->delete('/forum_category/:category_id', function ($category_id) use ($router) {
            $category = $this->findCategory($category_id, $router);
            $cid = $category['seminar_id'];

            if (!\ForumPerm::has("remove_category", $cid)) {
                $router->halt(401);
            }

            \ForumCat::remove($category_id, $cid);

            $router->render(null, 204);
        });


        // Areas (= Bereiche)
        $router->get('/forum_category/:category_id/areas', function ($category_id) use ($router) {
            $category = $this->findCategory($category_id, $router);

            if (!\ForumPerm::has('view', $category['seminar_id'])) {
                $router->halt(401);
            }

            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $total  = $this->countAreas($category_id);

            $areas = 

            $result = array(
                'areas'     => $this->getAreas($category_id, $offset, $limit),
                'pagination' => $router->paginate($total, $offset, $limit, '/forum_category', $category_id, 'areas'),
            );

            $router->render($result, 200);
        });

        $router->post('/forum_category/:category_id/areas', function ($category_id) use ($router) {
            $category = $this->findCategory($category_id, $router);
            $cid = $category['seminar_id'];

            if (!\ForumPerm::has('add_area', $cid)) {
                $router->halt(401);
            }

            $subject = Request::get('subject');

            if (!strlen($subject = trim($subject))) {
                $router->halt(400, 'Subject required.');
            }

            $content = Request::get('content');

            if (!strlen($content)) {
                $router->halt(400, 'Content required.');
            }
            $content = trim($content);

            $anonymous = Request::int('anonymous', 0);

            $entry_id = $this->createEntry($cid, $cid, $subject, $content, $anonymous);

            \ForumCat::addArea($category_id, $entry_id);

            $entry = $this->findEntry($entry_id, $router);
            $router->render(compact('entry'), 201);
        });


        // Postings (= Themen und Postings)
        $router->get('/forum_entry/:entry_id', function($entry_id) use ($router) {
            $entry = $this->findEntry($entry_id, $router);
            $cid   = $entry['seminar_id'];

            if (!\ForumPerm::has('view', $cid)) {
                $router->halt(401);
            }

            $router->render(compact('entry'), 200);
        });

        $router->post('/forum_entry/:entry_id', function($parent_id) use ($router) {
            $entry = $this->findEntry($parent_id, $router);
            $cid = $entry['seminar_id'];

            $perm = self::isArea($entry) ? 'add_area' : 'add_entry';

            if (!\ForumPerm::has($perm, $cid)) {
                die;
                $router->halt(401);
            }

            $subject = (string)trim(Request::get('subject'));
            $content = (string)trim(Request::get('content'));

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

            $entry_id = $this->createEntry($parent_id, $cid, $subject, $content, $anonymous);

            $entry = $this->findEntry($entry_id, $router);
            $router->render(compact('entry'), 201);
        });

        $router->put('/forum_entry/:entry_id', function($entry_id) use ($router) {
            $entry = $this->findEntry($entry_id, $router);
            $cid = $entry['seminar_id'];

            $perm = self::isArea($entry) ? 'edit_area' : 'edit_entry';

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

            $entry = $this->findEntry($entry_id, $router);
            $router->render(compact('entry'), 205);
        });

        $router->delete('/forum_entry/:entry_id', function($entry_id) use ($router) {

            $entry = $this->findEntry($entry_id, $router);
            $cid = $entry['seminar_id'];

            if (!\ForumPerm::hasEditPerms($entry_id) || !\ForumPerm::has('remove_entry', $cid)) {
                $this->error(401);
            }

            \ForumEntry::delete($entry_id);
            $router->render(null, 204);
        });
    }

    /*********************
     *                   *
     * PRIVATE FUNCTIONS *
     *                   *
     *********************/


    private function findEntry($entry_id, $router)
    {
        $raw = \ForumEntry::getConstraints($entry_id);
        if ($raw === false) {
            $router->halt(404);
        }

        $entry = $this->convertEntry($raw);

        $children = \ForumEntry::getEntries($entry_id, \ForumEntry::WITHOUT_CHILDS, '', 'ASC', 0, false);

        if (isset($children['list'][$entry_id])) {
            unset($children['list'][$entry_id]);
        }

        $entry['children'] = array();
        foreach (array_values($children['list']) as $childentry) {
            $entry['children'][] = $this->convertEntry($childentry);
        }

        return $entry;
    }

    public function convertEntry($raw)
    {
        $entry = array();
        foreach(words("topic_id seminar_id mkdate chdate anonymous depth") as $key) {
            $entry[$key] = $raw[$key];
        }

        $entry['subject']      = $raw['name'];
        $entry['content_html'] = \ForumEntry::getContentAsHtml($raw['content']);
        $entry['content']      = \ForumEntry::killEdit($raw['content']);

        return $entry;
    }


    private static function isArea($entry)
    {
        return 1 === $entry['depth'];
    }

    private function createEntry($parent_id, $course_id, $subject, $content, $anonymous)
    {
        $topic_id  = self::generateID();

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

    private function findCategory($category_id, $router)
    {
        $result = array();

        if ($cat = \ForumCat::get($category_id)) {
            $result = $cat;
        } else {
            $router->halt(404);
        }

        return $result;
    }

    private function countAreas($category_id)
    {
        return sizeof(\ForumCat::getAreas($category_id));
    }

    private function getAreas($category_id, $offset = 0, $limit = 10)
    {
        $offset = (int) $offset;
        $limit  = (int) $limit;

        $areas = array();

        foreach (\ForumCat::getAreas($category_id, $offset, $limit) as $area) {
            $areas[] = $this->convertEntry($area);
        }

        return $areas;
    }

    private static function generateID()
    {
        return md5(uniqid(rand()));
    }
}
