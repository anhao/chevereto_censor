<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 19:39.
 */

namespace Alone88\ImageCensor\Tencent;

use Alone88\ImageCensor\Tool\HttpUtil;

class AipBase
{
    protected $appId;
    protected $appKey;

    public function __construct($appId = null, $appKey = null)
    {
        $this->appId = trim($appId);
        $this->appKey = trim($appKey);
        $this->client = new HttpUtil();
    }

    /**
     * @param $url
     * @param $data
     * @param array $headers
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function request($url, $data, $headers = [])
    {
        $data['app_id'] = $this->appId;
        $data['time_stamp'] = time();
        $data['nonce_str'] = $this->getRandomStr();
        $sign = $this->getReqSign($data, $this->appKey);
        $data['sign'] = $sign;

        $rep = $this->client->post($url, $data, $headers);
        $obj = $this->proccessResult($rep['content']);

        return $obj;
    }

    public function getRandomStr()
    {
        return md5(uniqid(microtime(true), true));
    }

    /** 获得 sign.
     * @param $params
     * @param $key
     */
    public function getReqSign($params, $appkey)
    {
        ksort($params);
        $str = '';
        foreach ($params as $key => $value) {
            if ($value !== '') {
                $str .= $key.'='.urlencode($value).'&';
            }
        }
        // 3. 拼接app_key
        $str .= 'app_key='.$appkey;

        // 4. MD5运算+转换大写，得到请求签名
        $sign = strtoupper(md5($str));

        return $sign;
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

    /**
     * @return string
     */
    public function getAppId()
    {
        return $this->appId;
    }

    /**
     * @param string $appId
     */
    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    /**
     * @return string
     */
    public function getAppKey()
    {
        return $this->appKey;
    }

    /**
     * @param string $appKey
     */
    public function setAppKey($appKey)
    {
        $this->appKey = $appKey;
    }
}
