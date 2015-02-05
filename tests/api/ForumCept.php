<?php 

/* * * * * * * * * * * * * * * * * * * * * * * */
/*   T E S T   F O R   S T U D . I P   3 . 2   */
/* * * * * * * * * * * * * * * * * * * * * * * */

// ID der Standard Testveranstaltung
require_once rtrim(shell_exec('pwd'), "\r\n") .'/tests/api/forumcept_config.php';
$course_id = $config['cid'];

$I = new ApiGuy($scenario);
$I->wantTo('check if the forum services are working');
$I->amHttpAuthenticated($config['user'], $config['pass']);
$I->sendGET('discovery');
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();


// get the courses
$I->sendGET('courses');
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();
$I->seeResponseContains('"course_id":"'. $course_id .'"');


// get the forum categories
$I->sendGET('courses/'. $course_id .'/forum_categories?limit=1000');
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();
$I->seeResponseContains('"entry_name":"Allgemein"');


// create a category
$I->sendPOST('courses/'. $course_id .'/forum_categories', array('name' => 'Testkategorie'));
$I->seeResponseCodeIs(201);
$I->seeResponseIsJson();
$category_response = $I->grabResponse();
$category_id       = $I->grabDataFromJsonResponse('category.category_id');


// retrieve the newly created category and check that it equals the repsonse from the post request
$I->sendGET('forum_category/'. $category_id);
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();
$this->assertEquals($I->grabResponse(), $category_response);


// rename the previously created category
$I->sendPUT('forum_category/'. $category_id, array('name' => 'Umbenannte Kategorie'));
$I->seeResponseCodeIs(205);
$I->seeResponseIsJson();
$I->seeResponseContains('"entry_name":"Umbenannte Kategorie"');


// delete the previously created category
$I->sendDELETE('forum_category/'. $category_id);
$I->seeResponseCodeIs(204);






// create a category and add an area to it
$I->sendPOST('courses/'. $course_id .'/forum_categories', array('name' => 'Testkategorie 2'));
$category_id = $I->grabDataFromJsonResponse('category.category_id');

$I->sendPOST('forum_category/'. $category_id .'/areas', array('subject' => 'Testbereich', 'content' => 'Inhalt des Testbereichs'));
$I->seeResponseCodeIs(201);
$I->seeResponseIsJson();
$area_response = $I->grabResponse();
$area_id       = $I->grabDataFromJsonResponse('entry.topic_id');

$I->seeResponseContains('"subject":"Testbereich"');
$I->seeResponseContains('"content":"Inhalt des Testbereichs"');


// get newly created area
$I->sendGET('forum_entry/'. $area_id);
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();
$this->assertEquals($I->grabResponse(), $area_response);


// check getting areas is working
$I->sendGET('forum_category/'. $category_id .'/areas');
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();
$I->seeResponseContains('"subject":"Testbereich"');
$I->seeResponseContains('"content":"Inhalt des Testbereichs"');







// add an entry
$I->sendPOST('forum_entry/'. $area_id, array('subject' => 'Testthema', 'content' => 'Inhalt des Testthemas'));
$I->seeResponseCodeIs(201);
$I->seeResponseIsJson();
$post_response = $I->grabResponse();
$post_id       = $I->grabDataFromJsonResponse('entry.topic_id');

$I->seeResponseContains('"subject":"Testthema"');
$I->seeResponseContains('"content":"Inhalt des Testthemas"');


// get newly created entry
$I->sendGET('forum_entry/'. $post_id);
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();
$this->assertEquals($I->grabResponse(), $post_response);


// edit entry
$I->sendPUT('forum_entry/'. $post_id, array('subject' => 'Umbenanntes Testthema', 'content' => 'Umbenannter Inhalt des Testthemas'));
$I->seeResponseCodeIs(205);
$I->seeResponseIsJson();
$I->seeResponseContains('"subject":"Umbenanntes Testthema"');
$I->seeResponseContains('"content":"Umbenannter Inhalt des Testthemas"');


// add third level entry
// -> at first, we expect the call to fail, because no subject is allowed here any more
$I->sendPOST('forum_entry/'. $post_id, array('subject' => 'Testposting', 'content' => 'Inhalt des Testpostings'));
$I->seeResponseCodeIs(400);

// -> now we expect it to work
$I->sendPOST('forum_entry/'. $post_id, array('content' => 'Inhalt des Testpostings'));
$I->seeResponseCodeIs(201);
$I->seeResponseIsJson();
$I->seeResponseContains('"content":"Inhalt des Testpostings"');


$I->sendGET('forum_entry/'. $area_id .'/children');
$I->grabDataFromJsonResponse('pagination.offset');
$I->grabDataFromJsonResponse('pagination.limit');
$I->grabDataFromJsonResponse('pagination.total');

$I->seeResponseContains('"subject":"Umbenanntes Testthema"');
$I->seeResponseContains('"content":"Umbenannter Inhalt des Testthemas"');

// delete entry
$I->sendDELETE('forum_entry/'. $post_id);
$I->seeResponseCodeIs(204);
$I->seeResponseIsJson();

// -> check if the entry is REALLY deleted
$I->sendGET('forum_entry/'. $post_id);
$I->seeResponseCodeIs(404);