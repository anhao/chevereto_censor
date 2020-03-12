<?php

namespace CHV;

class Censor
{

    private $censorType;
    private $censor;

    /**
     * Censor constructor.
     */
    public function __construct()
    {
        $this->censorType = Settings::get("censor_type");
        $this->censor = \Alone88\ImageCensor\Factory\Censor::factory($this->censorType);
        if ($this->censorType == 1) {
            $this->bdCensor();
        } else if ($this->censorType == 2) {
            $this->txCensor();
        } else if ($this->censorType == 3) {
            $this->mcCensor();
        } else if ($this->censorType == 4) {
            $this->seCensor();
        }
    }

    /**
     * @param $image
     * @return mixed
     */
    private function bdCensor()
    {
        $this->censor->setAppId(Settings::get("bd_censor_app_id"));
        $this->censor->setApiKey(Settings::get("bd_censor_app_key"));
        $this->censor->setSecretKey(Settings::get("bd_censor_app_secret"));
    }

    private function txCensor()
    {
        $this->censor->setAppId(Settings::get("tx_censor_app_id"));
        $this->censor->setAppKey(Settings::get("tx_censor_app_key"));
    }

    private function mcCensor()
    {
        $this->censor->setApiKey(Settings::get("mc_censor_api_key"));
    }

    private function seCensor()
    {
        $this->censor->setApiUser(Settings::get("se_censor_user"));
        $this->censor->setApiSecret(Settings::get("se_censor_secret"));
    }


    /**
     * @param $source
     * @param $type
     * @throws \Exception
     */
    public static function censor($source, $type = 'file')
    {
        if ($type == 'url') {
            $res = (new Censor)->urlCensor($source);
        } else {
            $res = (new Censor)->imageCensor($source['tmp_name']);
        }
        if ($res == 2) {
            self::pronMode();
        } else if ($res == 3) {
            self::suspectMode();
        } else if ($res == 4) {
            self::failMode();
        }
    }

    private function urlCensor($url)
    {
        return $this->censor->imageCensor($url);
    }

    private function imageCensor($image)
    {
        return $this->censor->imageCensor($image);
    }

    /**
     * @throws \Exception
     */
    private static function pronMode()
    {
        $mode = Settings::get("pron_mode");
        if ($mode == 1) {
            throw new \Exception(_s("Image violation"), 201);
        } else if ($mode == 2) {
            $_REQUEST['nsfw'] = 1;
        }
    }

    /**
     * @throws \Exception
     */
    private static function suspectMode()
    {
        $mode = Settings::get("suspect_mode");
        if ($mode == 1) {
            throw new \Exception(_s("Image violation"), 201);
        } else if ($mode == 2) {
            $_REQUEST['nsfw'] = 1;
        }
    }

    /**
     * @throws \Exception
     */
    private static function failMode()
    {
        $mode = Settings::get("fail_mode");
        if ($mode == 1) {
            throw new \Exception(_s("Image censor failed"), 201);
        } else if ($mode == 2) {
            $_REQUEST['nsfw'] = 1;
        }
    }
}