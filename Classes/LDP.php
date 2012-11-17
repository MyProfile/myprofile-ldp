
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

#namespace Classes;
#namespace lib\Graphite;

/**
 * LDP class 
 *
 * @todo use boolean return methods for current functions which return html
 */ 
class LDP 
{
    /** SPRAQL endpoint address */
    private $endpoint;
    /** The requested URI */
    private $reqURI;
    /** The server's FQDN (i.e. http://example.com/ */
    private $base_uri;
    /** Cache dir (not used for now) */
    private $cache_dir;
    /** Graphite graph object containing the profile RDF data */
    private $graph;
    /** The hashed etag value corresponding to the graph contents */
    private $etag;

    /**
     * Build the selectors for adding more form content (default ttl is 24h)
     *
     * @param string    $reqURI     the requested resource URI
     * @param string    $base_uri   the server's FQDN 
     * @param string    $endpoint   the SPARQL endpoint
     * @return void
     */
    function __construct($reqURI, $base_uri, $endpoint) {
        // We're not interested in the hashed URI when storing the graph data
        $reqURI = explode('#', $reqURI);
        $this->reqURI = $reqURI[0];
        
        if (isset($base_uri))
            $this->base_uri = $base_uri;
        // set cache dir
        $this->cache_dir = 'cache/';
        
        // set the SPARQL endpoint address
        $this->endpoint = $endpoint; 
    }
    
    /**
     * Build the graph using SPARQL
     * (optionally fallback to Graphite if there is a problem with the SPARQL
     * endpoint )
     *
     * @return void
     */
    public function sparql_graph() {
        // Query the local store for the requested resource
        $db = sparql_connect($this->endpoint);
        $query = 'SELECT * FROM <'.$this->reqURI.'> WHERE { ?s ?p ?o }';

        $result = $db->query($query);

        // fallback to EasyRdf if there's a problem with the SPARQL endpoint
        if (!$result) {
            $this->direct_graph();
        } else {
            $query = "PREFIX dcterms: <http://purl.org/dc/terms/> ";
            $query .= "PREFIX ldp: <http://www.w3.org/ns/ldp#> ";
            $query .= "CONSTRUCT { ?s ?p ?o } WHERE { GRAPH <".$this->reqURI."> { ?s ?p ?o } }";
            
            //echo "DEBUG: SPARQL query=".$this->endpoint.'?query='.urlencode($query);

            $sparql = new EasyRdf_Sparql_Client($this->endpoint); 
            $graph = $sparql->query($query);
            $this->graph = $graph;
        }       
    }
    
    /** 
     * Build the grap using EasyRdf
     *
     * @return integer number of triples loaded
     */
    function direct_graph() {
        // Load the RDF graph data
        $graph = new EasyRdf_Graph();
        $count = $graph->load($this->reqURI);
        $this->graph = $graph;

        return $count;
    }
    
    /** 
     * Load the requested URI data (priority is for SPARQL, otherwise go with EasyRdf)
     *
     * @return void
     */
    public function load() {
        // check if we have a SPARQL endpoint configured
        if (strlen($this->endpoint) > 0) {
            // use the SPARQL endpoint 
            $this->sparql_graph();
        } else {
            // use the direct method (EasyRdf)
            $this->direct_graph();
        }
        
        // generate eTag hash from graph contents (used for caching)
        $this->etag = sha1(print_r($this->graph, true));
    }
    
