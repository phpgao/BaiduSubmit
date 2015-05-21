<?php

class BaiduSubmit_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action(){}

    public static function sitemap(){
        $db = Typecho_Db::get();
        $options = Helper::options();
        $plugin_config = Helper::options()->plugin('BaiduSubmit');


        $pages = $db->fetchAll($db->select()->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $options->gmtTime)
            ->where('table.contents.type = ?', 'page')
            ->order('table.contents.created', Typecho_Db::SORT_DESC));

        $articles = $db->fetchAll($db->select()->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $options->gmtTime)
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.created', Typecho_Db::SORT_DESC));

        //changefreq -> always、hourly、daily、weekly、monthly、yearly、never
        //priority -> 0.0优先级最低、1.0最高
        header("Content-Type: application/xml");
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset>\n";
        foreach ($pages AS $page) {
            $type = $page['type'];
            $routeExists = (NULL != Typecho_Router::get($type));
            $page['pathinfo'] = $routeExists ? Typecho_Router::url($type, $page) : '#';
            $page['permalink'] = Typecho_Common::url($page['pathinfo'], $options->index);

            echo "\t<url>\n";
            echo "\t\t<loc>" . $page['permalink'] . "</loc>\n";
            echo "\t\t<lastmod>" . date('Y-m-d', $page['modified']) . "</lastmod>\n";
            echo "\t\t<changefreq>daily</changefreq>\n";
            echo "\t\t<priority>0.8</priority>\n";
            echo "\t</url>\n";
        }
        foreach ($articles AS $article) {
            $type = $article['type'];
            $routeExists = (NULL != Typecho_Router::get($type));
            $article['pathinfo'] = $routeExists ? Typecho_Router::url($type, $article) : '#';
            $article['permalink'] = Typecho_Common::url($article['pathinfo'], $options->index);

            echo "\t<url>\n";
            echo "\t\t<loc>" . $article['permalink'] . "</loc>\n";
            echo "\t\t<lastmod>" . date('Y-m-d', $article['modified']) . "</lastmod>\n";
            echo "\t\t<changefreq>daily</changefreq>\n";
            echo "\t\t<priority>0.8</priority>\n";
            echo "\t</url>\n";
        }
        echo "</urlset>";
    }

}
