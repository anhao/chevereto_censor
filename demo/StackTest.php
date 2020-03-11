<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 22:48
 */

namespace Tests;
include '../vendor/autoload.php';


use Alone88\ImageCensor\Baidu\BDImageCensor;
use Alone88\ImageCensor\Factory\Censor;
use Alone88\ImageCensor\Moderate\MCImageCensor;
use Alone88\ImageCensor\Sightengine\SEImageCensor;
use Alone88\ImageCensor\Tencent\TXImageCensor;

class StackTest
{

    public function Baidu()
    {
        $bdcensor = new BDImageCensor("", "", "");
        print_r($bdcensor->imageCensor(""));
    }

    public function tencent()
    {
        $txcensor = new TXImageCensor("", "");
        print_r($txcensor->imageCensor(""));
    }

    public function moderate()
    {
        $mccensor = new MCImageCensor("");
        print_r($mccensor->imageCensor(""));
    }

    public function sightengine()
    {
        $secensor = new SEImageCensor("", "");
        print_r($secensor->imageCensor(""));
    }
}
