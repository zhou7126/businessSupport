<?php
namespace app\admin\command;

use think\Db;
use OSS\OssClient;
use OSS\Core\OssException;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;
use think\exception\PDOException;

/**
 * Oss
 */
class Oss extends Command
{
    protected function configure()
    {
        // 这里的hello就是命令行think后面的参数
        $this->setName('oss')->setDescription('OSS 上传');
    }

    protected function execute(Input $input, Output $output)
    {
        /*$table = "SystemPackage";
        $package = Db::name($table)
            ->where('is_oss',1)
            ->where('oss_status',0)
            ->order('created_at asc')
            ->limit(10)
            ->select();
        $accessKeyId = sysconf('storage_oss_keyid');
        $accessKeySecret = sysconf('storage_oss_secret');
        $endpoint = "http://". sysconf('storage_oss_endpoint');
        $bucket = sysconf('storage_oss_bucket');
        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);

        $uploading = function($id) {
            Db::name("SystemPackage")->where('id',$id)->update([
                'oss_status'=>1,
            ]);
        };
        $restart = function($id) {
            Db::name("SystemPackage")->where('id',$id)->update([
                'oss_status'=>0,
            ]);
        };
        $success = function($id, $url) {
            Db::name("SystemPackage")->where('id',$id)->update([
                'path'=>$url,
                'oss_status'=>2,
            ]);
        };
        foreach ($package as $key => $item){
            $data = json_decode($item['data'],true);
            $file = $data['path'] ?? '';
            $id = $item['id'];
            if(empty($file)) continue;

            $tmp = explode('/',$file);
            $filename = array_pop($tmp);
            try {
                echo "id:{$id}开始上传\n";
                $uploading($id);
                $object = date('Ymd') .'/'. $filename;
                $res = $ossClient->uploadFile($bucket, $object, ROOT_PATH . trim($file,'/'));
                if(empty($res['info']['url']))  {
                    echo "id:{$id}上传失败\n";
                    $restart($id);
                    continue;
                }
                $success($id, $res['info']['url']);
                echo "id:{$id}上传成功\n";

            } catch (OssException $e) {
                echo "id:{$id}上传失败\n";
                echo $e->getMessage() ."\n";
                $restart($id);
            } catch (Exception $e){
                echo "id:{$id}上传失败\n";
                echo $e->getMessage() ."\n";
                $restart($id);
            }
        }
        $output->writeln("完毕!");*/
    }

    public function ossUpload()
    {
        ini_set('memory_limit','3072M');    // 临时设置最大内存占用为3G
        set_time_limit(0);   // 设置脚本最大执行时间 为0 永不过期



        die;
    }

}