    /**
     * Store container RDF data into the triple store
     * @param string    $path       file system path
     * @param string    $rdf        the rdf data from the request
     * @param boolean   $overwrite  overwrite the local graph or not
     * @return integer  HTTP response code
     */
    function add_container($path, $rdf, $overwrite=false) {
        $exists = false;

        $remoteGraph = new Graphite();
        $remoteGraph->addTurtle($this->reqURI, $rdf);
        $remoteRes = $remoteGraph->resource($this->reqURI);

        // Check if a local graph exists already 
        $this->sparql_graph();

        if ($this->isContainer() == true)
            $exists = true;

        // Store graph only if it doesn't exist of if we have a PUT request
        if (($exists == false) || ($overwrite == true)) {
            $db = sparql_connect($this->endpoint);
            $query = 'PREFIX acl: <http://www.w3.org/ns/auth/acl#>
                    PREFIX ldp: <http://www.w3.org/ns/ldp#>
                    PREFIX dcterms: <http://purl.org/dc/terms/>
                    INSERT DATA INTO <'.$this->reqURI.'> {
                        <'.$this->reqURI.'> rdf:type ldp:Container;
                                            dcterms:title "'.$remoteRes->get('dcterms:title').'".
                    }';
            $result = $db->query($query);
            if ($result) {
                $ret = 201;
            } else {
                $ret = 500; // We have an error
                echo "SPARQL Error: cannot create the container!";
            }
            // Create directory on the file system
            /*
            if (!is_dir('../conf/')) {
                if (!mkdir('../conf/', 0775))
                    die('Failed to create conf/ dir...');
                $status = "<p><font color=\"green\"><strong>Success!</strong></font>";
                $status .=" Configuration dir has been created.</p>\n";
                $ok = true;
            } else {
                $status = "<p><strong>Skipped.</strong> Configuration dir already exists.</p>\n";
            }
            */
            return $ret;
        } else if ($exists == true) {
            // The requested path already exists - the reqest still succeeded
            $ret = 200;
            return $ret;
        }
    }
    
    /**
     * Get the last modified date in unix format
     *
     * @return string
     */
    function get_etag() {
        return $this->etag;
    }
    
    /**
     * Get the user's raw graph object
     *
     * @return EasyRdf_graph
     */
    function get_graph() {
        return $this->graph;
    }
    
    /** 
     * Get the whole graph, serialised in the specified format
     *
     * @param string $format    serialisation format (e.g. turtle, n3, etc.)
     * @return string
     */
    public function serialise($format) {
        return $this->graph->serialise($format);
    }

    /**
     * Check if the webid is a local and return the corresponding account name
     *
     * @param string $webid the WebID of the user
     *
     * @return boolean
     */ 
    private function is_local($webid) {
        $webid = (isset($webid)) ? $webid : $this->reqURI;
        if (strstr($webid, $_SERVER['SERVER_NAME']))
            return true;
        else
            return false;
    }
    
    /**
     * Check if the graph is empty or not
     *
     * @return boolean  Yes if graph is empty
     */
    public function isEmpty() {
        return $this->graph->isEmpty();
    }
    
    /**
     * Get the type of the graph/resource
     *
     * @param string $uri   the resource URI
     * @return string       the resource type
     */
    public function get_type($uri) {
        return $this->graph->resource($uri)->get('rdf:type');
    }
    
    /**
     * Check if the graph is a container or not
     * @return boolean  True if it is a container
     */
    private function isContainer() {
        $type = $this->graph->resource($this->reqURI)->get('rdf:type');
        if ($type == 'http://www.w3.org/ns/ldp#Container')
            return true;
        else
            return false;
    }
    
    /**
     * Check if the graph is a resource or not
     * @return boolean  True if it is a resource
     */
    private function isResource() {
        $r = $this->graph->resource($this->reqURI);
        return ($r->type() == 'http://www.w3.org/ns/ldp#Resource') ? true : false;
     }
    
    /**
     * Get local path for user (only if it's a local user)
     *
     * @param string $webid the WebID of the user
     *
     * @return string|false
     */
    function get_local_path($webid) {
        // verify if it's a local user or not
        if ($this->is_local($webid)) {
            $location = strstr($webid, $_SERVER['SERVER_NAME']);
            $path = explode('/', $location);
            $path = $path[1]."/".$path[2];
            return $path;
        } else {
            return false;
        }
    }
}
 
