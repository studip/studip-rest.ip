<?
class AdminController extends StudipController
{
    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);

        $GLOBALS['perm']->check('root');

        $layout = $GLOBALS['template_factory']->open('layouts/base');
        $this->set_layout($layout);

        Navigation::activateItem('/admin/config/oauth');
        PageLayout::setTitle(_('OAuth Administration'));

        $this->store = new OAuthConsumer;
        $this->types = array(
            'website' => _('Website'),
            'program' => _('Herkömmliches Desktopprogramm'),
            'app'     => _('Mobile App')
        );
    }

    function index_action() {
        $this->consumers = $this->store->getList();

        $this->setInfoboxImage('infobox/administration.jpg');

        $new = sprintf('<a href="%s">%s</a>',
                       $this->url_for('admin/edit'),
                       _('Neue Applikation registrieren'));
        $this->addToInfobox('Aktionen', $new, 'icons/16/black/plus.png');
    }

    function render_keys($key, $consumer = null) {
        if ($consumer === null) {
            $consumer = $this->store->load($key);
        }
        
        return array(
            'Consumer Key = ' . $consumer['consumer_key'],
            'Consumer Secret = ' . $consumer['consumer_secret'],
        );
    }
    
    function keys_action($key) {
        $details = $this->render_keys($key);
        
        if (Request::isXhr()) {
            $this->render_text(implode('<br>', $details));
        } else {
            PageLayout::postMessage(Messagebox::info(_('Die Schlüssel in den Details dieser Meldung sollten vertraulich behandelt werden!'), $details, true));
            $this->redirect('admin/index#' . $key);
        }
    }

    function edit_action($key = null) {
        $this->consumer = $this->store->extractConsumerFromRequest($key);

        if (Request::submitted('store')) {
            $errors = $this->store->validate($this->consumer);

            if (!empty($errors)) {
                $message = MessageBox::error(_('Folgende Fehler sind aufgetreten:'), $errors);
                PageLayout::postMessage($message);
                return;
            }
            
            $consumer = $this->store->store($this->consumer, Request::int('enabled', 0));
            
            if ($key) {
                $message = MessageBox::success(_('Die Applikation wurde erfolgreich gespeichert.'));
            } else {
                $details  = $this->render_keys($key, $consumer);
                $message = MessageBox::success(_('Die Applikation wurde erfolgreich erstellt, die Schlüssel finden Sie in den Details dieser Meldung.'), $details, true);
            }
            PageLayout::postMessage($message);
            $this->redirect('admin/index#' . $consumer['consumer_key']);
            return;
        }

        $this->set_layout($GLOBALS['template_factory']->open('layouts/base_without_infobox'));

        $this->id = $id;
    }
    
    function delete_action($key) {
        $this->store->delete($key);
        PageLayout::postMessage(MessageBox::success(_('Die Applikation wurde erfolgreich gelöscht.')));
        $this->redirect('admin/index');
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