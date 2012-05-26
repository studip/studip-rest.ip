<?php
namespace RestIP;

use \Request;

/**
 *
 **/
class NewsRoute implements \APIPlugin
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
        $router->get('/news(/range/:range_id)', function ($range_id = false) use ($router)
        {
            $range_id = $range_id ?: $GLOBALS['user']->id;

            if (!Helper::UserHasAccessToRange($range_id)) {
                $router->halt(403, sprintf('User may not access range %s', $range_id));
            }

            $news  = array_values(News::loadRange($range_id));

            if ($router->compact()) {
                $router->render(compact('news'));
                return;
            }

            foreach ($news as $index => $n) {
                if ($n['allow_comments']) {
                    $comments = $router->dispatch('get', '/news/:news_id/comments', $n['news_id']);
                    $news[$index]['comments'] = $comments['comments'];
                }
            }
            $users = array_values(NewsRoute::extractUsers($news, $router));

            $router->render(compact('news', 'users'));
        })->conditions(array('range_id' => '(studip|[a-f0-9]{32})'));

        // Create news for a specific range
        $router->post('/news(/range/:range_id)', function () use ($router) {
            $range_id = $range_id ?: $GLOBALS['user']->id;

            if (!Helper::UserHasAccessToRange($range_id)) {
                $router->halt(403, sprintf('User may not access range %s', $range_id));
            }

            $title = trim(Request::get('title'));
            if (empty($title)) {
                $router->halt(406, 'No news title provided');
            }

            $body = trim(Request::get('body'));
            if (empty($body)) {
                $router->halt(406, 'No news body provided');
            }

            $news = new \StudipNews();
            $news->user_id        = $GLOBALS['user']->id;
            $news->author         = $GLOBALS['user']->getFullName();
            $news->topic          = $title;
            $news->body           = $body;
            $news->date           = time();
            $news->expire         = Request::int('expire', 2 * 7 * 24 * 60 * 60);
            $news->allow_comments = Request::int('allow_comments', 0);
            if (!$news->store()) {
                $router->halt(501, 'Could not create news');
            }

            $news->addRange($range_id);
            $news->storeRanges();

            $router->render($router->dispatch('get', '/news/:news_id', $news->news_id), 201);
        })->conditions(array('range_id' => '(studip|[a-f0-9]{32})'));

        // Get news data
        $router->get('/news/:news_id', function ($news_id) use ($router) {
            $news  = News::load($news_id);
            if (!$news) {
                $router->halt(404, sprintf('News %s not found', $news_id));
            }

            if ($router->compact()) {
                $router->render(compact('news'));
                return;
            }

            $users = NewsRoute::extractUsers(array($news), $router);
            if ($news['allow_comments']) {
                $news['comments'] = reset($router->dispatch('get', '/news/:news_id/comments', $news_id));
            }
            $router->render(compact('news', 'users'));
        });

        // Update news
        $router->put('/news/:news_id', function ($news_id) use ($router) {
            global $_PUT;
            
            $news = new \StudipNews($news_id);
            if (!$news) {
                $router->halt(404, sprintf('News %s not found', $news_id));
            }

/*
            // TODO Check access
            if (!Helper::UserHasAccessToRange($range_id)) {
                $router->halt(403, sprintf('User may not access range %s', $range_id));
            }
*/
            if (isset($_PUT['title'])) {
                $title = trim($_PUT['title']);
                if (empty($title)) {
                    $router->halt(406, 'No news title provided');
                }
                $news->topic = $title;
            }

            if (isset($_PUT['body'])) {
                $body = trim($_PUT['body']);
                if (empty($body)) {
                    $router->halt(406, 'No news body provided');
                }
                $news->body = $body;
            }

            // date?

            if (isset($_PUT['expire'])) {
                $news->expire = $_PUT['expire'] ?: $news->expire;
            }
            if (isset($_PUT['allow_comments'])) {
                $news->allow_comments = (int)$_PUT['allow_comments'];
            }

            if (!$news->store()) {
                $router->halt(501, 'Could not update news');
            }

            $router->render($router->dispatch('get', '/news/:news_id', $news->news_id), 201);
        });

        // Delete news
        $router->delete('/news/:news_id', function ($news_id) use ($router) {
            $news = \StudipNews::find($news_id);
            if (!$news) {
                $router->halt(404, sprintf('News %s not found', $news_id));
            }

            $news->delete();
            $router->halt(200, sprintf('Deleted news %s.', $news_id));
        });
    }

    static function extractUsers($collection, $router)
    {
        $users = array();
        foreach ((array)$collection as $item) {
            if (!empty($item['comments'])) {
                foreach ($item['comments'] as $comment) {
                    if (!isset($users[$comment['user_id']])) {
                        $user = $router->dispatch('get', '/user(/:user_id)', $comment['user_id']);
                        $users[$comment['user_id']] = $user['user'];
                    }
                }
            }

            if (!isset($users[$item['user_id']])) {
                $user = $router->dispatch('get', '/user(/:user_id)', $item['user_id']);
                $users[$item['user_id']] = $user['user'];
            }
            if ($item['chdate_uid'] && !isset($users[$item['chdate_uid']])) {
                $user = $router->dispatch('get', '/user(/:user_id)', $item['chdate_uid']);
                $users[$item['chdate_uid']] = $user['user'];
            }
        }
        return $users;
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

        return $news;
    }

    static function load($id)
    {
        $result = array();
        foreach ((array)$id as $i) {
            $news = \StudipNews::find($i);
            $news = self::adjust($news);
            $result[] = $news;
        }

        return is_array($id) ? $result : reset($result);
    }

    static function loadRange($range_id)
    {
        $news = \StudipNews::GetNewsByRange($range_id);
        $news = array_map('self::adjust', $news);

        return $news;
    }
}