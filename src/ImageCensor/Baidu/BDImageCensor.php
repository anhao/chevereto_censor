<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 19:32
 */

namespace Alone88\ImageCensor\Baidu;


class BDImageCensor extends AipBase
{
    /**
     * @var string
     */
    private $imageCensorUserDefinedUrl = 'https://aip.baidubce.com/rest/2.0/solution/v1/img_censor/v2/user_defined';

    /** 图片鉴黄
     *
     * @var string
     *
     * return number 1:正常,2:色情,3:疑似,4鉴别失败
     */
    public function imageCensor($image)
    {

        $data = array();

        $isUrl = substr(trim($image), 0, 4) === 'http';
        if (!$isUrl) {
            $data['image'] = base64_encode(file_get_contents($image));
        } else {
            $data['imgUrl'] = $image;
        }

        try {
            $data = $this->request($this->imageCensorUserDefinedUrl, $data);
        } catch (\Exception $e) {
            return 4;
        }
        if (isset($data['error_code'])) return 4;
        $conclusionType = $data['conclusionType'];
        return $conclusionType;
    }
}