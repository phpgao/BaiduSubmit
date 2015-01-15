<?php
class BaiduSubmit_Action extends Typecho_Widget implements Widget_Interface_Do
{
	public function action()
	{
        $checksign = $_GET['checksign'];
        if (!$checksign || strlen($checksign) !== 32 ){
            exit;
        }

        $data = Typecho_Widget::widget('Widget_Options')->plugin('BaiduSubmit');

        if ($data->checksign == $checksign) {
            echo $data->checksign;
        }
	}
}
