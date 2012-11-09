<?php

class TestRequest
{
    private $uri;
    private $method = 'GET';
    private $accept = 'text/turtle';
    private $maxRedirects = 4;
    private $postData;
    private $putFile;
    private $putSize;
    private $title;

    private $cert_path = '/var/www/auth/public/tests/agentWebID.pem';
    private $cert_pass = '1234';
    
    function __construct ($uri=null) {
        if ($uri)
            $this->uri = $uri;
    }
    
    public function testHTML() {
        $result = $this->connect();
        $ret = "<p><table>\n";
        $ret .= "<tr><td colspan=\"3\"><strong>".$this->title."</strong></td></tr>\n";
        $ret .= "<tr>\n";
        $ret .= "   <td><pre><strong>Request:</strong></pre>\n";
        $ret .= "   <td width=\"5\"></td>\n";
        $ret .= "   <td><pre><strong>Response:</strong></pre>\n";
        $ret .= "</tr>\n";
        $ret .= "<tr>\n";
        $ret .= "   <td><pre>".print_r($result['info']['request_header'], true)."</pre></td>\n";
        $ret .= "   <td width=\"5\"></td>\n";
        $ret .= "   <td><pre>";
        $ret .= "   Content-Type: ".$result['info']['content_type']."<br/>";
        $ret .= "   HTTP code: ".$result['info']['http_code']."<br/>";
        $ret .= "   </pre>";
        $ret .= "   </td>\n";
        $ret .= "</tr>\n";
        $ret .= "<tr>\n";
        $ret .= "<td colspan=\"3\"><pre>Content:<br/>".$result['content']."</pre></td>\n";
        $ret .= "</tr>\n";
        $ret .= "</table></p>\n";
        return $ret;
    }
    
    
    /**ldp
     * Load RDF data into the graph from a URI.
     *
     * If no URI is given, then the URI of the graph will be used.
     *
     * The docurunment type is optional but should be specified if it
     * can't be guessed or got from the HTTP headers.
     *
     * @param  string  $uri     The URI of the data to load
     * @param  string  $format  Optional format of the data (eg. rdfxml)
     * @return integer          The number of triples added to the graph
     */
    public function connect() {

        // Send the request using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->uri);

        // Configure for POST and PUT
        if ($this->method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postData);
        } else if ($this->method == 'PUT') {
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_INFILE, $this->putFile);
            curl_setopt($ch, CURLOPT_INFILESIZE, $this->putSize);
        }
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->maxRedirects);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        // SSL options

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSLCERT, $this->cert_path);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->cert_pass);

        // Add additional user specified headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: ".$this->accept, 
                                                "Content-Type: ".$this->accept));

        // grab URL and pass it to the browser
        $content = curl_exec($ch);
        $info = curl_getinfo($ch);
        
        if (curl_errno($ch)) { 
            print "Error: " . curl_errno($ch) . ': '. curl_error($ch); 
        } else { 
            // Close the connection
            curl_close($ch); 
        } 
        return array("content" => $content, "info" => $info);
    }
        
    function setUri($uri) {
        $this->uri = $uri;
    }
    
    function setMethod($method) {
        $this->method = $method;
    }
    
    function setAccept($accept) {
        $this->accept = $accept;
    }
    
    function setMaxRedirects($max) {
        $this->maxRedirects = $max;
    }

    function setPostData($data) {
        $this->postData = $data;
    }

    // File can be the contents of a file
    function setPutFile($file) {
        $this->putFile = $file;
        $this->putSize = sizeof($file);
    } 
    
    function setTitle($title) {
        $this->title = $title;
    }

    /**
     * Prepare the request headers
     *
     * @ignore
     * @return array
     */
    protected function prepareHeaders()
    {
        $headers = array();

        // Set the connection header
        if (!isset($this->headers['connection'])) {
            $headers[] = "Connection: close";
        }

        // Set the Accept header
        if (isset($this->accept)) {
            $headers[] = "Accept: " . $this->accept;
        }

        // Add all other user defined headers
        foreach ($headers as $header) {
            list($name, $value) = $header;
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $headers[] = "$name: $value";
        }

        return $headers;
    }

}
