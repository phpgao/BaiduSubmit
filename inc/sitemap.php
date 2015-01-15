<?php



class BaidusubmitSitemap
{
    const TYPE_ALL = 1;
    const TYPE_INC = 2;

    static function getMaxTid()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts WHERE post_status='publish'");
    }

    static function addSitemap($url, $type, $start, $end)
    {
        global $wpdb;
        return $wpdb->insert($wpdb->prefix.'baidusubmit_sitemap', array(
            'url' => $url,
            'type' => $type,
            'start' => intval($start),
            'end' => $end,
            'create_time' => time(),
        ));
    }


    static function getSiteFromUrl($url)
    {
        $url = trim($url);
        $pos = 0;
        if (0 == strncasecmp('http://', $url, 7)) {
            $pos = 7;
        }
        if (($end = strpos($url, '/', $pos)) > 0) {
            return substr($url, $pos, $end-$pos);
        }
        return substr($url, $pos);
    }

    static function dateFormat($time, $only_date=false)
    {
        //date_default_timezone_set(get_option('timezone_string', 'Asia/Shanghai'));
        if ($only_date) {
            return date('Y-m-d',$time);
        }
        return date('Y-m-d',$time).'T'.date('H:i:s',$time);
    }

    static function genPostUrl($post)
    {
        return get_permalink($post);
    }

    static function getPostIdByIdRange($start_id, $end_id)
    {
        global $wpdb;
        $start_id = intval($start_id);
        $end_id = intval($end_id);
        $wpdb->query("SELECT ID FROM $wpdb->posts WHERE ID >= $start_id AND ID <= $end_id AND post_status = 'publish' AND post_password = ''");
        $ret = array();
        foreach ($wpdb->last_result as $val) {
            $ret[] = $val->ID;
        }
        return $ret;
    }

    static function getCommentListByPostId($post_id)
    {
        global $wpdb;
        $post_id = intval($post_id);
        $wpdb->query("SELECT comment_ID,comment_author,comment_date,comment_date_gmt,comment_content FROM $wpdb->comments "
                . "WHERE comment_post_ID=$post_id AND comment_approved='1' ORDER BY comment_ID");
        $ret = array();
        foreach ($wpdb->last_result as $val) {
            $ret[] = $val;
        }
        return $ret;
    }

    /**
     *
     * @param WP_Post $post
     * @param array $multimedia
     */
    static function filterContent(WP_Post $post, &$multimedia)
    {
        $imgtype = array('jpg','gif','png','bmp','jpeg');
        $audtype = array('wav','mid','mp3','m3u','wma','vqf','ra');
        $vidtype = array('swf','fla','flv','swi','f4v','asx','mpg','mpeg','aui','wmv','rm','rv','rmvb','mov');

        $multimedia = array('image' => array(), 'audio' => array(), 'video' => array());
        $matches = array();
        $content = trim($post->post_content);

        if (false !== stripos($content, '<img')) {
            $imgregex = '/<img\s*(.*?)\s*src\s*=\s*["\'](.*?)["\'][^>]*>/isx';
            if (preg_match_all($imgregex, $content, $matches)) {
                foreach ($matches[2] as $url) {
                    if (0 != strncasecmp('http', $url, 4)) {
                        continue;
                    }
                    $multimedia['image'][$url] = $url;
                }
            }
        }

        if (false !== stripos($content, '<embed') || false !== stripos($content, '<source')) {
            $embregex = '/<(embed|source)\s*(.*?)\s*src\s*=\s*["\'](.*?)["\'][^>]*>/isx';
            if (preg_match_all($embregex, $content, $matches)) {
                foreach ($matches[3] as $url) {
                    if (0 != strncasecmp('http', $url, 4)) {
                        continue;
                    }
                    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                    if (in_array($ext, $audtype)) {
                        $multimedia['audio'][$url] = array('url' => $url);
                    } else if (in_array($ext, $vidtype)) {
                        $multimedia['video'][$url] = array('url' => $url);
                    }
                }
            }
        }

        if (false !== stripos($content, '<a')) {
            $linkregex = '/<a\s*(.*?)href\s*=\s*["\'](.*?)["\'](.*?)>(.*?)<\/a>/isx';
            if (preg_match_all($linkregex, $content, $matches)) {
                $linklist = $matches[2];
                $captionlist = $matches[4];
                foreach ($linklist as $key => $url) {
                    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                    if (in_array($ext, $imgtype)) {
                        $multimedia['image'][$url] = $url;
                    } else if (in_array($ext, $audtype)) {
                        $multimedia['audio'][$url] = array('name' => $captionlist[$key], 'url' => $url);
                    } else if (in_array($ext, $vidtype) || stripos('.swf?', $url)) {
                        $multimedia['video'][$url] = array('caption' => $captionlist[$key], 'url' => $url);
                    }
                }
            }
        }

        if (false !== stripos($content, '[audio') || false !== stripos($content, '[video')) {
            $ubbregex = '/\[(audio|video)\s*(.*?)"(http:\/\/.*?)"(.*?)\](.*?)\[(\/audio|video)\]/isx';
            if (preg_match_all($ubbregex, $content, $matches)) {
                $typelist = $matches[1];
                $linklist = $matches[3];
                $captionlist = $matches[5];
                foreach ($captionlist as $key => $val) {
                    if (empty($val)) $captionlist[$key] = $linklist[$key];
                }
                foreach ($linklist as $key => $url) {
                    if (stripos($typelist[$key], 'audio') !== false) {
                        $multimedia['audio'][$url] = array('name' => $captionlist[$key], 'url' => $url);
                    } else if (stripos($typelist[$key], 'video') !== false) {
                        $multimedia['video'][$url] = array('caption' => $captionlist[$key], 'url' => $url);
                    }
                }
            }
        }

        // reset array key
        $multimedia['image'] = array_values($multimedia['image']);
        $multimedia['audio'] = array_values($multimedia['audio']);
        $multimedia['video'] = array_values($multimedia['video']);

        return strip_tags($post->post_content);
    }

    static function getPostIdByTimeRange($start_time, $end_time, $limit)
    {
        global $wpdb;
        $start_time = date('Y-m-d H:i:s', $start_time);
        $end_time = date('Y-m-d H:i:s', $end_time);
        $limit = intval($limit);
        $sql = "SELECT ID FROM $wpdb->posts WHERE  post_modified >= '$start_time' AND  post_modified <= '$end_time' AND post_status = 'publish' LIMIT $limit";
        $wpdb->query($sql);
        $ret = array();
        foreach ($wpdb->last_result as $val) {
            $ret[] = $val->ID;
        }
        return $ret;
    }

    static function getSitemap($type, $start, $end=0)
    {
        global $wpdb;
        if (!$end) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}baidusubmit_sitemap WHERE `type`=%d AND `start`=%d", $type, $start));
        }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}baidusubmit_sitemap WHERE `type`=%d AND `start`=%d AND `end`=%d", $type, $start, $end));
    }

    static function updateSitemap($id, $data)
    {
        global $wpdb;
        $wpdb->update($wpdb->prefix.'baidusubmit_sitemap', (array)$data, array('sid' => intval($id)));
    }

    static function headerStatus($status)
    {
       // 'cgi', 'cgi-fcgi'
       header('Status: '.$status, TRUE);
       header($_SERVER['SERVER_PROTOCOL'].' '.$status);
    }

    static function stripInvalidXml($value)
    {
        $ret = '';
        if (empty($value)) {
            return $ret;
        }

        $length = strlen($value);
        for ($i=0; $i < $length; $i++) {
            $current = ord($value[$i]);
            if ($current == 0x9 || $current == 0xA || $current == 0xD ||
            ($current >= 0x20 && $current <= 0xD7FF) ||
            ($current >= 0xE000 && $current <= 0xFFFD) ||
            ($current >= 0x10000 && $current <= 0x10FFFF)) {
                $ret .= chr($current);
            } else {
                $ret .= ' ';
            }
        }
        return $ret;
    }

    static function printIndexHeader()
    {
        header('Content-Type: text/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
    }

    static function printIndexFooter()
    {
        echo '</sitemapindex>';
    }

    static function printSitemapList($sitemap_arr, $site, $suffix='')
    {
        if (!is_array($sitemap_arr)) return;
        if (isset($_GET['debug'])) {
            foreach($sitemap_arr as $sitemap) {
                echo '<sitemap>';
                echo '<loc><![CDATA[', $site, 'wp-content/plugins/baidusubmit/sitemap.php?', $sitemap->url, $suffix, ']]></loc>';
                echo '<debug>';
                echo '<itemCount><![CDATA[', $sitemap->item_count, ']]></itemCount>';
                echo '<fileSize><![CDATA[', $sitemap->file_size, ']]></fileSize>';
                echo '<lostTime><![CDATA[', $sitemap->lost_time, ']]></lostTime>';
                echo '<end><![CDATA[', $sitemap->end, ']]></end>';
                echo '</debug>';
                echo '</sitemap>', "\n";
            }
        } else {
            foreach($sitemap_arr as $sitemap) {
                echo '<sitemap><loc><![CDATA[', $site, 'wp-content/plugins/baidusubmit/sitemap.php?', $sitemap->url, $suffix, ']]></loc></sitemap>', "\n";
            }
        }
    }

    static function genSitemapPasswd()
    {
        return substr(md5(mt_rand(10000000, 99999999).microtime()), 0, 16);
    }

    static function submitIndex($action, $type, $site, $sppasswd, $sign)
    {
        $zzaction = '';
        if (0 == strncasecmp('del', $action, 3)) {
            $zzaction = '/deleteSitemap';
        } else if (0 == strncasecmp('add', $action, 3)) {
            $zzaction = '/saveSitemap';
        } else {
            return false;
        }

        $script = '';
        $stype = '';
        if (1 == $type) {
            $script = 'indexall';
            $stype = 'all';
        } else if (2 == $type) {
            $script = 'indexinc';
            $stype = 'inc';
        } else {
            return false;
        }

        $path = BAIDUSUBMIT_PLUGIN_PATH;
        $indexurl = "{$site}{$path}baidusubmit/sitemap.php?m={$script}&p={$sppasswd}";
        $config = include dirname(__FILE__) . DIRECTORY_SEPARATOR . 'const.php';
        $zzsite = $config['zzplatform'];
        $submiturl = $zzsite.$zzaction.'?siteurl='.urlencode($site).'&indexurl='.urlencode($indexurl).'&tokensign='.urlencode($sign).'&type='.$stype.'&resource_name=RDF_Other_Blogposting';

        $ret = BaidusubmitSitemap::httpSend($submiturl);

        return array(
            'body' => $ret,
            'url'  => $submiturl,
        );
    }

    static function encodeUrl($url)
    {
        $hexchars = '0123456789ABCDEF';
        $i = 0;
        $ret = '';
        while (isset($url[$i])) {
            $c = $url[$i];
            $j = ord($c);
            if ($c == ' ') {
                $ret .= '%20';
            }
            else if ($j > 127) {
                $ret .= '%' . $hexchars[$j>>4] . $hexchars[$j&15];
            }
            else {
                $ret .= $c;
            }
            $i++;
        }
        return $ret;
    }

    static function updateUrlStat($num)
    {
        $num = intval($num);
        $time = strtotime('today');
        global $wpdb;
        if ($wpdb->get_var("SELECT urlcount FROM {$wpdb->prefix}baidusubmit_urlstat WHERE ctime=$time")) {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}baidusubmit_urlstat SET urlnum=urlnum+%d, urlcount=urlcount+%d WHERE ctime=%d", $num, $num, $time));
        } else {
            $precount = $wpdb->get_var("SELECT urlcount FROM {$wpdb->prefix}baidusubmit_urlstat ORDER BY ctime DESC LIMIT 1");
            $wpdb->insert("{$wpdb->prefix}baidusubmit_urlstat", array('ctime' => $time, 'urlnum' => $num, 'urlcount' => $precount+$num));
        }

        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}baidusubmit_urlstat WHERE ctime < %d", $time-86400*7));
    }

    static function getUrlStat()
    {
        global $wpdb;
        $wpdb->query("SELECT * FROM {$wpdb->prefix}baidusubmit_urlstat ORDER BY ctime DESC");
        return $wpdb->last_result;
    }

    static function getSitemapCount($type)
    {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}baidusubmit_sitemap WHERE `type`=".intval($type));
    }

    static function getSitemapMaxEnd($type)
    {
        global $wpdb;
        return $wpdb->get_var("SELECT MAX(`end`) FROM {$wpdb->prefix}baidusubmit_sitemap WHERE `type`=".intval($type));
    }

    static function setIndexLastCrawl($offset)
    {
        $offset = intval($offset);
        if ($offset < 0) return;
        $lastcrawl = self::getIndexLastCrawl();
        if (0 == $offset || $offset != $lastcrawl['offset']) {
            $stime = time();
            return BaidusubmitOptions::setOption('lastcrawl', "$offset:$stime");
        }
    }

    static function getIndexLastCrawl()
    {
        list($offset, $stime) = explode(':', BaidusubmitOptions::getOption('lastcrawl', '0:0'));
        return array('offset' => $offset, 'stime' => $stime);
    }

    static function getSitemapList($type, $offset=-1, $limit=0)
    {
        global $wpdb;
        if ($offset >= 0 && $limit > 0) {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}baidusubmit_sitemap WHERE type=%d LIMIT %d, %d", $type, $offset, $limit));
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}baidusubmit_sitemap WHERE type=%d", $type));
    }

    static function deleteIncreaseHistory($time)
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}baidusubmit_sitemap WHERE type=%d AND end<=%d", self::TYPE_INC, $time));
    }

    static function genSchemaByPostId($post_id, &$post=null)
    {
        require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'schema.php';
        $post = get_post($post_id);
        $schema = new BaidusubmitSchemaPost();
        $schema->setTitle($post->post_title);
        $schema->setLastmod($post->post_modified);
        $schema->setCommentCount($post->comment_count);
        $schema->setPublishTime($post->post_date);

        $_user = WP_User::get_data_by('id', $post->post_author);
        $schema->setAuthor($_user->display_name);

        $_url = BaidusubmitSitemap::genPostUrl($post);
        $schema->setUrl($_url);
        $schema->setLoc($_url);

        $_term = get_the_terms($post, 'category');
        if ($_term && isset($_term[0])) {
            $schema->setTerm($_term[0]->name);
        }

        $_tags = get_the_terms($post, 'post_tag');
        if ($_tags && is_array($_tags)) {
            $_t = array();
            foreach ($_tags as $x) {
                $_t[] = $x->name;
            }
            $schema->setTags($_t);
        }

        $multimedia = array();
        $_content = BaidusubmitSitemap::filterContent($post, $multimedia);
        $schema->setContent($_content);
        if (!empty($multimedia['image'])) {
            $schema->setPictures($multimedia['image']);
        }
        if (!empty($multimedia['audio'])) {
            foreach ($multimedia['audio'] as $a) {
                $audio = new BaidusubmitSchemaAudio();
                $audio->setName((string)@$a['name']);
                $audio->setUrl((string)@$a['url']);
                $schema->addAudio($audio);
            }
            unset($a, $audio);
        }
        if (!empty($multimedia['video'])) {
            foreach ($multimedia['video'] as $v) {
                $video = new BaidusubmitSchemaVideo();
                $video->setCaption((string)@$v['caption']);
                $video->setThumbnail((string)@$v['thumbnail']);
                $video->setUrl((string)@$v['url']);
                $schema->addVideo($video);
            }
            unset($v, $video);
        }

        $commentlist = BaidusubmitSitemap::getCommentListByPostId($post->ID);
        if ($commentlist) {
            foreach ($commentlist as $c) {
                $comm = new BaidusubmitSchemaComment();
                $comm->setText($c->comment_content);
                $comm->setTime($c->comment_date);
                $comm->setCreator($c->comment_author);
                $schema->addComment($comm);

            }
            $schema->setLatestCommentTime($c->comment_date);
            unset($c, $comm);
        } else {
            $schema->setLatestCommentTime($post->post_date);
        }

        return $schema;
    }

    static function genPostXml($xml)
    {
        $c .= '<?xml version="1.0" encoding="UTF-8"?><urlset>' . "\n";
        $c .= $xml;
        $c .= '</urlset>';
        $c .= "\n";
        return $c;
    }

    static function genDeleteXml($url)
    {
        $xml = "<url><loc><![CDATA[{$url}]]></loc></url>";
        return self::genPostXml($xml);
    }

    static function sendXml($xml, $type)
    {
        require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'options.php';

        $siteurl = BaidusubmitOptions::getOption('siteurl');
        $token = BaidusubmitOptions::getOption('pingtoken');
        if (!$token) return false;

        $const = include dirname(__FILE__).DIRECTORY_SEPARATOR.'const.php';
        $pingurl = $const['zzpingurl'];

        if ($type === 1) {  //新增或更新
            $url .= $pingurl . '?site='.urlencode($siteurl).'&resource_name=RDF_Other_Blogposting&method=add';
        }
        if ($type === 2) {  //删除
            $url .= $pingurl . '?site='.urlencode($siteurl).'&resource_name=sitemap&method=del';
        }
        $sign = md5($siteurl.$xml.$token);
        $url .= '&sign='.$sign;

        return self::httpSend($url, 0, $xml);
    }

    static function httpSend($url, $limit=0, $post='', $cookie='', $timeout=15)
    {
        $return = '';
        $matches = parse_url($url);
        $scheme = $matches['scheme'];
        $host = $matches['host'];
        $path = $matches['path'] ? $matches['path'].(@$matches['query'] ? '?'.$matches['query'] : '') : '/';
        $port = !empty($matches['port']) ? $matches['port'] : 80;

        if (function_exists('curl_init') && function_exists('curl_exec')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $scheme.'://'.$host.':'.$port.$path);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            if ($post) {
                curl_setopt($ch, CURLOPT_POST, 1);
                $content = is_array($port) ? http_build_query($post) : $post;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
            }
            if ($cookie) {
                curl_setopt($ch, CURLOPT_COOKIE, $cookie);
            }
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            $data = curl_exec($ch);
            $status = curl_getinfo($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            if ($errno || $status['http_code'] != 200) {
                return;
            } else {
                return !$limit ? $data : substr($data, 0, $limit);
            }
        }

        if ($post) {
            $content = is_array($port) ? http_build_query($post) : $post;
            $out = "POST $path HTTP/1.0\r\n";
            $header = "Accept: */*\r\n";
            $header .= "Accept-Language: zh-cn\r\n";
            $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $header .= "User-Agent: ".@$_SERVER['HTTP_USER_AGENT']."\r\n";
            $header .= "Host: $host:$port\r\n";
            $header .= 'Content-Length: '.strlen($content)."\r\n";
            $header .= "Connection: Close\r\n";
            $header .= "Cache-Control: no-cache\r\n";
            $header .= "Cookie: $cookie\r\n\r\n";
            $out .= $header.$content;
        } else {
            $out = "GET $path HTTP/1.0\r\n";
            $header = "Accept: */*\r\n";
            $header .= "Accept-Language: zh-cn\r\n";
            $header .= "User-Agent: ".@$_SERVER['HTTP_USER_AGENT']."\r\n";
            $header .= "Host: $host:$port\r\n";
            $header .= "Connection: Close\r\n";
            $header .= "Cookie: $cookie\r\n\r\n";
            $out .= $header;
        }

        $fpflag = 0;
        $fp = false;
        if (function_exists('fsocketopen')) {
            $fp = fsocketopen($host, $port, $errno, $errstr, $timeout);
        }
        if (!$fp) {
            $context = stream_context_create(array(
                'http' => array(
                    'method' => $post ? 'POST' : 'GET',
                    'header' => $header,
                    'content' => $content,
                    'timeout' => $timeout,
                ),
            ));
            $fp = @fopen($scheme.'://'.$host.':'.$port.$path, 'b', false, $context);
            $fpflag = 1;
        }

        if (!$fp) {
            return '';
        } else {
            stream_set_blocking($fp, true);
            stream_set_timeout($fp, $timeout);
            @fwrite($fp, $out);
            $status = stream_get_meta_data($fp);
            if (!$status['timed_out']) {
                while (!feof($fp) && !$fpflag) {
                    if (($header = @fgets($fp)) && ($header == "\r\n" ||  $header == "\n")) {
                        break;
                    }
                }
                if ($limit) {
                    $return = stream_get_contents($fp, $limit);
                } else {
                    $return = stream_get_contents($fp);
                }
            }
            @fclose($fp);
            return $return;
        }
    }
}