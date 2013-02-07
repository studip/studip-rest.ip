<?php
namespace RestIP;

use \APIPlugin, \Request, \DBManager, \PDO, \StudipNews;

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

            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $total  = NewsComments::countByNewsId($news_id);


            $result = array(
                'comments'   => NewsComments::loadByNewsId($news_id, $offset, $limit),
                'pagination' => $router->paginate($total, $offset, $limit, '/news', $news_id, 'comments'),
            );

            $router->render($result);
        });

        // Create comment for a news
        $router->post('/news/:news_id/comments', function ($news_id) use ($router) {
            $news = StudipNews::find($news_id);
            if (!$news) {
                $router->halt(404, sprintf('News "%s" not found', $news_id));
            }

            $comment = new StudipComments();
            $comment->object_id = $news_id;
            $comment->user_id   = $GLOBALS['user']->id;
            $comment->content   = Helper::Sanitize(Request::get('content'));

            if (empty($comment->content)) {
                $router->halt(406, 'No comment provided');
            }

            if (!$comment->store()) {
                $router->halt(500, 'Could not create comment for news "%s"', $news_id);
            }

            $comment = NewsComments::load($news_id, $comment->comment_id);

            $router->render(compact('comment'), 201);
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

            $router->render(compact('comment'));
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

            $router->render(array('comment' => null), 205);
        });
    }
}

class NewsComments
{
    /* StudipComments does not really give us all the info we need */
    static function load($news_id, $comment_id)
    {
        $query = "SELECT comment_id, content AS comment, mkdate, chdate, user_id, object_id AS news_id
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

    public static function countByNewsId($news_id)
    {
        $query = "SELECT COUNT(*)
                  FROM comments
                  WHERE object_id = :news_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':news_id', $news_id);
        $statement->execute();
        return $statement->fetchColumn() ?: 0;
    }

    public static function loadByNewsId($news_id, $offset = 0, $limit = 10)
    {
        $offset = (int) $offset;
        $limit  = (int) $limit;

        $query = "SELECT comment_id, content AS comment, mkdate, chdate, user_id, object_id AS news_id
                  FROM comments
                  WHERE object_id = :news_id
                  ORDER BY mkdate
                  LIMIT {$offset}, {$limit}";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':news_id', $news_id);
        $statement->execute();
        $comments = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($comments as &$comment) {
            $comment['comment_original'] = $comment['comment'];
            $comment['comment']          = formatReady($comment['comment']);
        }

        return $comments;
    }
}