<?php
namespace RestIP;

require_once 'public/plugins_packages/core/Forum/models/ForumCat.php';
require_once 'public/plugins_packages/core/Forum/models/ForumEntry.php';

use \Exception, \DBManager, \PDO, \StudipPDO, \ForumCat, \ForumEntry;

class Forum
{
    protected $course_id;

    public static function Get($course_id)
    {
        return new self($course_id);
    }

    public function __construct($course_id)
    {
        $this->course_id = $course_id;
    }

    public function countForums()
    {
        $query = "SELECT COUNT(*)
                  FROM forum_categories
                  WHERE seminar_id = :course_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->execute();

        return $statement->fetchColumn() ?: 0;
    }

    public function getForums($offset = 0, $limit = 10)
    {
        $offset = (int) $offset;
        $limit  = (int) $limit;
        $query = "SELECT category_id AS forum_id, entry_name AS name, pos AS position
                  FROM forum_categories
                  WHERE seminar_id = :course_id
                  ORDER BY pos ASC
                  LIMIT {$offset}, {$limit}";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getForum($forum_id)
    {
        $query = "SELECT category_id AS forum_id, entry_name AS name, pos AS position
                  FROM forum_categories
                  WHERE seminar_id = :course_id AND category_id = :forum_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':forum_id', $forum_id);
        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function setForum($forum_id, $name)
    {
        ForumCat::setName($forum_id, $name);
        return true;
    }

    public function insertForum($name)
    {
        $forum_id = ForumCat::add($this->course_id, $name);
        return $forum_id;
    }

    public function deleteForum($forum_id)
    {
        ForumCat::remove($forum_id, $this->course_id);
        return true;
    }

    /* TOPICS */

    public function countTopics($forum_id)
    {
        $query = "SELECT COUNT(*)
                  FROM forum_entries
                  JOIN forum_categories_entries USING (topic_id)
                  WHERE seminar_id = :course_id AND category_id = :forum_id AND depth = 1";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':forum_id', $forum_id);
        $statement->execute();

        return $statement->fetchColumn() ?: 0;
    }

    public function getTopics($forum_id, $offset = 0, $limit = 10)
    {
        $offset = (int) $offset;
        $limit  = (int) $limit;

        $query = "SELECT topic_id, name AS subject, content, mkdate, chdate, user_id, anonymous
                  FROM forum_entries
                  JOIN forum_categories_entries USING (topic_id)
                  WHERE seminar_id = :course_id AND category_id = :forum_id AND depth = 1
                  ORDER BY mkdate DESC
                  LIMIT {$offset}, {$limit}";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':forum_id', $forum_id);
        $statement->execute();
        $topics = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($topics as $index => $topic) {
            $topics[$index]['content_original'] = ForumEntry::killEdit($topic['content']);;
            $topics[$index]['content']          = formatReady(ForumEntry::parseEdit($topic['content']));
        }

        return $topics;
    }

    public function getTopic($forum_id, $topic_id)
    {
        $query = "SELECT topic_id, name AS subject, content, mkdate, chdate, user_id, anonymous
                  FROM forum_entries
                  JOIN forum_categories_entries USING (topic_id)
                  WHERE seminar_id = :course_id AND category_id = :forum_id AND topic_id = :topic_id AND depth = 1";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':forum_id', $forum_id);
        $statement->bindValue(':topic_id', $topic_id);
        $statement->execute();
        $topic = $statement->fetch(PDO::FETCH_ASSOC);
        
        if ($topic) {
            $topic['content_original'] = ForumEntry::killEdit($topic['content']);
            $topic['content']          = formatReady(ForumEntry::parseEdit($topic['content']));
        }

        return $topic;
    }

    public function setTopic($forum_id, $topic_id, $subject, $content)
    {
        ForumEntry::update($topic_id, $subject, $content);
        return true;
    }

    public function insertTopic($forum_id, $subject, $content, $options = array())
    {
        $parent_id = $options['parent_id'] ?: $this->course_id;
        $anonymous = $options['anonymous'] ?: false;
        $topic_id  = md5(uniqid('forum-post', true));

        $data = array(
            'topic_id'    => $topic_id,
            'seminar_id'  => $this->course_id,
            'user_id'     => $GLOBALS['user']->id,
            'name'        => $subject,
            'content'     => $content,
            'author'      => $GLOBALS['user']->getFullName(),
            'author_host' => $_SERVER['REMOTE_ADDR'],
            'anonymous'   => (int)$anonymous,
        );
        ForumEntry::insert($data, $parent_id);
        ForumCat::addArea($forum_id, $topic_id);
        
        return $topic_id;
    }

    public function deleteTopic($forum_id, $topic_id, $with_replies = true)
    {
        ForumEntry::delete($topic_id);
        return true;
    }

    /* HELPER */
    
    private function getBorders($forum_id, $topic_id, $depth = null)
    {
        $query = "SELECT lft, rgt
                  FROM forum_entries
                  WHERE seminar_id = :course_id AND topic_id = :topic_id AND depth = IFNULL(:depth, depth)";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':topic_id', $topic_id);
        $statement->bindValue(':depth', $depth);
        $statement->execute();
        return $statement->fetch(PDO::FETCH_NUM);
    }

    /* THREADS */
    
    public function countThreads($forum_id, $topic_id)
    {
        list($left, $right) = $this->getBorders($forum_id, $topic_id, 1);
        
        $query = "SELECT COUNT(*)
                  FROM forum_entries
                  WHERE seminar_id = :course_id AND lft > :left AND rgt < :right AND depth = 2";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':left', $left);
        $statement->bindValue(':right', $right);
        $statement->execute();

        return $statement->fetchColumn() ?: 0;
    }
    
    public function getThreads($forum_id, $topic_id, $offset = 0, $limit = 10)
    {
        $offset             = (int) $offset;
        $limit              = (int) $limit;
        list($left, $right) = $this->getBorders($forum_id, $topic_id, 1);

        $query = "SELECT topic_id AS thread_id, name AS subject, content, mkdate, chdate, user_id, anonymous
                  FROM forum_entries
                  WHERE seminar_id = :course_id AND lft > :left AND rgt < :right AND depth = 2
                  ORDER BY mkdate DESC
                  LIMIT {$offset}, {$limit}";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':left', $left);
        $statement->bindValue(':right', $right);
        $statement->execute();
        $threads = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($threads as $index => $thread) {
            $threads[$index]['content_original'] = ForumEntry::killEdit($thread['content']);
            $threads[$index]['content']          = formatReady(ForumEntry::parseEdit($thread['content']));
        }

        return $threads;
    }
    
    public function getThread($forum_id, $topic_id, $thread_id)
    {
        $query = "SELECT topic_id AS thread_id, name AS subject, content, mkdate, chdate, user_id, anonymous
                  FROM forum_entries
                  WHERE seminar_id = :course_id AND topic_id = :thread_id AND depth = 2";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':thread_id', $thread_id);
        $statement->execute();
        $post = $statement->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            $post['content_original'] = ForumEntry::killEdit($post['content']);
            $post['content']          = formatReady(ForumEntry::parseEdit($post['content']));
        }

        return $post;
    }
    
