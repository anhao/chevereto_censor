<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 20:53
 */

namespace Alone88\ImageCensor\Moderate;

use Alone88\ImageCensor\Tool\HttpUtil;

/** moderatecontent 鉴黄
 * 申请地址 https://www.moderatecontent.com/
 * Class BDImageCensor
 * @package Alone88\BDImageCensor\Moderate
 */
class MCImageCensor
{
    /**
     * @var appkey
     */
    protected $apikey;
    private $imageCensorUrl = "https://www.moderatecontent.com/api/v2";

    public function __construct($apiKey = null)
    {
        $this->apikey= $apiKey;
        $this->client = new HttpUtil();
    }

    public function imageCensor($image)
    {
        $data = [];
        $isUrl = substr(trim($image), 0, 4) === 'http';
        if (!$isUrl) {
            $data['file'] = $image;
        } else {
            $data['url'] = $image;
        }
        $data = $this->request($this->imageCensorUrl, $data, $isUrl);
        $errcode = $data['error_code'];
        if ($errcode != 0) return 4;
        $rating_index = $data['rating_index'];
        return $rating_index == 3 ? 2 : 1;
    }

    /**
     * @param $url
     * @param $data
     * @param array $headers
     * @throws \Exception
     */
    public function request($url, $data, $isUrl, $headers = [])
    {
        $data['key'] = $this->apikey;
        if (!$isUrl) {
            $response = $this->client->postFile($url, $data, 'file', $headers);
        } else {
            $response = $this->client->post($url, $data, $headers);
        }
        return $this->proccessResult($response['content']);
    }

    /**
     * 格式化结果
     * @param $content string
     * @return mixed
     */
    protected function proccessResult($content)
    {
        return json_decode($content, true);
    }

    /**
     * @return appkey
     */
    public function getApikey()
    {
        return $this->apikey;
    }

    /**
     * @param appkey $apikey
     */
    public function setApikey($apikey)
    {
        $this->apikey = $apikey;
    }

}