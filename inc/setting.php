<?php


class BaidusubmitSetting
{

    private static $log_adapter = 'file';

    public static function get_sys_config()
    {
        return Helper::options();
    }


    public static function get_plugin_config()
    {
        return Helper::options()->plugin('BaiduSubmit');
    }


    public static function get_password()
    {
        $options = Helper::options()->plugin('BaiduSubmit');
        return $options['passwd'];
    }


    public static function get_const()
    {
        $current_dir = '.' . __TYPECHO_PLUGIN_DIR__ . '/BaiduSubmit/';
        $const_file = $current_dir . 'inc/const.php';
        return require $const_file;

    }

    public static function set_log_format($type)
    {
        self::$log_adapter = $type . '_logger';
    }


    protected static function file_logger($data)
    {
        $now = date('Y-m-d H:i:s');
        $log = "时间:{$now}\n主体:{$data[0]}\n操作:{$data[1]}\n结果:{$data[2]}\n";
        if ($data[3] != null) {
            $more = var_export($data[3], 1);
            $log .= "更多:{$more}\n\n";
        } else {
            $log .= "\n";
        }
        $a = @file_put_contents('/tmp/baidusitemap.log', $log, FILE_APPEND);
    }


    public static function checkPasswd()
    {
        if (!isset($_GET['p']) || !isset($_GET['m'])) {
            BaidusubmitSitemap::headerStatus(404);
            return 'Invalid args!';
        }

        $passwd = self::get_plugin_config()->passwd;
        if ($passwd != $_GET['p']) {
            BaidusubmitSitemap::headerStatus(404);
            return 'Invalid password!';
        }
        return true;
    }

    public static function logger($s, $a, $o, $r, $m = null)
    {
        $db = Typecho_Db::get();

        if (is_array($m)) {
            $m = var_export($m, 1);
        }

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

}