    public function setThread($forum_id, $topic_id, $thread_id, $subject, $content)
    {
        ForumEntry::update($thread_id, $subject, $content);
        return true;
    }
    
    public function insertThread($forum_id, $topic_id, $subject, $content, $options = array())
    {
        $parent_id = $topic_id;
        $anonymous = $options['anonymous'] ?: false;
        $thread_id = md5(uniqid('forum-thread', true));

        $data = array(
            'topic_id'    => $thread_id,
            'seminar_id'  => $this->course_id,
            'user_id'     => $GLOBALS['user']->id,
            'name'        => $subject,
            'content'     => $content,
            'author'      => $GLOBALS['user']->getFullName(),
            'author_host' => $_SERVER['REMOTE_ADDR'],
            'anonymous'   => (int)$anonymous,
        );
        ForumEntry::insert($data, $parent_id);
        
        return $thread_id;
    }
    
    public function deleteThread($forum_id, $topic_id, $thread_id)
    {
        ForumEntry::delete($thread_id);
        return true;
    }

    /* POSTS */

    public function countPosts($forum_id, $topic_id, $thread_id)
    {
        list($left, $right) = $this->getBorders($forum_id, $thread_id, 2);
        
        $query = "SELECT COUNT(*)
                  FROM forum_entries
                  WHERE seminar_id = :course_id AND lft >= :left AND rgt <= :right AND depth > 2";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':left', $left);
        $statement->bindValue(':right', $right);
        $statement->execute();

        return $statement->fetchColumn() ?: 0;
    }

