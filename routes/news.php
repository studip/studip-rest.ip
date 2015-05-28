<?php
namespace RestIP;
use \Request, \DBManager, \PDO, \APIPlugin, \StudipNews;

/**
 *
 **/
class NewsRoute implements APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/news(/range/:range_id)' => _('Ankündigungen einer Range'),
            '/news/:news_id'          => _('Ankündigungen'),
        );
    }

    public static function before()
    {
        require_once 'lib/classes/StudipNews.class.php';
    }

    /**
     *
     **/
    public function routes(&$router)
    {
        // Get news of a range id
        $router->get('/news(/range/:range_id)', function ($range_id = false) use ($router) {
            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $total  = News::countRange($range_id);

            $range_id = $range_id ?: $GLOBALS['user']->id;
            if (!Helper::UserHasAccessToRange($range_id)) {
                $router->halt(403, sprintf('User may not access range "%s"', $range_id));
            }
            $last_visit = object_get_visit($range_id, "news");
            $result = array(
                'news'       => News::loadRange($range_id, $offset, $limit, $last_visit),
                'pagination' => $router->paginate($total, $offset, $limit, '/news/range', $range_id),
            );

            $router->render($result);
        })->conditions(array('range_id' => '(studip|[a-f0-9]{32})'));

        // Create news for a specific range
        $router->post('/news(/range/:range_id)', function ($range_id = null) use ($router) {
            $range_id = $range_id ?: $GLOBALS['user']->id;

            if (!Helper::UserHasAccessToRange($range_id)) {
                $router->halt(403, sprintf('User may not access range %s', $range_id));
            }

            $news = new StudipNews();
            $news->user_id        = $GLOBALS['user']->id;
            $news->author         = $GLOBALS['user']->getFullName();
            $news->topic          = Helper::Sanitize(Request::get('title'));
            $news->body           = Helper::Sanitize(Request::get('body'));
            $news->date           = time();
            $news->expire         = Request::int('expire', 2 * 7 * 24 * 60 * 60);
            $news->allow_comments = Request::int('allow_comments', 0);

            if (empty($news->topic)) {
                $router->halt(406, 'No news title provided');
            }
            if (empty($news->body)) {
                $router->halt(406, 'No news body provided');
            }

            if (!$news->store()) {
                $router->halt(500, 'Could not create news');
            }

            $news->addRange($range_id);
            $news->storeRanges();
            $news = $news->toArray();

            header('Cache-Control: private');
            $router->expires('+6 hours');
            $router->render(compact('news'), 201);
        })->conditions(array('range_id' => '(studip|[a-f0-9]{32})'));

        // Get news data
        $router->get('/news/:news_id', function ($news_id) use ($router) {
            $news  = News::load($news_id);
            if (!$news) {
                $router->halt(404, sprintf('News %s not found', $news_id));
            }

            $router->render(compact('news'));
        });

        // Update news
        $router->put('/news/:news_id', function ($news_id) use ($router) {
            global $_PUT;

            $news = new StudipNews($news_id);
            if (!$news) {
                $router->halt(404, sprintf('News %s not found', $news_id));
            }

/*
            // TODO Check access
            if (!Helper::UserHasAccessToRange($range_id)) {
                $router->halt(403, sprintf('User may not access range %s', $range_id));
            }
*/

            $news->topic  = Helper::Sanitize(Request::get('title'));
            $news->body   = Helper::Sanitize(Request::get('body'));

            if (isset($_PUT['expire'])) {
                $news->expire = Request::int('expire');
            }
            if (isset($_PUT['allow_comments'])) {
                $news->allow_comments = Request::int('allow_comments', 0);
            }

            $news->chdate     = time();
            $news->chdate_uid = $GLOBALS['user']->id;

            if (empty($news->topic)) {
                $router->halt(406, 'No news title provided');
            }
            if (empty($news->body)) {
                $router->halt(406, 'No news body provided');
            }

            if (!$news->store()) {
                $router->halt(500, 'Could not update news');
            }

            $news = News::load($news_id);
            $router->render(compact('news'));
        });

        // Delete news
        $router->delete('/news/:news_id', function ($news_id) use ($router) {
            $news = StudipNews::find($news_id);
            if (!$news) {
                $router->halt(404, sprintf('News %s not found', $news_id));
            }

            $news->delete();
            $router->render(array('news' => null), 205);
        });
    }
}

class News
{
    static function adjust($news)
    {
        if (!is_array($news)) {
            $news = $news->toArray();
        }

        $news['body_original'] = $news['body'];
        $news['body']          = formatReady($news['body']);
        $news['chdate_uid']    = trim($news['chdate_uid']);

        unset($news['author']);

        // Add comment ids
        if ($news['allow_comments']) {
            $query = "SELECT comment_id FROM comments WHERE object_id = ? ORDER BY mkdate";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array($news['news_id']));
            $news['comments'] = $statement->fetchAll(PDO::FETCH_COLUMN);
        }

        return $news;
    }

    static function load($id)
    {
        $result = array();
        foreach ((array)$id as $i) {
            if ($news = StudipNews::find($i)) {
                $news = self::adjust($news);
            }
            $result[] = $news;
        }

        return is_array($id) ? $result : reset($result);
    }

    public static function countRange($range_id)
    {
        $query = "SELECT COUNT(*)
                  FROM news_range
                  INNER JOIN news USING (news_id)
                  WHERE range_id = :range_id
                    AND UNIX_TIMESTAMP() BETWEEN `date` AND `date` + expire";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':range_id', $range_id);
        $statement->execute();

        return $statement->fetchColumn();
    }

    public static function loadRange($range_id, $offset = 0, $limit = 10, $last_visit = 7776000)
    {
        $offset = (int) $offset;
        $limit  = (int) $limit;
        $news = array();

        $query = "SELECT news_id, topic, body, `date`, user_id, expire,
                         allow_comments, chdate, chdate_uid, mkdate
                  FROM news_range
                  INNER JOIN news USING (news_id)
                  WHERE range_id = :range_id
                    AND UNIX_TIMESTAMP() BETWEEN `date` AND `date` + expire
                  ORDER BY `date` DESC, chdate DESC, topic ASC
                  LIMIT {$offset}, {$limit}";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':range_id', $range_id);
        $statement->execute();

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach($result as $ne) {
            $ne['new'] = $ne['chdate'] >= $last_visit;
            $news[]    = $ne;
        }
        $news = array_map('self::adjust', $news);

        return $news;
    }
}
