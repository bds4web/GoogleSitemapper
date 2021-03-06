<?php
require 'GoogleSitemapper.php';

$sitemap = new GoogleSitemapper();
$sitemap->setSiteAddress('http://www.uzmansorusu.com');
//$sitemap->enableAutoSave();
// $sitemap->disableCompression();
// $sitemap->addUrl('/questions');
//$sitemap->setSitemapFileName('some_dir/sitemap');
//$sitemap->setAutoSavePerUrl(20);


$sitemap->addUrl('http://deneme.com/fasdfasd');
$sitemap->addUrl('/überşaft' . uniqid() . '?şakacı=1&deneme=2<test>"\'?test_question mark');
$sitemap->addUrl('http://deneme.com/');
$sitemap->addUrl('http://deneme.com');
$url = $sitemap->addUrl('http://deneme.com/', GoogleSitemapper::CHANGEFREQ_ALWAYS, null, null, '/images/a.gif');

$sitemap->addImage($url, 'images/a.gif');
$sitemap->addImage($url, 'http://www.dneme.com/images/a.gif');
$sitemap->addImage($url, array(
    'loc' => 'http://www.dneme.com/images/ü.gif',
    'caption' => 'Deneme',
    'title' => 'hebele',
    'license' => 'http://asda.com'
));


for ($i = 0; $i < 100; $i++) {
    $sitemap->addUrl('/überşaft' . uniqid() . '?şakacı=1&deneme=2<test>"\'?test_question mark');
}

$sitemap->addUrl('/son');

//echo $sitemap->createXml();
$sitemap->saveXml(true);
// $sitemap->saveXml();
//$sitemap->ping();

echo memory_get_peak_usage(true) . "\n";
echo "Finished\n";
