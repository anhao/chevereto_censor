<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 19:58.
 */

namespace Alone88\ImageCensor\Tencent;

/** 腾讯图片审核 支持jpg、png、bmp
 * Class BDImageCensor.
 */
class TXImageCensor extends AipBase
{
    /** 图片鉴黄.
     *
     * @var string
     *
     * return number 1:正常,2:色情,3:疑似,4鉴别失败
     */
    private $imageCenorUrl = 'https://api.ai.qq.com/fcgi-bin/vision/vision_porn';

    public function imageCensor($image)
    {
        $data = [];
        $isUrl = substr(trim($image), 0, 4) === 'http';
        if (!$isUrl) {
            $data['image'] = base64_encode(file_get_contents($image));
        } else {
            $data['image_url'] = $image;
        }

        try {
            $data = $this->request($this->imageCenorUrl, $data);
        } catch (\Exception $e) {
            return 4;
        }
        $ret = $data['ret'];
        if ($ret != 0) {
            return 4;
        }
        $normal_hot_porn = $data['data']['tag_list'][2];
        $pron_confidence = $normal_hot_porn['tag_confidence'];
        if ($pron_confidence > 83) {
            return 2;
        }
        $hot = $data['data']['tag_list'][1];
        $hot_confidence = $hot['tag_confidence'];
        $normal = $data['data']['tag_list'][0];
        $normal_confidence = $normal['tag_confidence'];
        if ($hot_confidence > $normal_confidence) {
            return 3;
        }

        return 1;
    }
}
