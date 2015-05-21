<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 百度结构化插件 for Typecho
 *
 * @package BaiduSubmit
 * @author  老高
 * @version 0.4
 * @link http://www.phpgao.com/typecho_plugin_baidusubmit.html
 */
class BaiduSubmit_Plugin implements Typecho_Plugin_Interface
{

    public static function activate()
    {
        //挂载发布文章和页面的接口
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('BaiduSubmit_Plugin', 'send');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('BaiduSubmit_Plugin', 'send');

        //添加网站地图功能
        Helper::addRoute('baidu_sitemap', '/baidu_sitemap.xml', 'BaiduSubmit_Action', 'sitemap');
        return '插件安装成功，请进入设置填写准入密钥';
    }

    public static function deactivate()
    {
        Helper::removeRoute('baidu_sitemap');
        return '插件卸载成功';
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        //保存接口调用地址
        $element = new Typecho_Widget_Helper_Form_Element_Text('api', null, null, _t('接口调用地址'), '请登录百度站长平台获取');
        $form->addInput($element);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 准备数据
     * @param $contents 文章内容
     * @param $class 调用接口的类
     * @throws Typecho_Plugin_Exception
     */
    public static function send($contents, $class)
    {

        //如果文章属性为隐藏或滞后发布
        if ('publish' != $contents['visibility'] || $contents['created'] > time()) {
            return;
        }

        //获取系统配置
        $options = Helper::options();

        //判断是否配置好API
        if (is_null($options->plugin('BaiduSubmit')->api)) {
            return;
        }

        //获取文章类型
        $type = $contents['type'];

        //获取路由信息
        $routeExists = (NULL != Typecho_Router::get($type));

        //生成永久连接
        $path_info = $routeExists ? Typecho_Router::url($type, $contents) : '#';
        $permalink = Typecho_Common::url($path_info, $options->index);

        //调用post方法
        self::send_post($permalink, $options);
    }

    /**
     * 发送数据
     * @param $url 准备发送的url
     * @param $options 系统配置
     */
    public static function send_post($url, $options)
    {

        //获取API
        $api = $options->plugin('BaiduSubmit')->api;

        //准备数据
        if (is_array($url)) {
            $urls = $url;
        } else {
            $urls = array($url);
        }

        $result = array();
        //错误状态
        $result['error'] = 1;
        //提交URL数
        $result['num'] = count($urls);
        //返回值
        $result['return'] = '';

        try {
            //为了保证成功调用，老高先做了判断
            if (false == Typecho_Http_Client::get()) {
                throw new Typecho_Plugin_Exception(_t('对不起, 您的主机不支持 php-curl 扩展而且没有打开 allow_url_fopen 功能, 无法正常使用此功能'));
            }

            //发送请求
            $http = Typecho_Http_Client::get();
            $http->setData(implode("\n", $urls));
            $http->setHeader('Content-Type', 'text/plain');
            $json = $http->send($api);
            $return = json_decode($json, 1);

            $result = array();
            $result['msg'] = "请求成功";
            $result['return'] = $json;


            if (isset($return['success']) || array_key_exists('success', $return)) {
                $result['num'] = $return['success'];
                $result['remain'] = $return['remain'];
                $result['error'] = 0;
            }

        } catch (Typecho_Plugin_Exception $e) {
            $result['msg'] = "发送请求时遇到了问题";
        }

        $result['time'] = time();

        self::log($result);
    }

    public static function log($data, $direction = 1)
    {

        if ($direction) {
            $data['direction'] = 'request';
        } else {
            $data['direction'] = 'respond';
        }

        file_put_contents('/tmp/php.log', var_export($data, 1) . "\n", FILE_APPEND);
    }
}