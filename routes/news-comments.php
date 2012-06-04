<?php
# namespace RestIP;

# use \Request;

/**
 *
 **/
class NewsCommentsRoute implements APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/news/:news_id/comments'             => _('Kommentare zu einer Ankündigung'),
            '/news/:news_id/comments/:comment_id' => _('Kommentardaten'),
        );
    }

    public static function before()
    {
        require_once 'lib/classes/StudipNews.class.php';
        require_once 'lib/classes/StudipComments.class.php';
    }

    /**
     *
     **/
    public function routes(&$router)
    {
    // Comments
        // Load comments for a news
        $router->get('/news/:news_id/comments', function ($news_id) use ($router) {
            $news = StudipNews::find($news_id);
            if (!$news) {
                $router->halt(404, sprintf('News "%s" not found', $news_id));
            }

            if (!$news->allow_comments) {
                $router->halt(406, sprintf('Comments are disabled for news "%s"', $news_id));
            }

            $comments = NewsComments::loadByNewsId($news_id);

            if ($router->compact()) {
                $router->render(compact('comments'));
                return;
            }

            $users = NewsCommentsRoute::extractUsers($comments, $router);
            $router->render(compact('comments', 'users'));
        });

        // Create comment for a news
        $router->post('/news/:news_id/comments', function ($news_id) use ($router) {
            $content = trim(Request::get('content'));
            if (empty($content)) {
                $router->halt(406, 'No comment provided');
            }

            $news = StudipNews::find($news_id);
            if (!$news) {
                $router->halt(404, sprintf('News "%s" not found', $news_id));
            }

            $comment = new StudipComments();
            $comment->object_id = $news_id;
            $comment->user_id   = $GLOBALS['user']->id;
            $comment->content   = $content;
            if (!$comment->store()) {
                $router->halt(500, 'Could not create comment for news "%s"', $news_id);
            }

            $router->render($router->dispatch('get', '/news/:news_id/comments/:comment_id', $news_id, $comment->comment_id), 201);
        });

        // Load comment
        $router->get('/news/:news_id/comments/:comment_id', function ($news_id, $comment_id) use ($router) {
            $news = StudipNews::find($news_id);
            if (!$news) {
                $router->halt(404, 'News "%s" not found', $news_id);
            }

            $comment = NewsComments::load($news_id, $comment_id);
            if (!$comment) {
                $router->halt(404, 'Comment "%s" for news "%s" not found', $comment, $news_id);
            }

            if ($router->compact()) {
                $router->render(compact('comment'));
                return;
            }

            $users = NewsCommentsRoute::extractUsers(array($comments), $router);
            $router->render(compact('comment', 'users'));
        });

        // Remove news comment
        $router->delete('/news/:news_id/comments/:comment_id', function ($news_id, $comment_id) use ($router) {
            $news = StudipNews::find($news_id);
            if (!$news) {
                $router->halt(404, 'News "%s" not found', $news_id);
            }

            $comment = StudipComments::find($comment_id);
            if (!$comment) {
                $router->halt(404, 'Comment "%s" for news "%s" not found', $comment_id, $news_id);
            }

            if (!$comment->delete()) {
                $router->halt(500, 'Comment "%s" for news "%s" could not be deleted.', $comment_id, $news_id);
            }

            $router->halt(200, 'Deleted comment "%s" for news "%s"', $comment_id, $news_id);
        });
    }

    static function extractUsers($collection, $router)
    {
        $users = array();
        foreach ((array)$collection as $item) {
            if (!isset($users[$item['user_id']])) {
                $user = $router->dispatch('get', '/user(/:user_id)', $item['user_id']);
                $users[$item['user_id']] = $user['user'];
            }
        }
        return array_values($users);
    }
}

class NewsComments
{
    /* StudipComments does not really give us all the info we need */
    static function load($news_id, $comment_id)
    {
        $query = "SELECT comment_id, content AS comment, mkdate, chdate, user_id
                  FROM comments
                  WHERE object_id = ? AND comment_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($news_id, $comment_id));
        $comment = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            return false;
        }

        $comment['comment_original'] = $comment['comment'];
        $comment['comment']          = formatReady($comment['comment']);

        return $comment;
    }

    static function loadByNewsId($news_id)
    {
        $query = "SELECT comment_id, content AS comment, mkdate, chdate, user_id
                  FROM comments
                  WHERE object_id = ?
                  ORDER BY mkdate";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($news_id));
        $comments = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($comments as &$comment) {
            $comment['comment_original'] = $comment['comment'];
            $comment['comment']          = formatReady($comment['comment']);
        }

        return $comments;
    }
}