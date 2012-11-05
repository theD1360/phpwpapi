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
            "content" => '<div class="clearfix"><a href="http://localhost/wp-content/uploads/2012/09/beard_48_1_handlebar_mustache.png"><img class="alignleft  wp-image-40" title="beard_48_1_handlebar_mustache" src="http://184.106.156.213/wp-content/uploads/2012/09/beard_48_1_handlebar_mustache.png" alt="" width="322" height="154" /></a> This is a fancy post layout that I plan on using for the video section of the site. When the user clicks on the mustache it will open a video player lightbox and begin the playback.</div>
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
?>
`