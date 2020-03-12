<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/11
 * Time: 13:35.
 */

namespace Alone88\ImageCensor\Factory;

use Alone88\ImageCensor\Baidu\BDImageCensor;
use Alone88\ImageCensor\Moderate\MCImageCensor;
use Alone88\ImageCensor\Sightengine\SEImageCensor;
use Alone88\ImageCensor\Tencent\TXImageCensor;

class Censor
{
    public static function factory($type)
    {
        if ($type == 1) {
            return new BDImageCensor();
        } elseif ($type == 2) {
            return new TXImageCensor();
        } elseif ($type == 3) {
            return new MCImageCensor();
        } elseif ($type == 4) {
            return new SEImageCensor();
        }
    }
}
