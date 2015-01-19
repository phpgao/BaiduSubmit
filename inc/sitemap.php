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
        }else{
            $_ids = $ids;
        }

        //对应的文章信息
        $post_list = self::get_post_info_by_cid($_ids);

        require_once '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/schema.php';

        $post_schema = new BaidusubmitSchemaPost();

        $schema_arr = array();

        //查询作者数组
        self::get_author_list();
        if(count($post_list) == 0){
            throw new Typecho_Plugin_Exception('no posts!');
        }
        foreach ($post_list as $k => $v) {
            dump($v);

            $post_schema->setLastmod($v['modified']);
            $post_schema->setTags($v['tags']);
            $post_schema->setAuthor(self::get_user_screen_name($v['authorId']));
            dump($post_schema);
            die;
            //查询评论方法


            //图片方法


        }


    }

    /**
     * 根据cid查询文章信息
     * @param $ids
     * @return array
     * @throws Typecho_Db_Exception
     */
    public static function get_post_info_by_cid($ids)
    {

        $id_set = implode(',', $ids);
        $id_set = '' . $id_set . '';

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

        $tag_list = self::get_tag_by_cid($id_set);
        $comment_list = self::get_comment_by_cid($id_set);

        foreach ($content_info as $v) {
            $cid = $v['cid'] + 0;
            $content_list[$cid] = $v;
            if($tag_list){
                if(key_exists($cid,$tag_list)){
                    $content_list[$cid]['tags'] = $tag_list[$cid];
                }
            }

            if($content_info){
                if(key_exists($cid,$comment_list)){
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

        if(count($data) == 0){
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
        if(count($data) == 0){
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
}