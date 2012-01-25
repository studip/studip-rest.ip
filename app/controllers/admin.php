<?
class AdminController extends StudipController
{
    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);
        
        $GLOBALS['perm']->check('root');
        $layout = $GLOBALS['template_factory']->open('layouts/base');
        $this->set_layout($layout);

        $options = array(
            'dsn'      => 'mysql:host=' . $GLOBALS['DB_STUDIP_HOST']
                       .       ';dbname=' . $GLOBALS['DB_STUDIP_DATABASE'],
            'username' => $GLOBALS['DB_STUDIP_USER'],
            'password' => $GLOBALS['DB_STUDIP_PASSWORD']
        );
        $this->store = OAuthStore::instance('pdo', $options);
    }
    
    function index_action() {
        $this->applications = $this->store->listConsumerApplications();
        
        $this->setInfoboxImage('infobox/administration.jpg');
        
        $new = sprintf('<a href="%s">%s</a>',
                       $this->url_for('admin/edit'),
                       _('Neue Applikation erstellen'));
        $this->addToInfobox('Aktionen', $new, 'icons/16/black/plus.png');
    }
    
    function edit_action() {
    }


    /**
     * Spawns a new infobox variable on this object, if neccessary.
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
     * above displayed category - write spoken not
     * tech language ^^
     * @param String $text The content of the item, may contain html
     * @param String $icon Icon to display in front the item, path is
     * relative to :assets:/images
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