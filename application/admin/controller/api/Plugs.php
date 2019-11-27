<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\admin\controller\api;

use ApkParser\Parser;
use app\admin\model\AuthGroup;
use app\admin\service\NodeService;
use library\Controller;
use library\File;
use think\Exception;
use think\exception\PDOException;
use think\Loader;
use think\response\Json;
use OSS\OssClient;
use OSS\Core\OssException;
use OSS\Core\OssUtil;

/**
 * 后台插件管理
 * Class Plugs
 * @package app\admin\controller\api
 */
class Plugs extends Controller
{

    /**
     * 系统图标选择器
     */
    public function icon()
    {
        $this->title = '图标选择器';
        $this->field = input('field', 'icon');
        $this->fetch();
    }

    /**
     * 获取文件上传参数
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function check()
    {
        $diff1 = explode(',', strtolower(input('exts', '')));
        $diff2 = explode(',', strtolower(sysconf('storage_local_exts')));
        $exts = array_intersect($diff1, $diff2);
        $this->success('获取文件上传参数', [
            'exts' => join('|', $exts),
            'mime' => File::mine($exts),
            'type' => $this->getUploadType(),
            'data' => $this->getUploadData(),
        ]);
    }

    /**
     * 后台通用文件上传
     * @return Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function upload()
    {
        if (!NodeService::islogin()) {
            $this->error('访问授权失败，请重新登录授权再试！');
        }
        if (!($file = $this->getUploadFile()) || empty($file)) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传异常，文件可能过大或未上传']]);
        }
        if (!$file->checkExt(strtolower(sysconf('storage_local_exts')))) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传类型受限，请在后台配置']]);
        }
        if ($file->checkExt('php,sh')) {
            return json(['uploaded' => false, 'error' => ['message' => '可执行文件禁止上传到本地服务器']]);
        }
        $this->safe = boolval(input('safe'));
        $this->uptype = $this->getUploadType();
        $this->extend = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        $name = File::name($file->getPathname(), $this->extend, '', 'md5_file');
        $info = File::instance($this->uptype)->save($name, file_get_contents($file->getRealPath()), $this->safe);
//        $savePath = ROOT_PATH .
        if (is_array($info) && isset($info['url'])) {
            return json(['uploaded' => true, 'filename' => $name, 'url' => $this->safe ? $name : $info['url'],'key'=> '/' . $info['key']]);
        } else {
            return json(['uploaded' => false, 'error' => ['message' => '文件处理失败，请稍候再试！']]);
        }
    }

    /**
     * 生成文件上传参数
     * @return array
     * @throws \think\Exception
     */
    private function getUploadData()
    {
        if ($this->getUploadType() === 'qiniu') {
            $file = File::instance('qiniu');
            return ['url' => $file->upload(true), 'token' => $file->buildUploadToken(), 'uptype' => $this->getUploadType()];
        } else {
            return ['url' => '/bet365bet.php/api.plugs/upload', 'token' => uniqid('local_upload_'), 'uptype' => $this->getUploadType()];
        }
    }

