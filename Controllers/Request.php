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
    /** Errors array */
    private $errors = array();
        
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
                $format = 'rdfxml'; 
            } else if (strstr($headerType, 'n3')) {
                $format = 'n3';
            } else if (strstr($headerType, 'turtle')) {
                $format = 'turtle';              
            } else if (strstr($headerType, 'ntriples')) {
                $format = 'ntriples';  
            } else if (strstr($headerType, 'html')) {
                $format = 'html';
            } 
            $this->format = $format;
        }
    }

    /**
     * POST data to an ldp:Container or to an ldp:Resource
     * @param string $data  RDF representation of the request
     * 
     * @return string   the URI of the new Container/Resource
     */
    public function post($data) {
        // Check to see if we have a container for each path level of 
        // the request, otherwise recursively create containers
        // Start with first element

        // Create the full path including host name
        $path = $this->reqURI;
        $uri = BASE_URI.'/'.$path;
            
        // Get the type of the request (container/resource)
        $remoteGraph = new Graphite();
        $count = $remoteGraph->addTurtle($uri, $data);
        $type = $remoteGraph->resource($uri)->type();

        if ($type == 'http://www.w3.org/ns/ldp#Container') {
            // Add trailing slash if missing
            if (substr($uri, -1) != '/') {
                $uri .= '/';
                $path .= '/';
            }
            // Add container
            $container = new LDP($uri, BASE_URI, SPARQL_ENDPOINT);
            $status = $container->add_container($path, $data);
            $this->status = $status;
        } else if ($type == 'http://www.w3.org/ns/ldp#Resource') {
            // add resource
            $resource = new LDP($uri, BASE_URI, SPARQL_ENDPOINT);
            $status = $resource->add_resource();
            $this->status = $status;
        } else {
            $this->status = 501; // only implemented for Containers/Resources
        }
        // debug
        array_push($this->errors, 'Level='.$uri.' | Type='.$type);
        return rtrim($uri);
    }

    /**
     * GET data from an ldp:Container or to an ldp:Resource
     *
     * @return void
     */
    public function get()
    {
        $uri = BASE_URI.'/'.$this->reqURI;
        $ldp = new LDP($uri, BASE_URI, SPARQL_ENDPOINT);
        $ldp->load();

        if ($ldp->isEmpty() == true)  {
            $this->body = 'Resource '.$uri.' not found.';
            $this->status = 404;
        } else {   
            $this->etag = $ldp->get_etag();

            // return RDF or text
            if ($this->format !== 'html') {
                $this->body = $ldp->serialise($this->format);
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
    public function get_etag()
    {
        return $this->etag;
    }

    /**
     * Delete a cached graph from the SPARQL store
     *
     * @param string $requestOwner the user requesting the delete action
     * @return boolean
     */
    public function deleteCache($requester)
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
    public function get_body()
    {
        return $this->body;
    }

    /**
     * Get the status code for the HTTP response (e.g. 200, 404, etc.)
     *
     * @return integer
     */
    public function get_status()
    {
        return $this->status;
    }
    
    /** 
     * Return any errors we may have encountered
     * @return string
     */
    public function get_errors() {
        foreach ($this->errors as $err) {
            echo "<br/>".$err;
        }
    }

    /**
     * Return a URI without the hash fragment (#me)
     * @return string
     */
    public function hashless($uri)
    {
        $uri = explode('#', $uri);
        return $uri[0];
    }
}

