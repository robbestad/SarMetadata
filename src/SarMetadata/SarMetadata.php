<?php

namespace SarMetadata;

use SarMetadata\FastImage;

class SarMetadata
{

    public $error_code, $error_response;
    protected $url, $response, $standard, $og, $xpath;

    public function __construct()
    {
    }

    public function getMeta($url)
    {
        $this->url = $url;
        if (!$html = $this->_crawl()) {
            return false;
        }

        $this->response = (object)array(
            'title' => '',
            'domain' => '',
            'description' => '',
            'keywords' => (object)array(),
            'author' => (object)array(
                    'name' => '',
                    'href' => ''
                ),
            'image' => (object)array(
                    'url' => '',
                    'height' => '',
                    'width' => '',
                ),
            'twitter' => (object)array(
                    'tweets' => (object)array(
                            'count' => ''),
                ),
            'facebook' => (object)array(
                    'likes' => (object)array(
                            'count' => ''),
                    'shares' => (object)array(
                            'count' => ''),
                    'comments' => (object)array(
                            'count' => ''),
                    'total' => (object)array(
                            'count' => '')
                ),
        );

        $this->standard = $this->og = array();

        libxml_use_internal_errors(true);
        $doc = new \DomDocument();
        $doc->loadHTML($html);
        $this->xpath = new \DOMXPath($doc);
        $query = '//*/meta';
        $metas = $this->xpath->query($query);
        if ($metas) {
            foreach ($metas as $meta) {
                $name = $meta->getAttribute('name');
                $property = $meta->getAttribute('property');
                $content = $meta->getAttribute('content');
                if (!empty($name)) {
                    $this->standard[$name] = $content;
                } else if (!empty($property)) {
                    // can be more than one article:tag
                    if ($property == 'article:tag') {
                        if (isset($this->og['article:tag'])) {
                            $this->og['article:tag'][] = $content;
                        } else {
                            $this->og['article:tag'] = array($content);
                        }
                    }
                    $this->og[$property] = $content;
                }
            }

            $this->_getTitle();
            $this->_getDescription();
            $this->_getKeywords();
            $this->_getAuthor();
            $this->_getImage();
        } else {

            // return title at the very minimum
            $this->_getTitle();
        }

        // return this in both cases
        $this->_getTweetCount();
        $this->_getFacebookCounts();
        $this->_getDomain();

        return $this->response;
    }

    /**
     * @param int $timeout
     * @param int $connect_timeout
     * @param int $num_tries
     * @param array $other_curl_options (eg. user-agent)
     * @param array $custom_fail_strings optionally search for strings that represent failure (such as "error")
     * @return bool|string
     */
    private function _crawl(
        $timeout = 10,
        $connect_timeout = 3,
        $num_tries = 3,
        $other_curl_options = array(),
        $custom_fail_strings = array()
    )
    {

        for ($i = 0; $i < $num_tries; $i++) {
            $curl_handle = curl_init();
            curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/535.19 (KHTML, like Gecko) Chrome/18.0.1025.151 Safari/535.19 (SVENAPP)');
            curl_setopt($curl_handle, CURLOPT_URL, $this->url);
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
            curl_setopt($curl_handle, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);

            if (count($other_curl_options) > 0) {
                foreach ($other_curl_options as $name => $value) {
                    curl_setopt($curl_handle, $name, $value);
                }
            }

            $buffer = curl_exec($curl_handle);
            $curlinfo = curl_getinfo($curl_handle);
            curl_close($curl_handle);

            $custom_fail = false;
            if (count($custom_fail_strings) > 0) {
                foreach ($custom_fail_strings as $custom_fail_string) {
                    if (stristr($buffer, $custom_fail_string)) {
                        $custom_fail = true;
                        break;
                    }
                }
            }

            if (($curlinfo['http_code'] < 400) && ($curlinfo['http_code'] != 0) && (!$custom_fail)) {
                return $buffer;
            }

            // only report error if this is the last try
            if ($i == ($num_tries - 1)) {
                // error condition
                $this->error_code = $curlinfo['http_code'];
                $this->error_response = $buffer;
                return false;
            }
        }

        return false;
    }

    private function _getTitle()
    {
        if (isset($this->og['og:title'])) {
            $this->response->title = $this->og['og:title'];
        } else {
            $query = '//*/title';
            $titles = $this->xpath->query($query);
            if ($titles) {
                foreach ($titles as $title) {
                    $this->response->title = $title->nodeValue;
                    break;
                }
            }
        }
    }

    private function _getDescription()
    {
        if (isset($this->og['og:description'])) {
            $this->response->description = $this->og['og:description'];
        } else if (isset($this->standard['description'])) {
            $this->response->description = $this->standard['description'];
        }
    }

    private function _getKeywords()
    {
        if (isset($this->standard['keywords'])) {
            $keywords = explode(',', $this->standard['keywords']);
            foreach ($keywords as $k => $v) {
                $keywords[$k] = trim($v);
            }
            $this->response->keywords = (object)$keywords;
        } else if (isset($this->og['article:tag'])) {
            $this->response->keywords = (object)$this->og['article:tag'];
        }
    }

    private function _getAuthor()
    {
        $query = '//*/a[starts-with(@rel, \'author\')]';
        $authors = $this->xpath->query($query);
        if ($authors) {
            foreach ($authors as $author) {
                $this->response->author = (object)array('name' => $author->nodeValue, 'href' => $author->getAttribute('href'));
                break;
            }
        } else if (isset($this->og['article:author'])) {
            $this->response->author = (object)array('name' => '', 'href' => $this->og['article:author']);
        }
    }

    private function _getImage()
    {
        if (isset($this->og['og:image'])) {
            $this->response->image->url = $this->og['og:image'];
            $image = new FastImage($this->og['og:image']);
            list($width, $height) = $image->getSize();
            $this->response->image->width = $width;
            $this->response->image->height = $height;
        }
        return false;
    }

    private function _getTweetCount()
    {
        $twitter = "http://urls.api.twitter.com/1/urls/count.json?url=" . $this->url;
        $this->response->twitter->tweets = json_decode(file_get_contents($twitter), true)["count"];
    }

    private function _getFacebookCounts()
    {
        $fql = "SELECT share_count, like_count, comment_count, total_count ";
        $fql .= " FROM link_stat WHERE url = '" . $this->url . "'";

        $apifql = "https://api.facebook.com/method/fql.query?format=json&query=" . urlencode($fql);

        $fbstats = json_decode(file_get_contents($apifql));

        $this->response->facebook->likes = $fbstats[0]->like_count;
        $this->response->facebook->shares = $fbstats[0]->share_count;
        $this->response->facebook->comments = $fbstats[0]->comment_count;
        $this->response->facebook->total = $fbstats[0]->total_count;
    }

    private function _getDomain()
    {
        preg_match('@^(?:http://)?([^/]+)@i', $this->url, $matches);
        $host = $matches[1];

        // get last two segments of host name
        preg_match('/[^.]+\.[^.]+$/', $host, $matches);
        if ($matches[0]) {
            if (substr($matches[0], -2) == "uk") {
                preg_match('/[^.]+\.[^.]+\.[^.]+$/', $host, $matches);
            }
            $domain = $matches[0];
        } else {
            $domain = " ";
        }
        $this->response->domain=$domain;
    }


}
