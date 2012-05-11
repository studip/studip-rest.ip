<?php
namespace RestIP;

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
            '/news(/range/:range_id)' => _('Ankündigungen einer Entität'),
            '/news/:news_id'          => _('Ankündigungen'),
        );
    }

    /**
     *
     **/
    public function routes(&$router)
    {
        require_once 'lib/classes/StudipNews.class.php';

        $router->get('/news(/range/:range_id)', function ($range_id = false) use ($router)
        {
            $range_id = $range_id ?: $GLOBALS['user']->id;

            if (!Helper::UserHasAccessToRange($range_id)) {
                $router->halt(403, sprintf('User may not access range %s', $range_id));
            }

            $news  = News::loadRange($range_id);
            $users = NewsRoute::extractUsers($news, $router);

            $router->render(compact('news', 'users'));
        })->conditions(array('range_id' => '(studip|[a-f0-9]{32})'));

        $router->post('/news(/range/:range_id)', function () use ($router) {
            $range_id = $range_id ?: $GLOBALS['user']->id;

            if (!Helper::UserHasAccessToRange($range_id)) {
                $router->halt(403, sprintf('User may not access range %s', $range_id));
            }

            $router->halt(501, 'Not implemented');
        })->conditions(array('range_id' => '(studip|[a-f0-9]{32})'));

        $router->get('/news/:news_id', function ($news_id) use ($router) {
            $news  = News::load($news_id);
            $users = NewsRoute::extractUsers($news, $router);
            
            $router->render(compact('news', 'users'));
        });

        $router->post('/news/:news_id', function ($news_id) use ($router) {
            $router->halt(501, 'Nope, no updating of news yet');
        });

        $router->delete('/news/:news_id', function ($news_id) use ($router) {
            \StudipNews::find($news_id)->delete();

            $router->halt(204);
        });
    }

    static function extractUsers($collection, $router)
    {
        $users = array();
        foreach ((array)$collection as $item) {
            if ($item['allow_comments']) {
                foreach ($item['comments'] as $comment) {
                    if (!isset($users[$comment['user_id']])) {
                        $users[$comment['user_id']] = reset($router->dispatch('get', '/user(/:user_id)', $comment['user_id']));
                    }
                }
            }

            if (!isset($users[$item['user_id']])) {
                $users[$item['user_id']] = reset($router->dispatch('get', '/user(/:user_id)', $item['user_id']));
            }
            if ($item['chdate_uid'] && !isset($users[$item['chdate_uid']])) {
                $users[$item['chdate_uid']] = reset($router->dispatch('get', '/user(/:user_id)', $item['chdate_uid']));
            }
        }
        return $users;
    }
}

class News
{
    static function adjust($news)
    {
        if (is_array($news)) {
            $news['body_original'] = $news['body'];
            $news['body']          = formatReady($news['body']);
            $news['chdate_uid']    = trim($news['chdate_uid']);

            unset($news['author']);

            if ($news['allow_comments']) {
                $news['comments'] = self::loadComments($news['news_id']);
            }
        }

        return $news;
    }

    static function load ($id)
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

    static function loadComments($news_id)
    {
        $query = "SELECT comment_id, content AS comment, mkdate, chdate, user_id
                  FROM comments
                  WHERE object_id = ?
                  ORDER BY mkdate";
        $statement = \DBManager::get()->prepare($query);
        $statement->execute(array($news_id));
        $comments = $statement->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($comments as &$comment) {
            $comment['comment_original'] = $comment['comment'];
            $comment['comment']          = formatReady($comment['comment']);
        }

        return $comments;
    }
}