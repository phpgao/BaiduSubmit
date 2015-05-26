<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 百度结构化插件 for Typecho
 *
 * @package BaiduSubmit
 * @author  老高
 * @version 0.5.2
 * @link http://www.phpgao.com/typecho_plugin_baidusubmit.html
 */
class BaiduSubmit_Plugin implements Typecho_Plugin_Interface
{

    public static function activate()
    {
        $msg = self::install();

        //挂载发布文章和页面的接口
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('BaiduSubmit_Action', 'send');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('BaiduSubmit_Action', 'send');

        //添加网站地图功能
        Helper::addRoute('baidu_sitemap', '/baidu_sitemap.xml', 'BaiduSubmit_Action', 'sitemap');
        Helper::addPanel(1, 'BaiduSubmit/Logs.php', '百度结构化日志', '百度结构化日志', 'administrator');
        Helper::addRoute('baidu_sitemap_advanced', __TYPECHO_ADMIN_DIR__ . 'baidu_sitemap/advanced', 'BaiduSubmit_Action', 'send_all');
        return $msg . '请进入设置填写接口调用地址';
    }

    public static function render(){
        $options = Helper::options();
        echo '<a href="';
        $options->adminUrl('baidu_sitemap/advanced');
        echo '">百度结构化插件</a>';
    }

    public static function deactivate()
    {
        $msg = self::uninstall();
        return $msg . '插件卸载成功';
    }

    public static function index(){
        echo 1;
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {

        $element = new Typecho_Widget_Helper_Form_Element_Text('api', null, null, _t('接口调用地址'), '请登录百度站长平台获取');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Text('group', null, 15, _t('分组URL数'), '每天最多只能发送50条，请酌情设置');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Radio('delete', array(0 => '不删除', 1 => '删除'), 0, _t('卸载是否删除数据表'));
        $form->addInput($element);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }



    public static function install()
    {

        try {
            return self::addTable();
        } catch (Typecho_Db_Exception $e) {
            if ('42S01' == $e->getCode()) {
                $msg = '数据库已存在!';
                return $msg;
            }
        }
    }

    public static function uninstall()
    {
        //删除路由
        Helper::removeRoute('baidu_sitemap');
        Helper::removeRoute('baidu_sitemap_advanced');
        Helper::removePanel(1, 'BaiduSubmit/Logs.php');
        //获取配置，是否删除数据表
        if (Helper::options()->plugin('BaiduSubmit')->delete == 1) {
            return self::remove_table();
        }
    }

    public static function addTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $sql = "CREATE TABLE `{$prefix}baidusubmit` (
                    `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
                    `subject` varchar(255) COMMENT '主体',
                    `action` varchar(255) COMMENT '动作',
                    `object` varchar(255) COMMENT '对象',
                    `result` varchar(255) COMMENT '结果',
                    `more` text COMMENT '更多信息',
                    `time` bigint COMMENT '时间',
                    PRIMARY KEY (`id`)
                )DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";
        $db->query($sql);
        return "数据库安装成功！";
    }

    public static function remove_table()
    {
        //删除表
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        try {
            $db->query("DROP TABLE `" . $prefix . "baidusubmit`", Typecho_Db::WRITE);
        } catch (Typecho_Exception $e) {
            return "删除日志表失败！";
        }
        return "删除日志表成功！";
    }


}