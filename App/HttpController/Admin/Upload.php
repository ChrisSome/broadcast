<?php


namespace App\HttpController\Admin;

use App\Base\AdminController;
use App\lib\ClassArr;
use App\Utility\Message\Status;
use EasySwoole\Http\AbstractInterface\Controller;

class Upload extends AdminController
{

    function index()
    {
        // TODO: Implement index() method.
        $request = $this->request();
        $file = $request->getUploadedFile('file');
        $isImage = getimagesize($file->getTempName());
        if ($isImage) {
            $type = 'image';
        }

        if (empty($type)) {
            return $this->writeJson(400, '上传文件不合法');
        }
        try {
            $classObj = new ClassArr();
            $classStats = $classObj->uploadClassStat();
            $uploadObj = $classObj->initClass($type, $classStats, [$request, $type]);
            $file = $uploadObj->upload();
        } catch (\Exception $e) {
            return $this->writeJson(400, $e->getMessage(), []);
        }
        if (empty($file)) {
            return $this->writeJson(400, "上传失败", []);
        }

        $data = [
            'url' => $file,
        ];
        //return $this->writeJson(200, "OK", $data);
        $data = ['code' => Status::CODE_OK, 'data' => [
            'src' => $file,
            'title' => '上传图片'
        ]];
        $this->dataJson($data);
    }
}