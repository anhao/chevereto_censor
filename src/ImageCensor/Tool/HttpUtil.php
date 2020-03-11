<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 19:45
 */

namespace Alone88\ImageCensor\Tool;


use Exception;

class HttpUtil
{

    /**
     * HttpUtil constructor.
     * @param array $headers
     */
    public function __construct($headers = array())
    {
        $this->headers = $this->buildHeaders($headers);
        $this->connectTimeout = 60000;
        $this->socketTimeout = 60000;
        $this->conf = array();
    }

    /**
     * @param array $headers
     * @return array
     */
    public function buildHeaders(array $headers)
    {
        $result = array();
        foreach ($headers as $k => $v) {
            $result[] = sprintf('%s:%s', $k, $v);
        }
        return $result;
    }

    /**
     * 连接超时
     * @param int $ms 毫秒
     */
    public function setConnectionTimeoutInMillis($ms)
    {
        $this->connectTimeout = $ms;
    }

    /**
     * 响应超时
     * @param int $ms 毫秒
     */
    public function setSocketTimeoutInMillis($ms)
    {
        $this->socketTimeout = $ms;
    }

    /**
     * 配置
     * @param array $conf
     */
    public function setConf($conf)
    {
        $this->conf = $conf;
    }

    /**
     * @param  string $url
     * @param  array $data HTTP POST BODY
     * @param  array $param HTTP URL
     * @param  array $headers HTTP header
     * @return array
     * @throws Exception
     */
    public function post($url, $data = array(), $params = array(), $headers = array())
    {
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));

        $ch = curl_init();
        $this->prepare($ch);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code === 0) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);
        return array(
            'code' => $code,
            'content' => $content,
        );
    }

    /**
     *
     * @param  string $url
     * @param  array $params 参数
     * @return string
     */
    private function buildUrl($url, $params)
    {
        if (!empty($params)) {
            $str = http_build_query($params);
            return $url . (strpos($url, '?') === false ? '?' : '&') . $str;
        } else {
            return $url;
        }
    }

    /**
     * 请求预处理
     * @param resource $ch
     */
    public function prepare($ch)
    {
        foreach ($this->conf as $key => $value) {
            curl_setopt($ch, $key, $value);
        }
    }

    public function postFile($url, $params, $key, $headers = [])
    {
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));

        $ch = curl_init();
        $this->prepare($ch);
        $data = [];
        if ($key) {
            if (class_exists('\CURLFile')) {
                $data = array($key => new \CURLFile(realpath($params[$key])));
            } else {
                $data = array($key => '@' . realpath($params[$key]));
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, !empty($data) ? array_merge($params, $data) : $params);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code === 0) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);
        return array(
            'code' => $code,
            'content' => $content,
        );
    }

    /**
     * @param  string $url
     * @param  array $datas HTTP POST BODY
     * @param  array $param HTTP URL
     * @param  array $headers HTTP header
     * @return array
     */
    public function multi_post($url, $datas = array(), $params = array(), $headers = array())
    {
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));

        $chs = array();
        $result = array();
        $mh = curl_multi_init();
        foreach ($datas as $data) {
            $ch = curl_init();
            $chs[] = $ch;
            $this->prepare($ch);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
            curl_multi_add_handle($mh, $ch);
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            usleep(100);
        } while ($running);

        foreach ($chs as $ch) {
            $content = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result[] = array(
                'code' => $code,
                'content' => $content,
            );
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);

        return $result;
    }

    /**
     * @param  string $url
     * @param  array $param HTTP URL
     * @param  array $headers HTTP header
     * @return array
     */
    public function get($url, $params = array(), $headers = array())
    {
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));

        $ch = curl_init();
        $this->prepare($ch);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code === 0) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);
        return array(
            'code' => $code,
            'content' => $content,
        );
    }


}