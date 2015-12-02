<?php
namespace RestIP;
use \APIPlugin, \DBManager, \PDO, \User;

/**
 *
 **/
class RessourcesRoute implements APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/ressources/:ressource_id' => _('Informationen zu einer Ressource'),
            '/ressources/user(/:user_id)' => _('Gibt an an welcher Ressource sich ein Nutzer Befindet'),
            '/user/:ressource_id' => _('Speichert Ressourceninformation zu einem Nutzer'),
            '/user/ressource(/:user_id)' => _('Liefert Ressourceninformation zu einem Nutzer')
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

        $router->get('/ressources/:ressource_id', function ($ressource_id) use ($router) {
            $ro = \ResourceObject::Factory($ressource_id);

            if (!$ro || !$ro->isRoom()) {
                $router->halt(404, sprintf('There is no ressource of the category room for given identifier "%s"', $ressource_id));
            }

            $cat_id = $ro->getCategoryId();
            $properties =  RessourceHelper::getProperties($ressource_id, $cat_id);

            $ressource = array();

            $ressource['id'] = $ro->getId();
            $ressource['name'] = $ro->getName();
            $ressource['category_name'] = $ro->getCategoryName();
            $ressource['parent_id'] = $ro->getParentId();
            $ressource['description'] = $ro->getDescription();
            $ressource['properties'] = $properties;
            $ressource['users'] = RessourceHelper::getUsersForRessource($ro->getId());
            // TODO courses


            $router->render($ressource);

        });


        $router->post('/user/:ressource_id', function($ressource_id) use ($router) {

            $GLOBALS['user']->id;
            $ro = \ResourceObject::Factory($ressource_id);

            if (!$ro || !$ro->isRoom()) {
                $router->halt(404, sprintf('There is no ressource of the category room for given identifier "%s"', $ressource_id));
            }
            $geoid = 'geoid'; // TODO

            RessourceHelper::setRessourceForUser($user_id, $ro->getId(), $geoid);
            $router->render(201);
        });

        $router->get('/user/ressource(/:user_id)', function($user_id = false) use ($router) {

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

            if($user_ressource = RessourceHelper::getRessourceForUser($user_id)) {
                $router->render($user_ressource);
            } else {
                $router->halt(404, sprintf('There is no ressource informarion for given user "%s"', $user_id));
                return;
            }
        });
    }
}

class RessourceHelper {

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


    static function setRessourceForUser($user_id, $rid, $geoid){

        $query = "SELECT * FROM `restip_user_location` WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id));

        if($statement->fetch(PDO::FETCH_ASSOC)){
            $statement = DBManager::get()->prepare("UPDATE
                `restip_user_location` SET ressource_id = ?, geoLocation = ?, chdate = ?
                WHERE user_id =?");
            $statement->execute(array($rid, $geoid, time(), $user_id));
        } else {
            $query = "INSERT INTO `restip_user_location` (user_id, ressource_id, geoLocation, mkdate, chdate)
                        VALUES (?,?,?, ?, ?)";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array($user_id, $rid, $geoid, time(), time()));
        }
    }

    static function getRessourceForUser($user_id){
        $query = "SELECT * FROM `restip_user_location` WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id));
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    static function getUsersForRessource($ressource_id) {
        $query = "SELECT user_id FROM `restip_user_location` WHERE ressource_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($ressource_id));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

}
