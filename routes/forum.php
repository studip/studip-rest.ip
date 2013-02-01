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
            require dirname(__FILE__) . '/../models/forum-old.php';
        } else {
            require dirname(__FILE__) . '/../models/forum-new.php';
        }
    }

    public function routes(&$router)
    {
        // Forums / Categories
        $router->get('/courses/:course_id/forums', function ($course_id) use ($router) {
            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $forum  = Forum::Get($course_id);
            $total  = $forum->countForums();

            $result = array(
                'forums'     => $forum->getForums($offset, $limit),
                'pagination' => $router->paginate($total, $offset, $limit, '/courses', $course_id, 'forums'),
            );

            $router->render($result);
        });

        $router->post('/courses/:course_id/forums', function ($course_id) use ($router) {
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id)) {
                $router->halt(403);
            }
            $name  = Helper::Sanitize(Request::get('name'));
            $forum = Forum::get($course_id);

            $forum_id = $forum->insertForum($name);
            if (!$forum_id) {
                $router->halt(500, 'Error creating the forum.');
            }

            $forum = $forum->getForum($forum_id);
            $router->render(compact('forum'), 201);
        });

        $router->get('/courses/:course_id/forum/:forum_id', function ($course_id, $forum_id) use ($router) {
            $forum = Forum::Get($course_id)->getForum($forum_id);
            $router->render(compact('forum'));
        });

        $router->put('/courses/:course_id/forum/:forum_id', function ($course_id, $forum_id) use ($router) {
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id)) {
                $router->halt(403);
            }
            $name  = Helper::Sanitize(Request::get('name'));
            $forum = Forum::Get($course_id);
            if (!$forum->setForum($forum_id, $name)) {
                $router->halt(500, 'Could not update forum');
            }

            $forum = $forum->getForum($forum_id);
            $router->render(compact('forum'), 205);
        });

        $router->delete('/courses/:course_id/forum/:forum_id', function ($course_id, $forum_id) use ($router) {
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id)) {
                $router->halt(403);
            }
            $forum = Forum::get($course_id);
            if (!$forum->deleteForum($forum_id)) {
                $router->halt(500, 'Could not delete forum.');
            }
            $router->render(array('forum' => null), 205);
        });

        // Topics
        $router->get('/courses/:course_id/forum/:forum_id/topics', function ($course_id, $forum_id) use ($router) {
            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $forum  = Forum::get($course_id);
            $total  = $forum->countTopics($forum_id);

            $result = array(
                'topics'     => $forum->getTopics($forum_id, $offset, $limit),
                'pagination' => $router->paginate($total, $offset, $limit, '/courses', $course_id, 'forum', $forum_id, 'topics'),
            );

            $router->render($result);
        });

        $router->post('/courses/:course_id/forum/:forum_id/topics', function ($course_id, $forum_id) use ($router) {
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id)) {
                $router->halt(403);
            }
            $forum     = Forum::get($course_id);
            $subject   = Helper::Sanitize(Request::get('subject'));
            $content   = Helper::Sanitize(Request::get('content'));
            $anonymous = Request::int('anonymous', 0) > 0;

            $topic_id = $forum->insertTopic($forum_id, $subject, $content, compact('anonymous'));
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
            $forum     = Forum::get($course_id);
            $subject   = Helper::Sanitize(Request::get('subject'));
            $content   = Helper::Sanitize(Request::get('content'));

            if (!$forum->setTopic($forum_id, $topic_id, $subject, $content)) {
                $router->halt(500, 'Could not update topic.');
            }

            $topic = $forum->getTopic($forum_id, $topic_id);
            $router->render(compact('topic'), 205);
        });

        $router->delete('/courses/:course_id/forum/:forum_id/topic/:topic_id', function ($course_id, $forum_id, $topic_id) use ($router) {
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id)) {
                $router->halt(403);
            }
            $forum = Forum::get($course_id);
            if ($forum->deleteTopic($forum_id, $topic_id) === false) {
                $router->halt(500, 'Could not delete topic.');
            }
            $router->render(array('topic' => null), 205);
        });

        // Threads
        $router->get('/courses/:course_id/forum/:forum_id/topic/:topic_id/threads', function ($course_id, $forum_id, $topic_id) use ($router) {
            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $forum  = Forum::get($course_id);
            $total  = $forum->countThreads($forum_id, $topic_id);

            $result = array(
                'threads'    => $forum->getThreads($forum_id, $topic_id, $offset, $limit),
                'pagination' => $router->paginate($total, $offset, $limit, '/courses', $course_id, 'forum', $forum_id, 'topic', $topic_id, 'threads'),
            );

            $router->render($result);
        });

        $router->post('/courses/:course_id/forum/:forum_id/topic/:topic_id/threads', function ($course_id, $forum_id, $topic_id) use ($router) {
            $forum     = Forum::get($course_id);
            $subject   = Helper::Sanitize(Request::get('subject'));
            $content   = Helper::Sanitize(Request::get('content'));
            $parent_id = Request::option('parent_id', '0');
            $anonymous = Request::int('anonymous', 0) > 0;

            $thread_id = $forum->insertThread($forum_id, $topic_id, $subject, $content, compact('parent_id', 'anonymous'));
            if (!$thread_id) {
                $router->halt(500, 'Error creating the thread.');
            }

            $thread = $forum->getThread($forum_id, $topic_id, $thread_id);
            $router->render(compact('thread'), 201);
        });

        $router->get('/courses/:course_id/forum/:forum_id/topic/:topic_id/thread/:thread_id', function ($course_id, $forum_id, $topic_id, $thread_id) use ($router) {
            $thread = Forum::get($course_id)->getThread($forum_id, $topic_id, $thread_id);
            $router->render(compact('thread'));
        });

        $router->put('/courses/:course_id/forum/:forum_id/topic/:topic_id/thread/:thread_id', function ($course_id, $forum_id, $topic_id, $thread_id) use ($router) {
            $forum = Forum::get($course_id);
            $subject = Helper::Sanitize(Request::get('subject'));
            $content = Helper::Sanitize(Request::get('content'));
            if (!$forum->setThread($forum_id, $topic_id, $thread_id, $subject, $content)) {
                $router->halt(500, 'Could not update thread');
            }

            $thread = $forum->getThread($forum_id, $topic_id, $thread_id);
            $router->render(compact('thread'), 205);
        });

        $router->delete('/courses/:course_id/forum/:forum_id/topic/:topic_id/thread/:thread_id', function ($course_id, $forum_id, $topic_id, $thread_id) use ($router) {
            $forum = Forum::get($course_id);
            if (!$forum->deleteThread($forum_id, $topic_id, $thread_id)) {
                $router->halt(500, 'Could not delete thread.');
            }

            $router->render(array('thread' => null), 205);
        });

        // Posts
        $router->get('/courses/:course_id/forum/:forum_id/topic/:topic_id/thread/:thread_id/posts', function ($course_id, $forum_id, $topic_id, $thread_id) use ($router) {
            $offset = Request::int('offset', 0);
            $limit  = Request::int('limit', 10) ?: 10;
            $forum  = Forum::get($course_id);
            $total  = $forum->countPosts($forum_id, $topic_id, $thread_id);

            $result = array(
                'posts'      => $forum->getPosts($forum_id, $topic_id, $thread_id, $offset, $limit),
                'pagination' => $router->paginate($total, $offset, $limit, '/courses', $course_id, 'forum', $forum_id, 'topic', $topic_id, 'threads', $thread_id, 'posts'),
            );

            $router->render($result);
        });

        $router->post('/courses/:course_id/forum/:forum_id/topic/:topic_id/thread/:thread_id/posts', function ($course_id, $forum_id, $topic_id, $thread_id) use ($router) {
            $forum     = Forum::get($course_id);
            $subject   = Helper::Sanitize(Request::get('subject'));
            $content   = Helper::Sanitize(Request::get('content'));
            $parent_id = Request::option('parent_id', $thread_id);
            $anonymous = Request::int('anonymous', 0) > 0;

            $post_id = $forum->insertPost($forum_id, $topic_id, $thread_id, $subject, $content, compact('parent_id', 'anonymous'));
            if (!$post_id) {
                $router->halt(500, 'Error creating the post.');
            }

            $post = $forum->getPost($forum_id, $topic_id, $thread_id, $post_id);
            $router->render(compact('post'), 201);
        });

        $router->get('/courses/:course_id/forum/:forum_id/topic/:topic_id/thread/:thread_id/post/:post_id', function ($course_id, $forum_id, $topic_id, $thread_id, $post_id) use ($router) {
            $post = Forum::get($course_id)->getPost($forum_id, $topic_id, $thread_id, $post_id);
            $router->render(compact('post'));
        });

        $router->put('/courses/:course_id/forum/:forum_id/topic/:topic_id/thread/:thread_id/post/:post_id', function ($course_id, $forum_id, $topic_id, $thread_id, $post_id) use ($router) {
            $forum = Forum::get($course_id);
            $subject = Helper::Sanitize(Request::get('subject'));
            $content = Helper::Sanitize(Request::get('content'));
            if (!$forum->setPost($forum_id, $topic_id, $thread_id, $post_id, $subject, $content)) {
                $router->halt(500, 'Could not update post');
            }

            $post = $forum->getPost($forum_id, $topic_id, $thread_id, $post_id);
            $router->render(compact('post'), 205);
        });

        $router->delete('/courses/:course_id/forum/:forum_id/topic/:topic_id/thread/:thread_id/post/:post_id', function ($course_id, $forum_id, $topic_id, $thread_id, $post_id) use ($router) {
            $forum = Forum::get($course_id);
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id) && $forum->postHasReplies($forum_id, $topic_id, $thread_id, $post_id)) {
                $router->halt(409, 'Post cannot be deleted since it already has replies.');
            }
            if (!$forum->deletePost($forum_id, $topic_id, $thread_id, $post_id)) {
                $router->halt(500, 'Could not delete post.');
            }

            $router->render(array('post' => null), 205);
        });
    }
}
