<?php
/*
Plugin Name: Post from AWeber
Plugin URI: https://github.com/davidfcarr/post-from-aweber
Description: Create a blog post based on the archive page for an AWeber email broadcast. Strips out extraneous HTML such as table coding. Allows you to edit the results in the WordPress editor. Appears as a Classic Block in the Gutenberg editor, but you can "convert to blocks" if desired.
Author: David F. Carr
Author URI: http://www.carrcommunications.com
Version: 1.6
*/

function post_from_aweber_fetch () {
global $current_user;
$id = 0;

if(isset($_POST['archive']))
{
if(wp_verify_nonce($_POST['_wpnonce'],'post_from_aweber') && current_user_can('edit_posts'))
    {
        echo '<div class="notice notice-info"><p>Security check: OK</p></div>';

        $content = file_get_contents(esc_url_raw($_POST['archive']));
        preg_match('/<h2.+>(.+)<\/h2>/',$content,$matches);
        $title = $matches[1];

        $parts = explode('</section>',$content);
        //array_shift($parts);
        $debug = $content = aweber_html_to_blocks($parts[1]);//implode('',$parts)
        $content = wp_kses_post($content);
        $post['post_title'] = sanitize_text_field($title);
        $post['post_content'] = $content;
        $post['post_status'] = 'draft';
        $post['post_author'] = $current_user->ID;
        $id = wp_insert_post($post);
        
    }
    else
        echo '<div class="notice notice-error"><p>Security error</p></div>';
}

printf('<h1>Fetch an AWeber archive page ...</h1> <p>...and turn it into a WordPress blog post.</p><form method="post" action="%s">Archive url: <input name="archive" type="text" value="" />%s<button>Get</button></form>',admin_url('edit.php?page=post_from_aweber_fetch'),wp_nonce_field('post_from_aweber'));

if($id) {
    printf('<h1>Draft Post Created</h1><p><a href="%s">Edit / Publish</a></p><p>Showing Preview Below</p>',admin_url('post.php?action=edit&post='.$id));
        
    printf('<h2>%s</h2>',$title);
    echo $content;

    echo '<pre>'.htmlentities($debug).'</pre>';
}

// test url http://archive.aweber.com/geeknews/HmWs_/h/March_2020_News_Map_of_Dive.htm

}// end function

function post_from_aweber_archive_menu() {
    add_submenu_page('edit.php', __("AWeber Post",'rsvpmaker'), __("AWeber Post",'rsvpmaker'), 'edit_posts', "post_from_aweber_fetch", "post_from_aweber_fetch" );  
}

add_action('admin_menu','post_from_aweber_archive_menu');

function aweber_html_to_blocks($html) {
    $html = str_replace("\n"," ",$html);// remove excess line breaks
    $html = preg_replace('/style="[^"]+"/',"",$html);
    $html = preg_replace('/(<a[^>]+>)?([\n\s]{0,5})?(<img[^>]+>)([\n\s]{0,5})?(<\/a>)/is',"$1$3$5\n",$html);
    $html = preg_replace('/<\/(p|tr|div|h1|h2|h3|h4|h5|li|ol|ul)>/',"$0\n",$html);
    $html = preg_replace('/<(li|ol|ul)>/',"\n$0",$html);
    $html = trim(strip_tags($html,'<img>,<a>,<h1>,<h2>,<h3>,<h4>,<ol>,<ul>,<li><figure><b><i><strong><em>'));
    $html = preg_replace_callback('/<a .*?<\/a>/is',function ($matches) {
        $target = $matches[0];
        if(strpos($target,"\n")) {
            $target = str_replace("\n"," ",$target);
        }
        return $target;
    },$html);
    $html = preg_replace_callback('/<li .*?<\/li>/is',function ($matches) {
        $target = $matches[0];
        if(strpos($target,"\n")) {
            $target = str_replace("\n"," ",$target);
        }
        return $target;
    },$html);
    $blocks = preg_split('/\n{1,}/is',$html);
    $output = '';
    foreach($blocks as $index => $block) {
        $line = '';
        if(!preg_match('/[a-zA-Z0-9]+/',$block)) // no alphanumerics
            continue;
        $block = trim($block);
        if(empty($block))
            continue;
        preg_match('/<\/{0,1}(ul|ol|li|)/',$block, $listmatch);
        if(strpos($block,'<img') !== false) {
            preg_match('/href="([^"]+)"/',$block,$hmatch);
            preg_match('/src="([^"]+)"/',$block,$smatch);
            preg_match('/alt="([^"]+)"/',$block,$amatch);
            $src = preg_replace('/https:.+googleusercontent[^#]+#/','',$smatch[1]);
            $alt = empty($amatch[1]) ? '' : $amatch[1];
            $block = '<img src="'.$src.'" alt="'.$alt.'"/>';
            if(!empty($hmatch[1]))
                $block = '<a href="'.$hmatch[1].'">'.$block.'</a>';
            $line .= '<!-- wp:image {"linkDestination":"custom"} -->'."\n<figure class=\"wp-block-image\">".$block."</figure>\n<!-- /wp:image -->\n\n";
        }
        elseif(strpos($block,'<h2') !== false)
            $line .= "<!-- wp:heading -->\n".$block."\n<!-- /wp:heading -->\n";
        elseif(strpos($block,'<h3') !== false)
            $line .= "<!-- wp:heading  {\"level\":3} -->\n".$block."\n<!-- /wp:heading -->\n\n";
        elseif(strpos($block,'<h1') !== false)
            $line .= "<!-- wp:heading  {\"level\":1} -->\n".$block."\n<!-- /wp:heading -->\n\n";
        elseif(strpos($block,'<h4') !== false)
            $line .= "<!-- wp:heading  {\"level\":4} -->\n".$block."\n<!-- /wp:heading -->\n\n";
        elseif(strpos($block,'<h5') !== false)
            $line .= "<!-- wp:heading  {\"level\":5} -->\n".$block."\n<!-- /wp:heading -->\n\n";
        elseif(strpos($block,'<p') !== false)
            $line .= "<!-- wp:paragraph -->\n".$block."\n<!-- /wp:paragraph -->\n\n";
        elseif(!empty($listmatch[1]))
            {
                $line .= $block."\n";
                if(preg_match('/<\/(ul|ol){1,}/',$block))
                    $line .= "\n";
            }
        else {
            $line .= "<!-- wp:paragraph -->\n<p>".$block."</p>\n<!-- /wp:paragraph -->\n\n";
        }
        $output .= $line;
    }
    
    return $output;
}

?>