<?php
/**
 * RestipPlugin.class.php
 *
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @version 0 alpha
 */

class RestipPlugin extends StudIPPlugin implements SystemPlugin {

    public function perform ($unconsumed_path) {
        throw new Exception($unconsumed_path);
    }

}
