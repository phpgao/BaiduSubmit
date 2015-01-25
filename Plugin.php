<?php

/**
 * 百度结构化插件
 *
 * @package BaiduSubmit(Beta)
 * @author  老高@PHPer
 * @version 0.2
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
        $current_dir = '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/';
        require $current_dir . 'inc/sitemap.php';
        $msg = self::install();

        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('BaiduSubmit_Action', 'send_add_xml');
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->delete = array('BaiduSubmit_Action', 'send_del_xml');
        //检查checksign
        Helper::addRoute('checksign', '/checksign/', 'BaiduSubmit_Action', 'checksign');
        //网站地图
        Helper::addRoute('sitemap_by_phpgao', '/baidusubmit/sitemap', 'BaiduSubmit_Action', 'baidusitemap');
        return "{$msg} 安装成功！";
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
        self::uninstall();
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

        $checksign = new Typecho_Widget_Helper_Form_Element_Hidden('checksign', NULL, NULL, _t('checksign'));
        $form->addInput($checksign);

        $token = new Typecho_Widget_Helper_Form_Element_Hidden('token', NULL, NULL, _t('token'));
        $form->addInput($token);

        $passwd = new Typecho_Widget_Helper_Form_Element_Hidden('passwd', NULL, NULL, _t('passwd'));
        $form->addInput($passwd);

        $max = new Typecho_Widget_Helper_Form_Element_Text('max', null, 5000, _t('一个sitemap文件中包含主题数'),'0表示所有文章(慎用)');
        $form->addInput($max);

        $renew = new Typecho_Widget_Helper_Form_Element_Radio('delete', array(0 => '不删除', 1 => '删除'), 0, _t('卸载是否删除数据表'));
        $form->addInput($renew);


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

    public static function render()
    {
        echo '<span class="message success">' . Typecho_Widget::widget('Widget_Options')->plugin('BaiduSubmit')->max . '</span>';
    }


    public static function configHandle($config, $is_init)
    {
        $current_dir = '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/';

        if (false == Typecho_Http_Client::get()) {
            throw new Typecho_Plugin_Exception(_t('对不起, 您的主机不支持 php-curl 扩展而且没有打开 allow_url_fopen 功能, 无法正常使用此功能'));
        }
        //获取预定义常量
        //载入必要文件
        $const_file = $current_dir . 'inc/const.php';
        require_once $current_dir . 'inc/setting.php';
        require_once $current_dir . 'inc/sitemap.php';

        //获取系统配置
        $options = Helper::options();
        $siteurl = $options->siteUrl;
        $config_from_file = require $const_file;
        //dump($options);

        //如果更新或者初始化
        if ($is_init) {

            $config['passwd'] = substr(md5(mt_rand(10000000, 99999999) . microtime()), 0, 16);
            //$curl = new Typecho_Http_Client_Adapter_Curl();
            //去站长平台获取随机串
            $result = BaidusubmitSitemap::httpSend($config_from_file['zzplatform'] . '/getCheckSign?siteurl=' . urlencode($siteurl) . '&sitetype=' . $config_from_file['siteTypeKey']);
            $data = json_decode($result);

            if (isset($data->status) && '0' != $data->status) {
                BaidusubmitSetting::logger('我', '获取checksign', '百度站长', 'failed', array($config_from_file, $result, $data));
            }
            //保存checksign和密码
            $config['checksign'] = $data->checksign;

            helper::configPlugin('BaiduSubmit', $config);

            //站长平台回调的URL
            $url = $siteurl . 'checksign/?checksign=' . $config['checksign'];;
            $sigurl = $config_from_file['zzplatform'] . '/auth?checksign=' . $data->checksign . '&checkurl=' . urlencode($url) . '&siteurl=' . urlencode($siteurl);

            $authData = BaidusubmitSitemap::httpSend($sigurl); //去站长平台进行验证
            $output = json_decode($authData);

            #if (isset($output->status) && '0' == $output->status) {
            if (1) {
                $token = $output->token;
                $config['token'] = $token;

                //生成验证字符串
                $sign = md5($siteurl . $token);
                $allreturnjson = BaidusubmitSitemap::submitIndex('add', 1, $siteurl, $config['passwd'], $sign);
                $allresult = json_decode($allreturnjson['body']);
                if (!isset($allresult->status) || '0' != $allresult->status) {
                    BaidusubmitSetting::logger('我', '获取提交结果', '百度服务器', 'failed', $allreturnjson['body']);
                }

                //保存token
                helper::configPlugin('BaiduSubmit', $config);
                //获取到正确的token后开始提交sitemap
            } elseif (in_array($output->status, array(1, 2001, 2002, 2003, 2008))) {
                $e = array(
                    1 => 'Parameter error',
                    2 => 'No site information',
                    100 => 'System error',
                    2001 => 'Checksign does not exists',
                    2002 => 'Sign detection failed',
                    2003 => 'Checkurl request failed',
                    2008 => 'Checkurl does not belong to siteurl',
                );
                BaidusubmitSetting::logger('百度服务器', '返回token', '我', 'wrong', $e[$output->status]);
            } else {
                BaidusubmitSetting::logger('我', '获取token', 'failed', array($sigurl, $url, $authData));
            }

        }

        //保存设置
        helper::configPlugin('BaiduSubmit', $config);


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
                    `more` varchar(255) COMMENT '更多信息',
                    `time` bigint COMMENT '时间',
                    PRIMARY KEY (`id`)
                )DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT='baidusitemap'";

        $db->query($sql);

        return "数据库安装成功";
    }


    public static function install()
    {
        $msg = '';
        try {
            $msg = self::addTable();
        } catch (Typecho_Db_Exception $e) {

            if ('42S01' == $e->getCode()) {
                $msg = '数据库已存在!';
            }
        }
        $db = Typecho_Db::get();
        self::logger('我', '安装', '插件', '成功', $msg);
    }

    public static function logger($s, $a, $o, $r, $m = null)
    {
        $db = Typecho_Db::get();
        $db->query($db->insert('table.baidusubmit')
            ->rows(array(
                'subject' => $s,
                'action' => $a,
                'object' => $o,
                'result' => $r,
                'more' => $m,
                'time' => time()
            )));
    }

    public static function uninstall()
    {
        //删除路由
        Helper::removeRoute('checksign');
        Helper::removeRoute('sitemap_by_phpgao');
        # 删除与密码关联的网站地图
        $current_dir = '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/';
        require $current_dir . 'inc/sitemap.php';
        require $current_dir . 'inc/setting.php';
        $plugin_config = BaidusubmitSetting::get_plugin_config();
        $token = $plugin_config->token;
        $passwd = $plugin_config->passwd;
        $siteurl = BaidusubmitSetting::get_sys_config()->siteUrl;
        $sign = md5($siteurl . $token);
        BaidusubmitSitemap::submitIndex('del', BaidusubmitSitemap::TYPE_ALL, $siteurl, $passwd, $sign);
        //BaidusubmitSitemap::submitIndex('del', BaidusubmitSitemap::TYPE_INC, $siteurl, $passwd, $sign);
        //获取配置，是否删除数据表
        if ($plugin_config->delete == 1) {
            self::remove_table();
        } else {
            self::logger('我', '卸载', '插件', '成功');
        }

    }

    public static function remove_table()
    {
        //删除表
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        try{
            $db->query("DROP TABLE `" . $prefix . "baidusubmit`", Typecho_Db::WRITE);
        }catch (Typecho_Exception $e){

        }
    }
}
