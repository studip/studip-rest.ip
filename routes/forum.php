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
        if (strcmp($GLOBALS['SOFTWARE_VERSION'], '2.4') < 0) {
            include 'models/forum-old.php';
        } else {
            include 'models/forum-new.php';
        }
    }

    public function routes(&$router)
    {
        $router->get('/courses/:course_id/forums', function ($course_id) use ($router) {
            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $forums = array(
                $course_id => _('Forum der Veranstaltung'),
            );
            $pagination = array('offset' => 0, 'limit' => $limit, 'total' => 1);
            $router->render(compact('forums', 'pagination'));
        });

        $router->post('/courses/:course_id/forums', function ($course_id) use ($router) {
            $router->halt(501);
        });

        $router->get('/courses/:course_id/forum/:forum_id', function ($course_id, $forum_id) use ($router) {
            $forum = array(
                'forum_id' => $course_id,
                'name'     => _('Forum der Veranstaltung'),
            );
            $router->render(compact('forum'));
        });

        $router->put('/courses/:course_id/forum/:forum_id', function ($course_id, $forum_id) use ($router) {
            $router->halt(501);
        });

        $router->delete('/courses/:course_id/forum/:forum_id', function ($course_id, $forum_id) use ($router) {
            $router->halt(501);
        });

        $router->get('/courses/:course_id/forum/:forum_id/topics', function ($course_id, $forum_id) use ($router) {
            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $forum  = Forum::get($course_id);
            $total  = $forum->countTopics();

            $result = array(
                'topics' => $forum->getTopics($forum_id, $offset, $limit),
            );

            if ($pagination = $router->paginate($total, $offset, $limit, '/courses', $course_id, 'forum', $forum_id, 'topics')) {
                $result['pagination'] = $pagination;
            }

            $router->render($result);
        });

        $router->post('/courses/:course_id/forum/:forum_id/topics', function ($course_id, $forum_id) use ($router) {
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id)) {
                $router->halt(403, 'Forbidden');
            }
            $forum     = Forum::get($course_id);
            $subject   = Helper::Sanitize(Request::get('subject'));
            $content   = Helper::Sanitize(Request::get('content'));
            $anonymous = Request::int('anonymous', 0) > 0;

            $topic_id = $forum->insertTopic($subject, $content, $forum_id, $anonymous);
            if (!$topic_id) {
                $router->halt(500, 'Error creating the topic.');
            }

            $topic = $forum->getTopic($forum_id, $topic_id);
            $router->render(compact('topic'), 201);
        });

        $router->get('/courses/:course_id/forum/:forum_id/topic/:topic_id', function ($course_id, $forum_id, $topic_id) use ($router) {
            $topic = Forum::get($course_id)->getTopic($forum_id, $topic_id);
            $router->render(compact('topic'));
        });

        $router->put('/courses/:course_id/forum/:forum_id/topic/:topic_id', function ($course_id, $forum_id, $topic_id) use ($router) {
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id)) {
                $router->halt(403);
            }

        });

        $router->delete('/courses/:course_id/forum/:forum_id/topic/:topic_id', function ($course_id, $forum_id, $topic_id) use ($router) {
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id)) {
                $router->halt(403);
            }
            $forum = Form::get($course_id);
            if (!$forum->deleteTopic($forum_id, $topic_id)) {
                $router->halt(500, 'Could not delete topic.');
            }
            $router->render(array('topic' => null), 205);
        });

        $router->get('/courses/:course_id/forum/:forum_id/topic/:topic_id/posts', function ($course_id, $forum_id, $topic_id) use ($router) {
            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $forum  = Forum::get($course_id);
            $total  = $forum->countPosts($forum_id, $topic_id);

            $result = array(
                'posts' => $forum->getPosts($forum_id, $topic_id, $offset, $limit),
            );

            if ($pagination = $router->paginate($total, $offset, $limit, '/courses', $course_id, 'forum', $forum_id, 'topic', $topic_id, 'posts')) {
                $result['pagination'] = $pagination;
            }

            $router->render($result);
        });

        $router->post('/courses/:course_id/forum/:forum_id/topic/:topic_id/posts', function ($course_id, $forum_id, $topic_id) use ($router) {
            $forum     = Forum::get($course_id);
            $subject   = Helper::Sanitize(Request::get('subject'));
            $content   = Helper::Sanitize(Request::get('content'));
            $parent_id = Request::option('parent_id', $topic_id);
            $anonymous = Request::int('anonymous', 0) > 0;

            $post_id = $forum->insertPost($forum_id, $topic_id, $subject, $content, compact('parent_id', 'anonymous'));
            if (!$post_id) {
                $router->halt(500, 'Error creating the post.');
            }

            $post = $forum->getPost($forum_id, $topic_id, $post_id);
            $router->render(compact('post'), 201);
        });

        $router->get('/courses/:course_id/forum/:forum_id/topic/:topic_id/post/:post_id', function ($course_id, $forum_id, $topic_id, $post_id) use ($router) {
            $post = Forum::get($course_id)->getPost($forum_id, $topic_id, $post_id);
            $router->render(compact('post'));
        });

        $router->put('/courses/:course_id/forum/:forum_id/topic/:topic_id/post/:post_id', function ($course_id, $forum_id, $topic_id, $post_id) use ($router) {
            $forum = Forum::get($course_id);
            if ($forum->postHasReplies($forum_id, $topic_id, $post_id)) {
                $router->halt(409, 'Post cannot be updated since it already has replies.');
            }
            $subject = Helper::Sanitize(Request::get('subject'));
            $content = Helper::Sanitize(Request::get('content'));
            if (!$forum->setPost($forum_id, $topic_id, $post_id, $subject, $content)) {
                $router->halt(500, 'Could not update post');
            }

            $post = $forum->getPost($forum_id, $topic_id, $post_id);
            $router->render(compact('post'), 205);
        });

        $router->delete('/courses/:course_id/forum/:forum_id/topic/:topic_id/post/:post_id', function ($course_id, $forum_id, $topic_id, $post_id) use ($router) {
            $forum = Forum::get($course_id);
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id) && $forum->postHasReplies($forum_id, $topic_id, $post_id)) {
                $router->halt(409, 'Post cannot be deleted since it already has replies.');
            }
            if (!$forum->deletePost($forum_id, $topic_id, $post_id)) {
                $router->halt(500, 'Could not delete post.');
            }

            $router->render(array('post' => null), 205);
        });
    }
}
