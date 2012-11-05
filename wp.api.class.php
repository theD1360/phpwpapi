<?php 
/*
    Name: Wordpress API Connection Class
    Author: Diego Alejos
    For: CPRIT
    Description: This class is designed to interface with JSON API plugin for
    wordpress. This will allow us to access a wordpress installation through
    a RESTful interface and give us secure access to it's inner workings.
    This script was written as a part of the mediaroom project and is included
    in the tranScod3.php daemon. 
    
    Dependencies: DOMDocument, and php cURL
    
*/

class wp_connect{
    private $wp_url, $wp_user, $wp_password, $wp_cookie, $wp_dev = 0;
    
    /*
        Instantiate the class, we need to have a URL, username, and password 
        to avoid errors.
    */
    function __construct($url, $username, $password, $cookie = "cookieJar.txt"){
        $this->setURL($url);
        $this->setLogin($username, $password);
        $this->setCookieJar($cookie);
    }
    
    /*
        Set and verify the wordpress install url
    */
    public function setURL($url){
        if(empty($url) || !filter_var($url, FILTER_VALIDATE_URL))
            throw new Exception("Wordpress installation url '$url' is invalid.\n");
    
        $this->wp_url = rtrim($url, "/");
    }
    
    /*
        Set the login verification credentials
    */
    public function setLogin($username, $password){
        if(empty($username) || empty($password))
            throw new Exception("Missing login credentials.\n");
        $this->wp_user = $username;
        $this->wp_password = $password;
    }
    
    /*
        Set the cookie jar file and verify permissions
    */
    public function setCookieJar($cookie){
        if(empty($cookie))
            throw new Exception("The cookie jar cannot be empty!\n");
        if(!is_writable($cookie) || !is_readable($cookie))
            throw new Exception("The cookie jar file needs to be have read/write permissions.\n");
            
        $this->wp_cookie = $cookie;
    }
    
    /*
        This function should verify the user login data and set our cookies
    */
    public function authenticate(){
        $path = "wp-login.php";
        $data = array(
            "log" => $this->wp_user,
            "pwd" => $this->wp_password,
            "wp-submit" => "Log%20In",
            "redirect_to" =>  $this->wp_url ."/wp-admin/",
            "testcookie" => "1"
        );


        /* This will return raw HTML Data if request was succesfull otherwise it will throw an exception */
        $results = $this->requestRaw($path, $data, "POST");

        /* 
            We need to check the resulting html for an error message because the api doesn't interface with the login natively 
            This may change in the future. This should be the only place we need DOMDocument.
        */        

            $dom = new DOMDocument();
            $dom->validateOnParse = true;

            @$dom->loadHTML($results);
            $elem = @$dom->getElementById("login_error");
                $error = trim(strip_tags(@$elem->textContent));
                // If we were able to find an error throw an exception
                if(!empty($error)){
                    throw new Exception("Could not authenticate. ($error)\n");
                }
            

        
        return $results;

    }
    

    
    /*
        =======API helper methods below=======
    */

        
    public function getNonce($controller, $method){
        $results = $this->apiRequest("get_nonce", array("controller"=>$controller, "method"=>$method));
        return $results->nonce;
    }

    public function getInfo(){
        $results = $this->apiRequest("info");;
        return $results;
    }
    
    /*
        Helper Method for creating posts
        This will automatically get the nonce for us and submit the posting.
        
    */
    public function createPost($post = array()){
 
        if(empty($post))
            throw new Exception("Your post cannot be empty!\n");
 
        // Get the required nonce for this function
        $nonce = $this->getNonce("posts", "create_post");
        $data = array(
            "nonce" => $nonce,
            "status" => "",
            "title" => "",
            "content" => "",
            "author" => "",
            "categories" => "",
            "tags"=>""
        );
        
        $data = array_merge($data, $post);
        
        return $this->apiRequest("posts/create_post", $data);
       
    }

    /* 
        =======CURL Request and API interfaces=======
    */
    public function apiRequest($method, $post_data=array(), $post_method ="get"){
        $data = array(
            "json"=>$method,
            "dev"=>$this->wp_dev
        );
        
        if(!empty($post_data))
            $data = array_merge($data, $post_data);
        
        // Get rid of any junk. 
        // NOTE: this may cause issues if we want to pass empty values
        // This is may need to change depending on usage
        $data = array_filter($data);
        
        if(empty($data))
            throw new Exception("JSON API request cannot be empty!\n");
        
        return $this->request("/", $data, $post_method);
        
    }  

    /*
        This method will be the main API access method 
    */
    public function request($path, $data=array(), $method="GET"){
        $results = json_decode($this->requestRaw($path, $data, $method));

        if(strtoupper($results->status)!="OK")
            throw new Exception("Request failed (".$results->error.")\n");

        return $results;

    }

    /*
        CURL request abstraction.
        This is abstracted so that we can use cookie authentication and
        get raw data while another method will be used to handle json api data
    */
    private function requestRaw($path, $data = array(), $method="GET", $referer="/"){
       
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $this->wp_url . "/" . ltrim($path, "/"));

        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_COOKIESESSION, FALSE);
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->wp_cookie);
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->wp_cookie);
        curl_setopt ($ch, CURLOPT_REFERER, $this->wp_url . "/" . ltrim($referer, "/"));

              
        curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        switch(strtoupper($method)){
            case "POST": 
                 curl_setopt ($ch, CURLOPT_POST, 1);
            break;
            case "PUT": 
                 curl_setopt ($ch, CURLOPT_PUT, 1);
            break;
            case "HEAD":
                 curl_setopt ($ch, CURLOPT_HEAD, 1);
            break;
            default:
                case "GET":
                    /*
                        There is no CURLOPT_GET
                    */
                break;

        }


        $result = curl_exec ($ch);

        if(curl_errno($ch)){
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      
            if($status != 200)
                throw new Exception("Could not connect to wordpress api server. Status $status (".curl_error($ch).")\n");
        }

        curl_close($ch);
        
        return $result;    
    }

}

/*
    Testing the class
*/

/*

try{
    $wp = new wp_connect("http://184.106.156.213/", "admin", "55G0ldP3ak");
    

   $attachments = $wp->apiRequest("attachments/get_attachments");
   foreach($attachments as $k => $a){
    if(isset($a->mime_type)){
        echo $a->url."\n";
        if(strstr($a->mime_type, "video")){
         var_dump($wp->createPost(array(
            "title" => "API test post {$a->title}",
            "content" => '<div class="clearfix"><a href="http://184.106.156.213/wp-content/uploads/2012/09/beard_48_1_handlebar_mustache.png"><img class="alignleft  wp-image-40" title="beard_48_1_handlebar_mustache" src="http://184.106.156.213/wp-content/uploads/2012/09/beard_48_1_handlebar_mustache.png" alt="" width="322" height="154" /></a> This is a fancy post layout that I plan on using for the video section of the site. When the user clicks on the mustache it will open a video player lightbox and begin the playback.</div>
<div><small><br />
</small></div>
<h5 style="text-align: justify;"><strong>Download:</strong> <a href="'.$a->url.'">Original</a> <a href="http://asdfsdf">flv</a> | <a href="http://asdfdsaf">1080</a> | <a href="http://asdfasdf">720</a> | <a href="http://asdfsadf">480</a> | <a href="http://asdfsad">mov</a> | <a href="http://asdfas">mpeg</a> | <a href="http://asdf">m4v</a></h5>
',
            "media" => array($a->id)
        )));       
        }
    }
   }
 
}catch(Exception $e){
    echo $e->getMessage();
}

*/
?>
