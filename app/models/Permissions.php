<?php

# namespace RestIP;

/**
 *
 **/
class Permissions
{
    private $consumer_key;
    private $permissions = array();

    /**
     *
     **/
    public function __construct($consumer_key = null, $user_id = null)
    {
        $this->consumer_key = $consumer_key;

        foreach ($this->loadPermissions(null) as $permission) {
            if (!isset($this->permissions[$permission['route_id']])) {
                $this->permissions[$permission['route_id']] = array();
            }
            $this->permissions[$permission['route_id']][$permission['method']] = (bool)$permission['granted'];
        }

        if ($consumer_key) {
            foreach ($this->loadPermissions($consumer_key) as $permission) {
                if (!isset($this->permissions[$permission['route_id']])) {
                    $this->permissions[$permission['route_id']] = array();
                }

                if (isset($this->permissions[$permission['route_id']][$permission['method']])
                    && $this->permissions[$permission['route_id']][$permission['method']])
                {
                    $this->permissions[$permission['route_id']][$permission['method']] = (bool)$permission['granted'];
                }
            }
        }
    }

    /**
     *
     **/
    private function loadPermissions($consumer_key)
    {
        $query = "SELECT route_id, method, granted FROM oauth_api_permissions WHERE consumer_key = IFNULL(?, 'global')";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($consumer_key));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     *
     **/
    public function check($route, $method)
    {
        $route_id = md5($route);

        return isset($this->permissions[$route_id][$method])
            && $this->permissions[$route_id][$method];
    }

    /**
     *
     **/
    public function set($route, $method, $granted)
    {
        $query = "INSERT INTO oauth_api_permissions (route_id, consumer_key, method, granted) "
               . "VALUES (?, IFNULL(?, 'global'), ?, ?) ON DUPLICATE KEY UPDATE granted = VALUES(granted)";

        DBManager::get()
            ->prepare($query)
            ->execute(array(md5($route), $this->consumer_key, $method, $granted));
    }
}
