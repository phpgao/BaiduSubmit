<?php

class BaiduSubmit_Action extends Typecho_Widget implements Widget_Interface_Do
{

    public function __construct()
    {
        define('TYPE_ALL', 1);
        define('TYPE_INC', 2);
    }

    public function checksign()
    {
        $checksign = $_GET['checksign'];
        if (!$checksign || strlen($checksign) !== 32) {
            exit;
        }

        $data = Typecho_Widget::widget('Widget_Options')->plugin('BaiduSubmit');

        if ($data->checksign == $checksign) {
            echo $data->checksign;
        }
    }


    public function action(){}

    public function sitemap()
    {
        require dirname(__FILE__).DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR.'sitemap.php';
        /*获取地图类型
        sitemap
        删除文章
        增量文章
        */
        $type = $_GET['type'];

        $type += 0;


        switch ($type) {
            case TYPE_ALL:
                $this->gen_sitemap_all();
                break;

            case TYPE_INC:
                echo "增量";
                break;
        }


    }


    protected function gen_sitemap_all(){
        $this->print_xml_header();
        //$this->print_xml_footer();
        $options = $this->widget('Widget_Options');
        $siteUrl = $options->siteUrl;
        echo '<sitemap><loc><![CDATA[', $siteUrl, 'sitemap.xml]]></loc></sitemap>', "\n";
        $this->print_xml_footer();
    }



    protected function print_xml_header(){
        header('Content-Type: text/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
    }


    protected function print_xml_footer()
    {
        echo '</sitemapindex>';
    }

}
