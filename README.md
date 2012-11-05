phpwpapi
========

PHP class for interfacing with Wordpress API

https://github.com/dphiffer/wp-json-api

usage
-----
`
<?php
try{
    $wp = new wp_connect("http://localhost/", "admin", "p0t4t0");
    

   $attachments = $wp->apiRequest("attachments/get_attachments");
   foreach($attachments as $k => $a){
    if(isset($a->mime_type)){
        echo $a->url."\n";
        if(strstr($a->mime_type, "video")){
         var_dump($wp->createPost(array(
            "title" => "API test post {$a->title}",
            "content" => 'Thisi is a test post',
            "media" => array($a->id)
        )));       
        }
    }
   }
 
}catch(Exception $e){
    echo $e->getMessage();
}
?>
`