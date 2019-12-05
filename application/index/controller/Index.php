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

namespace app\index\controller;

use library\Controller;
use think\Db;
use think\exception\HttpException;
use think\Request;

/**
 * 模板路由控制器
 * Class Index
 * @package app\index\controller
 */
class Index extends Controller
{
    /**
     * 入口域名判断
     */
    public function index()
    {
        $domain = $this->request->host();
        $schemeDomain = $this->request->domain();
        //dump($domain);
        //dump($schemeDomain);

        // 域名跳转
        $jumpData = Db::name('SystemDomain')->where(['call_domain' => $domain, 'status' => 1])->find();
        //dump($jumpData);
        if (!empty($jumpData) && !empty($jumpData['jump_domain'])) {
            $jumpUrl = stripos($jumpData['jump_domain'], 'http') !== false ? $jumpData['jump_domain'] : "http://" . $jumpData['jump_domain'];
            echo "<script language='JavaScript'>self.location='{$jumpUrl}';</script>";
            exit();
        }

        // 域名绑定
        $appKey = input('appkey');
        if (!empty($appKey)) {
            $domainData = [
                'app_id' => '',
                'domain' => '',
                'channel_code' => '',
                'statistics_code' => '',
            ];
            $bindData = Db::name('SystemApp')->where([
                ['app_key', '=', $appKey],
                ['status', '=', 1]
            ])->find();
        } else {
            $domainData = Db::name('SystemAppDomain')
                ->where('domain', 'like', "%{$domain}%")
                ->field('app_id,domain,channel_code,statistics_code')
                ->find();
            $bindData = Db::name('SystemApp')->where([
                ['id', '=', $domainData['app_id']],
                ['status', '=', 1]
            ])->find();
        }
        if (!empty($bindData)) {
            $bindData = array_merge($bindData, $domainData);
            $channelCode = empty($bindData['channel_code']) ? $bindData['default_channel_code'] : $bindData['channel_code'];
            $openinstallKey = empty($bindData['openinstall_app_key']) ? $bindData['default_openinstall_app_key'] : $bindData['openinstall_app_key'];
            $statisticsCode = empty($bindData['statistics_code']) ? $bindData['default_statistics_code'] : $bindData['statistics_code'];
            $tempData = Db::name('SystemTemplate')->where(['id' => $bindData['template_id'], 'is_deleted' => 0])->find();
            if (!empty($tempData)) {
                $coprData = $fetchData = [
                    'base_url' => $schemeDomain . '/' . $tempData['package'] . '/', // 页面默认URL
                    'download_type' => $bindData['download_type'], // 应用下载方式，1普通下载，2openinstall
                    'channel_code' => [
                        "val" => $channelCode,
                        "desc" => ""
                    ], // 渠道号
                    'openintsall_app_key' => [
                        "val" => $openinstallKey,
                        "desc" => ""
                    ], // 渠道号
                    'ad_config_install_type' => $bindData['ad_config_install_type'], // 安卓安装方式，1托管APK，2外部APK
                    'ad_download_url' => '', // 安卓安装地址
                    'pg_config_install_type' => $bindData['pg_config_install_type'], // 苹果安装方式，1托管IPA，2外部IPA，3AppStore及其他，4外部plist
                    'pg_download_url' => '', // 苹果安装地址
                    'statistics_code' => $statisticsCode, // 统计代码
                ];
                if (!empty($bindData['ext_json']) && !empty(json_decode($bindData['ext_json'], true))) {
                    foreach (json_decode($bindData['ext_json'], true) as $extKey => $extVal) {
                        if (isset($coprData[$extKey])) {
                            continue;
                        }
                        $fetchData[$extKey] = $extVal;
                    }
                }
                // 查询安卓下载地址
                $adInstallArr = json_decode($bindData['ad_config_install_data'], true);
                if ($fetchData['ad_config_install_type'] == 1) {
                    $packageData = Db::name('SystemPackage')->where('id', $adInstallArr['apk_id'])->find();
                    if (!empty($packageData) && !empty($packageData['path'])) {
                        $fetchData['ad_download_url'] = $packageData['path'];
                    }
                } else {
                    $fetchData['ad_download_url'] = $adInstallArr['download_url'];
                }

                // 查询苹果下载地址
                $pgInstallArr = json_decode($bindData['pg_config_install_data'], true);
                if ($fetchData['pg_config_install_type'] == 1) {
                    $packageData = Db::name('SystemPackage')->where('id', $pgInstallArr['ipa_id'])->find();
                    if (!empty($packageData) && !empty($packageData['path'])) {
                        $plistData = json_decode($packageData['data'], true);
                        if (isset($plistData['plist_path'])) {
                            $fetchData['pg_download_url'] = "itms-services://?action=download-manifest&url=" . $plistData['plist_path'];
                        }
                    }
                } else if ($fetchData['pg_config_install_type'] == 2) {
                    $fetchData['pg_download_url'] = $pgInstallArr['download_url'];
                } else if ($fetchData['pg_config_install_type'] == 3) {
                    $fetchData['pg_download_url'] = $pgInstallArr['app_store_url'];
                } else {
                    $fetchData['pg_download_url'] = 'itms-services://?action=download-manifest&url=' . $pgInstallArr['plist'];
                }

                // 渲染视图
                if (is_file($tempData['package'] . '/m/index.html') && $this->request->isMobile()) {
                    $fetchData['base_url'] .= 'm/';
                    $this->fetch($tempData['package'] . '/m/index.html', $fetchData);
                    return;
                } else if (is_file($tempData['package'] . '/index.html')) {
                    $this->fetch($tempData['package'] . '/index.html', $fetchData);
                    return;
                }
            }
        }

        echo "<script language='JavaScript'>self.location='/404';</script>";
        exit();
    }

}
