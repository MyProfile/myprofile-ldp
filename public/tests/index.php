<?php

// RDF libs
require 'Http/TestRequest.php';

$uri = (isset($_REQUEST['uri'])) ? $_REQUEST['uri'] : "";
$form = '<form name="test" method="GET">';
$form .= 'LDP URI: ';
$form .= '<input type="text" name="uri" value="'.$uri.'">';
$form .= '<input type="submit" name="submit" value="Test!">';
$form .= '';
$form .= '</form>';
echo $form;

// Let the tests begin!
if (isset($_REQUEST['uri'])) {

    $container = $_REQUEST['uri'];
    // ugly hack to avoid extra / at the end
    $slash = (substr($_REQUEST['uri'], -1) == '/') ? '':'/';
    $container .= $slash.'testcontainer';
    
/* --- POST container --- */
    $postData = "@prefix dcterms: <http://purl.org/dc/terms/>.\n".
                "@prefix ldp: <http://www.w3.org/ns/ldp#>.\n".
                "<>\n".
                "   a ldp:Container ;\n".
                "   dcterms:title \"A new empty container\" .";

    $test = new TestRequest();
    $test->setUri($container);
    $test->setMethod('POST');
    $test->setPostData($postData);
    $test->setAccept('text/turtle');
    $test->setTitle('POST container on '.$container);
    $ret = $test->testHTML();

    echo $ret;

/* --- GET container --- */
    $test = new TestRequest();
    $test->setUri($container);
    $test->setMethod('GET');
    $test->setAccept('text/turtle');
    $test->setTitle('GET container on '.$container);
    $ret = $test->testHTML();

    echo $ret;

    
}


