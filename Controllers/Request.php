<?php
/**
 *  Copyright (C) 2012 MyProfile Project
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal 
 *  in the Software without restriction, including without limitation the rights 
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
 *  copies of the Software, and to permit persons to whom the Software is furnished 
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all 
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, 
 *  INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A 
 *  PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT 
 *  HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION 
 *  OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE 
 *  SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 *  @author Andrei Sambra 
 */

 
/**
 * The Profile controller class handles actions related to user profiles
 */
class Request 
{
    /** Slim app object */
    private $app;
    /** HTTP reponse body */
    private $body;
    /** HTTP reponse code */
    private $status;
    /** requested URI */
    private $reqURI;
    /** Serialization format (e.g. rdfxml, n3, etc.) */
    private $format;
    /** eTag hash of the graph's contents */
    private $etag;
    /** Request data */
    private $data;
        
    // hack until proper AC is used
    //private $allowed_users = array("https://my-profile.eu/people/deiu/card#me", 
    //                "https://my-profile.eu/people/myprofile/card#me");

    /**
     * Constructor for the class
     * (sets the HTTP response body contents and status code)
     *
     * @param Slim $app   the Slim REST API object (used for the HTTP response)
     *
     * @return void 
     */
    function __construct ($app, $reqURI)
    {
        if ( ! $app) {
            echo 'Controller_User -> Constructor error: missing app object.';
            exit(1);
        } else {
            // setting the app object
            $this->app = $app;
            // requested URI           
            $this->reqURI = $reqURI;            
            // Use turtle by default 
            $format = 'Turtle';
            // Set format based on the request
            $headerType = $this->app->request()->headers('Accept');
            if (strstr($headerType, 'application/rdf+xml')) {
                $format = 'RDFXML'; 
            } else if (strstr($headerType, 'n3')) {
                $format = 'N3';
            } else if (strstr($headerType, 'turtle')) {
                $format = 'Turtle';              
            } else if (strstr($headerType, 'ntriples')) {
                $format = 'NTriples';  
            } else if (strstr($headerType, 'html')) {
                $format = 'html';
            } 
            $this->format = $format;
        }
    }

    
    public function post($data) {
        // Check to see if we have a container for each path level of 
        // the request, otherwise recursively create containers
        // Start with first element

        // Create path from the request
        $path = implode('/', $this->reqURI);
        // Create the full path including host name
        $base = BASE_URI.'/'.$path;
            
        // Prepare container/resource as requested
        $remoteGraph = new Graphite();
        $count = $remoteGraph->addTurtle($base, $data);
        
        $type = $remoteGraph->resource($base)->get("rdf:type");
        
        //echo "<pre>".$remoteGraph->dumpText()."</pre>";
        echo '<br/>Level='.$base.' | Type='.$type; 

        if ($type == 'http://www.w3.org/ns/ldp#Container') {
            // add container
            $container = new LDP($base, BASE_URI, SPARQL_ENDPOINT);
            $ok = $container->add_container($path, $data);
            if ($ok == true)
                echo "<br/>Added container at ".$base."<br/>";
            else
                echo "<br/>Cannot add container for ".$base."<br/>";
        } else if ($type == 'http://www.w3.org/ns/ldp#Resource') {
            // add resource
            $resource = new LDP($base, BASE_URI, SPARQL_ENDPOINT);
            $resource->add_resource();
        }
    }

    /**
     * Display a user's profile based on the requested format
     * (sets the HTTP response body contents and status code)
     *
     * @param string $on_behalf the WebID on behalf of whom the request is made
     *
     * @return void
     */
    public function get()
    {
        $path = implode('/', $this->reqURI);
        $res = new LDP($path, BASE_URI, SPARQL_ENDPOINT);
        $res->load();

        if (sizeof($res->get_graph()) == 0)  {
            $this->body = 'Resource '.$path.' not found.';
            $this->status = 404;
        } else {   
            $this->etag = $res->get_etag();

            // return RDF or text
            if ($this->format !== 'html') {
                $this->body = $res->serialize($this->format);
            } else {
                include 'views/tabulator/index.php';
            }
            $this->status = 200;
        }
    }

    /**
     * Get the etag hash for the requested data.
     * @return string
     */
    function get_etag()
    {
        return $this->etag;
    }

    /**
     * Delete a cached graph from the SPARQL store
     *
     * @param string $requestOwner the user requesting the delete action
     * @return boolean
     */
    function deleteCache($requester)
    {
        $allowed = false; // for now
        $requester = $this->hashless($requester);
        
        // Delete only if the requested graph is the user's own graph
        if ($this->reqURI === $requester)
            $allowed = true;

        if ($allowed === true) {
            // delete
            $db = sparql_connect(SPARQL_ENDPOINT);
            $sql = "CLEAR GRAPH <" . $this->reqURI . ">";
            $res = $db->query($sql);

            // Set up the response
            $this->body = 'Successfully deleted '.$this->reqURI.".\n";
            $this->status = 200;
            
            return true;
        } else {
            // 401 unauthorized 
            // Set up the response
            $this->body = 'You are not authorized to delete '.$this->reqURI.".\n";
            $this->status = 401;

            return false;
        }
    }

    /**
     * Get the contents of the body for HTTP reponse 
     *
     * @return string
     */
    function get_body()
    {
        return $this->body;
    }

    /**
     * Get the status code for the HTTP response (e.g. 200, 404, etc.)
     *
     * @return integer
     */
    function get_status()
    {
        return $this->status;
    }
    
    /**
     * Return a URI without the hash fragment (#me)
     * @return string
     */
    function hashless($uri)
    {
        $uri = explode('#', $uri);
        return $uri[0];
    }
}

