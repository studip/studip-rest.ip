<?php
namespace RestIP;
use \APIPlugin, \DBManager, \PDO, \User;

/**
 *
 **/
class ResourcesRoute implements APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/resource/:geo_location' => _('Resourceninformation zu einer gegebenen Geo-Location'),
            '/resource/user/:resource_id' => _('Speichert Resourceninformation zu einem Nutzer'),
            '/user/resource(/:user_id)' => _('Liefert Resourceninformation zu einem Nutzer')
        );
    }

    /**
     *
     **/
    public static function before()
    {
        require_once 'lib/raumzeit/raumzeit_functions.inc.php';
    }

    /**
     *
     **/
    public function routes(&$router)
    {

        $router->get('/resource/:geo_location', function ($geo_location) use ($router) {
            if($ri = ResourceHelper::getResourceIdForGeoLocation($geo_location)) {
                $resource_id = $ri['resource_id'];
                if($resource = ResourceHelper::getResourceInfo($resource_id)){

                    $router->render($resource);
                } else {
                    $router->halt(404, sprintf('There is no resource of the category room for given identifier "%s"', $resource_id));
                }
            } else {
                $router->halt(404, sprintf('There is no resource information for given geo Location "%s"', $geo_location));
                return;
            }
        });


        $router->post('/resource/user/:resource_id', function($resource_id) use ($router) {

            $user_id = $GLOBALS['user']->id;
            $ro = \ResourceObject::Factory($resource_id);

            if (!$ro || !$ro->isRoom()) {
                $router->halt(404, sprintf('There is no resource of the category room for given identifier "%s"', $resource_id));
            }

            $geoid = ResourceHelper::getGeoLocationForResource($resource_id);
    

            ResourceHelper::setResourceForUser($user_id, $ro->getId(), $geoid);
            $router->render(201);
        });

        $router->get('/user/resource(/:user_id)', function($user_id = false) use ($router) {

            if($user_id) {
                $user = User::find($user_id);
                $is_visible = $GLOBALS['user']->id === $user_id || get_visibility_by_id($user_id);
                if (!$user || !$is_visible) {
                    $router->halt(404, sprintf('User %s not found', $user_id));
                    return;
                }
            } else {
                $user_id = $GLOBALS['user']->id;
            }

            if($user_resource = ResourceHelper::getResourceForUser($user_id)) {
                $router->render($user_resource);
            } else {
                $router->halt(404, sprintf('There is no resource information for given user "%s"', $user_id));
                return;
            }
        });
    }
}

class ResourceHelper {

    static function getResourceIdForGeoLocation($geoloaction) {

        //working geolocation 52.290637-8.004867
        $query = "SELECT resource_id FROM resources_properties LEFT JOIN resources_objects_properties USING (property_id) WHERE  name = 'geoLocation' AND state = ?";

        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($geoloaction));

        return $statement->fetch(PDO::FETCH_ASSOC);

    }

    static function getResourceInfo($resource_id) {
        $ro = \ResourceObject::Factory($resource_id);
        if (!$ro || !$ro->isRoom()) {
            return false;
        }
        $cat_id = $ro->getCategoryId();
        $properties =  ResourceHelper::getProperties($resource_id, $cat_id);

        $resource_info = array();

        $resource_info['id'] = $ro->getId();
        $resource_info['name'] = $ro->getName();
        $resource_info['category_name'] = $ro->getCategoryName();
        $resource_info['parent_id'] = $ro->getParentId();
        $resource_info['description'] = $ro->getDescription();
        $resource_info['properties'] = $properties;
        $resource_info['users'] = ResourceHelper::getUsersForResource($ro->getId());
        // TODO courses

        return $resource_info;
    }


    static function getProperties($rid, $cat_id){
        $query = "SELECT b.name, a.state, b.type, b.options
                 FROM resources_objects_properties AS a
                 LEFT JOIN resources_properties AS b USING (property_id)
                 LEFT JOIN resources_categories_properties AS c USING (property_id)
                 WHERE resource_id = ? AND c.category_id = ?";

       $query .= " ORDER BY b.name";
       $statement = DBManager::get()->prepare($query);
       $statement->execute(array(
           $rid,
           $cat_id
       ));

       $temp = array();

       while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
           $temp[] = array($row['name'] => $row['state']);
       }
       return $temp;
    }


    static function setResourceForUser($user_id, $rid, $geoid){

        $query = "SELECT * FROM `restip_user_location` WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id));

        if($statement->fetch(PDO::FETCH_ASSOC)){
            $statement = DBManager::get()->prepare("UPDATE
                `restip_user_location` SET resource_id = ?, geoLocation = ?, chdate = ?
                WHERE user_id =?");
            $statement->execute(array($rid, $geoid, time(), $user_id));
        } else {
            $query = "INSERT INTO `restip_user_location` (user_id, resource_id, geoLocation, mkdate, chdate)
                        VALUES (?,?,?, ?, ?)";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array($user_id, $rid, $geoid, time(), time()));
        }
    }

    static function getResourceForUser($user_id){
        $query = "SELECT * FROM `restip_user_location` WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id));
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    static function getUsersForResource($resource_id) {
        $query = "SELECT user_id FROM `restip_user_location` WHERE resource_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($resource_id));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    static function getGeoLocationForResource($resource_id){
        $query = 'SELECT state FROM  resources_objects_properties LEFT JOIN resources_properties USING (property_id) WHERE  name = "geoLocation" AND resource_id = ?';
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($resource_id));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
