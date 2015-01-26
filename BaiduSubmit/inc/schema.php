<?php

class BaidusubmitSchemaPost
{
    private $_lastmod;
    private $_loc;
    private $_title;
    private $_url;
    private $_publishTime;
    private $_content;
    private $_author;
    private $_keywords;
    private $_term;
    private $_pictures;
    private $_commentCount;
    private $_latestCommentTime;

    private $_commentList = array();
    private $_videoList = array();
    private $_audioList = array();


    public function setLastmod($time)
    {
        if (!preg_match('#^\d+$#', $time)) {
            $time = strtotime($time);
        }
        $this->_lastmod = BaidusubmitSitemap::dateFormat($time);
    }

    public function setLoc($url)
    {
        $this->_loc = $url;
    }

    public function setTitle($title)
    {
        $this->_title = $title;
    }

    public function setUrl($url)
    {
        $this->_url = $url;
    }

    public function setPublishTime($time)
    {
        if (!preg_match('#^\d+$#', $time)) {
            $time = strtotime($time);
        }
        $this->_publishTime = BaidusubmitSitemap::dateFormat($time);
    }

    public function setContent($content)
    {
        $this->_content = BaidusubmitSitemap::stripInvalidXml($content);
    }

    public function setTags(array $tags)
    {
        $this->_keywords = $tags;
    }

    public function setAuthor($author)
    {
        $this->_author = trim($author);
    }

    /**
     * 只能属于某一个分类
     */
    public function setTerm($term)
    {
        $this->_term = $term;
    }

    public function setPictures(array $pics)
    {
        $this->_pictures = $pics;
    }

    public function setCommentCount($count)
    {
        $this->_commentCount = intval($count);
    }

    public function setLatestCommentTime($time)
    {
        if (!preg_match('#^\d+$#', $time)) {
            $time = strtotime($time);
        }
        $this->_latestCommentTime = BaidusubmitSitemap::dateFormat($time);
    }

    public function addComment(BaidusubmitSchemaComment $comment)
    {
        $this->_commentList[] = $comment;
    }

    public function addVideo(BaidusubmitSchemaVideo $video)
    {
        $this->_videoList[] = $video;
    }

    public function addAudio(BaidusubmitSchemaAudio $audio)
    {
        $this->_audioList[] = $audio;
    }

    public function toXml()
    {
        $keywords = '';
        if (!$this->_keywords || !is_array($this->_keywords)) {
            $this->_keywords = array('NONE');
        }
        foreach ($this->_keywords as $x) {
            $keywords .= "<keywords><![CDATA[{$x}]]></keywords>\n";
        }

        $pics = '';
        if ($this->_pictures && is_array($this->_pictures)) {
            foreach ($this->_pictures as $x) {
                if (strncasecmp('http://', $x, 7) !== 0) continue;
                $x = BaidusubmitSitemap::encodeUrl($x);
                $pics .= "<articlePicture><![CDATA[$x]]></articlePicture>\n";
            }
        }

        $comment = '';
        foreach ($this->_commentList as $x) {
            $comment .= $x->toXml();
        }

        $video = '';
        foreach ($this->_videoList as $x) {
            $video .= $x->toXml();
        }

        $audio = '';
        foreach ($this->_audioList as $x) {
            $audio .= $x->toXml();
        }

        return
            "<url>\n" .
            "<loc><![CDATA[{$this->_url}]]></loc>\n" .
            "<lastmod>{$this->_lastmod}</lastmod>\n" .
            "<data>\n" .
            "<blogposting>\n" .
            "<headline><![CDATA[{$this->_title}]]></headline>\n" .
            "<url><![CDATA[{$this->_url}]]></url>\n" .
            "<articleAuthor>\n" .
            "<articleAuthor>\n" .
            "<alias><![CDATA[{$this->_author}]]></alias>\n" .
            "</articleAuthor>\n" .
            "</articleAuthor>\n" .
            "<articleBody><![CDATA[{$this->_content}]]></articleBody>\n" .
            "<articleTime>{$this->_publishTime}</articleTime>\n" .
            "<articleModifiedTime>{$this->_lastmod}</articleModifiedTime>\n" .
            "{$keywords}" .
            "<articleSection><![CDATA[{$this->_term}]]></articleSection>\n" .
            "{$pics}\n" .
            "{$video}" .
            "{$audio}" .
            "{$comment}" .
            "<articleCommentCount>{$this->_commentCount}</articleCommentCount>\n" .
            "<articleLatestComment>{$this->_latestCommentTime}</articleLatestComment>\n" .
            "</blogposting>\n" .
            "</data>\n" .
            "</url>\n";
    }
}

class BaidusubmitSchemaComment
{
    private $_text;
    private $_time;
    private $_creator;

    public function setText($text)
    {
        $this->_text = trim($text);
    }

    public function setTime($time)
    {
        if (!preg_match('#^\d+$#', $time)) {
            $time = strtotime($time);
        }
        $this->_time = BaidusubmitSitemap::dateFormat($time, $only_date = TRUE);
    }

    public function getTime()
    {
        if (isset($this->_time)) {
            return $this->_time;
        }
        return '';
    }

    public function setCreator($creator)
    {
        $this->_creator = trim($creator);
    }

    public function toXml()
    {
        return '<comment>' .
        '<commentText><![CDATA[' . $this->_text . ']]></commentText>' .
        '<commentTime>' . $this->_time . '</commentTime>' .
        '<creator>' .
        '<person>' .
        '<alias>' . $this->_creator . '</alias>' .
        '</person>' .
        '</creator>' .
        '</comment>' . "\n";
    }
}

class BaidusubmitSchemaVideo
{
    private $_caption;
    private $_thumbnail;
    private $_url;

    public function setCaption($caption)
    {
        $this->_caption = trim($caption);
    }

    public function setThumbnail($thumbnail)
    {
        $this->_thumbnail = trim($thumbnail);
    }

    public function setUrl($url)
    {
        $this->_url = trim($url);
    }

    public function toXml()
    {
        return '<video>' .
        '<caption><![CDATA[' . $this->_caption . ']]></caption>' .
        '<thumbnail><![CDATA[' . $this->_thumbnail . ']]></thumbnail>' .
        '<url><![CDATA[' . $this->_url . ']]></url>' .
        '</video>' . "\n";
    }
}

class BaidusubmitSchemaAudio
{
    private $_name;
    private $_url;

    function setName($name)
    {
        $this->_name = trim($name);
    }

    function setUrl($url)
    {
        $this->_url = trim($url);
    }

    function toXml()
    {
        return '<audio>' .
        '<name><![CDATA[' . $this->_name . ']]></name>' .
        '<url><![CDATA[' . $this->_url . ']]></url>' .
        '</audio>' . "\n";
    }
}
