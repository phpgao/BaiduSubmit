<?php

class BaiduSubmit_Action extends Typecho_Widget implements Widget_Interface_Do
{

    public function __construct()
    {
        $this->_dir = '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/';
        require $this->_dir . 'sitemap.php';
        require $this->_dir . 'setting.php';

        define('TYPE_ALL', 1);
        define('TYPE_INC', 2);
    }

    public function checksign()
    {
        $checksign = $_GET['checksign'];
        if (!$checksign || strlen($checksign) !== 32) {
            exit;
        }

        $data = Helper::options()->plugin('BaiduSubmit');

        if ($data->checksign == $checksign) {
            echo $data->checksign;
        }
    }


    public function action()
    {
    }

    public function baidusitemap()
    {

        $msg = BaidusubmitSetting::checkPasswd();

        if (true !== $msg) {
            BaidusubmitSetting::logger($_SERVER['HTTP_USER_AGENT'], '请求', 'sitemap', 'failed', $msg);
            die;
        }

        $method = strval($_GET['m']);
        if(in_array($method,array('indexall','indexinc'))){
        #if (method_exists($this, $method)) {
            call_user_func_array(array($this, $method),$_REQUEST);
        }else{
            BaidusubmitSetting::logger($_SERVER['HTTP_USER_AGENT'], '请求', 'sitemap', 'failed', "Wrong param {$method}");
        }
    }


    protected function indexall()
    {
        # 默认取设置的个数
        $max_num = BaidusubmitSetting::get_plugin_config()->max;

        if($max_num == 0){
            $ids = BaidusubmitSitemap::get_post_id_by_range(1);
        }else{
            $ids = BaidusubmitSitemap::get_post_id_by_max($max_num);
        }


        $content = BaidusubmitSitemap::gen_elenment_by_cid($ids);

        $this->print_xml_header();
        foreach ($content as $v) {
            echo $v->toXml();
        }

        $this->print_xml_footer();
    }


    protected function print_xml_header()
    {
        header('Content-Type: text/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset>';
    }


    protected function print_xml_footer()
    {
        echo '</urlset>';
    }

    public function send_add_xml($a,$b){

        require '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/' . 'sitemap.php';
        require '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/' . 'setting.php';
        $post = $b->next();
        if($post['status'] != 'publish' || $post['created']>time()) {
            BaidusubmitSetting::logger('我', '提交删除', '百度服务器', 'failed', '条件错误');
            return false;
        }
        $id = $post['cid'];
        if($id){
            $schemas = BaidusubmitSitemap::gen_elenment_by_cid($id);
            $base_xml = $schemas[0]->toXml();
            $content = BaidusubmitSitemap::genPostXml($base_xml);
            $r = BaidusubmitSitemap::sendXml($content, 1);
            if(false !== $r){
                BaidusubmitSetting::logger('我','提交更新','百度服务器','success',"文章ID->{$id}" . $r);
            }else{
                BaidusubmitSetting::logger('我','提交更新','百度服务器','failed',"文章ID->{$id}" . $r);
            }
        }
    }

    public function send_del_xml($id,$b){

        require_once '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/' . 'sitemap.php';
        require_once '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/inc/' . 'setting.php';

        if($id){
            debug_print_backtrace();
            $schemas = BaidusubmitSitemap::gen_elenment_by_cid($id);
            $base_xml = $schemas[0]->toXml();
            $content = BaidusubmitSitemap::genDeleteXml($base_xml);
            $r = BaidusubmitSitemap::sendXml($content, 2);
            if(false !== $r){
                BaidusubmitSetting::logger('我','提交删除','百度服务器','success',"文章ID->{$id}" . $r);
            }else{
                BaidusubmitSetting::logger('我','提交删除','百度服务器','failed',"文章ID->{$id}" . $r);
            }
        }
    }

}
