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

 *  The above copyright notice and this permission notice shall be included in all 
 *  copies or substantial portions of the Software.

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
            // data
            $this->data = $data;
            
            // Use turtle by default 
            $format = 'turtle';
            // Set format based on the request
            $headerType = $this->app->request()->headers('Accept');
            if (strstr($headerType, 'application/rdf+xml')) {
                $format = 'rdfxml'; 
            } else if (strstr($headerType, 'n3')) {
                $format = 'n3';
            } else if (strstr($headerType, 'text/turtle')) {
                $format = 'turtle';              
            } else if (strstr($headerType, 'ntriples')) {
                $format = 'ntriples';  
            } else if (strstr($headerType, 'text/html')) {
                $format = 'html';
            } 
            $this->format = $format;
        }
    }

    
    public function post($data) {
        // Check to see if we have a container for each path level of 
        // the request, otherwise recursively create containers
        // Start with first element
        $this->data = $data;

        $level = ''; 
        $i=0;
        while ($i < sizeof($this->reqURI)) {
            // add the current level to the previous path
            $level .= '/'.$this->reqURI[$i];
            // create the current base path
            $base = BASE_URI.$level;
                
            // Search if a graph exists for current level
            $localGraph = new LDP($base, BASE_URI, SPARQL_ENDPOINT);
            $localGraph->load();
            $type = $localGraph->get_type();
            echo '<br/>Level='.$base.' | Type='.$type; 

            // Create container/resource as requested
            $remoteGraph = new Graphite();
            $remoteGraph->addTurtle($base, $this->data);
                
            $res = $remoteGraph->resource($base);
            $cont = $remoteGraph->allOfType('ldp:Container');
            $res = $remoteGraph->allOfType('ldp:Resource');

            if (sizeof($cont) > 0) {
                // add container
                $container = new LDP($base, BASE_URI, SPARQL_ENDPOINT);
                $ok = $container->add_container($this->data);
            } else if (sizeof($res) > 0) {
                // add resource
                $resource = new LDP($base, BASE_URI, SPARQL_ENDPOINT);
                $resource->add_resource();
            }

            $i++;
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
        
        $res = new Resource($this->reqURI, BASE_URI, SPARQL_ENDPOINT);
        $res->loadLocal();

        if ($res->get_graph()->isEmpty())  {
            $this->body = 'Resource '.$this->reqURI.' not found.';
            $this->status = 404;
        } else {   
            $this->etag = $res->get_etag();

            // return RDF or text
            if ($this->format !== 'html') {
                $this->body = $res->serialise($this->format);
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

