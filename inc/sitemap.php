<?php



class BaidusubmitSitemap
{
    const TYPE_ALL = 1;
    const TYPE_INC = 2;

    public static $_dir;

    public function __construct()
    {
        self::$_dir = '.'. __TYPECHO_PLUGIN_DIR__.'/BaiduSubmit/inc/';

    }
    /**
     * 根据范围返回文章ID
     * @param $start
     * @param null $end
     * @return array
     * @throws Typecho_Db_Exception
     * @throws Typecho_Exception
     */
    public static function get_post_id_by_range($start, $end=NULL){
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $now = time();
        if($end == NULL){
            $articles = $db->fetchAll($db->select('MAX(`cid`) AS max')->from('table.contents')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', $now)
                ->where('table.contents.type = ?', 'post'));
            $end = $articles[0]['max'];
        }
        if(is_null($end)){
            throw new Typecho_Exception(_t('max id error!'));
        }

        if($start > $end){
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

        foreach($articles as $v){
            $ids[] = $v['cid'];
        }

        return $ids;
    }


    public static function gen_elenment_by_cid($ids){

        if(!is_array($ids)){
            $_ids[] = $ids;
        }

        $_ids = $ids;

        //获取到结果集准备与ids匹配信息
        $info_arr = self::get_post_info_by_cid($_ids);

        require_once '.'. __TYPECHO_PLUGIN_DIR__.'/BaiduSubmit/inc/schema.php';

        $post_schema = new BaidusubmitSchemaPost();

        $schema_arr = array();

        //查询作者数组



        foreach($ids as $k => $v){
            $post_schema->setAuthor(123);

            //查询评论方法


            //图片方法


        }




    }


    public static function get_post_info_by_cid($ids){
        $id_set = implode(',', $ids);
        $id_set = ''.$id_set.'';

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

        return $content_info;
    }

    static function dateFormat($time, $only_date=false)
    {
        //date_default_timezone_set(get_option('timezone_string', 'Asia/Shanghai'));
        if ($only_date) {
            return date('Y-m-d',$time);
        }
        return date('Y-m-d',$time).'T'.date('H:i:s',$time);
    }
}