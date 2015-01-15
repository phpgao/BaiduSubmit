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
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('BaiduSubmit_Plugin', 'send_xml');
        Helper::addRoute('BaiduSubmit', '/checksign/', 'BaiduSubmit_Action', 'action');
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
        Helper::removeRoute('BaiduSubmit');
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

        $checksign = new Typecho_Widget_Helper_Form_Element_Text('checksign', array('如果你看景这句话，请更新'), '', _t('checksign如果不知道这个是什么，请勿更改！'));
        $form->addInput($checksign);

        $token = new Typecho_Widget_Helper_Form_Element_Text('token', array('如果你看景这句话，请更新'), '', _t('token如果不知道这个是什么，请勿更改！'));
        $form->addInput($token);


        $renew = new Typecho_Widget_Helper_Form_Element_Checkbox('renew', array(0 => '更新'), '', _t('是否更新checksign'));
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


    public static function send_xml(){
        dump(func_get_args());
        die;
    }

    public static function render()
    {
        echo '<span class="message success">' . Typecho_Widget::widget('Widget_Options')->plugin('BaiduSubmit')->max . '</span>';
    }


    public static function configHandle($config, $is_init)
    {


        //获取预定义常量
        //使用
        $const_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'const.php';
        require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'sitemap.php';
        //获取系统配置
        $options = Typecho_Widget::widget('Widget_Options');
        $site_url = $options->siteUrl;
        $config_from_file = require_once $const_file;
        //dump($options);

        //如果更新或者初始化
        if (!is_null($config['renew']) || $is_init) {

            //$curl = new Typecho_Http_Client_Adapter_Curl();
            //获取checksign
            $url = $config_from_file['zzplatform'] . '/getCheckSign?siteurl=' . urlencode($site_url) . '&sitetype=' . $config_from_file['siteTypeKey'];

            //这里应该用function_exists判断
            $res = file_get_contents($url);

            $data = json_decode($res);

            if (isset($data->status) && 0 != $data->status) {
                $config['checksign'] = '';
            } else {
                $config['checksign'] = $data->checksign;
            }
            Widget_Plugins_Edit::configPlugin('BaiduSubmit', $config);
            $url = $site_url . 'checksign/?checksign=' . $config['checksign'];

            $sigurl = $config_from_file['zzplatform'] . '/auth?checksign=' . $config['checksign'] . '&checkurl=' . urlencode($url) . '&siteurl=' . urlencode($site_url);

            $token_json = file_get_contents($sigurl);

            $token = json_decode($token_json)->token;

            $config['token'] = $token;
            Widget_Plugins_Edit::configPlugin('BaiduSubmit', $config);


        }

        unset($config['renew']);

        //保存设置
        Widget_Plugins_Edit::configPlugin('BaiduSubmit', $config);


    }
}
