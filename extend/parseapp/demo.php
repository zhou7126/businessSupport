<?php
include __DIR__."/IpaParser.php";
include __DIR__."/ApkParser.php";

#apk解析
$main = new ApkParser;
$main->open('mt1.5.apk');
echo $main->getPackage();
echo "\n";
echo $main->getVersionName();
echo "\n";
echo $main->getVersionCode();
echo "\n";

echo $main->getAppName();
echo "\n";
echo $main->getMainActivity();
echo "\n";
#ipa解析
echo "IOS\n";
$main = new IpaParser;
echo $main->parse('http://testadmin.com/upload/e64a2230dfe856f2/0dd15a3d3d77d92e.ipa');
echo $main->getPackage();
echo $main->getVersion();
echo $main->getAppName();
var_dump( $main->getPlist() );