    public function getPosts($forum_id, $topic_id, $thread_id, $offset = 0, $limit = 10)
    {
        $offset             = (int) $offset;
        $limit              = (int) $limit;
        list($left, $right) = $this->getBorders($forum_id, $thread_id, 2);

        $query = "SELECT topic_id AS post_id, name AS subject, content, mkdate, chdate, user_id, anonymous
                  FROM forum_entries
                  WHERE seminar_id = :course_id AND lft >= :left AND rgt <= :right AND depth > 2
                  ORDER BY mkdate DESC
                  LIMIT {$offset}, {$limit}";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':left', $left);
        $statement->bindValue(':right', $right);
        $statement->execute();
        $posts = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as $index => $post) {
            $posts[$index]['content_original'] = ForumEntry::killEdit($post['content']);
            $posts[$index]['content']          = formatReady(ForumEntry::parseEdit($post['content']));
        }

        return $posts;
    }

    public function getPost($forum_id, $topic_id, $thread_id, $post_id)
    {
        $query = "SELECT topic_id AS post_id, name AS subject, content, mkdate, chdate, user_id, anonymous
                  FROM forum_entries
                  WHERE seminar_id = :course_id AND topic_id = :post_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':post_id', $post_id);
        $statement->execute();
        $post = $statement->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            $post['content_original'] = ForumEntry::killEdit($post['content']);
            $post['content']          = formatReady(ForumEntry::parseEdit($post['content']));
        }

        return $post;
    }

    public function postHasReplies($forum_id, $topic_id, $thread_id, $post_id)
    {
        return false;

        // There's no restriction on editing in this forum, so we don't need this

        // $query = "SELECT rgt - lft > 1
        //           FROM forum_entries
        //           WHERE seminar_id = :course_id AND topic_id = :post_id";
        // $statement = DBManager::get()->prepare($query);
        // $statement->bindValue(':course_id', $this->course_id);
        // $statement->bindValue(':post_id', $post_id);
        // $statement->execute();
        // 
        // return $statement->fetchColumn() > 0;
    }

    public function setPost($forum_id, $topic_id, $thread_id, $post_id, $subject, $content)
    {
        ForumEntry::update($post_id, $subject, $content);
        return true;
    }

    public function insertPost($forum_id, $topic_id, $thread_id, $subject, $content, $options = array())
    {
        $parent_id = $options['parent_id'] ?: $thread_id;
        $anonymous = $options['anonymous'] ?: false;
        $post_id  = md5(uniqid('forum-post', true));

        $data = array(
            'topic_id'    => $post_id,
            'seminar_id'  => $this->course_id,
            'user_id'     => $GLOBALS['user']->id,
            'name'        => $subject,
            'content'     => $content,
            'author'      => $GLOBALS['user']->getFullName(),
            'author_host' => $_SERVER['REMOTE_ADDR'],
            'anonymous'   => (int)$anonymous,
        );
        ForumEntry::insert($data, $parent_id);
        
        return $post_id;
    }

    public function deletePost($forum_id, $topic_id, $thread_id, $post_id, $with_replies = true)
    {
        ForumEntry::delete($post_id);
        return true;
    }
}
