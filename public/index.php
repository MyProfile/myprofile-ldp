<?php
/*
 *  Copyright (C) 2012 MyProfile Project - http://myprofile-project.org
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal 
 *  in the Software without restriction, including without limitation the rights 
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
 *  copies of the Software, and to permit persons to whom the Software is furnished 
 *  to do so, subject to the following conditions:

 *  The above copyright notice and this permission notice shall be included in all 
 *  copies or substantial portions of the Software.

 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, 
 *  INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A 
 *  PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT 
 *  HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION 
 *  OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE 
 *  SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
 
set_include_path(get_include_path() . PATH_SEPARATOR . '../');
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib/');

/* ---- LIBRARIES ---- */

// Load the Slim framework and related stuff
require 'lib/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require 'Middleware/LDContentType.php';

// Load libs
require 'lib/logger.php';

// RDF libs
require 'lib/arc/ARC2.php';
require 'lib/Graphite.php';
require 'lib/sparqllib.php';

// Load local classes
require 'Classes/LDP.php';
// Load WebIDauth class
require 'Classes/WebidAuth.php';
// Load Wall class
require 'Classes/Wall.php';

// load controller
require 'Controllers/Request.php';

/* ---- CONFIGURATION ---- */

// Load configuration variables
require 'conf/config.php';

// Get the current document URI
$page_uri = 'http';
if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == 'on') {
    $page_uri .= 's';
}
$page_uri .= '://' . $_SERVER['SERVER_NAME'];
// this is the base uri 
$base_uri = $page_uri;
define ('BASE_URI', $base_uri);


//phpinfo(INFO_VARIABLES);
$log = new KLogger ('../logs/log.txt', 1);

// Load Slim framework
$app = new \Slim\Slim();

// Register ContentType middleware
$app->add(new LDContentType());

// Configure the REST API 
$app->config(array(
    'debug' => true
));

/* ---- RETURN CODES + MESSAGES ---- */

$error_codes = array(
    '200' => '200 OK',
    '400' => '400 Bad request',
    '401' => '401 Unauthorized',
    '403' => '403 Forbidden',
    '404' => '404 Not found'
);


/* ---- ROUTES ---- */

// Redirect all requests made to /, to /user/
$app->get('/', function () use ($app) {
    $base = BASE_URI;
    $version = VERSION;
    include 'views/welcome/index.php';
});

$app->get('/people', function() use ($app, $log) {
    echo "Displaying all people";    
});

$app->get('/:reqURI+', function($reqURI) use ($app, $log) {
    echo print_r($reqURI, true);

    $req_ctrl = new Request($app, $reqURI);
    $req_ctrl->get();
});

$app->post('/:reqURI+', function($reqURI) use ($app, $log) {
    $env = $app->environment();
    $data = $env['slim.input'];

    // DEBUG
    echo "<pre>".htmlentities($data)."</pre>";

    $req_ctrl = new Request($app, $reqURI);
    $req_ctrl->post($data);
});


/*
// GET a local user's profile
$app->get('/people/:user(/:card)', function ($user, $card) use ($app, $log) {
    //echo 'name='.print_r($name, true);
    
    // get the parameters from the URI   
    $name = $user.'/'.$card;
    $reqURI = BASE_URI.'/people/'.$name;

    $auth = new \Classes\WebidAuth();

    // prepare the response object
    $response = $app->response();
    
    $debug = $app->request()->get('debug') ? true : false;
    // prepare the controller for users
    $ctrl = new \Controllers\Resource($app, $reqURI);
    
    $log_text = '';
    
    // check if user is authenticated
    //$isAuthenticated = $auth->processReq();
    
    // Log some useful debug stuff
    $log_text .= 'Displaying profile for WebID: ' . $reqURI . "\n";
    $log_text .= ' * AcceptType: ' . $app->request()->headers('Accept') . "\n";
    
    // Log some useful debug stuff
    $log_text .= ' * Authenticated user: ' . $auth->getIdentity() . "\n";
    
    $ctrl->view($auth->getIdentity());
    $body = $ctrl->get_body();
    $status = $ctrl->get_status();

    $body = ($debug) ? '<pre>'.$log_text.'</pre>'.$body : $body;
    
    $app->etag($ctrl->get_etag());
    $app->response()->body($body);
    $app->response()->status($status);

    $headers = $app->response()->headers();
    $log_text .= ' * ContentType: ' . $headers['Content-Type'] . "\n";
    $log->LogInfo($log_text);
});


// POST a user's profile data
$app->post('/profile/:user(/:card)', function () use ($app, $log, $error_codes) {
    // nothing yet
});


// DELETE a user's profile 
$app->delete('/profile/:user(/:card)', function () use ($app, $log, $error_codes) {
    // Prepare the authentication object
    $auth = new \Classes\WebidAuth($redir=true);
    
    // Authenticate user
    $isAuthenticated = $auth->processReq();
    
    if ($isAuthenticated === true) {
        $ctrl = new \Controllers\Profile($app, $auth->getIdentity());
        $ctrl->deleteCache($auth->getIdentity());

        // Set the appropriate response
        $app->response()->status($ctrl->get_status());
    } else {
        $app->response()->body($error_codes('403'));
        $app->response()->status(403);
    }
});

// GET all messages for a wall belonging to a user (WebID)
// - OR - 
// GET $limit number of messages starting from $offset message
$app->get('/wall/', function () use ($app, $log) {
    // The WebID of the user who owns the wall
    $webid  = urldecode($app->request()->get('webid'));
    // The offset number of messages
    $offset = urldecode($app->request()->get('offset'));
    // The limit number of messages
    $limit  = urldecode($app->request()->get('limit'));
    
//    $auth = new Classes_WebidAuth(CERT_PATH, CERT_PASS);  
//    $isAuth = $auth->processReq();

  //  if ($isAuth === true) {
        // fetch the wall posts
        $wall = new \Classes\Wall($webid, $log);
        echo "Wall graph URI=" . $wall->getWall();
        echo '<pre>'.print_r($wall->getWallPosts(), true).'</pre>';
   // } else {
        // not allowed
   // }    
});

*/

/**
 * Run the Slim application
 */
$app->run();

