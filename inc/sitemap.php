<?php


class BaidusubmitSitemap
{
    const TYPE_ALL = 1;
    const TYPE_INC = 2;

    public static $_dir;
    public static $author_list = array();


    public function __construct()
    {
        self::$_dir = '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/';

    }

    /**
     * 根据范围返回文章ID
     * @param $start
     * @param null $end
     * @return array
     * @throws Typecho_Db_Exception
     * @throws Typecho_Exception
     */
    public static function get_post_id_by_range($start, $end = NULL)
    {

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $now = time();
        if ($end == NULL) {
            $articles = $db->fetchAll($db->select('MAX(`cid`) AS max')->from('table.contents')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', $now)
                ->where('table.contents.type = ?', 'post'));
            $end = $articles[0]['max'];
        }
        if (is_null($end)) {
            throw new Typecho_Exception(_t('max id error!'));
        }

        if ($start > $end) {
            list($start, $end) = array($end, $start);
        }

        $ids = array();

        $articles = $db->fetchAll($db->select('cid')->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $now)
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.cid >= ?', $start)
            ->where('table.contents.cid <= ?', $end)
            ->order('table.contents.cid', Typecho_Db::SORT_DESC));

        foreach ($articles as $v) {
            $ids[] = $v['cid'];
        }

        return $ids;
    }


    public static function gen_elenment_by_cid($ids)
    {

        if (!is_array($ids)) {
            $_ids[] = $ids;
        } else {
            $_ids = $ids;
        }

        //对应的文章信息
        $post_list = self::get_post_info_by_cid($ids);

        require_once '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/schema.php';
        $options = Helper::options();


        $schema_arr = array();

        //查询作者数组
        self::get_author_list();
        if (count($post_list) == 0) {
            throw new Typecho_Plugin_Exception('no posts!');
        }
        foreach ($post_list as $k => $v) {
            $post_schema = new BaidusubmitSchemaPost();

            $post_schema->setTitle($v['title']);

            $post_schema->setPublishTime($v['created']);

            $post_schema->setLastmod($v['modified']);

            $post_schema->setUrl($v['permalink']);

            if (isset($v['tags'])) {
                $post_schema->setTags($v['tags']);
            }
            # 设置作者
            $post_schema->setAuthor(self::get_user_screen_name($v['authorId']));
            # 设置评论数
            $post_schema->setCommentCount($v['commentsNum']);
            # 添加评论
            if ($v['commentsNum'] > 0) {
                foreach ($v['comments'] as $comment) {
                    $post_schema->addComment($comment);
                }
                # 如果获取不到时间以上次修改时间为准
                $last_comment_time = $comment->getTime() ? $comment->getTime() : $v['modified'];

                $post_schema->setLatestCommentTime($last_comment_time);
            }
            # 设置分类(唯一)
            $post_schema->setTerm($v['category']);


            $multimedia = array();
            $_content = BaidusubmitSitemap::filterContent(Markdown::convert($v['text']), $multimedia);

            $post_schema->setContent($_content);

            if (!empty($multimedia['image'])) {
                $post_schema->setPictures($multimedia['image']);
            }
            if (!empty($multimedia['audio'])) {
                foreach ($multimedia['audio'] as $a) {
                    $audio = new BaidusubmitSchemaAudio();
                    $audio->setName((string)@$a['name']);
                    $audio->setUrl((string)@$a['url']);
                    $post_schema->addAudio($audio);
                }
                unset($a, $audio);
            }
            if (!empty($multimedia['video'])) {
                foreach ($multimedia['video'] as $v) {
                    $video = new BaidusubmitSchemaVideo();
                    $video->setCaption((string)@$v['caption']);
                    $video->setThumbnail((string)@$v['thumbnail']);
                    $video->setUrl((string)@$v['url']);
                    $post_schema->addVideo($video);
                }
                unset($v, $video);
            }


            $schema_arr[] = $post_schema;
            //查询评论方法


            //图片方法


        }
        return $schema_arr;
    }

    /**
     * 根据cid查询文章信息
     * @param $ids
     * @return array
     * @throws Typecho_Db_Exception
     */
    public static function get_post_info_by_cid($ids)
    {
        if (is_array($ids)) {
            $id_set = implode(',', $ids);
            $id_set = '' . $id_set . '';
        } else {
            $id_set = $ids;
        }


        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $now = time();

        $sql = "SELECT * FROM {$prefix}contents
                        WHERE `status` = 'publish'
                        AND `created` < {$now}
                        AND `type` = 'post'
                        AND `cid` IN ({$id_set})
                        ORDER BY `cid` DESC";

        $content_info = $db->fetchAll($sql);

        $category_list = self::get_category_by_cid($id_set);

        $tag_list = self::get_tag_by_cid($id_set);
        $comment_list = self::get_comment_by_cid($id_set);

        # 获取永久连接
        # 代码见 var/Widget/Abstract/Contents.php
        $options = Helper::options();
        $type = 'post';
        $routeExists = (NULL != Typecho_Router::get($type));


        foreach ($content_info as $v) {
            $cid = $v['cid'] + 0;
            $content_list[$cid] = $v;
            $content_list[$cid]['category'] = $category_list[$cid];

            # 永久连接
            $content_list[$cid]['pathinfo'] = $routeExists ? Typecho_Router::url($type, $v) : '#';
            $content_list[$cid]['permalink'] = Typecho_Common::url($content_list[$cid]['pathinfo'], $options->index);

            # tag列表
            if ($tag_list) {
                if (@array_key_exists($cid, $tag_list)) {
                    $content_list[$cid]['tags'] = $tag_list[$cid];
                }
            }

            # 评论列表 数组格式
            if ($comment_list) {
                if (@array_key_exists($cid, $comment_list)) {
                    $content_list[$cid]['comments'] = $comment_list[$cid];
                }
            }

        }
        return $content_list;
    }

    public static function dateFormat($time, $only_date = false)
    {
        //date_default_timezone_set(get_option('timezone_string', 'Asia/Shanghai'));
        if ($only_date) {
            return date('Y-m-d', $time);
        }
        return date('Y-m-d', $time) . 'T' . date('H:i:s', $time);
    }


    public static function get_author_list($id = false)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $data = $db->fetchAll($db->select('uid,name,mail,url,screenName,created')->from('table.users'));

        foreach ($data as $k => $v) {
            self::$author_list[$v['uid']] = $v;
        }

        if ($id === false) {
            return self::$author_list;
        }

        return self::$author_list[$id];
    }

    public static function get_user_screen_name($id)
    {
        $id = $id + 0;
        if (count(self::$author_list) == 0) {
            self::$author_list = self::get_author_list();
        }

        return self::$author_list[$id]['screenName'];
    }

    public static function get_tag_by_cid($cid)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $sql = "SELECT cid,name
        FROM {$prefix}relationships as rs
        LEFT JOIN {$prefix}metas as m ON m.`mid` = rs.`mid`
        WHERE rs.`cid` in ({$cid})
        AND m.type = 'tag'";

        $data = $db->fetchAll($sql);

        if (count($data) == 0) {
            return false;
        }

        $tag_list = array();

        foreach ($data as $v) {
            $tag_list[$v['cid']][] = $v['name'];
        }

        return $tag_list;
    }

    public static function get_comment_by_cid($cid)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $sql = "SELECT cid,created,author,text
        FROM {$prefix}comments as c
        WHERE c.`cid` in ({$cid})
        AND c.status = 'approved'";
        $data = $db->fetchAll($sql);

        $comment_list = array();
        require_once '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/schema.php';
        if (count($data) == 0) {
            return false;
        }
        foreach ($data as $v) {
            $comment_obj = new BaidusubmitSchemaComment();
            $comment_obj->setCreator($v['author']);
            $comment_obj->setTime($v['created']);
            $comment_obj->setText($v['text']);
            $comment_list[$v['cid']][] = $comment_obj;
        }

        return $comment_list;
    }

    public static function get_category_by_cid($cid)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $sql = "SELECT cid,name
        FROM {$prefix}relationships as rs
        LEFT JOIN {$prefix}metas as m ON m.`mid` = rs.`mid`
        WHERE rs.`cid` in ({$cid})
        AND m.type = 'category' GROUP BY cid";
        $data = $db->fetchAll($sql);

        $category_list = array();

        foreach ($data as $v) {
            foreach ($data as $v) {
                $category_list[$v['cid']] = $v['name'];
            }
        }

        return $category_list;
    }


    static function stripInvalidXml($value)
    {
        $ret = '';
        if (empty($value)) {
            return $ret;
        }

        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $current = ord($value[$i]);
            if ($current == 0x9 || $current == 0xA || $current == 0xD ||
                ($current >= 0x20 && $current <= 0xD7FF) ||
                ($current >= 0xE000 && $current <= 0xFFFD) ||
                ($current >= 0x10000 && $current <= 0x10FFFF)
            ) {
                $ret .= chr($current);
            } else {
                $ret .= ' ';
            }
        }
        return $ret;
    }


    static function filterContent($post, &$multimedia)
    {
        $imgtype = array('jpg', 'gif', 'png', 'bmp', 'jpeg');
        $audtype = array('wav', 'mid', 'mp3', 'm3u', 'wma', 'vqf', 'ra');
        $vidtype = array('swf', 'fla', 'flv', 'swi', 'f4v', 'asx', 'mpg', 'mpeg', 'aui', 'wmv', 'rm', 'rv', 'rmvb', 'mov');

        $multimedia = array('image' => array(), 'audio' => array(), 'video' => array());
        $matches = array();
        $content = trim($post);

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

        return strip_tags($post);
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
            } else if ($j > 127) {
                $ret .= '%' . $hexchars[$j >> 4] . $hexchars[$j & 15];
            } else {
                $ret .= $c;
            }
            $i++;
        }
        return $ret;
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

        $indexurl = "{$site}baidusubmit/sitemap?m={$script}&p={$sppasswd}";
        $config = BaidusubmitSetting::get_const();
        $zzsite = $config['zzplatform'];
        $submiturl = $zzsite . $zzaction . '?siteurl=' . urlencode($site) . '&indexurl=' . urlencode($indexurl) . '&tokensign=' . urlencode($sign) . '&type=' . $stype . '&resource_name=RDF_Other_Blogposting';
        $ret = BaidusubmitSitemap::httpSend($submiturl);
        #BaidusubmitSetting::logger('我',"{$action} sitemap",'服务器','log',$indexurl);
        return array(
            'body' => $ret,
            'url' => $submiturl,
        );
    }


    static function sendXml($xml, $type)
    {
        $_dir = '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/';
        require_once $_dir . 'setting.php';
        require_once $_dir . 'const.php';
        $siteurl = BaidusubmitSetting::get_sys_config()->siteUrl;
        $token = BaidusubmitSetting::get_plugin_config()->token;
        if (!$token) return false;

        $const = require $_dir . 'const.php';;
        $pingurl = $const['zzpingurl'];

        if ($type === 1) {  //新增或更新
            $url .= $pingurl . '?site=' . urlencode($siteurl) . '&resource_name=RDF_Other_Blogposting&method=add';
        }
        if ($type === 2) {  //删除
            $url .= $pingurl . '?site=' . urlencode($siteurl) . '&resource_name=sitemap&method=del';
        }
        $sign = md5($siteurl . $xml . $token);
        $url .= '&sign=' . $sign;
        return self::httpSend($url, 0, $xml);
    }

    static function httpSend($url, $limit = 0, $post = '', $cookie = '', $timeout = 15)
    {
        $return = '';
        $matches = parse_url($url);
        $scheme = $matches['scheme'];
        $host = $matches['host'];
        $path = $matches['path'] ? $matches['path'] . (@$matches['query'] ? '?' . $matches['query'] : '') : '/';
        $port = !empty($matches['port']) ? $matches['port'] : 80;

        if (function_exists('curl_init') && function_exists('curl_exec')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $scheme . '://' . $host . ':' . $port . $path);
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
            $header .= "User-Agent: " . @$_SERVER['HTTP_USER_AGENT'] . "\r\n";
            $header .= "Host: $host:$port\r\n";
            $header .= 'Content-Length: ' . strlen($content) . "\r\n";
            $header .= "Connection: Close\r\n";
            $header .= "Cache-Control: no-cache\r\n";
            $header .= "Cookie: $cookie\r\n\r\n";
            $out .= $header . $content;
        } else {
            $out = "GET $path HTTP/1.0\r\n";
            $header = "Accept: */*\r\n";
            $header .= "Accept-Language: zh-cn\r\n";
            $header .= "User-Agent: " . @$_SERVER['HTTP_USER_AGENT'] . "\r\n";
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
            $fp = @fopen($scheme . '://' . $host . ':' . $port . $path, 'b', false, $context);
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
                    if (($header = @fgets($fp)) && ($header == "\r\n" || $header == "\n")) {
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


    static function headerStatus($status)
    {
        // 'cgi', 'cgi-fcgi'
        header('Status: ' . $status, TRUE);
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status);
    }

    /**
     * return last top ids
     * @param $max
     * @throws Typecho_Db_Exception
     * @throws Typecho_Exception
     */
    public static function get_post_id_by_max($max)
    {
        if ($max < 1) {
            throw new Typecho_Exception('max取值错误');
        }
        $db = Typecho_Db::get();

        $ids = $db->fetchAll($db->select('cid')->from('table.contents')->order('cid', Typecho_Db::SORT_DESC)->limit($max));
        if (false != $ids) {
            $last_id = array_pop($ids);
        }
        return self::get_post_id_by_range($last_id['cid']);
    }

    static function genPostXml($xml)
    {
        $c = '';
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
}