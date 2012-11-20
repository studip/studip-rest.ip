<?
class PluginController extends StudipController
{
    public function before_filter(&$action, &$args)
    {
        $this->plugin = $this->dispatcher->plugin;
        
        $path = $this->plugin->getPluginPath();
        
        require_once $path . '/classes/buttons/Interactable.class.php';
        require_once $path . '/classes/buttons/Button.class.php';
        require_once $path . '/classes/buttons/LinkButton.class.php';

        PageLayout::addStylesheet($this->dispatcher->plugin->getPluginURL() . '/assets/buttons.css');
    }
    
/** from Stud.IP 2.3 **/

    /**
     * Spawns a new infobox variable on this object, if neccessary.
     *
     * @since Stud.IP 2.3
     **/
    private function populateInfobox()
    {
        if (!isset($this->infobox)) {
            $this->infobox = array(
                'picture' => 'blank.gif',
                'content' => array()
            );
        }
    }

    /**
     * Sets the header image for the infobox.
     *
     * @param String $image Image to display, path is relative to :assets:/images
     *
     * @since Stud.IP 2.3
     **/
    function setInfoBoxImage($image) {
        $this->populateInfobox();
        $this->infobox['picture'] = $image;
    }

    /**
     * Adds an item to a certain category section of the infobox. Categories
     * are created in the order this method is invoked. Multiple occurences of
     * a category will add items to the category.
     *
     * @param String $category The item's category title used as the header
     *                         above displayed category - write spoken not
     *                         tech language ^^
     * @param String $text The content of the item, may contain html
     * @param String $icon Icon to display in front the item, path is
     *                     relative to :assets:/images
     *
     * @since Stud.IP 2.3
     **/
    function addToInfobox($category, $text, $icon = 'blank.gif') {
        $this->populateInfobox();
        $infobox = $this->infobox;
        if (!isset($infobox['content'][$category])) {
            $infobox['content'][$category] = array(
                'kategorie' => $category,
                'eintrag' => array(),
            );
        }
        $infobox['content'][$category]['eintrag'][] = compact('icon', 'text');
        $this->infobox = $infobox;
    }
    
}