    /**
     * 获取文件上传方式
     * @return string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function getUploadType()
    {
        $this->uptype = input('uptype');
        if (!in_array($this->uptype, ['local', 'oss', 'qiniu'])) {
            $this->uptype = sysconf('storage_type');
        }
        return $this->uptype;
    }

    /**
     * 获取本地文件对象
     * @return \think\File
     */
    private function getUploadFile()
    {
        try {
            return $this->request->file('file');
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
    }


    public function uploadPackage()
    {
        try {
            $rJson = $this->upload();
        } catch (PDOException $e) {
            $this->error($e->getMessage());
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        $data = (object)$rJson->getData();
        if (!$data) {
            $this->error("上传失败");
        }

        if($data->uploaded == false){
            $this->error($data->error->message);
        }

        $tmp = explode('.',$data->key);
        $suffix = array_pop($tmp);
        $url = $data->key;
        $size = strlen(file_get_contents(ROOT_PATH . $url));
        $domain = $_SERVER['HTTP_HOST'];
        //解析文件
        if ($suffix == 'apk') {
            $apk = $this->readApk($url, $size);
            $this->success('成功',$apk);
        } else if ($suffix == 'ipa') {
            $ipa = $this->readIpa($url, $size,$domain);
            $this->success('成功',$ipa);
        }
    }


    public function uploadOss($file,$progressKey)
    {
        //上传OSS
        $accessKeyId = "LTAIatcTnNfZauK5";;
        $accessKeySecret = "WfZvPEEuqYvDMUhIGvc2nv1DNFdH2D";
        $endpoint = "http://oss-cn-beijing.aliyuncs.com";
        $bucket = "duke-apk-ipa";
        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        $tmp = explode('/',$file);
        $filename = array_pop($tmp);
        return $ossClient->uploadFile($bucket, $filename, $file);
        /*$obj = 'package';
        $res = $this->putObjectByRawApis($ossClient,$bucket,$obj,$file,$progressKey);
        $tmp = explode('/',$file);
        $filename = array_pop($tmp);
        if(!empty($res['info']['url'])){
            return ['url' => $endpoint . '/' .$obj . '/' . $filename] ;
        }
        return null;*/
    }


    /**
     * Use basic multipart upload for file upload.
     *
     * @param OssClient $ossClient OssClient instance
     * @param string $bucket bucket name
     * @throws OssException
     */
    function putObjectByRawApis($ossClient, $bucket,$object,$uploadFile,$progressKey)
    {
        /**
         *  step 1. Initialize a block upload event, that is, a multipart upload process to get an upload id
         */
        try {
            $uploadId = $ossClient->initiateMultipartUpload($bucket, $object);
        } catch (OssException $e) {
            $this->error(
                __FUNCTION__ . ": initiateMultipartUpload FAILED\n".
                $e->getMessage() . "\n"
            );
        }

        /*
         * step 2. Upload parts
         */
        $partSize = 1 * 1024 * 1024;
        $uploadFileSize = filesize($uploadFile);
        $pieces = $ossClient->generateMultiuploadParts($uploadFileSize, $partSize);
        $responseUploadPart = array();
        $uploadPosition = 0;
        $isCheckMd5 = true;
        $count = count($pieces);
        foreach ($pieces as $i => $piece) {
            $fromPos = $uploadPosition + (integer)$piece[$ossClient::OSS_SEEK_TO];
            $toPos = (integer)$piece[$ossClient::OSS_LENGTH] + $fromPos - 1;
            $upOptions = array(
                $ossClient::OSS_FILE_UPLOAD => $uploadFile,
                $ossClient::OSS_PART_NUM => ($i + 1),
                $ossClient::OSS_SEEK_TO => $fromPos,
                $ossClient::OSS_LENGTH => $toPos - $fromPos + 1,
                $ossClient::OSS_CHECK_MD5 => $isCheckMd5,
            );
            if ($isCheckMd5) {
                $contentMd5 = OssUtil::getMd5SumForFile($uploadFile, $fromPos, $toPos);
                $upOptions[$ossClient::OSS_CONTENT_MD5] = $contentMd5;
            }
            //2. Upload each part to OSS
            try {
                $responseUploadPart[] = $ossClient->uploadPart($bucket, $object, $uploadId, $upOptions);
            } catch (OssException $e) {
                $this->error(
                    __FUNCTION__ . ": initiateMultipartUpload, uploadPart - part#{$i} FAILED\n".
                    $e->getMessage() . "\n"
                );
            }
            //进度
            $tmp = ($i + 1) / $count * 100;
            // session($progressKey, round( $tmp ,2 ));
        }
        $uploadParts = array();
        foreach ($responseUploadPart as $i => $eTag) {
            $uploadParts[] = array(
                'PartNumber' => ($i + 1),
                'ETag' => $eTag,
            );
        }
        /**
         * step 3. Complete the upload
         */
        try {
            $res = $ossClient->completeMultipartUpload($bucket, $object, $uploadId, $uploadParts);
            //session($progressKey, null);
            return $res;
        } catch (OssException $e) {
            $this->error(
                __FUNCTION__ . ": completeMultipartUpload FAILED\n".
                $e->getMessage() . "\n"
            );
        }
    }


    public function readIpa($url, $size, $domain)
    {
        $filepath = ROOT_PATH . $url;

        Loader::import("parseapp.IpaParser");
        $ipa = new \IpaParser();
        $ipa->parse($filepath);
        //var_dump($ipa->getPlist());
        $arr['type'] = 2;
        $arr['package_file'] = $url;
        $arr['size'] = round($size / 1024 / 1024, 2);
        $arr['signname'] = $ipa->getPackage();
        $arr['version'] = $ipa->getPlist()['CFBundleShortVersionString'];
        $arr['appname'] = $ipa->getAppName();

        //读取图片
        if (file_exists($filepath)) {
            //解压
            $zip = new \ZipArchive();
            $zip->open($filepath);

            $newImgPath = str_replace(".ipa", "", $filepath);
            if (!is_dir($newImgPath)) {
                mkdir($newImgPath, 0777, true);
            }
            // todo AppIcon60x60@3x.png 图片名称写死，可能会有问题
            $imgPathInZip = $zip->getNameIndex(1) . "AppIcon60x60@3x.png";

            $zip->extractTo($newImgPath, $imgPathInZip);
            $zip->close();

            $imgPath = $newImgPath . "/" . $imgPathInZip;
            //解密图片
            $parser = new \IosPngParser\Parser();
            $parser::fix($imgPath, $imgPath);

            $arr['iconimage'] = str_replace(".ipa", "", $url) . "/" . $imgPathInZip;
        }

        //生成
        $plist = file_get_contents(ROOT_PATH . "../data/plist/default.plist");

        $plistName = rand(1000, 9999) . time();
        $app_plist = $plistName . ".plist";
        $put_plist = str_replace(array(
            '{{DOWNLOAD_URL}}',
            '{{LOGO57}}',
            '{{LOGO512}}',
            '{{PACKAGE}}',
            '{{VERSION}}',
            '{{NAME}}'),
            array(
                $domain . $url,
                $domain . $arr['iconimage'],
                $domain . $arr['iconimage'],
                $arr['signname'],
                $arr['version'],
                $arr['appname']
            ),
            $plist
        );

        file_put_contents(ROOT_PATH . '../public/plist/' . $app_plist, $put_plist);
        $arr['plist_path'] = '/plist/' . $app_plist;
        return $arr;
    }

    public function readApk($url, $size)
    {
        $filepath = ROOT_PATH . trim($url,'/');

        $arr['type'] = 1;
        $arr['package_file'] = $url;
        $arr['size'] = round($size / 1024 / 1024, 2);

        $apk = new Parser($filepath);
        $manifest = $apk->getManifest();

        $arr['signname'] = $manifest->getPackageName();
        $arr['version'] = $manifest->getVersionName();
        $arr['appname'] = $apk->getResources($manifest->getApplication()->getLabel())[0];

        $resourceId = $apk->getManifest()->getApplication()->getIcon();
        $resources = $apk->getResources($resourceId);
        $base64img = base64_encode(stream_get_contents($apk->getStream($resources[0])));
        //base64转img
        $iconPath = $this->base64_image_content("data:image/png;base64," . $base64img, ROOT_PATH . "upload/iconimage");
        $iconImage = substr($iconPath, strrpos($iconPath, "/upload"));
        $arr['iconimage'] = $iconImage;
        return $arr;

    }


    public function base64_image_content($base64_image_content, $path)
    {
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            $new_file = $path . "/" . date('Ymd', time()) . "/";
            if (!file_exists($new_file)) {
                var_dump($new_file);
                mkdir($new_file, 0700);
            }
            $new_file = $new_file . time() . ".{$type}";

            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                return '/' . $new_file;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

}
