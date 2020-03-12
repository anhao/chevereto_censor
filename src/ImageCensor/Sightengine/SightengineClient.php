<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 22:15.
 */

namespace Alone88\ImageCensor\Sightengine;

use Alone88\ImageCensor\Tool\HttpUtil;

class SightengineClient
{
    private $apiUser;
    private $apiSecret;
    private $imageCensorUrl = 'https://api.sightengine.com/1.0/check.json';
    private $model = 'nudity';

    public function __construct($apiUser = null, $apiSecret = null)
    {
        $this->apiUser = $apiUser;
        $this->apiSecret = $apiSecret;
        $this->client = new HttpUtil();
    }

    /** check url.
     * @param $url
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function url($url)
    {
        $data['url'] = $url;
        $data['api_user'] = $this->apiUser;
        $data['api_secret'] = $this->apiSecret;
        $data['models'] = $this->model;
        $rep = $this->client->get($this->imageCensorUrl, $data);

        return $this->proccessResult($rep['content']);
    }

    /**
     * 格式化结果.
     *
     * @param $content string
     *
     * @return mixed
     */
    protected function proccessResult($content)
    {
        return json_decode($content, true);
    }

    /** check file.
     * @param $file
     *
     * @throws \Exception
     */
    public function file($file)
    {
        $data['api_user'] = $this->apiUser;
        $data['api_secret'] = $this->apiSecret;
        $data['models'] = $this->model;
        $data['media'] = $file;
        $rep = $this->client->postFile($this->imageCensorUrl, $data, 'media');

        return $this->proccessResult($rep['content']);
    }

    /**
     * @return mixed
     */
    public function getApiUser()
    {
        return $this->apiUser;
    }

    /**
     * @param mixed $apiUser
     */
    public function setApiUser($apiUser)
    {
        $this->apiUser = $apiUser;
    }

    /**
     * @return mixed
     */
    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    /**
     * @param mixed $apiSecret
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }
}
