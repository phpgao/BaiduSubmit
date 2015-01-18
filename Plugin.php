<?php

/**
 * 百度结构化插件
 *
 * @package BaiduSubmit
 * @author phpgao
 * @version 0.0.1
 * @link http://www.phpgao.com/
 */
class BaiduSubmit_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {

        Typecho_Plugin::factory('admin/menu.php')->navBar = array('BaiduSubmit_Plugin', 'render');
        //Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('BaiduSubmit_Plugin', 'send_xml');
        //检查checksign
        Helper::addRoute('checksign', '/checksign/', 'BaiduSubmit_Action', 'checksign');
        //网站地图
        Helper::addRoute('sitemap_by_phpgao', '/baidusitemap/', 'BaiduSubmit_Action', 'baidusitemap');
        return "安装成功！";
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeRoute('checksign');
        Helper::removeRoute('sitemap_by_phpgao');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {

        $checksign = new Typecho_Widget_Helper_Form_Element_Text('checksign', NULL, NULL, _t('checksign'));
        $form->addInput($checksign);

        $token = new Typecho_Widget_Helper_Form_Element_Text('token', NULL, NULL, _t('token'));
        $form->addInput($token);

        $passwd = new Typecho_Widget_Helper_Form_Element_Text('passwd', NULL, NULL, _t('passwd'), '如果你不清楚以上功能，请勿修改');
        $form->addInput($passwd);


        $renew = new Typecho_Widget_Helper_Form_Element_Checkbox('renew', array(1 => '更新'), 0, _t('是否更新token'),'更新后会提交一次全部sitemap');
        $form->addInput($renew);


        $max = new Typecho_Widget_Helper_Form_Element_Text('max', null, 5000, _t('一个sitemap文件中包含主题数'));
        $form->addInput($max);


    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }


    public static function send_xml()
    {
        dump(func_get_args());
        die;
    }

    public static function render()
    {
        echo '<span class="message success">' . Typecho_Widget::widget('Widget_Options')->plugin('BaiduSubmit')->max . '</span>';
    }


    public static function configHandle($config, $is_init)
    {
        $current_dir = '.'. __TYPECHO_PLUGIN_DIR__.'/BaiduSubmit/';

        if (false == Typecho_Http_Client::get()) {
            throw new Typecho_Plugin_Exception(_t('对不起, 您的主机不支持 php-curl 扩展而且没有打开 allow_url_fopen 功能, 无法正常使用此功能'));
        }
        //获取预定义常量
        //使用
        $const_file = $current_dir . 'inc/const.php';
        require_once $current_dir . 'inc/setting.php';

        //获取系统配置
        $options = Helper::options();
        $site_url = $options->siteUrl;
        $config_from_file = require_once $const_file;
        //dump($options);

        //如果更新或者初始化
        if (!is_null($config['renew']) || $is_init) {

            $config['passwd'] = substr(md5(mt_rand(10000000, 99999999).microtime()), 0, 16);
            //$curl = new Typecho_Http_Client_Adapter_Curl();
            //获取checksign
            $url = $config_from_file['zzplatform'] . '/getCheckSign?siteurl=' . urlencode($site_url) . '&sitetype=' . $config_from_file['siteTypeKey'];

            $res = file_get_contents($url);

            $data = json_decode($res);

            if (isset($data->status) && 0 != $data->status) {
                $config['checksign'] = '';
            } else {
                $config['checksign'] = $data->checksign;
            }
            //保存checksign
            helper::configPlugin('BaiduSubmit', $config);
            $url = $site_url . 'checksign/?checksign=' . $config['checksign'];
            $sigurl = $config_from_file['zzplatform'] . '/auth?checksign=' . $config['checksign'] . '&checkurl=' . urlencode($url) . '&siteurl=' . urlencode($site_url);

            //系统会自动加载curl或socket适配器
            $client = Typecho_Http_Client::get();


            $token_json = $client->httpSend($sigurl);
            file_put_contents("/tmp/sitemap.log",var_export($token_json,1)."\n",FILE_APPEND);

            $token = json_decode($token_json)->token;
            $config['token'] = $token;

            //使用token提交sitemap

            //回调URL
            $indexurl = "{$site_url}sitemap.xml";
            $sign = md5($site_url.$token);

            $submiturl = $config_from_file['zzplatform'].'/saveSitemap?siteurl='. urlencode($site_url) .'&indexurl='.urlencode($indexurl).'&tokensign='.urlencode($sign).'&type=all'.'&resource_name=RDF_Other_Blogposting';
            $res = $client->httpSend($submiturl);
            $data = json_decode($res,1);
            //保存token
            helper::configPlugin('BaiduSubmit', $config);
            //获取到正确的token后开始提交sitemap


        }

        unset($config['renew']);

        //保存设置
        helper::configPlugin('BaiduSubmit', $config);


    }
}
