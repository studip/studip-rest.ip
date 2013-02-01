<?php
namespace RestIP;
use \APIException, \DBManager, \PDO, \StudipPDO;

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
        return 1;
    }

    public function getForums()
    {
        $result = array(
            array(
                'forum_id' => $this->course_id,
                'name'     =>  _('Forum der Veranstaltung'),
                'position' => 0,
            ),
        );
        return $result;
    }
    
    public function getForum($forum_id)
    {
        if ($forum_id != $this->course_id) {
            throw new APIException('Not implemented', 501);
        }
        return reset($this->getForums());
    }
    
    public function setForum($forum_id, $title, $description)
    {
        throw new APIException('Not implemented', 501);
    }
    
    public function deleteForum($forum_id)
    {
        throw new APIException('Not implemented', 501);
    }

    /* TOPICS */

    public function countTopics($forum_id)
    {
        return 1;
    }

    public function getTopics($forum_id, $offset = 0, $limit = 10)
    {
        $offset = (int) $offset;
        $limit  = (int) $limit;

        $result = array();
        if ($offset === 0) {
            $result[] = array(
                'topic_id'  => $this->course_id,
                'subject'   => _('Allgemeines Thema'),
                'content'   => '',
                'mkdate'    => 0,
                'chdate'    => 0,
                'user_id'   => '',
                'anonymous' => 0,
            );
        }
        return $result;
    }

    public function getTopic($forum_id, $topic_id)
    {
        if ($topic_id === $this->course_id) {
            return reset($this->getTopics($forum_id));
        }
        return false;
    }

    public function setTopic($forum_id, $topic_id, $subject, $content)
    {
        throw new APIException('Not implemented', 501);
    }

    public function insertTopic($forum_id, $subject, $content, $options = array())
    {
        throw new APIException('Not implemented', 501);
    }

    public function deleteTopic($forum_id, $topic_id, $with_replies = true)
    {
        throw new APIException('Not implemented', 501);
    }

    /* THREADS */
    
    public function countThreads($forum_id, $topic_id)
    {
        $query = "SELECT COUNT(*)
                  FROM px_topics
                  WHERE Seminar_id = :course_id AND parent_id = '0'";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->execute();

        return $statement->fetchColumn() ?: 0;
    }

    public function getThreads($forum_id, $topic_id, $offset = 0, $limit = 10)
    {
        $offset = (int) $offset;
        $limit  = (int) $limit;

        $query = "SELECT topic_id, name AS subject, description AS content, mkdate, chdate, user_id, anonymous
                  FROM px_topics
                  WHERE Seminar_id = :course_id AND parent_id = '0'
                  ORDER BY mkdate DESC
                  LIMIT {$offset}, {$limit}";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->execute();
        $threads = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($threads as $index => $thread) {
            $threads[$index]['content_original'] = $thread['content'];
            $threads[$index]['content']          = formatReady($thread['content']);
        }

        return $threads;
    }

    public function getThread($forum_id, $topic_id, $thread_id)
    {
        return $this->getPost($forum_id, $topic_id, $thread_id, $thread_id);
    }

    public function setThread($forum_id, $topic_id, $thread_id, $subject, $content)
    {
        return $this->setPost($forum_id, $topic_id, $thread_id, $thread_id, $subject, $content);
    }

    public function insertThread($forum_id, $topic_id, $subject, $content, $options = array())
    {
        $thread_id            = md5(uniqid('forum-thread', true));
        $options['thread_id'] = $thread_id;
        $options['post_id']   = $thread_id;

        return $this->insertPost($forum_id, $topic_id, $thread_id, $subject, $content, $options);
    }

    public function deleteThread($forum_id, $topic_id, $thread_id, $with_replies = true)
    {
        return $this->deletePost($forum_id, $topic_id, $thread_id, $thread_id, $with_replies);
    }

    /* POSTS */

    public function countPosts($forum_id, $topic_id, $thread_id)
    {
        $query = "SELECT COUNT(*)
                  FROM px_topics
                  WHERE Seminar_id = :course_id AND root_id = :thread_id AND parent_id != '0'";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':thread_id', $thread_id);
        $statement->execute();

        return $statement->fetchColumn() ?: 0;
    }

    public function getPosts($forum_id, $topic_id, $thread_id, $offset = 0, $limit = 10)
    {
        $offset = (int) $offset;
        $limit  = (int) $limit;

        $query = "SELECT topic_id AS post_id, name AS subject, description AS content, mkdate, chdate, user_id, anonymous
                  FROM px_topics
                  WHERE Seminar_id = :course_id AND root_id = :thread_id AND parent_id != '0'
                  ORDER BY mkdate DESC
                  LIMIT {$offset}, {$limit}";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':thread_id', $thread_id);
        $statement->execute();
        $posts = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as $index => $post) {
            $posts[$index]['content_original'] = $post['content'];
            $posts[$index]['content']          = formatReady($post['content']);
        }

        return $posts;
    }

    public function getPost($forum_id, $topic_id, $thread_id, $post_id)
    {
        $query = "SELECT topic_id AS post_id, name AS subject, description AS content, mkdate, chdate, user_id, anonymous
                  FROM px_topics
                  WHERE Seminar_id = :course_id AND root_id = :thread_id AND topic_id = :post_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':thread_id', $thread_id);
        $statement->bindValue(':post_id', $post_id);
        $statement->execute();
        $post = $statement->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            $post['content_original'] = $post['content'];
            $post['content']          = formatReady($post['content']);
        }

        return $post;
    }

    public function postHasReplies($forum_id, $topic_id, $thread_id, $post_id)
    {
        $query = "SELECT 1
                  FROM px_topics
                  WHERE Seminar_id = :course_id AND root_id = :thread_id AND parent_id = :post_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':thread_id', $thread_id);
        $statement->bindValue(':post_id', $post_id);
        $statement->execute();

        return $statement->fetchColumn() > 0;
    }

    public function setPost($forum_id, $topic_id, $thread_id, $post_id, $subject, $content)
    {
        if ($this->postHasReplies($forum_id, $topic_id, $thread_id, $post_id)) {
            throw new APIException('Post cannot be updated since it already has replies.', 409);
        }

        $query = "UPDATE px_topics
                  SET name = :subject, description = :content, chdate = UNIX_TIMESTAMP()
                  WHERE Seminar_id = :course_id AND root_id = :thread_id AND topic_id = :post_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':subject', $subject);
        $statement->bindValue(':content', $content);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':thread_id', $thread_id);
        $statement->bindValue(':post_id', $post_id);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function insertPost($forum_id, $topic_id, $thread_id, $subject, $content, $options = array())
    {
        $parent_id = $options['parent_id'] ?: '0';
        $anonymous = $options['anonymous'] ?: false;
        $post_id   = $options['post_id'] ?: md5(uniqid('forum-post', true));

        $query = "SELECT CONCAT(Vorname, ' ', Nachname) FROM auth_user_md5 WHERE user_id = :user_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':user_id', $GLOBALS['auth']->auth['uid']);
        $statement->execute();
        $author = $statement->fetchColumn();

        $query = "INSERT INTO px_topics
                    (topic_id, parent_id, root_id, name, description, mkdate, chdate, author, author_host,
                     Seminar_id, user_id, anonymous)
                  VALUES (:post_id, :parent_id, :thread_id, :subject, :content, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(),
                          :author, :host, :course_id, :user_id, :anonymous)";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':post_id', $post_id);
        $statement->bindValue(':parent_id', $parent_id);
        $statement->bindValue(':thread_id', $thread_id);
        $statement->bindValue(':subject', $subject);
        $statement->bindValue(':content', $content);
        $statement->bindValue(':author', $author);
        $statement->bindValue(':host', $_SERVER['REMOTE_ADDR']);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':user_id', $GLOBALS['auth']->auth['uid']);
        $statement->bindValue(':anonymous', (int)$anonymous);
        $statement->execute();

        return ($statement->rowCount() > 0)
            ? $post_id
            : false;
    }

    public function deletePost($forum_id, $topic_id, $thread_id, $post_id, $with_replies = true)
    {
        $query = "DELETE FROM px_topics
                  WHERE Seminar_id = :course_id AND root_id = :thread_id AND topic_id = :post_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':course_id', $this->course_id);
        $statement->bindValue(':thread_id', $thread_id);
        $statement->bindValue(':post_id', $post_id);
        $statement->execute();
        $result = $statement->rowCount() > 0;

        if (!$result || !$with_replies) {
            return $result;
        }

        $query = "SELECT topic_id
                  FROM px_topics
                  WHERE Seminar_id = :course_id AND root_id = :thread_id AND parent_id IN (:parent_ids)";
        $select_statement = DBManager::get()->prepare($query);
        $select_statement->bindValue(':course_id', $this->course_id);
        $select_statement->bindValue(':thread_id', $thread_id);

        $query = "DELETE FROM px_topics
                  WHERE Seminar_id = :course_id AND root_id = :thread_id AND topic_id IN (:post_ids)";
        $delete_statement = DBManager::get()->prepare($query);
        $delete_statement->bindValue(':course_id', $this->course_id);
        $delete_statement->bindValue(':thread_id', $thread_id);

        $parent_ids = array($post_id);
        while (count($parent_ids) > 0) {
            $select_statement->bindValue(':parent_ids', $parent_ids, StudipPDO::PARAM_ARRAY);
            $select_statement->execute();
            $post_ids = $select_statement->fetchAll(PDO::FETCH_COLUMN);
            $select_statement->closeCursor();

            if (count($post_ids) > 0) {
                $delete_statement->bindValue(':post_ids', $post_ids, StudipPDO::PARAM_ARRAY);
                $delete_statement->execute();
            }

            $parent_ids = $post_ids;
        }

        return true;
    }
}
