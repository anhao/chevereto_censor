<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 22:31.
 */

namespace Alone88\ImageCensor\Sightengine;

class SEImageCensor extends SightengineClient
{
    /** image censor.
     * @param $image
     *
     * @return int 1 正常，2 色情，3 疑似，4 失败
     */
    public function imageCensor($image)
    {
        try {
            $isUrl = substr(trim($image), 0, 4) === 'http';
            if (!$isUrl) {
                $data = $this->file($image);
            } else {
                $data = $this->url($image);
            }
        } catch (\Exception $e) {
            return 4;
        }

        return $this->getCensorLevel($data);
    }

    /** get image censor level.
     * @param $data
     *
     * @return int
     */
    private function getCensorLevel($data)
    {
        $status = $data['status'];
        if ($status != 'success') {
            return 4;
        }
        $nudity = $data['nudity'];
        $raw = $nudity['raw'];
        $safe = $nudity['safe'];
        $partial = $nudity['partial'];
        if ($raw > max($safe, $partial)) {
            return 2;
        }
        if ($partial > max($safe, $raw)) {
            return 3;
        }

        return 1;
    }
}
