<?php
  
    /**
    * KhtCms
    *
    * @author Beno�t HERVIER
    * @copyright 2011 Beno�t HERVIER khertan@khertan.net
    *
    * This library is free software; you can redistribute it and/or
    * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
    * License as published by the Free Software Foundation; either
    * version 3 of the License, or any later version.
    *
    * This library is distributed in the hope that it will be useful,
    * but WITHOUT ANY WARRANTY; without even the implied warranty of
    * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
    *
    * You should have received a copy of the GNU Affero General Public
    * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
    *
    */

    /**
        Datas
            -> Blog
            -> Cache
            -> Downloads
            -> Libs
            -> Pages
    */
    
    include('config.php');

    function Kht_GetRootPath() {
        global $config;
        return $config['InstallPath'];
    }
    function Kht_IsCurrentPage($page) {
        global $config;
        if ($page === $config['InstallPath'] . basename ( Kht_GetRequest(),'.md'))
            return True;
        return False;            
    }
    
    function Kht_GetThemePath() {
        global $config;
        return $config['InstallPath']. 'themes/'. $config['Theme'];
    }
    
    function Kht_GetRequest() {
        global $config;
        if (array_key_exists('path', $_REQUEST)) 
            $request = htmlspecialchars(stripslashes($_REQUEST['path']));
        else {
            return strtolower($config['Default']);
        }
        if ($request === '') return strtolower($config['Default']);
        $request=trim($request,'/');
        return $request;
    }

    function Kht__ob_start() {
        ob_start('ob_gzhandler');

        header('Content-Type: text/html; charset: UTF-8');
        header('Cache-Control: must-revalidate');
    }
    
    function Kht_ServeMarkdownPage($page) {
        
        
        // SERVE CONTENT WITH THE TEMPLATE
        global $config;
        $Kht_Content = '';
        $Kht_CurrentPath = $config['InstallPath'] . basename ( $page , '.md');
        $Kht_Title = basename ( $page , '.md');
        $Kht_CurrentPage = basename ( $page , '.md');

        if (@is_file($page))
            {
            $fcontent = file_get_contents($page);
                                    
            if ($fcontent) {
                include_once('libs/markdown.php');     
                $ccontent = '';
                $content_lines = explode("\n",$fcontent);
                foreach ($content_lines as $line) {
                    if ((stripos($line, "Date:", 0) !== 0)
                        && (stripos($line, "Title:", 0) !== 0)
                        && (stripos($line, "Tags:", 0) !== 0)
                        && (stripos($line, "Category:", 0) !== 0))
                        {$ccontent.=$line."\n";}
                }
                $tags = Kht_ExtractTags($fcontent);
                if ($tags !== '')
                    $Kht_Content['tags'] = $tags;                
                $date = Kht_ExtractDate($fcontent);
                if ($date !== 0)
                    $Kht_Content['date'] = $date;
                $title = Kht_ExtractTitle($fcontent);
                if ($title !== '')
                    $Kht_Content['title'] = $title;

                $Kht_Content['data'] = Markdown($ccontent);
                }
                
        
            //Templatization
            Kht__ob_start();
            include './themes/'.$config['Theme'].'/page.php';
            $content = ob_get_contents();

            }
            
        //Cache it
        $cache_path = './cache/' . str_replace('/','_',$page) . '.html';
        file_put_contents($cache_path,$content);
        ob_end_flush();
    }
    
    function Kht_Redirect($request) {
        if (file_exists('./datas/'.$request.'.md')) return;
        if (file_exists('./datas/pages/'.$request.'.md')) return;
        if (file_exists('./datas/blog/'.$request.'.md')) return;
        if (file_exists('./datas/'.str_replace(' ','_',$request).'.md')) 
        $request = basename ( $request );
        
        $redirect = array();
        
        // Is a page 
        $path = './datas/pages/';
        $dh = opendir($path);
        if ($dh !== false) {
            while (($file = readdir($dh)) !== false) {
                if($file !== '.' && $file !== '..' && !in_array($file, $redirect) && ($file != ".DS_Store") && (strpos($file, "~") == 0)) {
                    if (@file_exists($path.$file)) {
                        $file=pathinfo($file,PATHINFO_FILENAME);
                        $redirect[$file] = '/'.strtolower($file);
                    }
                }
            }
            closedir($dh);
        }
        
        // Is a blog   
        //blog prefix missing
        $path = './datas/blog/';
        $dh = opendir($path);
        if ($dh !== false) {
            while (($file = readdir($dh)) !== false) {
                if($file !== '.' && $file !== '..' && !in_array($file, $redirect) && ($file != ".DS_Store") && (strpos($file, "~") == 0)) {
                    if (@file_exists($path.$file)) {
                        $file=pathinfo($file,PATHINFO_FILENAME);
                        $redirect[$file] = '/blog/'.strtolower($file);                
                    }
                }
            }
            closedir($dh);
        }
        
        foreach($redirect as $key => $link) {
            if (strcasecmp($key, $request)==0) {
            header("HTTP/1.1 301 Moved Permanently");
                header('Location: http://'.$_SERVER['HTTP_HOST'].''.$link,True,301);
                return;
            }       
        }    
    }
    
    function Kht_ServePageCache($page) {
        global $config;
        $cache_path = './cache/' . str_replace('/','_',$page) . '.html';
        if ((is_file($cache_path)) && ($config['UseCache'] === True)){
            if (filemtime($cache_path) < filemtime($page)) {
                return False;
            }
            else {
                echo file_get_contents($cache_path);
                return True;
            }
        }
        return False;
    }

    function Kht_ServeCache($page) {
        $cache_path = './cache/'.$page.'.html';
        if ((file_exists($cache_path)) && ($config['UseCache'] === True)) {
            if (filemtime($cache_path)+3600 < time()) {
                echo file_get_contents($cache_path);
                return True;                
            }
            else {
                return False;
            }
        }
        return False;
    }
    
    
    function Kht_ExtractDateForSort($content) {
        global $config;
        $content_lines = explode("\n",$content);
        foreach ($content_lines as $line) {
            if (stripos($line, "Date:", 0) === 0) {  
                $date = strptime(substr($line,6), $config['DateFormat']);
                return mktime(0,0,0,$date['tm_mon'] + 1 ,
                                                     $date['tm_mday'] + 1,
                                                     $date['tm_year'] + 1900 );            
                }
        }
        return 0;
      }
                                                                                                                                          
    function Kht_ExtractDate($content) {
        global $config;
        $content_lines = explode("\n",$content);
        foreach ($content_lines as $line) {
            if (stripos($line, "Date:", 0) === 0) {            
                return strptime(substr($line,6), $config['DateFormat']);            
                }
        }
        return 0;
      }

    function Kht_ExtractTags($content) {
        $content_lines = explode("\n",$content);
        foreach ($content_lines as $line) {
            if (stripos($line, "Tags:", 0) === 0) {
                return substr($line,6);
                }
        }
        return '';
      }

    function Kht_ExtractCategory($content) {
        $content_lines = explode("\n",$content);
        foreach ($content_lines as $line) {
            if (stripos($line, "Category:", 0) === 0) {
                return substr($line,10);
                }
        }
        return '';
      }

    function Kht_ExtractTitle($content) {
        $content_lines = explode("\n",$content);
        foreach ($content_lines as $line) {
            if (stripos($line, "Title:", 0) === 0) {
                return substr($line,7);
                }
        }
        return '';
      }

    function endswith($hay, $needle) {
        return substr($hay, -strlen($needle)) === $needle;
    }

                                
    function Kht_recurseDir($dir)
        {     
            $files = array();
            
            
            if(is_dir($dir)){
                if($dh = opendir($dir)){
                    while(($file = readdir($dh)) !== false){                                            
                        if($file != '.' && $file != '..'){                                                
                            if (!endswith($dir, '/')) {
                                $dir .= '/';
                            }
                            if(!is_dir($dir . $file)) {                                                       
                                $files[$dir . $file] = $file; 
                            }else{             
                                $files[$dir . $file] = Kht_recurseDir($dir .  $file);
                            }	
                        }
                    }
                }
                closedir($dh);
            }
            
            return $files;
        }
                                
    function Kht_ServeDownloads(){
    
        // SERVE CONTENT WITH THE TEMPLATE
        global $config;
        $Kht_Content = array();
        $Kht_Title = 'Downloads';
        $Kht_CurrentPage = 'downloads';
        $Kht_CurrentPath = $config['InstallPath'].'downloads';
        $path = './datas/downloads/';
        
        //Templatization
        Kht__ob_start();
        $Kht_Content = Kht_recurseDir($path);
        include './themes/'.$config['Theme'].'/downloads.php';
        $content = ob_get_contents();

        //No Cache
        //file_put_contents($cache_path,$content);
        ob_end_flush();
      }
      
    function Kht_Pages() {
        $dir = './datas/pages/';
        $urlpath = $config['InstallPath'].'/pages/';
        $dh = opendir($dir);
        $files = array();

        while (($file = readdir($dh)) !== false) {
            $flag = false;
            if ((endswith($file, '.md')) && (strpos($file, "~") == 0)) {
                $content = file_get_contents($dir.$file);
                $cat = Kht_ExtractCategory($content);
                $path = substr($file, 0,strrpos($file,'.'));                
                if ($cat != '') {
                    $title = Kht_ExtractTitle($content);
                    if (!array_key_exists($cat, $files))
                        $files[$cat] = array($title => $urlpath.$path);
                    else
                        $files[$cat][$title] = $urlpath.$path;
                }
            }
            if ($files[$cat])
                ksort($files[$cat]);
        }
        ksort($files);
        return $files;
    }
    function Kht_ServeLastBlog($limit = 5){
    
        // SERVE CONTENT WITH THE TEMPLATE
        global $config;
        $Kht_Content = array();
        $Kht_Title = 'Blog';
        $Kht_CurrentPage = 'blog';
        $Kht_CurrentPath = $config['InstallPath'].'blog';
        include_once('libs/markdown.php');
        $path = './datas/blog/';
        $dir_contents = Kht_ReadDir($path);
        if ($limit>0)
            $dir_contents = array_slice($dir_contents,0,$limit );
        foreach ($dir_contents as $dir_content => $content_date) {        
            
            $post = array();
            $fcontent = file_get_contents($path.$dir_content);
            if ($fcontent) {
                $ccontent = '';
                $content_lines = explode("\n",$fcontent);
                foreach ($content_lines as $line) {
                    if ((stripos($line, "Date:", 0) !== 0)
                        && (stripos($line, "Title:", 0) !== 0)
                        && (stripos($line, "Tags:", 0) !== 0)
                        && (stripos($line, "Category:", 0) !== 0))
                        {$ccontent.=$line."\n";}
                    }

                $post['data'] = Markdown($ccontent);
                $post['date'] = Kht_ExtractDate($fcontent);
                $post['tags'] = Kht_ExtractTags($fcontent);
                $post['category'] = Kht_ExtractCategory($fcontent);
                $post['link'] = Kht_GetRootPath().'blog/'.htmlentities(pathinfo($dir_content, PATHINFO_FILENAME));
                $post['title'] = Kht_ExtractTitle($fcontent);
                $Kht_Content[] = $post;
            }        
        }
        
        //Templatization
        Kht__ob_start();
        if ($limit === -1)
            include './themes/'.$config['Theme'].'/archives.php';
        else
            include './themes/'.$config['Theme'].'/blog.php';
        $content = ob_get_contents();

        //Cache it
        if ($limit === -1)
            $cache_path = './cache/archives.html';
        else
            $cache_path = './cache/blog.html';
        file_put_contents($cache_path,$content);
        ob_end_flush();
      }

    function Kht_ReadDir($dir, $array = array()){
        $dh = opendir($dir);
        $files = array();
        while (($file = readdir($dh)) !== false) {
            $flag = false;
            if($file !== '.' && $file !== '..' && !in_array($file, $array) && ($file != ".DS_Store") && (strpos($file, "~") == 0)) {
                $files[$file] = Kht_ExtractDateForSort(file_get_contents($dir.$file));
            }
        }
        arsort($files);
        return $files;
    }    
    
    function Kht_Main() {
        global $config;
        $request = Kht_GetRequest();
        Kht_Redirect($request);        
        
        if ($request == 'blog') {
            $cached = Kht_ServeCache('blog');
            if ($cached === False)
                Kht_ServeLastBlog($config['NumberOfPosts']);
        }
        else if ($request == 'archives') {
            $cached = Kht_ServeCache('archives');
            if ($cached === False)
                Kht_ServeLastBlog(-1);
        }
        else if ($request == 'downloads') {
            Kht_ServeDownloads(); }
        else {

                if (file_exists('./datas/pages/'.$request.'.md'))
                    $page = './datas/pages/'.$request.'.md';
                else if (file_exists('./datas/pages/'.strtolower($request).'.md'))
                    $page = './datas/pages/'.strtolower($request).'.md';
                else if (file_exists('./datas/blog/'.$request.'.md'))
                    $page = './datas/blog/'.$request.'.md';
                else if (file_exists('./datas/blog/'.strtolower($request).'.md'))
                    $page = './datas/blog/'.strtolower($request).'.md';
                else if (file_exists('./datas/'.$request.'.md')) $page = './datas/'.$request.'.md';
                else if (file_exists('./datas/'.str_replace(' ','_',$request).'.md'))
                    $page = './datas/'.str_replace(' ', '_', $request).'.md';
                else if (file_exists('./datas/'.strtolower($request).'.md')) $page = './datas/'.strtolower($request).'.md';
                else {$page = './datas/pages/404.md'; error_log ( $request."\n", 3, './cache/404.log' ); header("HTTP/1.0 404 Not Found");}
                $cached = Kht_ServePageCache($page);
                if ($cached === False)
                    Kht_ServeMarkdownPage($page);
        }
        
    }
    
Kht_Main();
?>
