<?
class API_NewsController extends ApiController {
    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);
        
        require_once 'lib/classes/StudipNews.class.php';
    }
    
    function index_GET_action($range_id = 'studip') {        

        $range_id === 'studip' || $this->isAuthorized(); // A bit ugly, I know
        
        $news = StudipNews::GetNewsByRange($range_id);

        // Adjust data
        array_walk($news, function (&$item) {
            $item['chdate_uid'] = trim($item['chdate_uid']);
        });

        $this->data = $news;
    }
}
