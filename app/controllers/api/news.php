<?
class API_NewsController extends ApiController {
    function index_GET_action($range_id = 'studip') {        
        $range_id === 'studip' || $this->isAuthorized();
        
        require_once 'lib/classes/StudipNews.class.php';

        $news = StudipNews::GetNewsByRange($range_id);
        array_walk($news, function (&$item) {
            $item['chdate_uid'] = trim($item['chdate_uid']);
        });
        $this->data = $news;
    }
}
